<?php
/**
 * Who may apply, and what they must bring.
 *
 * Three ways in: through an employer signed up with Avesta, as someone
 * formally employed applying alone, or as someone with no payslip who
 * qualifies on security instead. Each asks for a different set of documents,
 * and the point of asking the question first is that an applicant only ever
 * sees their own list.
 *
 * assets/avesta/categories.json is the source. The form reads it so the
 * applicant sees the right list; this file enforces it when the application
 * arrives, because a browser field can be edited and a server check cannot.
 *
 * One rule governs the rollout: an application that names NO category is left
 * alone. The form did not always ask, so refusing those would reject every
 * application made before the chooser shipped — including any half-finished
 * one a borrower returns to. When the chooser is live everywhere, flipping
 * requireCategory in the JSON closes that door with no code change.
 */

const AV_CATEGORIES_FILE = __DIR__ . '/assets/avesta/categories.json';

/** Load and validate the published categories. Throws rather than guessing. */
function av_categories_load(?string $path = null): array {
    static $cache = [];
    $path = $path ?? AV_CATEGORIES_FILE;
    if (isset($cache[$path])) return $cache[$path];

    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Category table missing: ' . $path);
    }
    $cfg = json_decode($raw, true);
    if (!is_array($cfg) || !isset($cfg['categories']) || !is_array($cfg['categories'])) {
        throw new RuntimeException('Category table is not valid JSON: ' . $path);
    }

    foreach ($cfg['categories'] as $key => $c) {
        if (!isset($c['documents']) || !is_array($c['documents'])) {
            throw new RuntimeException("Category '$key' lists no documents");
        }
        if (!array_key_exists('collateralRequired', $c)) {
            throw new RuntimeException("Category '$key' does not say whether security is required");
        }
    }

    return $cache[$path] = $cfg;
}

/** Every category key, for a dropdown or a validation message. */
function av_category_keys(?array $cfg = null): array {
    $cfg = $cfg ?? av_categories_load();
    return array_keys($cfg['categories']);
}

/** One category, or null if the key is not published. */
function av_category(string $key, ?array $cfg = null): ?array {
    $cfg = $cfg ?? av_categories_load();
    return $cfg['categories'][$key] ?? null;
}

/** The human label for a document field, falling back to the field name. */
function av_document_label(string $field, ?array $cfg = null): string {
    $cfg = $cfg ?? av_categories_load();
    return $cfg['documentLabels'][$field] ?? $field;
}

/**
 * Has this document actually been supplied?
 *
 * A document arrives either as base64 on the way in, or as a stored path on a
 * record that has already been through extractDocuments(). Both count: a
 * borrower who uploaded their payslip yesterday has supplied it.
 */
function av_document_present(array $payload, string $field): bool {
    foreach ([$field . '_base64', $field, $field . '_url', $field . '_path'] as $key) {
        $v = $payload[$key] ?? null;
        if (is_string($v) && trim($v) !== '') return true;
    }
    return false;
}

/**
 * Is there at least one security item with something in it?
 *
 * The form collects up to three, as a description and an estimated value, and
 * separately as photographs. An item counts when it has either a description
 * or an uploaded photograph — a row of empty boxes is not security.
 */
function av_collateral_present(array $payload): bool {
    for ($i = 1; $i <= 3; $i++) {
        if (av_document_present($payload, 'doc_collateral' . $i)) return true;

        foreach (["collateral{$i}_desc", "collateral_desc_{$i}", "collateral{$i}"] as $key) {
            $v = $payload[$key] ?? null;
            if (is_string($v) && trim($v) !== '') return true;
        }
    }

    // The form may also send the items as a list rather than numbered fields.
    $list = $payload['collateral_items'] ?? $payload['collateral'] ?? null;
    if (is_array($list)) {
        foreach ($list as $item) {
            if (is_string($item) && trim($item) !== '') return true;
            if (is_array($item)) {
                $desc = trim((string) ($item['description'] ?? $item['desc'] ?? $item['item'] ?? ''));
                if ($desc !== '') return true;
            }
        }
    }
    return false;
}

/**
 * What is still missing before this application can be submitted.
 *
 * Returns human sentences, ready to show the applicant. An empty array means
 * nothing is missing. A payload with no category returns nothing missing
 * while requireCategory is false — see the note at the top of this file.
 */
function av_category_missing(array $payload, ?array $cfg = null): array {
    $cfg = $cfg ?? av_categories_load();

    $key = trim((string) ($payload['applicant_category'] ?? ''));
    if ($key === '') {
        return empty($cfg['requireCategory'])
            ? []
            : ['Choose how you are applying before submitting.'];
    }

    $category = av_category($key, $cfg);
    if ($category === null) {
        return ['That way of applying is not one we offer. Choose one of the published options.'];
    }

    $missing = [];
    foreach ($category['documents'] as $field) {
        if (!av_document_present($payload, $field)) {
            $missing[] = av_document_label($field, $cfg);
        }
    }
    if (!empty($category['collateralRequired']) && !av_collateral_present($payload)) {
        $missing[] = 'At least one security item, with a description or a photograph';
    }
    return $missing;
}

/** True when the application carries everything its category asks for. */
function av_category_complete(array $payload, ?array $cfg = null): bool {
    return av_category_missing($payload, $cfg) === [];
}
