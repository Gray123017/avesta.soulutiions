<?php
/**
 * AVESTA — share-tags.php
 *
 * One source for the title, description, canonical URL and preview card of
 * every public page.
 *
 * Why this file exists: until 28 November 2026 the public site had no Open
 * Graph tags at all. In Zambia a business is passed around on WhatsApp, and
 * WhatsApp does not run JavaScript — it fetches the page once and reads these
 * tags. Without them a shared link rendered as a bare blue URL with no title,
 * no description and no picture, which from a lender reads as something you
 * should not tap. Every referral anybody had already sent looked like that.
 *
 * Two rules the content here is bound by:
 *
 *   A share card is advertising. It may not claim a licence, registration or
 *   approval that Avesta has not been issued — that is precisely what the
 *   Money-Lenders Act is pointed at — and it may not quote a rate the engine
 *   will not honour. A test checks both against rates.json on every run.
 *
 *   The tags must be in the HTML the server sends. Anything written in by
 *   app.js after load is invisible to every preview fetcher there is.
 *
 * No side effects: this file defines functions and nothing else.
 */
declare(strict_types=1);

require_once __DIR__ . '/asset-version.php';

const AV_SHARE_BASE = 'https://avesta.solutions/';

/**
 * The card for one page. $key is the ?page= value, or a page's own name
 * ('loans', 'downloads', 'legal'). Anything unknown falls back to the front
 * door rather than shipping an empty card.
 */
function av_share(string $key = ''): array
{
    $cards = [
        '' => [
            'path'  => '',
            'title' => 'Avesta Enterprises · Lending & IT Consulting',
            'og'    => 'Avesta Enterprises — lending and IT, Ndola',
            'desc'  => 'Short-term lending and practical IT services across Zambia. '
                     . 'Based in Kansenshi, Ndola, working countrywide.',
            'image' => 'assets/avesta/share/avesta.png',
        ],
        'lending' => [
            'path'  => 'index.php?page=lending',
            'title' => 'Short-term loans in Ndola & across Zambia | Avesta Lending',
            'og'    => 'Avesta Lending — short-term finance, clearly explained',
            'desc'  => 'Borrow for one to four weeks with flat interest. The amount, the cost and '
                     . 'the repayment dates are stated before you commit.',
            'image' => 'assets/avesta/share/lending.png',
        ],
        'calculator' => [
            'path'  => 'index.php?page=calculator',
            'title' => 'Loan calculator | Avesta Lending',
            'og'    => 'Work out what a loan costs before you apply',
            'desc'  => 'Enter an amount and a term to see the interest, the total to repay and an '
                     . 'estimated repayment schedule, before you apply for anything.',
            'image' => 'assets/avesta/share/lending.png',
        ],
        'how-it-works' => [
            'path'  => 'index.php?page=how-it-works',
            'title' => 'How borrowing from Avesta works | Avesta Lending',
            'og'    => 'How borrowing from Avesta works',
            'desc'  => 'Four steps from choosing an amount to money in hand: what we ask for, how '
                     . 'the assessment runs, and what you sign before anything is owed.',
            'image' => 'assets/avesta/share/lending.png',
        ],
        'currency' => [
            'path'  => 'index.php?page=currency',
            'title' => 'Currency converter | Avesta Enterprises',
            'og'    => 'Kwacha currency converter',
            'desc'  => 'Convert between the kwacha and major currencies using a dated indicative '
                     . 'rate, or enter a rate you have already agreed with a provider.',
            'image' => 'assets/avesta/share/avesta.png',
        ],
        'it' => [
            'path'  => 'index.php?page=it',
            'title' => 'IT services in Ndola & across Zambia | Avesta Consulting',
            'og'    => 'Avesta Consulting — IT services across Zambia',
            'desc'  => 'Repairs, networks and Wi-Fi, printers, websites, data, CCTV and '
                     . 'cybersecurity. We tell you what a job costs before any work begins.',
            'image' => 'assets/avesta/share/it.png',
        ],
        'about' => [
            'path'  => 'index.php?page=about',
            'title' => 'About Avesta Enterprises | Ndola, Zambia',
            'og'    => 'About Avesta Enterprises',
            'desc'  => 'Who runs Avesta, what the two sides of the business do, and how to reach '
                     . 'the office on Thom Avenue in Kansenshi, Ndola.',
            'image' => 'assets/avesta/share/avesta.png',
        ],
        'contact' => [
            'path'  => 'index.php?page=contact',
            'title' => 'Contact Avesta Enterprises | Kansenshi, Ndola',
            'og'    => 'Contact Avesta Enterprises',
            'desc'  => 'Phone, WhatsApp, email and directions to the Avesta office in Kansenshi, '
                     . 'Ndola. Open Monday to Saturday, 08:00 to 17:00.',
            'image' => 'assets/avesta/share/avesta.png',
        ],
        'downloads' => [
            'path'  => 'downloads.php',
            'title' => 'G.I.T software downloads | Avesta Consulting',
            'og'    => 'G.I.T software — built in Ndola, free to download',
            'desc'  => 'Device health monitoring and other tools built by Avesta Consulting for '
                     . 'Zambian businesses. Requirements and test builds are listed in full.',
            'image' => 'assets/avesta/share/it.png',
        ],
        'legal' => [
            'path'  => 'legal.php',
            'title' => 'Privacy, terms and complaints | Avesta Enterprises',
            'og'    => 'Avesta Enterprises — privacy, terms and complaints',
            'desc'  => 'How Avesta handles your information, the terms that apply to a loan, and '
                     . 'what to do if something has gone wrong.',
            'image' => 'assets/avesta/share/avesta.png',
        ],
    ];

    $card = $cards[$key] ?? $cards[''];
    $card['url'] = AV_SHARE_BASE . $card['path'];
    // Versioned, so a redesigned card is not served from a week-old cache at
    // Meta's end; the query string is kept out of the file-exists check.
    $card['imageUrl'] = AV_SHARE_BASE . ltrim(av_asset('/' . $card['image']), '/');
    return $card;
}

/** The page key for index.php, from ?page=. */
function av_share_key_from_query(): string
{
    $page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
    $page = strtolower(trim($page));
    return preg_match('/^[a-z-]{0,32}$/', $page) ? $page : '';
}

/**
 * Print the head tags for a page. Call it inside <head>.
 *
 * $override lets a page keep wording it already does better than this file
 * can — downloads.php naming its actual products, legal.php varying by
 * policy — while still getting the canonical, the card and the image.
 * Accepts any of: title, desc, og, url.
 */
function av_share_head(string $key = '', bool $withStructuredData = false, array $override = []): void
{
    $c  = array_merge(av_share($key), array_filter($override, static fn($v) => $v !== null && $v !== ''));
    $e  = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    ?>
<title><?= $e($c['title']) ?></title>
<meta name="description" content="<?= $e($c['desc']) ?>">
<link rel="canonical" href="<?= $e($c['url']) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Avesta Enterprises">
<meta property="og:locale" content="en_ZM">
<meta property="og:title" content="<?= $e($c['og']) ?>">
<meta property="og:description" content="<?= $e($c['desc']) ?>">
<meta property="og:url" content="<?= $e($c['url']) ?>">
<meta property="og:image" content="<?= $e($c['imageUrl']) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Avesta Enterprises — lending and IT consulting, Ndola, Zambia">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($c['og']) ?>">
<meta name="twitter:description" content="<?= $e($c['desc']) ?>">
<meta name="twitter:image" content="<?= $e($c['imageUrl']) ?>">
<?php
    if ($withStructuredData) {
        av_share_structured_data();
    }
}

/**
 * The structured data for the front door, rendered server-side.
 *
 * It states only things that are true and checkable: who the business is,
 * where it is, how to reach it and when it is open. No rating, no review
 * count, no claim of accreditation — those would be fabricated, and Google
 * penalises invented review markup anyway.
 */
function av_share_structured_data(): void
{
    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => 'Avesta Enterprises',
        'url'      => AV_SHARE_BASE,
        'description' => 'Short-term lending and IT consulting in Ndola, Zambia.',
        'email'    => 'info@avesta.solutions',
        'telephone' => ['+260971013108', '+260769974200'],
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'House No. 3, Thom Avenue',
            'addressLocality' => 'Kansenshi, Ndola',
            'addressCountry'  => 'ZM',
        ],
        'areaServed' => ['@type' => 'Country', 'name' => 'Zambia'],
        'department' => [
            [
                '@type' => 'FinancialService',
                'name'  => 'Avesta Lending',
                'url'   => AV_SHARE_BASE . 'loans.php',
                'currenciesAccepted' => 'ZMW',
                'openingHours' => 'Mo-Sa 08:00-17:00',
            ],
            [
                '@type' => 'ProfessionalService',
                'name'  => 'Avesta Consulting',
                'url'   => AV_SHARE_BASE . 'index.php?page=it',
                'openingHours' => 'Mo-Sa 08:00-17:00',
            ],
        ],
    ];
    echo '<script type="application/ld+json">'
       . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
       . "</script>\n";
}
