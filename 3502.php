<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 3502.php  (KSWEB-compatible, mbstring-free)
|--------------------------------------------------------------------------
*/

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');
@ini_set('zlib.output_compression', '0');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $dir = __DIR__ . '/3502_data';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($dir . '/fatal.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $err['message'] . ' @ ' .
            $err['file'] . ':' . $err['line'] . PHP_EOL,
            FILE_APPEND | LOCK_EX);
        if (!headers_sent()) header('Content-Type: application/json');
        echo '{"ok":false,"fatal":"' . addslashes($err['message']) . '","line":' . $err['line'] . '}';
    }
});

$DATA_DIR = __DIR__ . '/3502_data';
if (!is_dir($DATA_DIR)) @mkdir($DATA_DIR, 0777, true);

$STATE_FILE = $DATA_DIR . '/state.json';
$LOG_FILE   = $DATA_DIR . '/log.txt';
$USER_AGENT = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';
$IPTV_INDEX = 'https://iptv-org.github.io/iptv/index.m3u';

$SOURCES = [
    ['label' => 'IPTV-ORG Index',  'url' => 'https://iptv-org.github.io/iptv/index.m3u',           'type' => 'complete'],
    ['label' => 'India',           'url' => 'https://iptv-org.github.io/iptv/countries/in.m3u',    'type' => 'india'],
    ['label' => 'UK',              'url' => 'https://iptv-org.github.io/iptv/countries/uk.m3u',    'type' => 'diaspora'],
    ['label' => 'USA',             'url' => 'https://iptv-org.github.io/iptv/countries/us.m3u',    'type' => 'diaspora'],
    ['label' => 'Australia',       'url' => 'https://iptv-org.github.io/iptv/countries/au.m3u',    'type' => 'diaspora'],
    ['label' => 'Pakistan',        'url' => 'https://iptv-org.github.io/iptv/countries/pk.m3u',    'type' => 'diaspora'],
    ['label' => 'Bangladesh',      'url' => 'https://iptv-org.github.io/iptv/countries/bd.m3u',    'type' => 'diaspora'],
    ['label' => 'Ireland',         'url' => 'https://iptv-org.github.io/iptv/countries/ie.m3u',    'type' => 'diaspora'],
    ['label' => 'Hindi',           'url' => 'https://iptv-org.github.io/iptv/languages/hin.m3u',   'type' => 'hindi'],
    ['label' => 'Bhojpuri',        'url' => 'https://iptv-org.github.io/iptv/languages/bho.m3u',   'type' => 'hindi'],
    ['label' => 'English India',   'url' => 'https://iptv-org.github.io/iptv/languages/eng.m3u',   'type' => 'english'],

    ['label' => 'Garden Music', 'url' => 'https://iptv-org.github.io/iptv/categories/music.m3u', 'type' => 'music'],
    ['label' => 'Garden Cartoon', 'url' => 'https://iptv-org.github.io/iptv/categories/animation.m3u', 'type' => 'cartoon'],
    ['label' => 'Garden Documentary', 'url' => 'https://iptv-org.github.io/iptv/categories/documentary.m3u', 'type' => 'science'],
    ['label' => 'Garden Sports', 'url' => 'https://iptv-org.github.io/iptv/categories/sports.m3u', 'type' => 'sports'],
];

$BLOCK = ['tamil','telugu','malayalam','kannada','bengali','bangla','marathi','gujarati','punjabi','odia','oriya','assamese','urdu','sun tv','sun news','ktv','adithya','gemini','eenadu','etv telugu','asianet','manorama','flowers tv','mathrubhumi','mazhavil','surya tv','udaya','colors kannada','colors tamil','colors marathi','zee kannada','zee tamil','zee telugu','zee keralam','star suvarna','star vijay','star maa','jaya tv','polimer','puthiya','thanthi','abn andhra','tv9 telugu','tv9 kannada','tv9 marathi','news18 tamil','news18 kerala','news18 kannada','news18 assam','dd chandana','dd yadagiri','dd malayalam','dd podhigai','dd sahyadri','chithiram','jaya max'];

$ENG_HINTS = ['wion','ndtv','republic','times now','cnn-news18','cnn news18','india today','mirror now','newsx','dd india','cnbc','et now','bloomberg','bbc','al jazeera','dw english','france 24','discovery','history tv','travelxp','good times'];

$SPORT_HINTS = ['star sports','sony six','sony ten','sony espn','dd sports','willow','cricket','ptv sports','ten cricket','sports18','jio cricket','t sports','tsports','unite8 sports','unite sports','fox sports','1sports','ssc sports','astro cricket','sky sports cricket','super sport cricket','a sports','geo super'];

$INTL_SPORT = ['cricket','willow','fox cricket','sky sports cricket','tnt sport','tnt sports','super sport','supersport','star sports','sony six','sony ten','sony espn','t sports','tsports','dd sports','ptv sports','ten cricket','sports18','cricket gold','astro cricket','nine cricket','7 cricket','fox sports'];

/*
|--------------------------------------------------------------------------
| UTF-8 cleaning — NO mbstring dependency
|--------------------------------------------------------------------------
*/

function strip_invalid_utf8($value)
{
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = strip_invalid_utf8($v);
        return $value;
    }

    if (is_string($value)) {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            return $value;
        }

        $clean = '';
        $len   = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($value[$i]);
            if ($c < 0x80) { $clean .= $value[$i]; continue; }
            if (($c & 0xE0) === 0xC0 && $i + 1 < $len && (ord($value[$i+1]) & 0xC0) === 0x80) {
                $clean .= substr($value, $i, 2); $i++; continue;
            }
            if (($c & 0xF0) === 0xE0 && $i + 2 < $len && (ord($value[$i+1]) & 0xC0) === 0x80 && (ord($value[$i+2]) & 0xC0) === 0x80) {
                $clean .= substr($value, $i, 3); $i += 2; continue;
            }
            if (($c & 0xF8) === 0xF0 && $i + 3 < $len && (ord($value[$i+1]) & 0xC0) === 0x80 && (ord($value[$i+2]) & 0xC0) === 0x80 && (ord($value[$i+3]) & 0xC0) === 0x80) {
                $clean .= substr($value, $i, 4); $i += 3; continue;
            }
            $clean .= '?';
        }
        return $clean;
    }

    return $value;
}

function utf8_clean($value) { return strip_invalid_utf8($value); }

function safe_json_encode($data)
{
    $data  = strip_invalid_utf8($data);
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;

    $json = json_encode($data, $flags);
    if ($json === false) $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($json === false) $json = '{"ok":false,"error":"encode failed"}';
    return $json;
}

/*
|--------------------------------------------------------------------------
| IO
|--------------------------------------------------------------------------
*/

function json_read(string $file, $default = [])
{
    if (!is_file($file)) return $default;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '' || trim($raw) === 'null') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function json_write(string $file, $data): bool
{
    $data  = strip_invalid_utf8($data);
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;

    $json = json_encode($data, $flags);
    if ($json === false) {
        log_line('json_encode FAILED: ' . json_last_error_msg());
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    }
    if ($json === false || $json === 'null') {
        log_line('FINAL encode failed');
        return false;
    }

    // KSWEB: rename() fail hota hai — direct write use karo
    // Pehle truncate karo, phir likho
    $fp = @fopen($file, 'w');
    if ($fp === false) {
        log_line('fopen FAILED: ' . $file);
        return false;
    }

    $written = @fwrite($fp, $json);
    @fflush($fp);
    @fclose($fp);

    if ($written === false || $written !== strlen($json)) {
        log_line('fwrite partial: ' . var_export($written, true) . ' vs ' . strlen($json));
        return false;
    }

    @chmod($file, 0666);

    return true;
}

function log_line(string $text): void
{
    global $LOG_FILE;
    $text = strip_invalid_utf8($text);
    @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $text . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function respond(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo safe_json_encode($data);
    exit;
}

function contains_any(string $haystack, array $needles): bool
{
    $haystack = strtolower($haystack);
    foreach ($needles as $n) {
        if ($n !== '' && strpos($haystack, strtolower($n)) !== false) return true;
    }
    return false;
}

function clean_name(string $name): string
{
    $name = strip_invalid_utf8(trim($name));
    $name = preg_replace('/\s*\(\d{3,4}p\)\s*$/i', '', $name);
    $name = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $name);
    return trim((string)$name);
}

function is_blocked(string $name): bool
{
    global $BLOCK;
    return contains_any($name, $BLOCK);
}

function category_of(string $group, string $name): string
{
    $g = strtolower($group . ' ' . $name);
    if (preg_match('/sport|cricket|football|hockey|tennis|wwe|boxing|soccer|t sports|star sports|sony six|sony ten|sony espn/i', $g)) return 'Sports';
    if (preg_match('/news|wion|ndtv|republic|times now|bbc|cnn|aaj tak/i', $g)) return 'News';
    if (preg_match('/movie|cinema|cineplex|film|classic/i', $g)) return 'Movie';
    if (preg_match('/music|sangeet|mtv|9xm|song/i', $g)) return 'Music';
    if (preg_match('/kid|cartoon|nick|pogo|hungama|disney|sony yay|cartoon network|discovery kids/i', $g)) return 'Cartoon';
    if (preg_match('/lifestyle|fox life|tlc|travelxp|food|ndtv good times|good times|fashion|ftv/i', $g)) return 'Lifestyle';
    if (preg_match('/science|discovery|national geographic|nat geo|animal planet|history tv|ngc/i', $g)) return 'Science';
    if (preg_match('/relig|devot|bhakti|sanskar/i', $g)) return 'Devotional';
    if (preg_match('/business|cnbc|et now|bloomberg/i', $g)) return 'Business';
    return 'Entertainment';
}

/*
|--------------------------------------------------------------------------
| Fetch / Parse
|--------------------------------------------------------------------------
*/

function fetch_text(string $url, int $timeout = 60): string
{
    global $USER_AGENT;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => $USER_AGENT,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Accept: */*', 'Cache-Control: no-cache']
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : '';
    }
    $ctx = stream_context_create([
        'http' => ['method'=>'GET','timeout'=>$timeout,'follow_location'=>1,'max_redirects'=>5,
                   'header'=>"User-Agent: {$USER_AGENT}\r\nAccept: */*\r\n"],
        'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false]
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

function parse_m3u(string $text, string $sourceType = ''): array
{
    static $langMap = null;
    if ($langMap === null) {
        $langMap = [];
        $apiFile = __DIR__ . '/3502_data/channels_api.json';
        if (is_file($apiFile)) {
            $api = json_decode(file_get_contents($apiFile), true);
            if (is_array($api)) {
                foreach ($api as $ch) {
                    if (!empty($ch['id']) && !empty($ch['languages'])) {
                        $langMap[strtolower($ch['id'])] = $ch['languages'];
                    }
                }
            }
        }
    }
    static $langMap = null;
    if ($langMap === null) {
        $apiFile = __DIR__ . '/3502_data/channels_api.json';
        $langMap = [];
        if (is_file($apiFile)) {
            $api = json_decode(file_get_contents($apiFile), true);
            if (is_array($api)) {
                foreach ($api as $ch) {
                    if (!empty($ch['id']) && !empty($ch['languages'])) {
                        $langMap[strtolower($ch['id'])] = $ch['languages'];
                    }
                }
            }
        }
    }
    $out = [];
    $current = null;
    $lines = preg_split('/\r\n|\r|\n/', $text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (stripos($line, '#EXTINF:') === 0) {
            $name = '';
            $comma = strpos($line, ',');
            if ($comma !== false) $name = trim(substr($line, $comma + 1));

            $group = 'General'; $logo = ''; $country = ''; $language = '';
            if (preg_match('/group-title="([^"]*)"/i',  $line, $m)) $group    = trim($m[1]);
            if (preg_match('/tvg-logo="([^"]*)"/i',     $line, $m)) $logo     = trim($m[1]);
            if (preg_match('/tvg-country="([^"]*)"/i',  $line, $m)) $country  = trim($m[1]);
            if (preg_match('/tvg-language="([^"]*)"/i', $line, $m)) $language = trim($m[1]);

            $tvgId = '';
            if (preg_match('/tvg-id="([^"]*)"/i', $line, $m)) {
                $tvgId = $m[1];
                $tvgId = preg_replace('/@.*$/', '', $tvgId);
            }
            if ($language === '' && $tvgId !== '' && isset($langMap[strtolower($tvgId)])) {
                $langs = $langMap[strtolower($tvgId)];
                $map = ['hin'=>'Hindi','bho'=>'Bhojpuri','eng'=>'English','tam'=>'Tamil','tel'=>'Telugu','mal'=>'Malayalam','kan'=>'Kannada','ben'=>'Bengali','mar'=>'Marathi','guj'=>'Gujarati','pan'=>'Punjabi','urd'=>'Urdu'];
                $firstLang = $langs[0] ?? '';
                $language = $map[$firstLang] ?? strtoupper($firstLang);
            }

            // Fallback: tvg-id se country nikaalo (e.g. AajTak.in@SD => IN)
            if ($country === '' && preg_match('/tvg-id="[^"]*\.([a-z]{2})@/i', $line, $m)) {
                $country = strtoupper($m[1]);
            }

            // Fallback: group-title se language guess karo
            if ($language === '' && $group !== '') {
                if (stripos($group, 'hindi') !== false) $language = 'Hindi';
                elseif (stripos($group, 'bhojpuri') !== false) $language = 'Bhojpuri';
                elseif (stripos($group, 'english') !== false) $language = 'English';
            }

            $current = [
                'name'     => clean_name($name),
                'group'    => strip_invalid_utf8($group),
                'logo'     => strip_invalid_utf8($logo),
                'country'  => strip_invalid_utf8($country),
                'language' => strip_invalid_utf8($language),
                'lang'     => strip_invalid_utf8($language),
                'source'   => $sourceType,
                'url'      => ''
            ];
            continue;
        }

        if ($current !== null && $line !== '' && $line[0] !== '#' && preg_match('#^https?://#i', $line)) {
            $current['url'] = trim($line);
            if ($current['name'] !== '' && $current['url'] !== '') {
                $current['category'] = category_of($current['group'], $current['name']);
                $out[] = $current;
            }
            $current = null;
        }
    }
    return $out;
}

function looks_indian(array $item): bool
{
    global $ENG_HINTS, $SPORT_HINTS;
    $name     = strtolower((string)($item['name'] ?? ''));
    $group    = strtolower((string)($item['group'] ?? ''));
    $country  = strtolower((string)($item['country'] ?? ''));
    $language = strtolower((string)($item['language'] ?? $item['lang'] ?? ''));

    if ($name === '' || is_blocked($name)) return false;
    if (preg_match('/(^|[,\s])in($|[,\s])/i', $country)) return true;
    if (strpos($language, 'hindi') !== false || strpos($language, 'bhojpuri') !== false) return true;

    $hints = ['india','indian','aaj tak','abp','ndtv','republic','wion','news18','zee','colors',
        'star sports','sony six','sony ten','sony espn','sports18','dd india','dd national',
        'dd sports','dd news','cnbc tv18','et now','india today','mirror now','newsx',
        'times now','mastiii','sangeet','bhojpuri','bollywood','desi','epic','sony yay',
        'hungama','pogo','nick','travelxp','good times','food food','fashion tv','discovery india'];

    if (contains_any($name . ' ' . $group, $hints)) return true;
    if (contains_any($name, $ENG_HINTS) || contains_any($name, $SPORT_HINTS)) return true;
    return false;
}

function garden_accept(array $item, string $type): bool
{
    $nameLower = strtolower((string)($item['name'] ?? ''));
    $whitelist = ['sony kal hindi','aaj tak','utsav bharat','utsav plus','cricket gold','dd sports','ptv sports','sky sports cricket','star sports 2','star sports select 2','t sports','willow','bbc earth','disney channel india'];
    foreach ($whitelist as $w) {
        if (strpos($nameLower, $w) !== false) return true;
    }
    global $ENG_HINTS, $SPORT_HINTS, $INTL_SPORT;
    $name  = strtolower((string)($item['name'] ?? ''));
    $group = strtolower((string)($item['group'] ?? ''));

    if ($name === '' || is_blocked($name)) return false;
    if ($type === 'complete') return looks_indian($item);
    if ($type === 'hindi' || $type === 'bhojpuri' || $type === 'india') return true;
    if ($type === 'diaspora') return false; // sirf whitelist wale upar accept ho chuke
    if ($type === 'english') return looks_indian($item);

    if ($type === 'music') {
        return contains_any($name, ['music','mtv','9xm','9x ','b4u','mastiii','masti','vh1','sangeet']);
    }
    if ($type === 'cartoon') {
        return contains_any($name, ['kid','cartoon','nick','pogo','hungama','disney','sony yay','cartoon network']);
    }
    if ($type === 'science') {
        return contains_any($name, ['science','discovery','national geographic','nat geo','animal planet','history tv','ngc','bbc earth']);
    }
    if (contains_any($name, $ENG_HINTS) || contains_any($name, $SPORT_HINTS)) return true;

    $cat = ['kid','cartoon','nick','pogo','hungama','disney','sony yay','cartoon network',
        'discovery kids','music','mtv','9xm','9x ','b4u music','mastiii','masti','vh1',
        'discovery','national geographic','nat geo','animal planet','history tv',
        'fashion tv','ftv','fox life','tlc','travelxp'];

    if (contains_any($name . ' ' . $group, $cat)) return true;
    if ($type === 'sports' && contains_any($name, $INTL_SPORT)) return true;
    return false;
}

function add_candidate(array &$list, array &$seen, array $item, string $sourceLabel): bool
{
    $url = trim((string)($item['url'] ?? ''));
    if ($url === '') return false;
    $key = strtolower($url);
    if (isset($seen[$key])) return false;
    $seen[$key] = true;

    $item['source_label'] = $sourceLabel;
    $item['category'] = category_of((string)($item['group'] ?? ''), (string)($item['name'] ?? ''));

    $probe = strtolower(trim(($item['name'] ?? '') . ' ' . ($item['group'] ?? '')));
    $item['hd'] = (preg_match('/\bhd\b/i', $probe) === 1);

    $item['name']  = strip_invalid_utf8($item['name']  ?? '');
    $item['group'] = strip_invalid_utf8($item['group'] ?? '');
    $item['logo']  = strip_invalid_utf8($item['logo']  ?? '');
    $list[] = $item;
    return true;
}

/*
|--------------------------------------------------------------------------
| Stream probe
|--------------------------------------------------------------------------
*/

function probe_stream(string $url): array
{
    global $USER_AGENT;
    $maxBytes = 8192;

    if (function_exists('curl_init')) {
        $body = ''; $status = 0; $contentType = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => $USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Accept: */*', 'Cache-Control: no-cache'],
            CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$status, &$contentType) {
                $line = trim($header);
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $m)) $status = (int)$m[1];
                if (stripos($line, 'Content-Type:') === 0) $contentType = trim(substr($line, strlen('Content-Type:')));
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$body, $maxBytes) {
                $rem = $maxBytes - strlen($body);
                if ($rem <= 0) return 0;
                $body .= substr($chunk, 0, $rem);
                if (strlen($body) >= $maxBytes) return 0;
                return strlen($chunk);
            }
        ]);
        curl_exec($ch);
        $error = curl_error($ch);
        if ($status === 0) $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($contentType === '') $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $ok = ($status >= 200 && $status < 400 && $body !== '');
        if ($ok && preg_match('/\.m3u8(?:\?|$)/i', $url)) $ok = (stripos($body, '#EXTM3U') !== false);

        return ['working'=>$ok,'status'=>$status,'bytes'=>strlen($body),'content_type'=>$contentType,'error'=>$error];
    }

    $ctx = stream_context_create([
        'http' => ['method'=>'GET','timeout'=>8,'follow_location'=>1,'max_redirects'=>5,
                   'header'=>"User-Agent: {$USER_AGENT}\r\nAccept: */*\r\n"],
        'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false]
    ]);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) return ['working'=>false,'status'=>0,'bytes'=>0,'error'=>'Connection failed'];
    @stream_set_timeout($fp, 8);
    $body = @fread($fp, $maxBytes);
    @fclose($fp);
    if (!is_string($body)) $body = '';
    $ok = ($body !== '');
    if ($ok && preg_match('/\.m3u8(?:\?|$)/i', $url)) $ok = (stripos($body, '#EXTM3U') !== false);
    return ['working'=>$ok,'status'=>$ok?200:0,'bytes'=>strlen($body),'error'=>$ok?'':'Empty response'];
}

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

function new_state(): array
{
    global $SOURCES;
    return [
        'phase'        => 'build',
        'source_index' => 0,
        'sources'      => $SOURCES,
        'candidates'   => [],
        'working'      => [],
        'nonworking'   => [],
        'seen'         => [],
        'check_index'  => 0,
        'source_stats' => [],
        'started_at'   => date('c'),
        'updated_at'   => date('c')
    ];
}

function save_state(array $state): void
{
    global $STATE_FILE;
    $state['updated_at'] = date('c');
    $ok = json_write($STATE_FILE, $state);
    log_line(($ok ? 'SAVE OK' : 'SAVE FAIL') .
             ' | src=' . ($state['source_index'] ?? '?') .
             ' | cand=' . count($state['candidates'] ?? []));
}
function process_source(array &$state, int $index): array
{
    global $SOURCES;
    $src   = $SOURCES[$index];
    $label = $src['label'];
    $url   = $src['url'];
    $type  = $src['type'];

    log_line("FETCH {$label}");
    $text = fetch_text($url, 90);

    if ($text === '') {
        log_line("FETCH FAILED {$label}");
        $state['source_stats'][] = ['label'=>$label,'type'=>$type,'fetched'=>0,'added'=>0,'total'=>count($state['candidates']),'error'=>true];
        $state['source_index'] = $index + 1;
        save_state($state);
        return ['label'=>$label,'added'=>0,'total'=>count($state['candidates']),'error'=>true];
    }

    $records = parse_m3u($text, $type);
    $fetched = count($records);
    unset($text);

    $added = 0;
    foreach ($records as $item) {
        if (!garden_accept($item, $type)) continue;
        if (add_candidate($state['candidates'], $state['seen'], $item, $label)) $added++;
    }
    unset($records);

    $state['source_stats'][] = ['label'=>$label,'type'=>$type,'fetched'=>$fetched,'added'=>$added,'total'=>count($state['candidates']),'error'=>false];

    // CRITICAL: index badhao aur save karo
    $state['source_index'] = $index + 1;
    save_state($state);

    log_line("{$label} => +{$added} (from {$fetched}) | total " . count($state['candidates']));
    return ['label'=>$label,'added'=>$added,'total'=>count($state['candidates']),'error'=>false];
}

function build_batch(array &$state): array
{
    global $SOURCES;

    if ($state['phase'] !== 'build') return ['done'=>true,'phase'=>$state['phase']];

    if ($state['source_index'] >= count($SOURCES)) {
        $state['phase'] = (count($state['candidates']) > 0 ? 'ready' : 'empty');
        $state['check_index'] = 0;
        save_state($state);
        return ['done'=>true,'phase'=>$state['phase'],'total'=>count($state['candidates'])];
    }

    $i = $state['source_index'];
    $result = process_source($state, $i);

    $finished = ($state['source_index'] >= count($SOURCES));

    if ($finished) {
        $state['phase'] = (count($state['candidates']) > 0 ? 'ready' : 'empty');
        save_state($state);
    }

    return [
        'done'    => $finished,
        'phase'   => $state['phase'],
        'label'   => $result['label'],
        'added'   => $result['added'],
        'total'   => count($state['candidates']),
        'source'  => $state['source_index'],
        'sources' => count($SOURCES)
    ];
}

function check_batch(array &$state, int $batchSize = 4): array
{
    if ($state['phase'] === 'ready') {
        $state['phase'] = 'check';
        $state['check_index'] = 0;
        $state['working'] = [];
        $state['nonworking'] = [];
        save_state($state);
    }

    if ($state['phase'] !== 'check') return ['done'=>($state['phase']==='complete'),'phase'=>$state['phase']];

    $total = count($state['candidates']);
    $checked = 0;

    while ($checked < $batchSize && $state['check_index'] < $total) {
        $i = $state['check_index'];
        $item = $state['candidates'][$i];
        $url = trim((string)($item['url'] ?? ''));
        $result = probe_stream($url);

        $item['checked_at']  = date('c');
        $item['status']      = ($result['working'] ? 'Working' : 'Non-working');
        $item['http_status'] = $result['status'];
        $item['bytes']       = $result['bytes'];
        if (isset($result['content_type'])) $item['content_type'] = $result['content_type'];

        if ($result['working']) $state['working'][] = $item;
        else $state['nonworking'][] = $item;

        $state['check_index']++;
        $checked++;

        log_line('[' . $state['check_index'] . '/' . $total . '] ' . ($item['name'] ?? 'Unknown') . ' => ' . $item['status']);
        save_state($state);
    }

    if ($state['check_index'] >= $total) {
        $state['phase'] = 'complete';
        save_state($state);
        save_combined_m3u($state);
        return ['done'=>true,'phase'=>'complete','checked'=>$total,'total'=>$total,
                'working'=>count($state['working']),'nonworking'=>count($state['nonworking'])];
    }

    return ['done'=>false,'phase'=>'check','checked'=>$state['check_index'],'total'=>$total,
            'working'=>count($state['working']),'nonworking'=>count($state['nonworking'])];
}

/*
|--------------------------------------------------------------------------
| M3U
|--------------------------------------------------------------------------
*/

function m3u_escape(string $v): string
{
    $v = strip_invalid_utf8($v);
    return str_replace(["\r","\n",'"'], ['','',"'"], $v);
}

function save_combined_m3u(array $state): void
{
    global $DATA_DIR;
    $combined = [];
    foreach ($state['working'] ?? [] as $c) $combined[] = $c;
    foreach ($state['candidates'] ?? [] as $c) {
        if (($c['status'] ?? '') === 'Working') continue;
        $combined[] = $c;
    }
    $seen = []; $unique = [];
    foreach ($combined as $c) {
        $url = strtolower(trim($c['url'] ?? ''));
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true; $unique[] = $c;
    }
    $out = "#EXTM3U\n";
    foreach ($unique as $ch) {
        $name = m3u_escape((string)($ch['name'] ?? ''));
        $group = m3u_escape((string)($ch['category'] ?? $ch['group'] ?? 'General'));
        $out .= '#EXTINF:-1 group-title="' . $group . '",' . $name . "\n";
        $out .= trim((string)($ch['url'] ?? '')) . "\n";
    }
    file_put_contents($DATA_DIR . '/combined.m3u', $out);
    log_line('Combined M3U: ' . count($unique) . ' channels');
}

function make_m3u(array $channels): string
{
    $out = "#EXTM3U\r\n";
    foreach ($channels as $ch) {
        $name     = m3u_escape((string)($ch['name'] ?? 'Unknown'));
        $group    = m3u_escape((string)($ch['category'] ?? $ch['group'] ?? 'General'));
        $logo     = m3u_escape((string)($ch['logo'] ?? ''));
        $country  = m3u_escape((string)($ch['country'] ?? ''));
        $language = m3u_escape((string)($ch['language'] ?? $ch['lang'] ?? ''));

        $attrs = ['group-title="' . $group . '"'];
        if ($logo !== '')     $attrs[] = 'tvg-logo="' . $logo . '"';
        if ($country !== '')  $attrs[] = 'tvg-country="' . $country . '"';
        if ($language !== '') $attrs[] = 'tvg-language="' . $language . '"';

        $out .= '#EXTINF:-1 ' . implode(' ', $attrs) . ',' . $name . "\r\n";
        $out .= trim((string)($ch['url'] ?? '')) . "\r\n";
    }
    return $out;
}

function download_m3u(string $filename, array $channels): void
{
    $content = make_m3u($channels);
    header('Content-Type: audio/x-mpegurl');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

function reset_all(): void
{
    global $STATE_FILE, $DATA_DIR;
    @unlink($STATE_FILE);
    foreach (['candidates.json','working.json','nonworking.json','log.txt','fatal.log'] as $f) {
        @unlink($DATA_DIR . '/' . $f);
    }
}

/*
|--------------------------------------------------------------------------
| API router
|--------------------------------------------------------------------------
*/

$action = strtolower(trim((string)($_GET['action'] ?? '')));
if ($action === '') {
    if (isset($_GET['build']))    $action = 'build';
    if (isset($_GET['check']))    $action = 'check';
    if (isset($_GET['status']))   $action = 'status';
    if (isset($_GET['reset']))    $action = 'reset';
    if (isset($_GET['download'])) $action = 'download';
}

if ($action === 'reset' || isset($_GET['fresh'])) {
    reset_all();
    if ($action === 'reset' && !isset($_GET['fresh'])) respond(['ok'=>true,'phase'=>'idle']);
}

if ($action === 'download') {
    $what = strtolower(trim((string)($_GET['type'] ?? $_GET['download'] ?? 'working')));
    $state = json_read($STATE_FILE, []);
    if (!is_array($state)) $state = [];

    if ($what === 'candidates') download_m3u('3502_candidates.m3u', $state['candidates'] ?? []);
    if ($what === 'nonworking' || $what === 'non-working') download_m3u('3502_nonworking.m3u', $state['nonworking'] ?? []);

    if ($what === 'combined') {
        $cf = $DATA_DIR . '/combined.m3u';
        if (is_file($cf)) {
            header('Content-Type: audio/x-mpegurl');
            header('Content-Disposition: attachment; filename="combined.m3u"');
            header('Content-Length: ' . filesize($cf));
            readfile($cf);
            exit;
        }
        download_m3u('combined.m3u', $state['candidates'] ?? []);
    }
    download_m3u('3502_working.m3u', $state['working'] ?? []);
}

if ($action === 'status') {
    $state = json_read($STATE_FILE, []);
    if (!is_array($state) || empty($state)) {
        respond(['ok'=>true,'phase'=>'idle','done'=>false,'total'=>0,'checked'=>0,'working'=>0,'nonworking'=>0]);
    }
    $phase = $state['phase'] ?? 'idle';
    respond([
        'ok'=>true,'phase'=>$phase,'done'=>($phase==='complete'),
        'total'=>count($state['candidates'] ?? []),
        'checked'=>(int)($state['check_index'] ?? 0),
        'working'=>count($state['working'] ?? []),
        'nonworking'=>count($state['nonworking'] ?? []),
        'source'=>(int)($state['source_index'] ?? 0),
        'sources'=>count($SOURCES),
        'source_stats'=>$state['source_stats'] ?? []
    ]);
}

if ($action === 'build') {
    $state = json_read($STATE_FILE, []);
    if (empty($state) || !isset($state['phase']) || $state['phase'] === 'complete' || $state['phase'] === 'empty') {
        $state = new_state();
        save_state($state);
    }
    if (($state['phase'] ?? '') === 'ready') {
        respond(['ok'=>true,'done'=>true,'phase'=>'ready','total'=>count($state['candidates'])]);
    }
    if (($state['phase'] ?? '') !== 'build') {
        respond(['ok'=>true,'done'=>true,'phase'=>$state['phase'] ?? 'idle','total'=>count($state['candidates'] ?? [])]);
    }
    $result = build_batch($state);
    respond([
        'ok'=>true,'action'=>'build',
        'done'=>$result['done'] ?? false,
        'phase'=>$result['phase'] ?? 'build',
        'label'=>$result['label'] ?? '',
        'added'=>$result['added'] ?? 0,
        'total'=>$result['total'] ?? 0,
        'source'=>$result['source'] ?? 0,
        'sources'=>$result['sources'] ?? count($SOURCES)
    ]);
}

if ($action === 'check') {
    $state = json_read($STATE_FILE, []);
    if (empty($state)) respond(['ok'=>false,'done'=>false,'phase'=>'idle','error'=>'Build required first']);
    if (($state['phase'] ?? '') === 'build') respond(['ok'=>false,'done'=>false,'phase'=>'build','error'=>'Build is not complete']);
    if (($state['phase'] ?? '') === 'empty') respond(['ok'=>true,'done'=>false,'phase'=>'empty','total'=>0,'checked'=>0]);
    if (($state['phase'] ?? '') === 'complete') {
        respond(['ok'=>true,'done'=>true,'phase'=>'complete',
            'total'=>count($state['candidates'] ?? []),
            'checked'=>count($state['candidates'] ?? []),
            'working'=>count($state['working'] ?? []),
            'nonworking'=>count($state['nonworking'] ?? [])]);
    }
    $result = check_batch($state, 20);
    respond([
        'ok'=>true,'action'=>'check',
        'done'=>$result['done'] ?? false,
        'phase'=>$result['phase'] ?? 'check',
        'checked'=>$result['checked'] ?? 0,
        'total'=>$result['total'] ?? 0,
        'working'=>$result['working'] ?? 0,
        'nonworking'=>$result['nonworking'] ?? 0
    ]);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>3502 IPTV Checker</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#080a0f;color:#fff;font-family:Arial,sans-serif}
body{padding:18px}
.wrap{max-width:900px;margin:auto}
h1{margin:0 0 6px;font-size:25px}
.sub{color:#9ca3af;font-size:13px;margin-bottom:18px}
.buttons{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:18px}
button,a.btn{border:0;border-radius:9px;padding:11px 15px;color:#fff;background:#202633;text-decoration:none;font-size:14px;cursor:pointer}
button.primary{background:#2563eb}
button.danger{background:#b91c1c}
a.btn{display:inline-block}
.card{background:#11151d;border:1px solid #252b36;border-radius:12px;padding:15px;margin-bottom:14px}
.progress{width:100%;height:12px;background:#20242c;border-radius:20px;overflow:hidden;margin:10px 0}
.bar{height:100%;width:0;background:#22c55e;transition:width .2s}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
.stat{background:#181d26;border-radius:9px;padding:12px}
.num{font-size:21px;font-weight:bold}
.lbl{color:#9ca3af;font-size:12px;margin-top:3px}
#log{white-space:pre-wrap;max-height:360px;overflow:auto;background:#05070a;border-radius:8px;padding:12px;font-size:12px;color:#b9c0cc}
@media(max-width:600px){body{padding:12px}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}button,a.btn{flex:1 1 45%}}
</style>
</head>
<body>
<div class="wrap">
<h1>3502 IPTV Checker</h1>
<div class="sub">Build Indian candidates → Check streams → Working / Non-working</div>
<div class="buttons">
<button class="primary" onclick="startBuild()">Build</button>
<button onclick="startCheck()">Check Working</button>
<button onclick="resetAll()" class="danger">Reset</button>
<a class="btn" href="?action=download&type=candidates">Candidates</a>
<a class="btn" href="?action=download&type=working">Working</a>
<a class="btn" href="?action=download&type=nonworking">Non-working</a>
</div>
<div class="card">
<div id="phase">Idle</div>
<div class="progress"><div id="bar" class="bar"></div></div>
<div class="stats">
<div class="stat"><div id="total" class="num">0</div><div class="lbl">Candidates</div></div>
<div class="stat"><div id="checked" class="num">0</div><div class="lbl">Checked</div></div>
<div class="stat"><div id="working" class="num">0</div><div class="lbl">Working</div></div>
<div class="stat"><div id="nonworking" class="num">0</div><div class="lbl">Non-working</div></div>
</div>
</div>
<div class="card"><div id="log">Ready.</div></div>
</div>
<script>
let running=false;
function setText(id,v){const el=document.getElementById(id);if(el)el.textContent=v;}
function log(msg){const el=document.getElementById('log');if(!el)return;el.textContent+='\n'+msg;el.scrollTop=el.scrollHeight;}
function update(data){
setText('phase',data.phase||'idle');
setText('total',data.total||0);
setText('checked',data.checked||0);
setText('working',data.working||0);
setText('nonworking',data.nonworking||0);
let p=0;
if(data.phase==='build'&&data.sources)p=(Number(data.source||0)/Number(data.sources))*100;
if((data.phase==='check'||data.phase==='complete')&&data.total)p=(Number(data.checked||0)/Number(data.total))*100;
if(p>100)p=100;
document.getElementById('bar').style.width=p+'%';
}
async function api(action){
const r=await fetch('?action='+action+'&_='+Date.now(),{cache:'no-store'});
const t=await r.text();
if(!t)throw new Error('Empty response (check 3502_data/fatal.log)');
try{return JSON.parse(t);}catch(e){throw new Error('Invalid JSON: '+t.slice(0,200));}
}
async function startBuild(){
if(running)return;running=true;
document.getElementById('log').textContent='Starting build...';
try{
while(true){
const data=await api('build');
update(data);
if(data.label)log(data.label+' → +'+(data.added||0)+' | total '+(data.total||0));
if(data.done&&(data.phase==='ready'||data.phase==='empty'))break;
await new Promise(r=>setTimeout(r,150));
}
log(document.getElementById('phase').textContent==='ready'?'BUILD COMPLETE':'BUILD EMPTY');
}catch(e){log('Build error: '+e.message);}finally{running=false;}
}
async function startCheck(){
if(running)return;running=true;
document.getElementById('log').textContent='Starting stream check...';
try{
while(true){
const data=await api('check');
update(data);
if(data.checked!==undefined)log('Checked '+data.checked+'/'+(data.total||0)+' | Working '+(data.working||0)+' | Non-working '+(data.nonworking||0));
if(data.done===true&&data.phase==='complete')break;
if(data.phase==='empty'){log('No candidates. Build first.');break;}
if(data.ok===false){log(data.error||'Check cannot start.');break;}
await new Promise(r=>setTimeout(r,120));
}
if(document.getElementById('phase').textContent==='complete')log('CHECK COMPLETE');
}catch(e){log('Check error: '+e.message);}finally{running=false;}
}
async function resetAll(){
if(!confirm('Reset Build/Check data?'))return;
try{
const r=await fetch('?action=reset&_='+Date.now(),{cache:'no-store'});
const data=await r.json();
if(data.ok){
document.getElementById('log').textContent='Reset complete.';
setText('phase','idle');setText('total',0);setText('checked',0);setText('working',0);setText('nonworking',0);
document.getElementById('bar').style.width='0%';
}
}catch(e){log('Reset error: '+e.message);}
}
(async function(){
try{
const data=await api('status');
update(data);
if(data.phase&&data.phase!=='idle')log('Existing state: '+data.phase+' | Candidates '+(data.total||0)+' | Checked '+(data.checked||0));
}catch(e){}
})();
</script>
</body>
</html>
