<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital — Main Router
//  ?section=vc|ma|pe|unicorn|opinion shows section page
//  No ?section param = homepage (latest from all sections)
// ─────────────────────────────────────────────────────────────
define('POSTS_DIR', __DIR__ . '/posts/');

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
if ($current_section && !isset($section_config[$current_section])) {
    $current_section = '';
}

$all_posts = load_published();

// ── SHARED CSS & HEADER ───────────────────────────────────────
$today = date('l, F j, Y');

// Build ticker from latest posts
$ticker_posts = array_slice($all_posts, 0, 8);

function render_head(string $title, string $section_key): void {
    global $today;
    $active = $section_key;
    $sections = [
        ''        => 'Home',
        'vc'      => 'VC Deals',
        'ma'      => 'M&amp;A Morgue',
        'pe'      => 'PE Corner',
        'unicorn' => 'Unicorn Watch',
        'opinion' => 'Opinion',
    ];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — DumbCapital</title>
<meta name="description" content="Satirical North American VC and M&A news. We call out bad deals so you don't have to.">
<link rel="canonical" href="https://www.dumbcapital.com/">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6066516193533329" crossorigin="anonymous"></script>
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
  nav a:hover{background:var(--accent);}
  nav a.active{background:var(--accent);}

  /* LAYOUT */
  .container{max-width:1200px;margin:0 auto;padding:0 40px;}
  .section-label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:var(--accent);border-bottom:1px solid var(--accent);display:inline-block;padding-bottom:2px;margin-bottom:18px;}

  /* SECTION BANNER */
  .section-banner{padding:28px 0 20px;border-bottom:2px solid var(--ink);margin-bottom:0;}
  .section-banner h1{font-family:'Playfair Display',serif;font-size:42px;font-weight:900;letter-spacing:-1px;margin-bottom:6px;}
  .section-banner p{font-size:14px;color:var(--muted);font-style:italic;font-family:'IBM Plex Sans',sans-serif;}

  /* HERO */
  .hero{padding:40px 0;border-bottom:2px solid var(--ink);display:grid;grid-template-columns:3fr 2fr;gap:0;}
  .hero-main{border-right:1px solid var(--ink);padding-right:36px;}
  .kicker{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:10px;}
  .hero-main h1{font-family:'Playfair Display',serif;font-size:48px;font-weight:900;line-height:1.05;letter-spacing:-1px;margin-bottom:18px;}
  .hero-main h2{font-family:'Playfair Display',serif;font-size:36px;font-weight:900;line-height:1.1;letter-spacing:-0.5px;margin-bottom:14px;}
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
  .side-story p{font-size:13px;color:var(--muted);line-height:1.55;}

  /* TAGS */
  .tag{display:inline-block;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.15em;text-transform:uppercase;padding:2px 8px;margin-bottom:8px;border:1px solid currentColor;}
  .tag-ma{color:#1a1a2e;border-color:#1a1a2e;}
  .tag-vc{color:#3a5a8a;border-color:#3a5a8a;}
  .tag-pe{color:#555;border-color:#555;}
  .tag-flop{background:var(--accent);color:#fff;border-color:var(--accent);}
  .tag-opinion{background:var(--ink);color:var(--paper);border-color:var(--ink);}
  .tag-unicorn{color:#7a4a9a;border-color:#7a4a9a;}

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

  /* SECTION PAGE GRID */
  .section-posts{padding:40px 0;}
  .posts-grid{display:grid;grid-template-columns:1fr 1fr;gap:36px;}
  .article-card{border-top:2px solid var(--ink);padding-top:16px;}
  .article-card h2{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;line-height:1.2;margin-bottom:8px;}
  .article-card p{font-size:13px;color:var(--muted);line-height:1.55;margin-bottom:8px;}
  .article-card .dumb-rating{font-size:10px;padding:5px 8px;margin:8px 0;}
  .article-card .body p{font-size:14px;color:#333;margin-bottom:10px;}

  /* OPINION BOX */
  .opinion-box{background:var(--ink);color:var(--paper);padding:36px 40px;margin:40px 0;}
  .opinion-box .label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:var(--accent);margin-bottom:14px;}
  .opinion-box blockquote{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;font-style:italic;line-height:1.3;margin-bottom:16px;max-width:820px;}
  .opinion-box .attribution{font-family:'IBM Plex Mono',monospace;font-size:11px;color:#888;}

  /* ABOUT */
  .about-strip{background:#ede9e0;border:1px solid #ccc;padding:28px 36px;margin:40px 0;display:flex;gap:32px;align-items:flex-start;}
  .big-d{font-family:'Playfair Display',serif;font-size:80px;font-weight:900;color:var(--accent);line-height:.85;flex-shrink:0;}
  .about-strip h3{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;margin-bottom:8px;}
  .about-strip p{font-size:14px;color:var(--muted);line-height:1.6;}

  /* EMPTY */
  .empty-notice{text-align:center;padding:80px 32px;color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:13px;border:1px dashed #ccc;margin:40px 0;}

  /* FOOTER */
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
  }
</style>
</head>
<body>
<?php
    // Ad 1
    echo '<div class="ad-wrap"><span class="ad-label">Advertisement</span>';
    echo '<ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins>';
    echo '<script>(adsbygoogle=window.adsbygoogle||[]).push({});</script></div>';

    // Ticker
    global $ticker_posts;
    echo '<div class="ticker-wrap"><div class="ticker-inner">';
    if ($ticker_posts) {
        $items = array_merge($ticker_posts, $ticker_posts);
        foreach ($items as $p) echo '<span>' . htmlspecialchars(strtoupper($p['headline']??''), ENT_QUOTES) . '</span>';
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
  <a href="/" class="<?= $active===''?'active':'' ?>">Home</a>
  <a href="/?section=vc" class="<?= $active==='vc'?'active':'' ?>">VC Deals</a>
  <a href="/?section=ma" class="<?= $active==='ma'?'active':'' ?>">M&amp;A Morgue</a>
  <a href="/?section=pe" class="<?= $active==='pe'?'active':'' ?>">PE Corner</a>
  <a href="/?section=unicorn" class="<?= $active==='unicorn'?'active':'' ?>">Unicorn Watch</a>
  <a href="/?section=opinion" class="<?= $active==='opinion'?'active':'' ?>">Opinion</a>
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
//  SECTION PAGE
// ═══════════════════════════════════════════════════════════════
if ($current_section) {
    $cfg = $section_config[$current_section];

    // Filter posts to this section only
    $section_posts = array_filter($all_posts, function($p) use ($current_section) {
        $s = $p['section'] ?? $p['tag'] ?? 'vc';
        // Also catch tag matches (e.g. tag=flop but section=vc)
        return $s === $current_section || ($p['tag']??'') === $current_section;
    });
    $section_posts = array_values($section_posts);

    $hero    = $section_posts[0] ?? null;
    $sidebar = array_slice($section_posts, 1, 2);
    $rest    = array_slice($section_posts, 3);

    render_head($cfg['title'], $current_section);
?>

<div class="container">
  <!-- Section Banner -->
  <div class="section-banner">
    <div class="section-label"><?= $cfg['label'] ?></div>
    <h1><?= $cfg['title'] ?></h1>
    <p><?= $cfg['desc'] ?></p>
  </div>

  <?php if ($hero): ?>
  <!-- Hero -->
  <div class="hero">
    <div class="hero-main">
      <div class="kicker">★ <?= e($hero['kicker']??'Latest') ?></div>
      <h2><?= e($hero['headline']) ?></h2>
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
        <h3><?= e($s['headline']) ?></h3>
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

  <!-- Ad mid-page -->
  <div class="ad-wrap" style="margin:8px 0;">
    <span class="ad-label">Advertisement</span>
    <ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins>
    <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
  </div>

  <?php if($rest): ?>
  <div class="section-posts">
    <div class="section-label">More from <?= $cfg['title'] ?></div>
    <div class="posts-grid">
      <?php foreach($rest as $p): ?>
      <div class="article-card">
        <?= tag_span($p['tag']??$current_section) ?>
        <h2><?= e($p['headline']) ?></h2>
        <p><?= e($p['subheadline']??'') ?></p>
        <div class="dumb-rating"><?= skulls((int)($p['dumb_rating']??3)) ?> &nbsp;<?= (int)($p['dumb_rating']??3) ?>/5 — <?= e($p['dumb_rating_label']??'') ?></div>
        <div class="byline"><?= e($p['date']??'') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-notice">📰 No posts in <?= $cfg['title'] ?> yet — check back after the next bot run (Tue/Fri).</div>
  <?php endif; ?>

</div>

<?php render_footer(); ?>
</body>
</html>

<?php
// ═══════════════════════════════════════════════════════════════
//  HOMEPAGE
// ═══════════════════════════════════════════════════════════════
} else {
    // Sort posts into sections
    $by_section = [];
    foreach ($all_posts as $p) {
        $s = $p['section'] ?? $p['tag'] ?? 'vc';
        if (!isset($section_config[$s])) $s = 'vc';
        $by_section[$s][] = $p;
    }

    // Hero = most recent post across all sections
    $hero    = $all_posts[0] ?? null;
    $sidebar = array_slice($all_posts, 1, 3);

    render_head('DumbCapital — VC & M&A News, Unfiltered', '');
?>

<div class="container">

<?php if($hero): ?>
<!-- HERO: Latest post -->
<div class="hero" style="padding:40px 0;border-bottom:2px solid var(--ink);">
  <div class="hero-main">
    <div class="kicker">★ <?= e($hero['kicker']??'Latest') ?></div>
    <h1><?= e($hero['headline']) ?></h1>
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
      <h3><?= e($s['headline']) ?></h3>
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

<!-- Ad mid-page -->
<div class="ad-wrap" style="margin:8px 0;">
  <span class="ad-label">Advertisement</span>
  <ins class="adsbygoogle" style="display:inline-block;width:728px;height:90px" data-ad-client="ca-pub-6066516193533329" data-ad-slot="auto" data-ad-format="horizontal" data-full-width-responsive="false"></ins>
  <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
</div>

<!-- SECTION STRIPS: one per section -->
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
      <h3><?= e($main['headline']) ?></h3>
      <p><?= e($main['subheadline']??'') ?></p>
      <div class="dumb-rating" style="font-size:10px;padding:5px 8px;"><?= skulls((int)($main['dumb_rating']??3)) ?> <?= (int)($main['dumb_rating']??3) ?>/5 — <?= e($main['dumb_rating_label']??'') ?></div>
      <div class="byline"><?= e($main['date']??'') ?></div>
    </div>
    <?php foreach($subs as $sub): ?>
    <div class="home-post-side">
      <?= tag_span($sub['tag']??$sec_key) ?>
      <h3><?= e($sub['headline']) ?></h3>
      <p><?= e($sub['subheadline']??'') ?></p>
      <div class="byline"><?= e($sub['date']??'') ?></div>
    </div>
    <?php endforeach; ?>
    <?php if(count($subs) < 2): // pad empty columns ?>
    <?php for($i=count($subs);$i<2;$i++): ?>
    <div class="home-post-side"></div>
    <?php endfor; ?>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

</div>

<?php
    render_footer();
}
?>
</body>
</html>
