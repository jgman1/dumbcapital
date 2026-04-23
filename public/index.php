<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital — Main Router
//  ?section=vc|ma|pe|unicorn|opinion  → section page
//  ?section=X&post=slug               → single article page
//  ?section=X&page=N                  → paginated section
//  (no params)                        → homepage
// ─────────────────────────────────────────────────────────────
define('POSTS_DIR',    __DIR__ . '/posts/');
define('SITE_URL',     'https://dumbcapital.com');
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
    return '/?section=' . urlencode($sec) . '&amp;post=' . urlencode($slug);
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
$current_section = $_GET['section'] ?? '';
$current_post    = $_GET['post']    ?? '';
$current_page    = max(1, (int)($_GET['page'] ?? 1));
if ($current_section && !isset($section_config[$current_section])) $current_section = '';

$all_posts = load_published();
$today     = date('l, F j, Y');
$ticker_posts = array_slice($all_posts, 0, 8);

// ── FAVICON SVG (inline base64) ────────────────────────────────
// Red "D" on dark background — matches brand
$favicon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" fill="#0e0e0e" rx="4"/><text x="5" y="26" font-family="Georgia,serif" font-size="26" font-weight="900" font-style="italic" fill="#c0392b">D</text></svg>';
$favicon_b64 = base64_encode($favicon_svg);

// ── SHARED HEAD ───────────────────────────────────────────────
function render_head(string $title, string $section_key, string $desc = ''): void {
    global $today, $favicon_b64, $section_config;
    $meta_desc = $desc ?: "Satirical North American VC and M&A news. We call out bad deals so you don't have to.";
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — DumbCapital</title>
<meta name="description" content="<?= e($meta_desc) ?>">
<link rel="canonical" href="https://dumbcapital.com/">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?= $favicon_b64 ?>">
<link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?= $favicon_b64 ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="DumbCapital">
<meta property="og:title" content="<?= e($title) ?> — DumbCapital">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<meta property="og:url" content="https://dumbcapital.com/">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($title) ?> — DumbCapital">
<meta name="twitter:description" content="<?= e($meta_desc) ?>">
<meta name="robots" content="index, follow">
<meta name="author" content="DumbCapital">

<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-LR6WXJEYLX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-LR6WXJEYLX');
</script>
<!-- Google AdSense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6066516193533329" crossorigin="anonymous"></script>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
  :root{--ink:#0e0e0e;--paper:#f5f2eb;--accent:#c0392b;--muted:#6b6560;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'IBM Plex Sans',sans-serif;font-size:16px;line-height:1.6;}

  /* ADS */
  .ad-wrap{text-align:center;padding:10px 0;background:#faf8f4;border-top:1px solid #e8e4dc;border-bottom:1px solid #e8e4dc;}
  .ad-label{font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#ccc;display:block;margin-bottom:3px;}
  .ad-wrap ins{max-width:728px;max-height:90px;display:inline-block!important;}

  /* TICKER */
  .ticker-wrap{background:var(--accent);color:#fff;font-family:'IBM Plex Mono',monospace;font-size:12px;overflow:hidden;white-space:nowrap;padding:6px 0;}
  .ticker-inner{display:inline-block;animation:ticker 55s linear infinite;}
  .ticker-inner span{margin:0 56px;}
  @keyframes ticker{from{transform:translateX(0)}to{transform:translateX(-50%)}}

  /* MASTHEAD */
  .masthead{border-bottom:3px solid var(--ink);padding:28px 40px 20px;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;}
  .masthead-left{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);line-height:1.9;}
  .masthead-right{text-align:right;font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);line-height:1.9;}
  .masthead-center{text-align:center;}
  .logo{font-family:'Playfair Display',serif;font-size:64px;font-weight:900;letter-spacing:-2px;line-height:1;color:var(--ink);text-decoration:none;display:block;}
  .logo em{color:var(--accent);font-style:italic;}
  .tagline{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.25em;text-transform:uppercase;color:var(--muted);margin-top:5px;}

  /* NAV */
  nav{background:var(--ink);display:flex;justify-content:center;flex-wrap:wrap;}
  nav a{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--paper);text-decoration:none;padding:10px 22px;border-right:1px solid #2a2a2a;transition:background .15s;display:block;}
  nav a:first-child{border-left:1px solid #2a2a2a;}
  nav a:hover,nav a.active{background:var(--accent);}

  /* LAYOUT */
  .container{max-width:1200px;margin:0 auto;padding:0 40px;}
  .section-label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:var(--accent);border-bottom:1px solid var(--accent);display:inline-block;padding-bottom:2px;margin-bottom:18px;}

  /* SECTION BANNER */
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
  .side-story h3 a{color:var(--ink);text-decoration:none;}
  .side-story h3 a:hover{color:var(--accent);}
  .side-story p{font-size:13px;color:var(--muted);line-height:1.55;}

  /* TAGS */
  .tag{display:inline-block;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.15em;text-transform:uppercase;padding:2px 8px;margin-bottom:8px;border:1px solid currentColor;}
  .tag-ma{color:#1a1a2e;border-color:#1a1a2e;}.tag-vc{color:#3a5a8a;border-color:#3a5a8a;}
  .tag-pe{color:#555;border-color:#555;}.tag-flop{background:var(--accent);color:#fff;border-color:var(--accent);}
  .tag-opinion{background:var(--ink);color:var(--paper);border-color:var(--ink);}.tag-unicorn{color:#7a4a9a;border-color:#7a4a9a;}

  /* CLICKABLE HEADLINES */
  a.article-link{color:var(--ink);text-decoration:none;}
  a.article-link:hover{color:var(--accent);}

  /* HOMEPAGE SECTION STRIPS */
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

  /* SINGLE ARTICLE PAGE */
  .article-full{max-width:780px;padding:40px 0;}
  .article-full h1{font-family:'Playfair Display',serif;font-size:44px;font-weight:900;line-height:1.05;letter-spacing:-1px;margin-bottom:16px;}
  .article-full .body p{font-size:16px;line-height:1.75;color:#333;margin-bottom:18px;}
  .back-link{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);text-decoration:none;display:inline-block;padding:10px 0 0;}
  .back-link:hover{color:var(--accent);}

  /* PAGINATION */
  .pagination{display:flex;align-items:center;justify-content:center;gap:12px;padding:40px 0 20px;font-family:'IBM Plex Mono',monospace;font-size:12px;}
  .pagination a{color:var(--ink);text-decoration:none;padding:8px 16px;border:1px solid var(--ink);transition:all .15s;}
  .pagination a:hover{background:var(--ink);color:var(--paper);}
  .pagination .current{padding:8px 16px;background:var(--accent);color:#fff;border:1px solid var(--accent);}
  .pagination .disabled{padding:8px 16px;color:#ccc;border:1px solid #ccc;cursor:default;}

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
    .hero{grid-template-columns:1fr;}
    .hero-main{border-right:none;border-bottom:1px solid var(--ink);padding-right:0;padding-bottom:30px;margin-bottom:30px;}
    .hero-main h1,.hero-main h2{font-size:30px;}
    .hero-sidebar{padding-left:0;}
    .home-posts-grid{grid-template-columns:1fr;}
    .home-post-main{border-right:none;border-bottom:1px solid var(--ink);padding-right:0;padding-bottom:20px;margin-bottom:20px;}
    .home-post-side{border-right:none;padding:0;border-bottom:1px solid #eee;padding-bottom:16px;margin-bottom:16px;}
    .posts-grid{grid-template-columns:1fr;}
    .about-strip{flex-direction:column;gap:12px;}
    .container{padding:0 20px;}
    .ad-wrap ins{max-width:320px;max-height:50px;}
    .article-full h1{font-size:30px;}
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
<nav>
  <a href="/" class="<?= $current_section===''&&!$current_post?'active':'' ?>">Home</a>
  <a href="/?section=vc" class="<?= $current_section==='vc'?'active':'' ?>">VC Deals</a>
  <a href="/?section=ma" class="<?= $current_section==='ma'?'active':'' ?>">M&amp;A Morgue</a>
  <a href="/?section=pe" class="<?= $current_section==='pe'?'active':'' ?>">PE Corner</a>
  <a href="/?section=unicorn" class="<?= $current_section==='unicorn'?'active':'' ?>">Unicorn Watch</a>
  <a href="/?section=opinion" class="<?= $current_section==='opinion'?'active':'' ?>">Opinion</a>
</nav>
<?php
} // end render_head()

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
    <a href="/">Home</a>
    <a href="/?section=vc">VC Deals</a>
    <a href="/?section=ma">M&amp;A Morgue</a>
    <a href="/?section=pe">PE Corner</a>
    <a href="/?section=unicorn">Unicorn Watch</a>
    <a href="/?section=opinion">Opinion</a>
  </div>
</footer>
<?php
}

// ═══════════════════════════════════════════════════════════════
//  SINGLE ARTICLE PAGE
// ═══════════════════════════════════════════════════════════════
if ($current_section && $current_post) {
    // Find the post
    $post = null;
    foreach ($all_posts as $p) {
        if (($p['slug']??'') === $current_post) { $post = $p; break; }
    }
    $cfg = $section_config[$current_section];
    $page_title = $post ? ($post['headline']??$cfg['title']) : $cfg['title'];
    $page_desc  = $post ? ($post['subheadline']??'') : $cfg['desc'];
    render_head($page_title, $current_section, $page_desc);
?>
<div class="container">
  <a href="/?section=<?= e($current_section) ?>" class="back-link">← Back to <?= $cfg['title'] ?></a>
  <?php if ($post): ?>
  <div class="article-full">
    <div class="kicker" style="margin-top:20px;">★ <?= e($post['kicker']??$cfg['label']) ?></div>
    <?= tag_span($post['tag']??$current_section) ?>
    <h1><?= e($post['headline']) ?></h1>
    <div class="dek"><?= e($post['subheadline']??'') ?></div>
    <div class="body"><?= $post['body_html'] ?></div>
    <div class="dumb-rating"><?= skulls((int)($post['dumb_rating']??3)) ?> &nbsp;Dumb Rating: <?= (int)($post['dumb_rating']??3) ?>/5 — <?= e($post['dumb_rating_label']??'') ?></div>
    <?php if(!empty($post['source_name'])||!empty($post['source_url'])): ?>
    <div class="source-link">Source: <?php if(!empty($post['source_url'])): ?><a href="<?= e($post['source_url']) ?>" target="_blank" rel="noopener"><?= e($post['source_name']??$post['source_url']) ?></a><?php else: ?><strong><?= e($post['source_name']) ?></strong><?php endif; ?></div>
    <?php endif; ?>
    <div class="byline">Published <?= e($post['date']??'') ?></div>

    <?php if(!empty($post['glossary_term'])): ?>
    <div class="opinion-box" style="margin-top:32px;">
      <div class="label">★ From the Glossary</div>
      <blockquote>"<?= e($post['glossary_term']) ?>"</blockquote>
      <div class="attribution"><?= e($post['glossary_definition']??'') ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="empty-notice">Article not found.</div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
</body></html>

<?php
// ═══════════════════════════════════════════════════════════════
//  SECTION PAGE (with pagination)
// ═══════════════════════════════════════════════════════════════
} elseif ($current_section) {
    $cfg = $section_config[$current_section];

    // Filter posts to this section
    $section_posts = array_values(array_filter($all_posts, function($p) use ($current_section) {
        $s = $p['section'] ?? $p['tag'] ?? 'vc';
        return $s === $current_section || ($p['tag']??'') === $current_section;
    }));

    $total_posts = count($section_posts);
    $total_pages = max(1, (int)ceil($total_posts / POSTS_PER_PAGE));
    $current_page = min($current_page, $total_pages);

    // Hero always = most recent (page 1 only)
    $hero         = $section_posts[0] ?? null;
    $sidebar      = array_slice($section_posts, 1, 2);
    // Cards = paginated, starting from post index 3
    $card_offset  = 3 + (($current_page - 1) * POSTS_PER_PAGE);
    $cards        = array_slice($section_posts, $card_offset, POSTS_PER_PAGE);

    render_head($cfg['title'], $current_section, $cfg['desc']);
?>
<div class="container">
  <div class="section-banner">
    <div class="section-label"><?= $cfg['label'] ?></div>
    <h1><?= $cfg['title'] ?></h1>
    <p><?= $cfg['desc'] ?></p>
  </div>

  <?php if ($hero && $current_page === 1): ?>
  <!-- Hero — full article, page 1 only -->
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
      <div class="byline">Published <?= e($hero['date']??'') ?></div>
    </div>
    <div class="hero-sidebar">
      <?php if($sidebar): ?>
      <div class="section-label">Also in <?= $cfg['title'] ?></div>
      <?php foreach($sidebar as $s): ?>
      <div class="side-story">
        <?= tag_span($s['tag']??$current_section) ?>
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

  <?php elseif(!$hero): ?>
  <div class="empty-notice">📰 No posts in <?= $cfg['title'] ?> yet — check back after the next bot run (Tue/Fri).</div>
  <?php endif; ?>

  <!-- Ad mid-page -->
  <div class="ad-wrap" style="margin:8px 0;">
    <span class="ad-label">Advertisement</span>
    <ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins>
    <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
  </div>

  <?php if($cards): ?>
  <div class="section-posts">
    <div class="section-label">
      <?= $current_page > 1 ? 'Page '.$current_page.' — ' : 'More from ' ?>
      <?= $cfg['title'] ?>
    </div>
    <div class="posts-grid">
      <?php foreach($cards as $p): ?>
      <div class="article-card">
        <?= tag_span($p['tag']??$current_section) ?>
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

  <!-- PAGINATION -->
  <?php if($total_pages > 1): ?>
  <div class="pagination">
    <?php if($current_page > 1): ?>
      <a href="/?section=<?= $current_section ?>&page=<?= $current_page-1 ?>">← Previous</a>
    <?php else: ?>
      <span class="disabled">← Previous</span>
    <?php endif; ?>

    <?php for($pg=1; $pg<=$total_pages; $pg++): ?>
      <?php if($pg === $current_page): ?>
        <span class="current"><?= $pg ?></span>
      <?php else: ?>
        <a href="/?section=<?= $current_section ?>&page=<?= $pg ?>"><?= $pg ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if($current_page < $total_pages): ?>
      <a href="/?section=<?= $current_section ?>&page=<?= $current_page+1 ?>">Next →</a>
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
    render_head('DumbCapital — VC & M&A News, Unfiltered', '');
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
    <div class="byline">Published <?= e($hero['date']??'') ?></div>
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

<div class="ad-wrap" style="margin:8px 0;">
  <span class="ad-label">Advertisement</span>
  <ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins>
  <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
</div>

<?php foreach($section_config as $sec_key => $sec_cfg):
    $posts = $by_section[$sec_key] ?? [];
    if (empty($posts)) continue;
    $main = $posts[0];
    $subs = array_slice($posts, 1, 2);
?>
<div class="home-section">
  <div class="home-section-header">
    <h2><?= $sec_cfg['label'] ?></h2>
    <a href="/?section=<?= $sec_key ?>">More <?= $sec_cfg['label'] ?> →</a>
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
