<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital — Main Site
//  Reads posts/*.json and renders the full newspaper site.
// ─────────────────────────────────────────────────────────────
define('POSTS_DIR', __DIR__ . '/../posts/');

function load_published(): array {
    $posts = [];
    foreach (glob(POSTS_DIR . '*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d && !empty($d['published'])) $posts[] = $d;
    }
    usort($posts, fn($a,$b) => strcmp($b['date']??'',$a['date']??''));
    return $posts;
}

$all = load_published();

$sections = [
    'vc'      => ['label' => 'VC Deals',       'anchor' => 'vc',      'posts' => []],
    'ma'      => ['label' => 'M&amp;A Morgue',  'anchor' => 'ma',      'posts' => []],
    'pe'      => ['label' => 'PE Corner',       'anchor' => 'pe',      'posts' => []],
    'unicorn' => ['label' => 'Unicorn Watch',   'anchor' => 'unicorn', 'posts' => []],
    'opinion' => ['label' => 'Opinion',         'anchor' => 'opinion', 'posts' => []],
];
foreach ($all as $p) {
    $s = $p['section'] ?? $p['tag'] ?? 'vc';
    if (!isset($sections[$s])) $s = 'vc';
    $sections[$s]['posts'][] = $p;
}

$hero    = $all[0] ?? null;
$sidebar = array_slice($all, 1, 2);
$ticker  = array_merge(array_slice($all,0,8), array_slice($all,0,8));

function tag_span(string $tag): string {
    $m = ['vc'=>'VC','ma'=>'M&amp;A','pe'=>'PE','flop'=>'Flop','unicorn'=>'Unicorn','opinion'=>'Opinion'];
    return '<span class="tag tag-'.htmlspecialchars($tag).'">'.($m[$tag]??strtoupper($tag)).'</span>';
}
function skulls(int $n): string { return str_repeat('💀', max(1,min(5,$n))); }
function e(string $s): string   { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$today = date('l, F j, Y');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DumbCapital — VC &amp; M&amp;A News, Unfiltered</title>
<meta name="description" content="Satirical North American VC and M&A news. We call out bad deals so you don't have to.">
<link rel="canonical" href="https://www.dumbcapital.com/">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">

<!-- Google AdSense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6066516193533329" crossorigin="anonymous"></script>

<style>
  :root{--ink:#0e0e0e;--paper:#f5f2eb;--accent:#c0392b;--muted:#6b6560;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'IBM Plex Sans',sans-serif;font-size:16px;line-height:1.6;}

  /* ADS — subtle, capped size, never disruptive */
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

  /* LAYOUT */
  .container{max-width:1200px;margin:0 auto;padding:0 40px;}
  .section-label{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.3em;text-transform:uppercase;color:var(--accent);border-bottom:1px solid var(--accent);display:inline-block;padding-bottom:2px;margin-bottom:18px;}

  /* HERO */
  .hero{padding:40px 0;border-bottom:2px solid var(--ink);display:grid;grid-template-columns:3fr 2fr;gap:0;}
  .hero-main{border-right:1px solid var(--ink);padding-right:36px;}
  .kicker{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:10px;}
  .hero-main h1{font-family:'Playfair Display',serif;font-size:48px;font-weight:900;line-height:1.05;letter-spacing:-1px;margin-bottom:18px;}
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

  /* SECTIONS */
  .section-block{padding:40px 0;border-bottom:1px solid var(--ink);}
  .section-block h2{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;margin-bottom:4px;}
  .posts-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:24px;}
  .article-card{border-top:2px solid var(--ink);padding-top:16px;}
  .article-card h3{font-family:'Playfair Display',serif;font-size:21px;font-weight:700;line-height:1.2;margin-bottom:8px;}
  .article-card p{font-size:13px;color:var(--muted);line-height:1.55;margin-bottom:8px;}
  .article-card .dumb-rating{font-size:10px;padding:5px 8px;margin:8px 0;}

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

  /* FOOTER */
  footer{background:var(--ink);color:var(--paper);padding:44px 40px;text-align:center;}
  .footer-logo{font-family:'Playfair Display',serif;font-size:38px;font-weight:900;letter-spacing:-1px;margin-bottom:10px;}
  .footer-logo em{color:var(--accent);font-style:italic;}
  footer p{font-family:'IBM Plex Mono',monospace;font-size:11px;color:#777;line-height:1.9;}
  .footer-nav{display:flex;justify-content:center;gap:28px;margin:20px 0 0;flex-wrap:wrap;}
  .footer-nav a{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:#888;text-decoration:none;}
  .footer-nav a:hover{color:var(--accent);}

  .empty-notice{text-align:center;padding:80px 32px;color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:13px;}

  @media(max-width:900px){
    .masthead{grid-template-columns:1fr;text-align:center;padding:20px;}
    .masthead-left,.masthead-right{display:none;}
    .logo{font-size:44px;}
    .hero{grid-template-columns:1fr;}
    .hero-main{border-right:none;border-bottom:1px solid var(--ink);padding-right:0;padding-bottom:30px;margin-bottom:30px;}
    .hero-main h1{font-size:32px;}
    .hero-sidebar{padding-left:0;}
    .posts-grid{grid-template-columns:1fr;}
    .about-strip{flex-direction:column;gap:12px;}
    .container{padding:0 20px;}
    .ad-wrap ins{max-width:320px;max-height:50px;}
  }
</style>
</head>
<body>

<!-- AD 1: Above masthead — thin leaderboard, unobtrusive -->
<div class="ad-wrap">
  <span class="ad-label">Advertisement</span>
  <ins class="adsbygoogle"
       style="display:inline-block;width:728px;height:90px"
       data-ad-client="ca-pub-6066516193533329"
       data-ad-slot="auto"
       data-ad-format="horizontal"
       data-full-width-responsive="false"></ins>
  <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
</div>

<!-- TICKER -->
<div class="ticker-wrap"><div class="ticker-inner">
<?php if($ticker): foreach($ticker as $p): ?>
  <span><?= e(strtoupper($p['headline']??'')) ?></span>
<?php endforeach; else: ?>
  <span>DUMBCAPITAL — SATIRICAL VC &amp; M&amp;A NEWS — NORTH AMERICA</span>
  <span>DUMBCAPITAL — SATIRICAL VC &amp; M&amp;A NEWS — NORTH AMERICA</span>
<?php endif; ?>
</div></div>

<!-- MASTHEAD -->
<div class="masthead">
  <div class="masthead-left">Est. when term sheets<br>outnumbered good ideas<br>www.dumbcapital.com</div>
  <div class="masthead-center">
    <a class="logo" href="/">Dumb<em>Capital</em></a>
    <div class="tagline">North American VC &amp; M&amp;A News — Unfiltered, Unimpressed, Unprofitable</div>
  </div>
  <div class="masthead-right">North America Edition<br><?= $today ?><br>Free (Like Your Equity)</div>
</div>

<!-- NAV -->
<nav>
  <a href="#vc">VC Deals</a>
  <a href="#ma">M&amp;A Morgue</a>
  <a href="#pe">PE Corner</a>
  <a href="#unicorn">Unicorn Watch</a>
  <a href="#opinion">Opinion</a>
  <a href="/admin/">Admin</a>
</nav>

<div class="container">

<?php if ($hero): ?>
<!-- HERO -->
<div class="hero">
  <div class="hero-main">
    <div class="kicker">★ <?= e($hero['kicker']??'Deal of the Week') ?></div>
    <h1><?= e($hero['headline']) ?></h1>
    <div class="dek"><?= e($hero['subheadline']??'') ?></div>
    <div class="body"><?= $hero['body_html'] ?></div>
    <div class="dumb-rating"><?= skulls((int)($hero['dumb_rating']??3)) ?> &nbsp;Dumb Rating: <?= (int)($hero['dumb_rating']??3) ?>/5 — <?= e($hero['dumb_rating_label']??'') ?></div>
    <?php if (!empty($hero['source_name']) || !empty($hero['source_url'])): ?>
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
    <?php if(empty($sidebar)): ?><p style="font-size:13px;color:var(--muted);">More stories coming soon.</p><?php endif; ?>
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

<!-- AD 2: Mid-page, between hero and sections -->
<div class="ad-wrap" style="margin:8px 0;">
  <span class="ad-label">Advertisement</span>
  <ins class="adsbygoogle"
       style="display:inline-block;width:728px;height:90px"
       data-ad-client="ca-pub-6066516193533329"
       data-ad-slot="auto"
       data-ad-format="horizontal"
       data-full-width-responsive="false"></ins>
  <script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>
</div>

<!-- SECTIONS -->
<?php foreach($sections as $key => $sec): ?>
<?php if(empty($sec['posts'])) continue; ?>
<div class="section-block" id="<?= $sec['anchor'] ?>">
  <div class="section-label"><?= $sec['label'] ?></div>
  <div class="posts-grid">
    <?php foreach(array_slice($sec['posts'],0,4) as $p): ?>
    <div class="article-card">
      <?= tag_span($p['tag']??$key) ?>
      <h3><?= e($p['headline']) ?></h3>
      <p><?= e($p['subheadline']??'') ?></p>
      <div class="dumb-rating"><?= skulls((int)($p['dumb_rating']??3)) ?> &nbsp;<?= (int)($p['dumb_rating']??3) ?>/5 — <?= e($p['dumb_rating_label']??'') ?></div>
      <div class="byline"><?= e($p['date']??'') ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- ABOUT -->
<div class="about-strip">
  <div class="big-d">D</div>
  <div>
    <h3>About DumbCapital</h3>
    <p>DumbCapital covers venture capital and M&amp;A in North America with the skepticism these markets have long deserved and rarely received. We are not impressed by large numbers. We are not moved by press releases. All articles are satirical commentary based on real, publicly reported deals. Nothing here is financial advice.</p>
  </div>
</div>

</div><!-- /container -->

<footer>
  <div class="footer-logo">Dumb<em>Capital</em></div>
  <p>North American VC &amp; M&amp;A News &nbsp;·&nbsp; www.dumbcapital.com<br>
  &copy; DumbCapital <?= date('Y') ?>. All articles are satirical commentary on real, publicly reported news.<br>
  Nothing published here constitutes financial, legal, or investment advice.</p>
  <div class="footer-nav">
    <a href="#vc">VC Deals</a><a href="#ma">M&amp;A Morgue</a><a href="#pe">PE Corner</a>
    <a href="#unicorn">Unicorn Watch</a><a href="#opinion">Opinion</a><a href="/admin/">Admin</a>
  </div>
</footer>
</body>
</html>
