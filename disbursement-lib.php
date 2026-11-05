<?php
/**
 * Recording that money went out.
 *
 * This file sends nobody any money, deliberately. Avesta is not licensed to
 * lend, and wiring a mobile-money API into an unlicensed lender is the one
 * change in this codebase that could do real harm — to borrowers, and to the
 * business. What it does instead is record a payout that a person made by
 * whatever means they actually used: the MoMo app, a bank portal, cash across
 * a counter. When there is a licence and a provider account, the place to put
 * that is behind av_disbursement_record(), with everything here still in front
 * of it.
 *
 * The refusals are the substance:
 *
 *   A loan already paid out cannot be paid out again. A duplicate payout is
 *   money gone with nothing to show for it.
 *
 *   Only an approved loan can be paid out.
 *
 *   An amount that is not the approved one needs a written reason. Paying out
 *   less than was agreed, while charging interest on the full sum, is a
 *   mistake only the borrower notices.
 *
 *   A payout cannot be dated in the future. The due date is counted from it,
 *   so a date that has not happened yet buys time nobody agreed to.
 *
 *   Anything but cash needs a reference. A mobile money transfer with no
 *   transaction id cannot be traced when the borrower says it never arrived.
 *
 * It also closes something that was already live: a loan could be marked
 * Disbursed from the status dropdown and never get a date, which leaves the
 * rate engine unable to work out when it is due — so it never goes overdue and
 * the 35% never applies. av_disbursement_undated() lists those.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rates-lib.php';        // av_money(): one place does money
require_once __DIR__ . '/remittance-lib.php';   // av_ngwee(), and the payroll schedule

/** Methods that are not cash, and therefore have something to quote. */
function av_disbursement_traceable(string $method): bool {
    return trim($method) !== '' && strtolower(trim($method)) !== 'cash';
}

/** What was approved, in ngwee. */
function av_disbursement_principal_ngwee(array $record): int {
    return av_ngwee($record['loan_amt'] ?? $record['loan_amount'] ?? 0);
}

/**
 * Record a payout against a loan.
 *
 * Returns ['ok' => true, 'record' => …] with the loan updated, or
 * ['ok' => false, 'error' => …] with a sentence for whoever is at the desk.
 * The caller saves the record; this does not, because the loan book has one
 * writer and its own locking.
 */
function av_disbursement_record(array $record, array $in): array {
    $existing = trim((string) ($record['disburse_date'] ?? ''));
    if ($existing !== '') {
        return ['ok' => false,
                'error' => 'This loan has already been paid out, on ' . $existing
                         . '. Paying it out again would send the money twice.'];
    }

    $status = trim((string) ($record['status'] ?? ''));
    if (strcasecmp($status, 'Approved') !== 0) {
        return ['ok' => false,
                'error' => 'Only an approved loan can be paid out. This one is "'
                         . ($status === '' ? 'not set' : $status) . '".'];
    }

    $method = trim((string) ($in['method'] ?? ''));
    if ($method === '') {
        return ['ok' => false, 'error' => 'Say how the money was sent.'];
    }

    $reference = trim((string) ($in['reference'] ?? ''));
    if (av_disbursement_traceable($method) && $reference === '') {
        return ['ok' => false,
                'error' => 'A transaction reference is needed for anything but cash. '
                         . 'Without one there is nothing to check against if the borrower '
                         . 'says the money never arrived.'];
    }

    $date = trim((string) ($in['date'] ?? '')) ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['ok' => false, 'error' => 'The date of the payout should look like 2026-11-05.'];
    }
    if ($date > date('Y-m-d')) {
        return ['ok' => false,
                'error' => 'A payout cannot be dated in the future. The loan is due a set '
                         . 'number of weeks from this date, so a date that has not happened '
                         . 'yet gives the borrower time nobody agreed to.'];
    }

    $paid      = av_ngwee($in['amount'] ?? 0);
    $principal = av_disbursement_principal_ngwee($record);
    if ($paid <= 0) {
        return ['ok' => false, 'error' => 'A payout has to be an amount above zero.'];
    }

    $reason = trim((string) ($in['override_reason'] ?? ''));
    if ($paid !== $principal && $reason === '') {
        return ['ok' => false,
                'difference_ngwee' => abs($paid - $principal),
                'error' => 'The approved amount is ' . av_money($principal) . ' and this payout '
                         . 'is ' . av_money($paid) . '. If that is deliberate, say why — the '
                         . 'borrower is charged interest on the approved amount either way.'];
    }

    $record['status']             = 'Disbursed';
    $record['disburse_date']      = $date;
    $record['disbursed_amount']   = round($paid / 100, 2);
    $record['disburse_method']    = $method;
    $record['disburse_reference'] = $reference;
    $record['disbursed_by']       = av_user()['username'] ?? 'system';
    $record['disbursed_at']       = date('c');
    if ($reason !== '') $record['disburse_note'] = $reason;

    return ['ok' => true, 'record' => $record];
}

/** Approved loans that have not gone out yet: the day's payout queue. */
function av_disbursement_pending(array $records): array {
    $out = [];
    foreach ($records as $r) {
        if (strcasecmp(trim((string) ($r['status'] ?? '')), 'Approved') !== 0) continue;
        if (trim((string) ($r['disburse_date'] ?? '')) !== '') continue;
        $out[] = $r;
    }
    return $out;
}

/**
 * Loans marked as disbursed that carry no date.
 *
 * Not hypothetical: the status dropdown has always been able to set Disbursed
 * on its own. Each of these is a loan the rate engine cannot age — it will
 * never show as overdue and will never attract the penalty — so they need a
 * date putting on them by hand.
 */
function av_disbursement_undated(array $records): array {
    $out = [];
    foreach ($records as $r) {
        if (strcasecmp(trim((string) ($r['status'] ?? '')), 'Disbursed') !== 0) continue;
        if (trim((string) ($r['disburse_date'] ?? '')) !== '') continue;
        $out[] = $r;
    }
    return $out;
}
