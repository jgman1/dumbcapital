<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital — Page View Tracker
//  Called via fetch() from index.php on every page load.
//  Filters bots, records views to posts/analytics.json
// ─────────────────────────────────────────────────────────────

define('ANALYTICS_FILE', __DIR__ . '/posts/analytics.json');

// ── BOT USER AGENT FILTER ─────────────────────────────────────
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
    'piplbot','seokicks','serpstatbot','brandverity','proximic',
    'crawler','spider','scraper','bot/','/bot','robot',
];

$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Block empty UA
if (empty($ua)) {
    http_response_code(204);
    exit;
}

// Block known bot UAs
foreach ($bot_patterns as $pattern) {
    if (strpos($ua, $pattern) !== false) {
        http_response_code(204);
        exit;
    }
}

// Only accept POST requests from our own domain
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Validate origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($origin) && strpos($origin, 'dumbcapital.com') === false) {
    http_response_code(403);
    exit;
}

// ── PARSE REQUEST ─────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
$page     = trim($input['page'] ?? '');
$section  = trim($input['section'] ?? '');
$slug     = trim($input['slug'] ?? '');
$duration = (int)($input['duration'] ?? 0); // seconds spent on page

// Must have a page identifier
if (empty($page)) {
    http_response_code(400);
    exit;
}

// Filter: must have spent at least 2 seconds on page (filters bots)
// Homepage gets a lower threshold since people may click quickly
$min_duration = ($page === '/') ? 1 : 2;
if ($duration < $min_duration) {
    http_response_code(204);
    exit;
}

// Sanitize inputs
$page    = substr($page, 0, 200);
$section = substr(preg_replace('/[^a-z]/', '', $section), 0, 20);
$slug    = substr(preg_replace('/[^a-z0-9\-]/', '', $slug), 0, 100);

// ── RECORD VIEW ───────────────────────────────────────────────
$today = date('Y-m-d');
$month = date('Y-m');

// Load existing analytics
$analytics = [];
if (file_exists(ANALYTICS_FILE)) {
    $raw = file_get_contents(ANALYTICS_FILE);
    $analytics = json_decode($raw, true) ?? [];
}

// Structure: analytics[page_key][month][day]
$page_key = $page === '/' ? '__home__' : ($section . '/' . $slug);

if (!isset($analytics[$page_key])) {
    $analytics[$page_key] = [
        'page'    => $page,
        'section' => $section,
        'slug'    => $slug,
        'label'   => $page === '/' ? 'Homepage' : ($section . '/' . $slug),
        'months'  => [],
    ];
}

if (!isset($analytics[$page_key]['months'][$month])) {
    $analytics[$page_key]['months'][$month] = ['total' => 0, 'days' => []];
}

if (!isset($analytics[$page_key]['months'][$month]['days'][$today])) {
    $analytics[$page_key]['months'][$month]['days'][$today] = 0;
}

$analytics[$page_key]['months'][$month]['days'][$today]++;
$analytics[$page_key]['months'][$month]['total']++;

// Keep only last 12 months per page to prevent file bloat
foreach ($analytics as $key => &$pdata) {
    if (isset($pdata['months']) && count($pdata['months']) > 12) {
        ksort($pdata['months']);
        $pdata['months'] = array_slice($pdata['months'], -12, 12, true);
    }
}
unset($pdata);

// Write back — use file locking to prevent race conditions
$fp = fopen(ANALYTICS_FILE, 'c');
if ($fp && flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($analytics, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

http_response_code(204);
exit;
