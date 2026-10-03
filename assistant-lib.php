<?php
// No customer data or credentials are returned by these helpers.
function av_chat_config($root) {
    $config = [];
    $file = dirname($root) . '/avesta-secrets.php';
    if (is_readable($file)) {
        ob_start();
        try { $value = require $file; if (is_array($value)) $config = $value; }
        catch (Throwable $e) { $config = []; }
        finally { ob_end_clean(); }
    }
    $key = trim((string)(getenv('OPENAI_API_KEY') ?: ($config['OPENAI_API_KEY'] ?? '')));
    if (!preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key)) $key = '';
    return ['key' => $key, 'model' => 'gpt-4.1-mini'];
}
function av_chat_reserve($file, $ip, $now) {
    $f = @fopen($file, 'c+');
    if (!$f || !flock($f, LOCK_EX)) { if ($f) fclose($f); return false; }
    try {
        $prefix = "<?php exit; ?>\n";
        $raw = stream_get_contents($f);
        $state = json_decode(substr($raw, strlen($prefix)), true);
        $hour = (int)floor($now / 3600); $day = (int)floor($now / 86400);
        if (!is_array($state) || ($state['day'] ?? null) !== $day) $state = ['day'=>$day, 'total'=>0, 'clients'=>[]];
        $id = hash('sha256', $ip);
        foreach ($state['clients'] as $client => $entry) if ($entry['hour'] !== $hour) unset($state['clients'][$client]);
        $entry = $state['clients'][$id] ?? ['hour'=>$hour, 'count'=>0];
        // Bound paid requests, including failed provider attempts.
        if ($state['total'] >= 100 || $entry['count'] >= 10) return false;
        $entry['count']++; $state['clients'][$id] = $entry; $state['total']++;
        $encoded = $prefix . json_encode($state);
        rewind($f); if (!ftruncate($f, 0) || fwrite($f, $encoded) !== strlen($encoded) || !fflush($f)) return false;
        @chmod($file, 0600); return true;
    } finally { flock($f, LOCK_UN); fclose($f); }
}
function av_chat_answer($response) {
    $text = ''; $sources = [];
    foreach (($response['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') continue;
        foreach (($item['content'] ?? []) as $part) {
            if (($part['type'] ?? '') !== 'output_text') continue;
            $text .= (string)($part['text'] ?? '') . "\n";
            foreach (($part['annotations'] ?? []) as $a) {
                $url = $a['url'] ?? '';
                if (($a['type'] ?? '') === 'url_citation' && is_string($url) && strlen($url) <= 2048 && filter_var($url, FILTER_VALIDATE_URL) && strtolower(parse_url($url, PHP_URL_SCHEME) ?? '') === 'https' && count($sources) < 8) {
                    $sources[$url] = ['url'=>$url, 'title'=>substr((string)($a['title'] ?? 'Source'), 0, 200)];
                }
            }
        }
    }
    if (!trim($text)) return null;
    return ['answer'=>substr(trim($text),0,16000), 'sources'=>array_values($sources), 'notice'=>$sources ? 'AI answer · online sources linked below' : 'AI answer · no online source was cited'];
}
