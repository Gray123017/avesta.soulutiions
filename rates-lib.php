<?php
/**
 * The price of credit, server side.
 *
 * Everything that binds a borrower is computed here. The browser has its own
 * copy in assets/avesta/domain.js so the calculator can answer without a round
 * trip, but that copy is a convenience: this file is what the agreement and the
 * statements are built from, and scripts/check-rates.mjs fails the build if the
 * two ever disagree.
 *
 * Money is handled in ngwee (integer hundredths) throughout. Nothing here uses
 * a float for an amount, because 0.1 + 0.2 is not 0.3 and a lender cannot have
 * a repayment schedule that does not add up to the total.
 *
 * Two rules worth knowing before changing anything:
 *
 *  1. The overdue band REPLACES the term rate. A four-week loan at 30% that
 *     goes overdue is charged 35% in total, not 30% + 35%.
 *  2. A loan is charged under the terms it was SIGNED under, never under
 *     today's configuration. Records written before this engine existed carry
 *     a different late rule entirely (7.5% per week, additive) and are left
 *     alone — av_rates_terms_for_record() decides which applies.
 */

const AV_RATES_FILE = __DIR__ . '/assets/avesta/rates.json';

/** Load and validate the published rate table. Throws rather than guessing. */
function av_rates_load(?string $path = null): array {
    static $cache = [];
    $path = $path ?? AV_RATES_FILE;
    if (isset($cache[$path])) return $cache[$path];

    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Rate table missing: ' . $path);
    }
    $cfg = json_decode($raw, true);
    if (!is_array($cfg)) {
        throw new RuntimeException('Rate table is not valid JSON: ' . $path);
    }

    // A malformed table must stop the request, not silently price a loan at
    // zero. Each of these is something a careless edit could produce.
    foreach (['version', 'terms', 'overdue'] as $key) {
        if (!isset($cfg[$key])) throw new RuntimeException("Rate table has no '$key'");
    }
    $terms = [];
    foreach ($cfg['terms'] as $weeks => $percent) {
        if (!ctype_digit((string) $weeks)) continue;          // skips _comment
        if (!is_numeric($percent) || $percent < 0 || $percent > 1000) {
            throw new RuntimeException("Rate for {$weeks} weeks is out of range");
        }
        $terms[(int) $weeks] = (float) $percent;
    }
    if (!$terms) throw new RuntimeException('Rate table defines no terms');
    ksort($terms);
    $cfg['terms'] = $terms;

    $o = $cfg['overdue'];
    foreach (['startPercent', 'stepPercent', 'stepEveryDays'] as $key) {
        if (!isset($o[$key]) || !is_numeric($o[$key])) {
            throw new RuntimeException("Overdue rule has no numeric '$key'");
        }
    }
    if ((int) $o['stepEveryDays'] < 1) {
        throw new RuntimeException('stepEveryDays must be at least 1');
    }

    return $cache[$path] = $cfg;
}

/** Flat interest percent for a term, or null if that term is not offered. */
function av_rate_for_weeks(int $weeks, ?array $cfg = null): ?float {
    $cfg = $cfg ?? av_rates_load();
    return $cfg['terms'][$weeks] ?? null;
}

/**
 * Quote a loan. Amount in ngwee; returns ngwee.
 *
 * Interest is flat: applied once to the principal, never compounded, which is
 * what the site has always promised and what the agreement says.
 */
function av_quote(int $principalNgwee, int $weeks, ?array $cfg = null): array {
    $cfg = $cfg ?? av_rates_load();
    if ($principalNgwee <= 0) {
        throw new InvalidArgumentException('Loan amount must be above zero');
    }
    $rate = av_rate_for_weeks($weeks, $cfg);
    if ($rate === null) {
        throw new InvalidArgumentException("No published rate for {$weeks} weeks");
    }
    $interest = (int) round($principalNgwee * $rate / 100);
    return [
        'principal' => $principalNgwee,
        'rate'      => $rate,
        'interest'  => $interest,
        'total'     => $principalNgwee + $interest,
        'weeks'     => $weeks,
        'version'   => $cfg['version'],
    ];
}

/**
 * Split a total into instalments that add up exactly.
 *
 * The remainder goes a ngwee at a time onto the earliest payments, so the
 * schedule always sums to the total and the borrower is never asked for a
 * fraction of a ngwee.
 */
function av_instalments(int $totalNgwee, int $count): array {
    if ($count < 1) throw new InvalidArgumentException('Need at least one instalment');
    $base = intdiv($totalNgwee, $count);
    $remainder = $totalNgwee - $base * $count;
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = $base + ($i < $remainder ? 1 : 0);
    }
    return $out;
}

/**
 * Which rules govern a given stored record.
 *
 * Records written before this engine carry no terms_version and were signed
 * under the old additive late rule. Their agreement is binding as signed, so
 * the ladder is not applied to them and the caller is told to fall back.
 */
function av_rates_terms_for_record(array $record, ?array $cfg = null): array {
    $cfg = $cfg ?? av_rates_load();
    $version = $record['terms_version'] ?? null;
    if ($version === null || $version === '') {
        return [
            'engine'  => 'legacy',
            'version' => null,
            'note'    => 'Signed before the ladder existed; late terms are in the '
                       . 'record\'s late_payment_rate field and govern as signed.',
            'legacyLatePercentPerWeek' => $cfg['legacy']['latePercentPerWeek'] ?? null,
        ];
    }
    return ['engine' => 'ladder', 'version' => $version, 'note' => null];
}

/**
 * Where an overdue loan stands on a given day.
 *
 * $dueDate and $asOf are Y-m-d in Africa/Lusaka. Days are counted whole: a
 * loan due on the 28th is not overdue on the 28th, and is one day overdue on
 * the 29th.
 */
function av_overdue_state(string $dueDate, string $asOf, ?array $cfg = null): array {
    $cfg = $cfg ?? av_rates_load();
    $o   = $cfg['overdue'];
    $tz  = new DateTimeZone('Africa/Lusaka');

    $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate, $tz);
    $now = DateTimeImmutable::createFromFormat('!Y-m-d', $asOf, $tz);
    if (!$due || !$now) {
        throw new InvalidArgumentException('Dates must be Y-m-d');
    }

    $grace     = (int) ($o['gracePeriodDays'] ?? 0);
    $startFrom = $due->modify('+' . $grace . ' days');
    $daysLate  = (int) $startFrom->diff($now)->format('%r%a');

    if ($daysLate <= 0) {
        return [
            'overdue'      => false,
            'daysOverdue'  => 0,
            'bandPercent'  => null,
            'nextStepOn'   => null,
            'nextBand'     => null,
            'capped'       => false,
        ];
    }

    $every = (int) $o['stepEveryDays'];
    $steps = intdiv($daysLate - 1, $every);          // 0 for the first period
    $band  = (float) $o['startPercent'] + $steps * (float) $o['stepPercent'];

    $cap    = isset($o['capPercent']) ? (float) $o['capPercent'] : null;
    $capped = $cap !== null && $band >= $cap;
    if ($capped) $band = $cap;

    $nextBandRaw = (float) $o['startPercent'] + ($steps + 1) * (float) $o['stepPercent'];
    $atCeiling   = $cap !== null && $band >= $cap;

    return [
        'overdue'     => true,
        'daysOverdue' => $daysLate,
        'bandPercent' => $band,
        'nextStepOn'  => $atCeiling
            ? null
            : $startFrom->modify('+' . (($steps + 1) * $every) . ' days')->format('Y-m-d'),
        'nextBand'    => $atCeiling ? null : min($nextBandRaw, $cap ?? $nextBandRaw),
        'capped'      => $capped,
    ];
}

/**
 * What a borrower owes today, with the working shown.
 *
 * Returns the same figures the borrower sees in their account and the admin
 * sees on the arrears table — there is deliberately only one function, so the
 * two can never disagree.
 */
function av_amount_owing(
    int $principalNgwee,
    int $weeks,
    string $dueDate,
    string $asOf,
    int $paidNgwee = 0,
    ?array $cfg = null
): array {
    $cfg   = $cfg ?? av_rates_load();
    $quote = av_quote($principalNgwee, $weeks, $cfg);
    $state = av_overdue_state($dueDate, $asOf, $cfg);

    // The band replaces the term rate; it is not added to it.
    //
    // But it may never REDUCE what is owed. The ladder starts at 35%, which is
    // below the 45% charged for the eight-week term, so without this floor an
    // eight-week loan became K400 cheaper the day it fell overdue — the
    // borrower was paid to default. Going overdue can cost more or cost the
    // same; it can never cost less.
    $rate = $state['overdue']
        ? max((float) $state['bandPercent'], (float) $quote['rate'])
        : (float) $quote['rate'];
    $floored  = $state['overdue'] && $rate > (float) $state['bandPercent'];
    $interest = (int) round($principalNgwee * $rate / 100);
    $total    = $principalNgwee + $interest;

    return [
        'principal'     => $principalNgwee,
        'rateApplied'   => $rate,
        'termRate'      => $quote['rate'],
        'interest'      => $interest,
        'totalDue'      => $total,
        'paid'          => $paidNgwee,
        'outstanding'   => max(0, $total - $paidNgwee),
        'settled'       => $paidNgwee >= $total,
        'bandPercent'   => $state['bandPercent'],
        'heldAtTermRate' => $floored,
        'overdue'       => $state['overdue'],
        'daysOverdue'   => $state['daysOverdue'],
        'nextStepOn'    => $state['nextStepOn'],
        'nextBand'      => $state['nextBand'],
        'capped'        => $state['capped'],
        'dueDate'       => $dueDate,
        'asOf'          => $asOf,
        'termsVersion'  => $cfg['version'],
    ];
}

/** Money for display: "K 5,200.00". Takes ngwee. */
function av_money(int $ngwee): string {
    return 'K ' . number_format($ngwee / 100, 2, '.', ',');
}
