<?php
$DATA_DIR = __DIR__ . '/3502_data';
$STATE_FILE = $DATA_DIR . '/state.json';

$state = json_decode(file_get_contents($STATE_FILE), true);
if (!$state || empty($state['candidates'])) { echo "No candidates\n"; exit(1); }

$candidates = $state['candidates'];
$total = count($candidates);
echo "Total: $total\n";

$UA = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';
$working = [];
$nonworking = [];
$concurrency = 100;
$chunks = array_chunk($candidates, $concurrency);

foreach ($chunks as $ci => $chunk) {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($chunk as $item) {
        $url = trim($item['url'] ?? '');
        if ($url === '') continue;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_USERAGENT      => $UA,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RANGE          => '0-2048',
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int)$ch] = ['ch' => $ch, 'item' => $item];
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.1);
    } while ($running > 0);

    foreach ($handles as $h) {
        $ch = $h['ch'];
        $item = $h['item'];
        $body = curl_multi_getcontent($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ok = ($status >= 200 && $status < 400 && $body !== '' && $body !== false);
        if ($ok && preg_match('/\.m3u8(?:\?|$)/i', $item['url'])) {
            $ok = (stripos($body, '#EXTM3U') !== false);
        }
        $item['status'] = $ok ? 'Working' : 'Non-working';
        $item['http_status'] = $status;
        if ($ok) $working[] = $item;
        else $nonworking[] = $item;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    echo "Batch " . ($ci + 1) . "/" . count($chunks) . " | working=" . count($working) . "\n";

    $state['working'] = $working;
    $state['nonworking'] = $nonworking;
    $state['check_index'] = min(($ci + 1) * $concurrency, $total);
    $state['phase'] = ($state['check_index'] >= $total) ? 'complete' : 'check';
    file_put_contents($STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function detect_lang($c) {
    static $langMap = null;
    if ($langMap === null) {
        $file = __DIR__ . '/langmap_indian.json';
        $langMap = is_file($file) ? json_decode(file_get_contents($file), true) : [];
        if (!is_array($langMap)) $langMap = [];
    }
    $url = strtolower(trim($c['url'] ?? ''));
    if ($url !== '' && isset($langMap[$url])) return $langMap[$url];
    return 'Unknown';
}

function makeM3U($items) {
    $out = "#EXTM3U\n";
    foreach ($items as $c) {
        $lang = detect_lang($c);
        $cat  = $c['category'] ?? $c['group'] ?? 'General';
        $name = $c['name'] ?? '';
        $logo = $c['logo'] ?? '';
        $url  = $c['url'] ?? '';
        $attrs = 'group-title="' . $cat . '"';
        if ($lang !== '') $attrs .= ' tvg-language="' . $lang . '"';
        if ($logo !== '') $attrs .= ' tvg-logo="' . $logo . '"';
        $out .= '#EXTINF:-1 ' . $attrs . ',' . $name . "\n";
        $out .= $url . "\n";
    }
    return $out;
}

file_put_contents('working.m3u', makeM3U($working));
file_put_contents('nonworking.m3u', makeM3U($nonworking));

$combined = array_merge($working, $nonworking);
$seen = []; $unique = [];
foreach ($combined as $c) {
    $url = strtolower(trim($c['url'] ?? ''));
    if ($url === '' || isset($seen[$url])) continue;
    $seen[$url] = true; $unique[] = $c;
}
file_put_contents('combined.m3u', makeM3U($unique));

echo "\n=== DONE ===\nWorking: " . count($working) . "\nNon-working: " . count($nonworking) . "\nCombined: " . count($unique) . "\n";
