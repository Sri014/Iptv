<?php
// Fetch all Indian language playlists + build URL → language map
$langs = [
    'Hindi'      => 'hin',
    'Bhojpuri'   => 'bho',
    'Tamil'      => 'tam',
    'Telugu'     => 'tel',
    'Malayalam'  => 'mal',
    'Kannada'    => 'kan',
    'Bengali'    => 'ben',
    'Marathi'    => 'mar',
    'Gujarati'   => 'guj',
    'Punjabi'    => 'pan',
    'Urdu'       => 'urd',
    'Odia'       => 'ori',
    'Assamese'   => 'asm',
    'Sanskrit'   => 'san',
    'Maithili'   => 'mai',
    'Konkani'    => 'kok',
    'Nepali'     => 'nep',
    'Sinhala'    => 'sin',
    'Rajasthani' => 'raj',
    'Haryanvi'   => 'bgc',
    'Magahi'     => 'mag',
    'Awadhi'     => 'awa',
    'Bundeli'    => 'bns',
    'Dogri'      => 'doi',
    'Kashmiri'   => 'kas',
    'Manipuri'   => 'mni',
    'Santali'    => 'sat',
    'Sindhi'     => 'snd',
    'English'    => 'eng',
];

$map = [];
$stats = [];

foreach ($langs as $name => $code) {
    $url = "https://iptv-org.github.io/iptv/languages/$code.m3u";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$body) {
        echo str_pad($name, 14) . ": FAIL (HTTP $httpCode)\n";
        continue;
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] !== '#') {
            $key = strtolower($line);
            if (!isset($map[$key])) {
                $map[$key] = $name;
                $count++;
            }
        }
    }
    $stats[$name] = $count;
    echo str_pad($name, 14) . ": $count\n";
    usleep(200000); // 200ms delay
}

file_put_contents('langmap_indian.json', json_encode($map));
echo "\n=== TOTAL ===";
echo "\nUnique URLs: " . count($map) . "\n";
echo "Saved: langmap_indian.json\n";
