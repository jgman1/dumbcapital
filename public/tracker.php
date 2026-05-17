<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital — Page View + Share Tracker
//  Handles two actions:
//    action=view   — record a page view (default)
//    action=share  — record a share button click
// ─────────────────────────────────────────────────────────────

define('ANALYTICS_FILE', __DIR__ . '/posts/analytics.json');

// ── ONLY ACCEPT POST REQUESTS ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── PARSE REQUEST FIRST ───────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? 'view'); // 'view' or 'share'
$page   = trim($input['page']   ?? '');
$section= trim($input['section']?? '');
$slug   = trim($input['slug']   ?? '');

if (empty($page)) { http_response_code(400); exit; }

// ── IP-BASED BOT FILTERING ────────────────────────────────────
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

function ip_in_range(string $ip, string $range): bool {
    if (strpos($range, '/') === false) return $ip === $range;
    [$subnet, $bits] = explode('/', $range);
    $ip_long     = ip2long($ip);
    $subnet_long = ip2long($subnet);
    if ($ip_long === false || $subnet_long === false) return false;
    $mask = -1 << (32 - (int)$bits);
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

function is_bot_ip(string $ip): bool {
    $bot_ranges = [
        '66.249.64.0/19','64.233.160.0/19','72.14.192.0/18',
        '209.85.128.0/17','216.239.32.0/19',
        '40.77.167.0/24','40.77.168.0/24','52.167.144.0/24',
        '52.167.145.0/24','52.167.146.0/24','20.191.45.0/24',
        '20.15.133.0/24','157.55.39.0/24','207.46.13.0/24',
        '207.46.12.0/24','5.255.253.0/24','5.255.231.0/24',
        '77.88.55.0/24','77.88.22.0/24','95.108.128.0/17',
        '141.8.142.0/24','220.181.108.0/24','220.181.51.0/24',
        '123.125.71.0/24','116.179.32.0/24','54.239.128.0/18',
        '52.44.229.0/24','54.85.0.0/16','3.89.0.0/16',
        '3.219.0.0/16','3.226.0.0/16','18.214.0.0/16',
        '44.213.0.0/16','54.235.0.0/16','100.25.0.0/16',
        '51.195.0.0/16','51.89.0.0/16','54.36.0.0/16',
        '217.182.195.0/24','51.68.111.0/24','216.244.66.0/24',
        '185.191.171.0/24','85.208.96.0/22','43.128.0.0/14',
        '43.132.0.0/14','43.136.0.0/14','43.152.0.0/14',
        '43.156.0.0/14','43.163.0.0/16','43.172.0.0/14',
        '43.192.0.0/14','47.79.0.0/16','47.82.0.0/16',
        '47.86.0.0/16','47.242.0.0/16','8.210.0.0/16',
        '8.218.0.0/16','192.42.116.0/24','185.220.100.0/22',
        '185.243.218.0/24','45.66.35.0/24','107.189.0.0/16',
    ];
    foreach ($bot_ranges as $range) {
        if (ip_in_range($ip, $range)) return true;
    }
    return false;
}

if (is_bot_ip($client_ip)) { http_response_code(204); exit; }

// ── USER AGENT BOT FILTERING ──────────────────────────────────
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if (empty($ua)) { http_response_code(204); exit; }

$bot_patterns = [
    'googlebot','bingbot','slurp','duckduckbot','baiduspider',
    'yandexbot','facebookexternalhit','linkedinbot','twitterbot',
    'whatsapp','slackbot','discordbot','telegrambot','applebot',
    'amazonbot','gptbot','chatgpt','anthropic','claudebot',
    'perplexitybot','semrushbot','ahrefsbot','mj12bot','dotbot',
    'rogerbot','screaming frog','ia_archiver','archive.org_bot',
    'mojeek','securityscanner','go-http-client','python-requests',
    'curl','wget','libwww','scrapy','masscan','zgrab','nuclei',
    'sqlmap','nikto','nmap','bytespider','petalbot','dataforseo',
    'seranking','zoominfo','coccocbot','seznambot','bingpreview',
    'google-inspection','google-extended','googleother','mediapartners',
    'adsbot','turaco','plesk','msnbot','adidxbot','duckduckgo',
    'proximic','brandverity','serpstat','crawler','spider','scraper',
    'bot/','robot','java/','apache-http','okhttp','python/',
    'axios','node-fetch','got/','undici','wp-rocket','pingdom',
    'uptimerobot','statuscake','newrelic','datadog','site24x7',
];
foreach ($bot_patterns as $pattern) {
    if (strpos($ua, $pattern) !== false) { http_response_code(204); exit; }
}

// ── VALIDATE ORIGIN ───────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($origin) && strpos($origin, 'dumbcapital.com') === false) {
    http_response_code(403);
    exit;
}

// ── VALIDATE PAGE ─────────────────────────────────────────────
$valid_sections = ['vc','ma','pe','unicorn','opinion','search'];
if ($page !== '/' && !in_array($section, $valid_sections)) {
    http_response_code(204);
    exit;
}

// ── SANITIZE ──────────────────────────────────────────────────
$page    = substr($page, 0, 200);
$section = substr(preg_replace('/[^a-z]/', '', $section), 0, 20);
$slug    = substr(preg_replace('/[^a-z0-9\-]/', '', $slug), 0, 100);
$page_key = $page === '/' ? '__home__' : ($section . '/' . $slug);

// ── LOAD ANALYTICS ────────────────────────────────────────────
$analytics = [];
if (file_exists(ANALYTICS_FILE)) {
    $raw = file_get_contents(ANALYTICS_FILE);
    $analytics = json_decode($raw, true) ?? [];
}

// Ensure page entry exists
if (!isset($analytics[$page_key])) {
    $analytics[$page_key] = [
        'page'    => $page,
        'section' => $section,
        'slug'    => $slug,
        'label'   => $page === '/' ? 'Homepage' : ($section . '/' . $slug),
        'months'  => [],
        'shares'  => 0,
    ];
}

// Ensure shares key exists on older entries
if (!isset($analytics[$page_key]['shares'])) {
    $analytics[$page_key]['shares'] = 0;
}

// ── HANDLE SHARE ACTION ───────────────────────────────────────
if ($action === 'share') {
    $analytics[$page_key]['shares']++;

    // Write and exit
    $fp = fopen(ANALYTICS_FILE, 'c');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($analytics, JSON_PRETTY_PRINT));
        fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    }
    http_response_code(204);
    exit;
}

// ── HANDLE VIEW ACTION ────────────────────────────────────────
$min_duration = ($page === '/') ? 2 : 3;
$duration = (int)($input['duration'] ?? 0);
if ($duration < $min_duration) { http_response_code(204); exit; }

$today = date('Y-m-d');
$month = date('Y-m');

if (!isset($analytics[$page_key]['months'][$month])) {
    $analytics[$page_key]['months'][$month] = ['total' => 0, 'days' => []];
}
if (!isset($analytics[$page_key]['months'][$month]['days'][$today])) {
    $analytics[$page_key]['months'][$month]['days'][$today] = 0;
}
$analytics[$page_key]['months'][$month]['days'][$today]++;
$analytics[$page_key]['months'][$month]['total']++;

// Keep only last 12 months
foreach ($analytics as $key => &$pdata) {
    if (isset($pdata['months']) && count($pdata['months']) > 12) {
        ksort($pdata['months']);
        $pdata['months'] = array_slice($pdata['months'], -12, 12, true);
    }
}
unset($pdata);

// Write
$fp = fopen(ANALYTICS_FILE, 'c');
if ($fp && flock($fp, LOCK_EX)) {
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($analytics, JSON_PRETTY_PRINT));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
}

http_response_code(204);
exit;
