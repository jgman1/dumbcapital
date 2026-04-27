<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital — Main Router
//  Clean URLs via .htaccess rewrite:
//    /                     → homepage
//    /vc/                  → section page
//    /vc/article-slug/     → single article
//    /vc/?page=2           → paginated section
//    /search/?q=query      → search results
// ─────────────────────────────────────────────────────────────
define('POSTS_DIR',      __DIR__ . '/posts/');
define('SITE_URL',       'https://dumbcapital.com');
define('POSTS_PER_PAGE', 10);

// ── HELPERS ───────────────────────────────────────────────────
function load_published(): array {
    $posts = [];
    if (!is_dir(POSTS_DIR)) return $posts;
    foreach (glob(POSTS_DIR . '*.json') as $f) {
        if (basename($f) === 'seen_cache.json') continue;
        $d = json_decode(file_get_contents($f), true);
        if ($d && !empty($d['published'])) $posts[] = $d;
    }
    usort($posts, fn($a,$b) => strcmp($b['date']??'',$a['date']??''));
    return $posts;
}
function tag_span(string $tag): string {
    $m = ['vc'=>'VC','ma'=>'M&amp;A','pe'=>'PE','flop'=>'Flop','unicorn'=>'Unicorn','opinion'=>'Opinion'];
    return '<span class="tag tag-'.htmlspecialchars($tag).'">'.($m[$tag]??strtoupper($tag)).'</span>';
}
function skulls(int $n): string { return str_repeat('💀', max(1,min(5,$n))); }
function e(string $s): string   { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function post_url(array $p): string {
    $sec  = $p['section'] ?? $p['tag'] ?? 'vc';
    $slug = $p['slug'] ?? '';
    return '/' . $sec . '/' . $slug . '/';
}
function section_url(string $sec, int $page = 1): string {
    return $page > 1 ? '/' . $sec . '/?page=' . $page : '/' . $sec . '/';
}
function reading_time(string $html): int {
    $words = str_word_count(strip_tags($html));
    return max(1, (int)round($words / 200));
}

// ── SECTION CONFIG ─────────────────────────────────────────────
$section_config = [
    'vc'      => ['label'=>'VC Deals',      'title'=>'VC Deals',      'desc'=>'Venture capital funding rounds, startup valuations, and the eternal optimism of people spending other people\'s money.'],
    'ma'      => ['label'=>'M&amp;A Morgue','title'=>'M&A Morgue',    'desc'=>'Mergers, acquisitions, and the synergies nobody can define. Deals that made sense in the boardroom.'],
    'pe'      => ['label'=>'PE Corner',     'title'=>'PE Corner',     'desc'=>'Private equity buyouts, leveraged everything, and the art of calling layoffs "operational improvement".'],
    'unicorn' => ['label'=>'Unicorn Watch', 'title'=>'Unicorn Watch', 'desc'=>'Billion-dollar valuations, down rounds, and the slow realization that a good pitch deck is not a business.'],
    'opinion' => ['label'=>'Opinion',       'title'=>'Opinion',       'desc'=>'Commentary, analysis, and hot takes on the VC and M&A world. Someone has to say it.'],
];

// ── ROUTING ───────────────────────────────────────────────────
// Parse clean URL: /section/slug/ or /section/ or /search/
$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts   = array_filter(explode('/', $request));
$parts   = array_values($parts);

$route_section = '';
$route_slug    = '';
$route_search  = false;
$current_page  = max(1, (int)($_GET['page'] ?? 1));
$search_query  = trim($_GET['q'] ?? '');

// ── 410 GONE — old URLs from previous sites on this domain ────
$gone_patterns = [
    '#^/index\.php/itm/#',
    '#^/index\.php/catalog/#',
    '#^/index\.php/customer/#',
    '#^/index\.php/checkout/#',
    '#^/goods/#',
    '#^/wp-content/#',
    '#^/wp-admin/#',
    '#^/wp-json/#',
    '#^/feed/#',
    '#^/author/#',
    '#^/tag/#',
    '#^/category/#',
    '#^/product/#',
    '#^/shop/#',
    '#^/cart/#',
    '#^/20(1[5-9]|2[0-4])/#',
];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
foreach ($gone_patterns as $pattern) {
    if (preg_match($pattern, $request_uri)) {
        http_response_code(410);
        echo '<!DOCTYPE html><html><head><title>410 Gone</title></head><body><h1>410 Gone</h1><p>This page no longer exists.</p></body></html>';
        exit;
    }
}

if (count($parts) === 0) {
    // Homepage
} elseif ($parts[0] === 'search') {
    $route_search = true;
} elseif (isset($section_config[$parts[0]])) {
    $route_section = $parts[0];
    if (isset($parts[1]) && $parts[1] !== '') {
        $route_slug = $parts[1];
    }
} else {
    // Legacy ?section= URLs — redirect to clean URLs
    $qs = $_GET['section'] ?? '';
    $qp = $_GET['post'] ?? '';
    if ($qs && isset($section_config[$qs])) {
        $redirect = $qp ? '/' . $qs . '/' . $qp . '/' : '/' . $qs . '/';
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        exit;
    }
}

$all_posts    = load_published();
$today        = date('l, F j, Y');
$ticker_posts = array_slice($all_posts, 0, 8);

// Favicon
$favicon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" fill="#0e0e0e" rx="4"/><text x="5" y="26" font-family="Georgia,serif" font-size="26" font-weight="900" font-style="italic" fill="#c0392b">D</text></svg>';
$favicon_b64 = base64_encode($favicon_svg);

// ── SHARED HEAD & NAV ──────────────────────────────────────────
function render_head(string $title, string $active_section, string $desc = '', string $canonical = ''): void {
    global $today, $favicon_b64, $search_query;
    $meta_desc = $desc ?: "Satirical North American VC and M&A news. We call out bad deals so you don't have to.";
    $canon     = $canonical ?: 'https://dumbcapital.com/';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — DumbCapital</title>
<meta name="description" content="<?= e($meta_desc) ?>">
<link rel="canonical" href="<?= e($canon) ?>">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?= $favicon_b64 ?>">
<link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?= $favicon_b64 ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="DumbCapital">
<meta property="og:title" content="<?= e($title) ?> — DumbCapital">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($title) ?> — DumbCapital">
<meta name="twitter:description" content="<?= e($meta_desc) ?>">
<meta name="robots" content="index, follow">
<meta name="author" content="DumbCapital">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-LR6WXJEYLX"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-LR6WXJEYLX');</script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6066516193533329" crossorigin="anonymous"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
  :root{--ink:#0e0e0e;--paper:#f5f2eb;--accent:#c0392b;--muted:#6b6560;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'IBM Plex Sans',sans-serif;font-size:16px;line-height:1.6;}
  .ad-wrap{text-align:center;padding:10px 0;background:#faf8f4;border-top:1px solid #e8e4dc;border-bottom:1px solid #e8e4dc;}
  .ad-label{font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#ccc;display:block;margin-bottom:3px;}
  .ad-wrap ins{max-width:728px;max-height:90px;display:inline-block!important;}
  .ticker-wrap{background:var(--accent);color:#fff;font-family:'IBM Plex Mono',monospace;font-size:12px;overflow:hidden;white-space:nowrap;padding:6px 0;}
  .ticker-inner{display:inline-block;animation:ticker 55s linear infinite;}
  .ticker-inner span{margin:0 56px;}
  @keyframes ticker{from{transform:translateX(0)}to{transform:translateX(-50%)}}
  .masthead{border-bottom:3px solid var(--ink);padding:28px 40px 20px;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;}
  .masthead-left{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);line-height:1.9;}
  .masthead-right{text-align:right;font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);line-height:1.9;}
  .masthead-center{text-align:center;}
  .logo{font-family:'Playfair Display',serif;font-size:64px;font-weight:900;letter-spacing:-2px;line-height:1;color:var(--ink);text-decoration:none;display:block;}
  .logo em{color:var(--accent);font-style:italic;}
  .tagline{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.25em;text-transform:uppercase;color:var(--muted);margin-top:5px;}
  /* NAV + SEARCH */
  .nav-wrap{background:var(--ink);display:flex;align-items:center;justify-content:center;}
  nav{display:flex;flex-wrap:wrap;justify-content:center;}
  nav a{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--paper);text-decoration:none;padding:10px 20px;border-right:1px solid #2a2a2a;transition:background .15s;display:block;}
  nav a:first-child{border-left:1px solid #2a2a2a;}
  nav a:hover,nav a.active{background:var(--accent);}
  .search-form{display:flex;align-items:center;padding:0 16px;border-left:1px solid #2a2a2a;}
  .search-form input{background:transparent;border:none;color:#fff;font-family:'IBM Plex Mono',monospace;font-size:11px;padding:10px 8px;outline:none;width:140px;}
  .search-form input::placeholder{color:#666;}
  .search-form button{background:none;border:none;color:#888;cursor:pointer;padding:8px 4px;font-size:14px;transition:color .15s;}
  .search-form button:hover{color:var(--accent);}
  /* LAYOUT */
  .container{max-width:1200px;margin:0 auto;padding:0 40px;}
  .section-label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:var(--accent);border-bottom:1px solid var(--accent);display:inline-block;padding-bottom:2px;margin-bottom:18px;}
  .section-banner{padding:28px 0 20px;border-bottom:2px solid var(--ink);}
  .section-banner h1{font-family:'Playfair Display',serif;font-size:42px;font-weight:900;letter-spacing:-1px;margin-bottom:6px;}
  .section-banner p{font-size:14px;color:var(--muted);font-style:italic;}
  /* HERO */
  .hero{padding:40px 0;border-bottom:2px solid var(--ink);display:grid;grid-template-columns:3fr 2fr;gap:0;}
  .hero-main{border-right:1px solid var(--ink);padding-right:36px;}
  .kicker{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:10px;}
  .hero-main h1,.hero-main h2{font-family:'Playfair Display',serif;font-size:42px;font-weight:900;line-height:1.05;letter-spacing:-1px;margin-bottom:18px;}
  .dek{font-size:17px;line-height:1.6;color:var(--muted);font-style:italic;border-left:3px solid var(--accent);padding-left:16px;margin-bottom:18px;}
  .body p{font-size:15px;line-height:1.7;color:#333;margin-bottom:14px;}
  .dumb-rating{display:inline-flex;align-items:center;gap:8px;margin:14px 0;font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);background:#ede9e0;padding:8px 12px;border:1px solid #ccc;}
  .byline{font-family:'IBM Plex Mono',monospace;font-size:10px;color:var(--muted);margin-top:10px;}
  .byline strong{color:var(--ink);}
  .source-link{font-family:'IBM Plex Mono',monospace;font-size:10px;color:var(--muted);display:block;margin-top:6px;}
  .source-link a{color:var(--accent);}
  .hero-sidebar{padding-left:36px;}
  .side-story{padding-bottom:22px;margin-bottom:22px;border-bottom:1px solid #ccc;}
  .side-story:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0;}
  .side-story h3{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;line-height:1.2;margin-bottom:6px;}
  .side-story h3 a,.hero-main h1 a,.hero-main h2 a{color:var(--ink);text-decoration:none;}
  .side-story h3 a:hover,.hero-main h1 a:hover,.hero-main h2 a:hover{color:var(--accent);}
  .side-story p{font-size:13px;color:var(--muted);line-height:1.55;}
  /* TAGS */
  .tag{display:inline-block;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.15em;text-transform:uppercase;padding:2px 8px;margin-bottom:8px;border:1px solid currentColor;}
  .tag-ma{color:#1a1a2e;border-color:#1a1a2e;}.tag-vc{color:#3a5a8a;border-color:#3a5a8a;}
  .tag-pe{color:#555;border-color:#555;}.tag-flop{background:var(--accent);color:#fff;border-color:var(--accent);}
  .tag-opinion{background:var(--ink);color:var(--paper);border-color:var(--ink);}.tag-unicorn{color:#7a4a9a;border-color:#7a4a9a;}
  a.article-link{color:var(--ink);text-decoration:none;}
  a.article-link:hover{color:var(--accent);}
  /* HOME SECTION STRIPS */
  .home-section{padding:36px 0;border-bottom:1px solid var(--ink);}
  .home-section-header{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;border-bottom:2px solid var(--ink);padding-bottom:10px;}
  .home-section-header h2{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;}
  .home-section-header a{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--accent);text-decoration:none;}
  .home-section-header a:hover{text-decoration:underline;}
  .home-posts-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:0;}
  .home-post-main{border-right:1px solid var(--ink);padding-right:24px;}
  .home-post-main h3{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;line-height:1.2;margin-bottom:8px;}
  .home-post-main p{font-size:14px;color:var(--muted);line-height:1.55;margin-bottom:10px;}
  .home-post-side{padding:0 0 0 24px;border-right:1px solid var(--ink);}
  .home-post-side:last-child{border-right:none;padding-right:0;}
  .home-post-side h3{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;line-height:1.2;margin-bottom:6px;}
  .home-post-side p{font-size:12px;color:var(--muted);line-height:1.5;}
  /* SECTION PAGE CARDS */
  .section-posts{padding:40px 0;}
  .posts-grid{display:grid;grid-template-columns:1fr 1fr;gap:36px;}
  .article-card{border-top:2px solid var(--ink);padding-top:16px;}
  .article-card h2{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;line-height:1.2;margin-bottom:8px;}
  .article-card h2 a{color:var(--ink);text-decoration:none;}
  .article-card h2 a:hover{color:var(--accent);}
  .article-card p{font-size:13px;color:var(--muted);line-height:1.55;margin-bottom:8px;}
  .article-card .dumb-rating{font-size:10px;padding:5px 8px;margin:8px 0;}
  .read-more{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);text-decoration:none;display:inline-block;margin-top:6px;}
  .read-more:hover{text-decoration:underline;}
  /* SINGLE ARTICLE */
  .article-full{max-width:780px;padding:40px 0;}
  .article-full h1{font-family:'Playfair Display',serif;font-size:44px;font-weight:900;line-height:1.05;letter-spacing:-1px;margin-bottom:16px;}
  .article-full .body p{font-size:16px;line-height:1.75;color:#333;margin-bottom:18px;}
  .back-link{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);text-decoration:none;display:inline-block;padding:10px 0 0;}
  .back-link:hover{color:var(--accent);}
  /* SHARE BUTTONS */
  .share-bar{display:flex;align-items:center;gap:10px;margin:28px 0;padding:20px 0;border-top:1px solid #ddd;border-bottom:1px solid #ddd;}
  .share-label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);margin-right:4px;}
  .share-btn{display:inline-flex;align-items:center;gap:6px;font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;padding:8px 14px;border:1px solid var(--ink);color:var(--ink);text-decoration:none;cursor:pointer;background:none;transition:all .15s;}
  .share-btn:hover{background:var(--ink);color:#fff;}
  .share-btn.copied{background:var(--accent);color:#fff;border-color:var(--accent);}
  .share-btn svg{width:14px;height:14px;fill:currentColor;flex-shrink:0;}
  /* RELATED ARTICLES */
  .related-section{padding:32px 0 0;margin-top:8px;border-top:2px solid var(--ink);}
  .related-section h3{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;margin-bottom:20px;}
  .related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
  .related-card{border-top:2px solid var(--ink);padding-top:14px;}
  .related-card h4{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;line-height:1.25;margin-bottom:6px;}
  .related-card h4 a{color:var(--ink);text-decoration:none;}
  .related-card h4 a:hover{color:var(--accent);}
  .related-card p{font-size:12px;color:var(--muted);line-height:1.5;}
  /* SEARCH */
  .search-results{padding:40px 0;}
  .search-results h1{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;margin-bottom:6px;}
  .search-results .sub{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);margin-bottom:32px;}
  .search-result-item{border-top:1px solid #ddd;padding:20px 0;}
  .search-result-item:first-of-type{border-top:2px solid var(--ink);}
  .search-result-item h2{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;margin-bottom:6px;}
  .search-result-item h2 a{color:var(--ink);text-decoration:none;}
  .search-result-item h2 a:hover{color:var(--accent);}
  .search-result-item p{font-size:13px;color:var(--muted);margin-bottom:6px;}
  .search-highlight{background:#fff3b0;padding:1px 3px;}
  .no-results{text-align:center;padding:60px 0;color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:13px;}
  /* PAGINATION */
  .pagination{display:flex;align-items:center;justify-content:center;gap:12px;padding:40px 0 20px;font-family:'IBM Plex Mono',monospace;font-size:12px;}
  .pagination a{color:var(--ink);text-decoration:none;padding:8px 16px;border:1px solid var(--ink);transition:all .15s;}
  .pagination a:hover{background:var(--ink);color:var(--paper);}
  .pagination .current{padding:8px 16px;background:var(--accent);color:#fff;border:1px solid var(--accent);}
  .pagination .disabled{padding:8px 16px;color:#ccc;border:1px solid #ccc;}
  /* OPINION BOX */
  .opinion-box{background:var(--ink);color:var(--paper);padding:36px 40px;margin:40px 0;}
  .opinion-box .label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:var(--accent);margin-bottom:14px;}
  .opinion-box blockquote{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;font-style:italic;line-height:1.3;margin-bottom:16px;max-width:820px;}
  .opinion-box .attribution{font-family:'IBM Plex Mono',monospace;font-size:11px;color:#888;}
  /* ABOUT / FOOTER */
  .about-strip{background:#ede9e0;border:1px solid #ccc;padding:28px 36px;margin:40px 0;display:flex;gap:32px;align-items:flex-start;}
  .big-d{font-family:'Playfair Display',serif;font-size:80px;font-weight:900;color:var(--accent);line-height:.85;flex-shrink:0;}
  .about-strip h3{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;margin-bottom:8px;}
  .about-strip p{font-size:14px;color:var(--muted);line-height:1.6;}
  .empty-notice{text-align:center;padding:80px 32px;color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:13px;border:1px dashed #ccc;margin:40px 0;}
  footer{background:var(--ink);color:var(--paper);padding:44px 40px;text-align:center;}
  .footer-logo{font-family:'Playfair Display',serif;font-size:38px;font-weight:900;letter-spacing:-1px;margin-bottom:10px;}
  .footer-logo em{color:var(--accent);font-style:italic;}
  footer p{font-family:'IBM Plex Mono',monospace;font-size:11px;color:#777;line-height:1.9;}
  .footer-nav{display:flex;justify-content:center;gap:28px;margin:20px 0 0;flex-wrap:wrap;}
  .footer-nav a{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:#888;text-decoration:none;}
  .footer-nav a:hover{color:var(--accent);}
  @media(max-width:900px){
    .masthead{grid-template-columns:1fr;text-align:center;padding:20px;}
    .masthead-left,.masthead-right{display:none;}
    .logo{font-size:44px;}
    .nav-wrap{flex-direction:column;}
    .search-form{border-left:none;border-top:1px solid #2a2a2a;width:100%;padding:6px 16px;}
    .search-form input{width:100%;}
    .hero{grid-template-columns:1fr;}
    .hero-main{border-right:none;border-bottom:1px solid var(--ink);padding-right:0;padding-bottom:30px;margin-bottom:30px;}
    .hero-main h1,.hero-main h2{font-size:28px;}
    .hero-sidebar{padding-left:0;}
    .home-posts-grid{grid-template-columns:1fr;}
    .home-post-main{border-right:none;border-bottom:1px solid var(--ink);padding-right:0;padding-bottom:20px;margin-bottom:20px;}
    .home-post-side{border-right:none;padding:0;border-bottom:1px solid #eee;padding-bottom:16px;margin-bottom:16px;}
    .posts-grid,.related-grid{grid-template-columns:1fr;}
    .about-strip{flex-direction:column;gap:12px;}
    .container{padding:0 20px;}
    .ad-wrap ins{max-width:320px;max-height:50px;}
    .article-full h1{font-size:28px;}
    .share-bar{flex-wrap:wrap;}
  }
</style>
</head>
<body>
<?php
    // Ad 1
    echo '<div class="ad-wrap"><span class="ad-label">Advertisement</span><ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script></div>';
    // Ticker
    global $ticker_posts;
    echo '<div class="ticker-wrap"><div class="ticker-inner">';
    if ($ticker_posts) {
        $items = array_merge($ticker_posts, $ticker_posts);
        foreach ($items as $tp) echo '<span>' . htmlspecialchars(strtoupper($tp['headline']??''), ENT_QUOTES) . '</span>';
    } else {
        echo '<span>DUMBCAPITAL — SATIRICAL VC &amp; M&amp;A NEWS — NORTH AMERICA</span><span>DUMBCAPITAL — SATIRICAL VC &amp; M&amp;A NEWS — NORTH AMERICA</span>';
    }
    echo '</div></div>';
?>
<div class="masthead">
  <div class="masthead-left">Est. when term sheets<br>outnumbered good ideas<br>www.dumbcapital.com</div>
  <div class="masthead-center">
    <a class="logo" href="/">Dumb<em>Capital</em></a>
    <div class="tagline">North American VC &amp; M&amp;A News — Unfiltered, Unimpressed, Unprofitable</div>
  </div>
  <div class="masthead-right">North America Edition<br><?= $today ?><br>Free (Like Your Equity)</div>
</div>
<div class="nav-wrap">
  <nav>
    <a href="/" class="<?= !$route_section&&!$route_search?'active':'' ?>">Home</a>
    <a href="/vc/" class="<?= $active_section==='vc'?'active':'' ?>">VC Deals</a>
    <a href="/ma/" class="<?= $active_section==='ma'?'active':'' ?>">M&amp;A Morgue</a>
    <a href="/pe/" class="<?= $active_section==='pe'?'active':'' ?>">PE Corner</a>
    <a href="/unicorn/" class="<?= $active_section==='unicorn'?'active':'' ?>">Unicorn Watch</a>
    <a href="/opinion/" class="<?= $active_section==='opinion'?'active':'' ?>">Opinion</a>
  </nav>
  <form class="search-form" action="/search/" method="GET">
    <input type="text" name="q" placeholder="Search articles..." value="<?= e($search_query) ?>">
    <button type="submit">&#9906;</button>
  </form>
</div>
<script>
(function(){
  var t=Date.now(),d=false;
  function pi(){var p=window.location.pathname,pts=p.replace(/^\/|\/$/g,'').split('/');return{page:p||'/',section:pts[0]||'',slug:pts[1]||''};}
  function st(){
    if(d)return;d=true;
    var dur=Math.round((Date.now()-t)/1000),i=pi();
    fetch('/tracker.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({page:i.page,section:i.section,slug:i.slug,duration:dur}),keepalive:true}).catch(function(){});
  }
  document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')st();});
  setTimeout(st,30000);
  window.addEventListener('pagehide',st);
})();
</script>
<?php
} // end render_head()

function render_ad(): void {
    echo '<div class="ad-wrap" style="margin:8px 0;"><span class="ad-label">Advertisement</span><ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script></div>';
}

function render_footer(): void { ?>
<div class="container">
  <div class="about-strip">
    <div class="big-d">D</div>
    <div>
      <h3>About DumbCapital</h3>
      <p>DumbCapital covers venture capital and M&amp;A in North America with the skepticism these markets have long deserved and rarely received. We are not impressed by large numbers. We are not moved by press releases. All articles are satirical commentary based on real, publicly reported deals. Nothing here is financial advice.</p>
    </div>
  </div>
</div>
<footer>
  <div class="footer-logo">Dumb<em>Capital</em></div>
  <p>North American VC &amp; M&amp;A News &nbsp;·&nbsp; www.dumbcapital.com<br>
  &copy; DumbCapital <?= date('Y') ?>. All articles are satirical commentary on real, publicly reported news.<br>
  Nothing published here constitutes financial, legal, or investment advice.</p>
  <div class="footer-nav">
    <a href="/">Home</a><a href="/vc/">VC Deals</a><a href="/ma/">M&amp;A Morgue</a>
    <a href="/pe/">PE Corner</a><a href="/unicorn/">Unicorn Watch</a><a href="/opinion/">Opinion</a>
  </div>
</footer>
<?php
}

function render_share_buttons(array $post): void {
    $url   = 'https://dumbcapital.com' . post_url($post);
    $title = $post['headline'] ?? '';
    $x_url = 'https://twitter.com/intent/tweet?text=' . urlencode($title) . '&url=' . urlencode($url);
    $li_url= 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($url);
?>
<div class="share-bar">
  <span class="share-label">Share:</span>
  <a class="share-btn" href="<?= e($x_url) ?>" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    Post on X
  </a>
  <a class="share-btn" href="<?= e($li_url) ?>" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
    Share on LinkedIn
  </a>
  <button class="share-btn" id="copy-btn" onclick="copyLink('<?= e($url) ?>')">
    <svg viewBox="0 0 24 24"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
    Copy Link
  </button>
</div>
<script>
function copyLink(url) {
  navigator.clipboard.writeText(url).then(function() {
    var btn = document.getElementById('copy-btn');
    btn.classList.add('copied');
    btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M4.5 12.75l6 6 9-13.5"/></svg> Copied!';
    setTimeout(function(){ btn.classList.remove('copied'); btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg> Copy Link'; }, 2500);
  });
}
</script>
<?php
}

function render_related(array $all_posts, array $current_post): void {
    $sec     = $current_post['section'] ?? $current_post['tag'] ?? 'vc';
    $slug    = $current_post['slug'] ?? '';
    $related = array_filter($all_posts, function($p) use ($sec, $slug) {
        $ps = $p['section'] ?? $p['tag'] ?? 'vc';
        return $ps === $sec && ($p['slug']??'') !== $slug;
    });
    $related = array_slice(array_values($related), 0, 3);
    if (!$related) return;
?>
<div class="related-section">
  <h3>More from this section</h3>
  <div class="related-grid">
    <?php foreach($related as $r): ?>
    <div class="related-card">
      <?= tag_span($r['tag']??$sec) ?>
      <h4><a href="<?= post_url($r) ?>"><?= e($r['headline']) ?></a></h4>
      <p><?= e($r['subheadline']??'') ?></p>
      <div class="byline" style="margin-top:6px;"><?= e($r['date']??'') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
}

// ═══════════════════════════════════════════════════════════════
//  SEARCH PAGE
// ═══════════════════════════════════════════════════════════════
if ($route_search) {
    render_head('Search', '', '', 'https://dumbcapital.com/search/');
    $results = [];
    if ($search_query) {
        $q = strtolower($search_query);
        foreach ($all_posts as $p) {
            $haystack = strtolower(
                ($p['headline']??'') . ' ' .
                ($p['subheadline']??'') . ' ' .
                strip_tags($p['body_html']??'')
            );
            if (strpos($haystack, $q) !== false) $results[] = $p;
        }
    }
?>
<div class="container">
  <div class="search-results">
    <?php if ($search_query): ?>
    <h1>Search results for "<?= e($search_query) ?>"</h1>
    <div class="sub"><?= count($results) ?> article<?= count($results) !== 1 ? 's' : '' ?> found</div>
    <?php else: ?>
    <h1>Search DumbCapital</h1>
    <div class="sub">Enter a term above to search all articles</div>
    <?php endif; ?>

    <?php if ($results): foreach($results as $p):
      $sec = $p['section'] ?? $p['tag'] ?? 'vc';
      // Highlight search term in snippet
      $snippet = e($p['subheadline']??'');
      if ($search_query) {
          $snippet = preg_replace('/(' . preg_quote(e($search_query), '/') . ')/i', '<mark class="search-highlight">$1</mark>', $snippet);
      }
    ?>
    <div class="search-result-item">
      <?= tag_span($p['tag']??$sec) ?>
      <h2><a href="<?= post_url($p) ?>"><?= e($p['headline']) ?></a></h2>
      <p><?= $snippet ?></p>
      <div class="byline"><?= e($p['date']??'') ?></div>
    </div>
    <?php endforeach; elseif($search_query): ?>
    <div class="no-results">No articles found for "<?= e($search_query) ?>". Try a different search term.</div>
    <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>

</body></html>

<?php
// ═══════════════════════════════════════════════════════════════
//  SINGLE ARTICLE PAGE
// ═══════════════════════════════════════════════════════════════
} elseif ($route_section && $route_slug) {
    $cfg  = $section_config[$route_section];
    $post = null;
    foreach ($all_posts as $p) {
        if (($p['slug']??'') === $route_slug) { $post = $p; break; }
    }
    $page_title = $post ? ($post['headline']??$cfg['title']) : $cfg['title'];
    $page_desc  = $post ? ($post['subheadline']??'') : $cfg['desc'];
    $canon      = 'https://dumbcapital.com' . post_url($post ?? ['section'=>$route_section,'slug'=>$route_slug,'tag'=>$route_section]);
    render_head($page_title, $route_section, $page_desc, $canon);
?>
<div class="container">
  <a href="/<?= e($route_section) ?>/" class="back-link">← Back to <?= $cfg['title'] ?></a>
  <?php if ($post): ?>
  <div class="article-full">
    <div class="kicker" style="margin-top:20px;">★ <?= e($post['kicker']??$cfg['label']) ?></div>
    <?= tag_span($post['tag']??$route_section) ?>
    <h1><?= e($post['headline']) ?></h1>
    <div class="dek"><?= e($post['subheadline']??'') ?></div>
    <div class="byline">
      Published <?= e($post['date']??'') ?>
      <?php $rt = reading_time($post['body_html']??''); ?>
      &nbsp;·&nbsp; <?= $rt ?> min read
    </div>

    <?php render_share_buttons($post); ?>

    <div class="body"><?= $post['body_html'] ?></div>
    <div class="dumb-rating"><?= skulls((int)($post['dumb_rating']??3)) ?> &nbsp;Dumb Rating: <?= (int)($post['dumb_rating']??3) ?>/5 — <?= e($post['dumb_rating_label']??'') ?></div>
    <?php if(!empty($post['source_name'])||!empty($post['source_url'])): ?>
    <div class="source-link">Source: <?php if(!empty($post['source_url'])): ?><a href="<?= e($post['source_url']) ?>" target="_blank" rel="noopener"><?= e($post['source_name']??$post['source_url']) ?></a><?php else: ?><strong><?= e($post['source_name']) ?></strong><?php endif; ?></div>
    <?php endif; ?>

    <?php render_share_buttons($post); ?>

    <?php if(!empty($post['glossary_term'])): ?>
    <div class="opinion-box" style="margin-top:32px;">
      <div class="label">★ From the Glossary</div>
      <blockquote>"<?= e($post['glossary_term']) ?>"</blockquote>
      <div class="attribution"><?= e($post['glossary_definition']??'') ?></div>
    </div>
    <?php endif; ?>

    <?php render_related($all_posts, $post); ?>
  </div>
  <?php else: ?>
  <div class="empty-notice">Article not found.</div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>

</body></html>

<?php
// ═══════════════════════════════════════════════════════════════
//  SECTION PAGE
// ═══════════════════════════════════════════════════════════════
} elseif ($route_section) {
    $cfg = $section_config[$route_section];
    $section_posts = array_values(array_filter($all_posts, function($p) use ($route_section) {
        $s = $p['section'] ?? $p['tag'] ?? 'vc';
        return $s === $route_section || ($p['tag']??'') === $route_section;
    }));
    $total_posts  = count($section_posts);
    $total_pages  = max(1, (int)ceil(max(0, $total_posts - 3) / POSTS_PER_PAGE));
    $current_page = min($current_page, $total_pages);
    $hero         = $section_posts[0] ?? null;
    $sidebar      = array_slice($section_posts, 1, 2);
    $card_offset  = 3 + (($current_page - 1) * POSTS_PER_PAGE);
    $cards        = array_slice($section_posts, $card_offset, POSTS_PER_PAGE);
    $canon        = 'https://dumbcapital.com/' . $route_section . '/';
    render_head($cfg['title'], $route_section, $cfg['desc'], $canon);
?>
<div class="container">
  <div class="section-banner">
    <div class="section-label"><?= $cfg['label'] ?></div>
    <h1><?= $cfg['title'] ?></h1>
    <p><?= $cfg['desc'] ?></p>
  </div>

  <?php if ($hero && $current_page === 1): ?>
  <div class="hero">
    <div class="hero-main">
      <div class="kicker">★ <?= e($hero['kicker']??'Latest') ?></div>
      <h2><a class="article-link" href="<?= post_url($hero) ?>"><?= e($hero['headline']) ?></a></h2>
      <div class="dek"><?= e($hero['subheadline']??'') ?></div>
      <div class="body"><?= $hero['body_html'] ?></div>
      <div class="dumb-rating"><?= skulls((int)($hero['dumb_rating']??3)) ?> &nbsp;Dumb Rating: <?= (int)($hero['dumb_rating']??3) ?>/5 — <?= e($hero['dumb_rating_label']??'') ?></div>
      <?php if(!empty($hero['source_name'])||!empty($hero['source_url'])): ?>
      <div class="source-link">Source: <?php if(!empty($hero['source_url'])): ?><a href="<?= e($hero['source_url']) ?>" target="_blank" rel="noopener"><?= e($hero['source_name']??$hero['source_url']) ?></a><?php else: ?><strong><?= e($hero['source_name']) ?></strong><?php endif; ?></div>
      <?php endif; ?>
      <div class="byline">Published <?= e($hero['date']??'') ?> &nbsp;·&nbsp; <?= reading_time($hero['body_html']??'') ?> min read</div>
      <a href="<?= post_url($hero) ?>" class="read-more" style="margin-top:12px;">Read full article →</a>
    </div>
    <div class="hero-sidebar">
      <?php if($sidebar): ?>
      <div class="section-label">Also in <?= $cfg['title'] ?></div>
      <?php foreach($sidebar as $s): ?>
      <div class="side-story">
        <?= tag_span($s['tag']??$route_section) ?>
        <h3><a class="article-link" href="<?= post_url($s) ?>"><?= e($s['headline']) ?></a></h3>
        <p><?= e($s['subheadline']??'') ?></p>
        <div class="dumb-rating" style="font-size:10px;padding:4px 8px;"><?= skulls((int)($s['dumb_rating']??3)) ?> <?= (int)($s['dumb_rating']??3) ?>/5</div>
        <div class="byline"><?= e($s['date']??'') ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if(!empty($hero['glossary_term'])): ?>
  <div class="opinion-box">
    <div class="label">★ From the Glossary</div>
    <blockquote>"<?= e($hero['glossary_term']) ?>"</blockquote>
    <div class="attribution"><?= e($hero['glossary_definition']??'') ?></div>
  </div>
  <?php endif; ?>
  <?php elseif (!$hero): ?>
  <div class="empty-notice">📰 No posts in <?= $cfg['title'] ?> yet — check back after the next bot run (Tue/Fri).</div>
  <?php endif; ?>

  <?php render_ad(); ?>

  <?php if($cards): ?>
  <div class="section-posts">
    <div class="section-label"><?= $current_page > 1 ? 'Page '.$current_page.' — ' : 'More from ' ?><?= $cfg['title'] ?></div>
    <div class="posts-grid">
      <?php foreach($cards as $p): ?>
      <div class="article-card">
        <?= tag_span($p['tag']??$route_section) ?>
        <h2><a href="<?= post_url($p) ?>"><?= e($p['headline']) ?></a></h2>
        <p><?= e($p['subheadline']??'') ?></p>
        <div class="dumb-rating"><?= skulls((int)($p['dumb_rating']??3)) ?> &nbsp;<?= (int)($p['dumb_rating']??3) ?>/5 — <?= e($p['dumb_rating_label']??'') ?></div>
        <div class="byline"><?= e($p['date']??'') ?></div>
        <a href="<?= post_url($p) ?>" class="read-more">Read full article →</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if($total_pages > 1): ?>
  <div class="pagination">
    <?php if($current_page > 1): ?>
      <a href="<?= section_url($route_section, $current_page-1) ?>">← Previous</a>
    <?php else: ?>
      <span class="disabled">← Previous</span>
    <?php endif; ?>
    <?php for($pg=1; $pg<=$total_pages; $pg++): ?>
      <?php if($pg===$current_page): ?><span class="current"><?= $pg ?></span>
      <?php else: ?><a href="<?= section_url($route_section, $pg) ?>"><?= $pg ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if($current_page < $total_pages): ?>
      <a href="<?= section_url($route_section, $current_page+1) ?>">Next →</a>
    <?php else: ?>
      <span class="disabled">Next →</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>

</body></html>

<?php
// ═══════════════════════════════════════════════════════════════
//  HOMEPAGE
// ═══════════════════════════════════════════════════════════════
} else {
    $by_section = [];
    foreach ($all_posts as $p) {
        $s = $p['section'] ?? $p['tag'] ?? 'vc';
        if (!isset($section_config[$s])) $s = 'vc';
        $by_section[$s][] = $p;
    }
    $hero    = $all_posts[0] ?? null;
    $sidebar = array_slice($all_posts, 1, 3);
    render_head('DumbCapital — VC & M&A News, Unfiltered', '', '', 'https://dumbcapital.com/');
?>
<div class="container">
<?php if($hero): ?>
<div class="hero" style="padding:40px 0;border-bottom:2px solid var(--ink);">
  <div class="hero-main">
    <div class="kicker">★ <?= e($hero['kicker']??'Latest') ?></div>
    <h1><a class="article-link" href="<?= post_url($hero) ?>"><?= e($hero['headline']) ?></a></h1>
    <div class="dek"><?= e($hero['subheadline']??'') ?></div>
    <div class="body"><?= $hero['body_html'] ?></div>
    <div class="dumb-rating"><?= skulls((int)($hero['dumb_rating']??3)) ?> &nbsp;Dumb Rating: <?= (int)($hero['dumb_rating']??3) ?>/5 — <?= e($hero['dumb_rating_label']??'') ?></div>
    <?php if(!empty($hero['source_name'])||!empty($hero['source_url'])): ?>
    <div class="source-link">Source: <?php if(!empty($hero['source_url'])): ?><a href="<?= e($hero['source_url']) ?>" target="_blank" rel="noopener"><?= e($hero['source_name']??$hero['source_url']) ?></a><?php else: ?><strong><?= e($hero['source_name']) ?></strong><?php endif; ?></div>
    <?php endif; ?>
    <div class="byline">Published <?= e($hero['date']??'') ?> &nbsp;·&nbsp; <?= reading_time($hero['body_html']??'') ?> min read</div>
    <a href="<?= post_url($hero) ?>" class="read-more" style="margin-top:12px;">Read full article →</a>
  </div>
  <div class="hero-sidebar">
    <div class="section-label">Also This Week</div>
    <?php foreach($sidebar as $s): ?>
    <div class="side-story">
      <?= tag_span($s['tag']??'vc') ?>
      <h3><a class="article-link" href="<?= post_url($s) ?>"><?= e($s['headline']) ?></a></h3>
      <p><?= e($s['subheadline']??'') ?></p>
      <div class="byline"><?= e($s['date']??'') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php if(!empty($hero['glossary_term'])): ?>
<div class="opinion-box">
  <div class="label">★ From the Glossary</div>
  <blockquote>"<?= e($hero['glossary_term']) ?>"</blockquote>
  <div class="attribution"><?= e($hero['glossary_definition']??'') ?></div>
</div>
<?php endif; ?>
<?php else: ?>
<div class="empty-notice">📰 No posts yet — the bot runs Tuesday &amp; Friday. Check back soon.</div>
<?php endif; ?>

<?php render_ad(); ?>

<?php foreach($section_config as $sec_key => $sec_cfg):
    $posts = $by_section[$sec_key] ?? [];
    if (empty($posts)) continue;
    $main = $posts[0];
    $subs = array_slice($posts, 1, 2);
?>
<div class="home-section">
  <div class="home-section-header">
    <h2><?= $sec_cfg['label'] ?></h2>
    <a href="/<?= $sec_key ?>/">More <?= $sec_cfg['label'] ?> →</a>
  </div>
  <div class="home-posts-grid">
    <div class="home-post-main">
      <?= tag_span($main['tag']??$sec_key) ?>
      <h3><a class="article-link" href="<?= post_url($main) ?>"><?= e($main['headline']) ?></a></h3>
      <p><?= e($main['subheadline']??'') ?></p>
      <div class="dumb-rating" style="font-size:10px;padding:5px 8px;"><?= skulls((int)($main['dumb_rating']??3)) ?> <?= (int)($main['dumb_rating']??3) ?>/5 — <?= e($main['dumb_rating_label']??'') ?></div>
      <div class="byline"><?= e($main['date']??'') ?></div>
      <a href="<?= post_url($main) ?>" class="read-more">Read more →</a>
    </div>
    <?php foreach($subs as $sub): ?>
    <div class="home-post-side">
      <?= tag_span($sub['tag']??$sec_key) ?>
      <h3><a class="article-link" href="<?= post_url($sub) ?>"><?= e($sub['headline']) ?></a></h3>
      <p><?= e($sub['subheadline']??'') ?></p>
      <div class="byline"><?= e($sub['date']??'') ?></div>
      <a href="<?= post_url($sub) ?>" class="read-more">Read more →</a>
    </div>
    <?php endforeach; ?>
    <?php for($i=count($subs);$i<2;$i++): ?><div class="home-post-side"></div><?php endfor; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php render_footer(); ?>

</body></html>
<?php } ?>
