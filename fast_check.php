<?php
$DATA_DIR = __DIR__ . '/3502_data';
$STATE_FILE = $DATA_DIR . '/state.json';

$state = json_decode(file_get_contents($STATE_FILE), true);
if (!$state || empty($state['candidates'])) { echo "No candidates\n"; exit(1); }

$candidates = $state['candidates'];
$total = count($candidates);
echo "Total candidates: $total\n";

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

// ============= M3U WRITER =============
function makeM3U($items) {
    $out = "#EXTM3U\n";
    foreach ($items as $c) {
        $lang = $c['lang'] ?? $c['language'] ?? '';
        $cat  = $c['category'] ?? $c['group'] ?? 'General';
        $name = $c['name'] ?? '';
        $logo = $c['logo'] ?? '';
        $url  = $c['url'] ?? '';
        if ($url === '') continue;

        $attrs = 'group-title="' . $cat . '"';
        if ($lang !== '') $attrs .= ' tvg-language="' . $lang . '"';
        if ($logo !== '') $attrs .= ' tvg-logo="' . $logo . '"';

        $out .= '#EXTINF:-1 ' . $attrs . ',' . $name . "\n";
        $out .= $url . "\n";
    }
    return $out;
}

// ============= WORKING.M3U =============
// Same channel multiple times allowed if URL different
// Same URL only once
function dedupByUrl($items) {
    $seen = [];
    $out = [];
    foreach ($items as $c) {
        $url = strtolower(trim($c['url'] ?? ''));
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true;
        $out[] = $c;
    }
    return $out;
}

// Working — different URL same channel allowed
$workingFinal = dedupByUrl($working);
file_put_contents('working.m3u', makeM3U($workingFinal));

// Non-working
$nonworkingFinal = dedupByUrl($nonworking);
file_put_contents('nonworking.m3u', makeM3U($nonworkingFinal));

// Combined
$combinedFinal = dedupByUrl(array_merge($working, $nonworking));
file_put_contents('combined.m3u', makeM3U($combinedFinal));

// ============= STATS =============
$stats = "IPTV STATS\n" . str_repeat("=", 50) . "\n\n";
$stats .= "Total candidates    : $total\n";
$stats .= "Working (final)     : " . count($workingFinal) . "\n";
$stats .= "Non-working (final) : " . count($nonworkingFinal) . "\n";
$stats .= "Combined (final)    : " . count($combinedFinal) . "\n";
$stats .= "Updated             : " . date("Y-m-d H:i:s") . " UTC\n";
file_put_contents('stats.txt', $stats);
echo "\n" . $stats;

// ============= CATEGORY + LANGUAGE PLAYLISTS =============
@mkdir('playlists', 0777, true);

$langs = [];
$cats = [];
foreach ($workingFinal as $c) {
    $l = $c['lang'] ?? $c['language'] ?? '';
    $g = $c['category'] ?? $c['group'] ?? 'General';
    if ($l !== '') $langs[$l] = true;
    if ($g !== '') $cats[$g] = true;
}

// Language-wise
foreach (array_keys($langs) as $lang) {
    $filtered = array_values(array_filter($workingFinal, function($c) use ($lang) {
        return strcasecmp($c['lang'] ?? $c['language'] ?? '', $lang) === 0;
    }));
    $safe = preg_replace('/[^a-z0-9]+/i', '-', strtolower($lang));
    file_put_contents("playlists/lang-$safe.m3u", makeM3U($filtered));
    echo "playlists/lang-$safe.m3u (" . count($filtered) . ")\n";
}

// Category-wise
foreach (array_keys($cats) as $cat) {
    $filtered = array_values(array_filter($workingFinal, function($c) use ($cat) {
        return strcasecmp($c['category'] ?? $c['group'] ?? '', $cat) === 0;
    }));
    $safe = preg_replace('/[^a-z0-9]+/i', '-', strtolower($cat));
    file_put_contents("playlists/cat-$safe.m3u", makeM3U($filtered));
    echo "playlists/cat-$safe.m3u (" . count($filtered) . ")\n";
}

// Language + Category combination
foreach (array_keys($langs) as $lang) {
    foreach (array_keys($cats) as $cat) {
        $filtered = array_values(array_filter($workingFinal, function($c) use ($lang, $cat) {
            $cl = $c['lang'] ?? $c['language'] ?? '';
            $cg = $c['category'] ?? $c['group'] ?? '';
            return strcasecmp($cl, $lang) === 0 && strcasecmp($cg, $cat) === 0;
        }));
        if (count($filtered) === 0) continue;
        $safeL = preg_replace('/[^a-z0-9]+/i', '-', strtolower($lang));
        $safeC = preg_replace('/[^a-z0-9]+/i', '-', strtolower($cat));
        file_put_contents("playlists/lang-$safeL-cat-$safeC.m3u", makeM3U($filtered));
        echo "playlists/lang-$safeL-cat-$safeC.m3u (" . count($filtered) . ")\n";
    }
}

echo "\n=== DONE ===\n";
echo "Total playlists in playlists/ folder: " . count(glob('playlists/*.m3u')) . "\n";
