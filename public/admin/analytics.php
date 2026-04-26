<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital Admin — Analytics
//  Access at: www.dumbcapital.com/admin/analytics.php
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
session_start();

$authed = isset($_SESSION['auth']) && $_SESSION['auth'] === SESSION_SECRET;
if (!$authed) {
    header('Location: /admin/');
    exit;
}

define('ANALYTICS_FILE', POSTS_DIR . 'analytics.json');
define('POSTS_DIR_PATH', POSTS_DIR);

// Load analytics data
$analytics = [];
if (file_exists(ANALYTICS_FILE)) {
    $analytics = json_decode(file_get_contents(ANALYTICS_FILE), true) ?? [];
}

// Load post headlines for display
$post_headlines = [];
foreach (glob(POSTS_DIR . '*.json') as $f) {
    if (basename($f) === 'analytics.json' || basename($f) === 'seen_cache.json') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d && isset($d['slug'])) {
        $sec = $d['section'] ?? $d['tag'] ?? 'vc';
        $key = $sec . '/' . $d['slug'];
        $post_headlines[$key] = $d['headline'] ?? $key;
    }
}

// Get all months across all pages
$all_months = [];
foreach ($analytics as $page_key => $pdata) {
    foreach (array_keys($pdata['months'] ?? []) as $month) {
        $all_months[$month] = true;
    }
}
krsort($all_months);
$all_months = array_keys($all_months);

// Selected month filter
$selected_month = $_GET['month'] ?? ($all_months[0] ?? date('Y-m'));

// Calculate totals for selected month
$page_totals = [];
$grand_total = 0;
foreach ($analytics as $page_key => $pdata) {
    $month_data = $pdata['months'][$selected_month] ?? null;
    if ($month_data) {
        $total = $month_data['total'] ?? 0;
        $page_totals[$page_key] = [
            'label'   => $page_key === '__home__' ? 'Homepage' : ($post_headlines[$page_key] ?? $page_key),
            'section' => $pdata['section'] ?? '',
            'slug'    => $pdata['slug'] ?? '',
            'page'    => $pdata['page'] ?? '',
            'total'   => $total,
            'days'    => $month_data['days'] ?? [],
        ];
        $grand_total += $total;
    }
}
arsort($page_totals);

// All-time totals
$alltime_totals = [];
$alltime_grand = 0;
foreach ($analytics as $page_key => $pdata) {
    $total = 0;
    foreach ($pdata['months'] ?? [] as $mdata) {
        $total += $mdata['total'] ?? 0;
    }
    $alltime_totals[$page_key] = $total;
    $alltime_grand += $total;
}
arsort($alltime_totals);

// Month label formatter
function month_label(string $ym): string {
    $dt = DateTime::createFromFormat('Y-m', $ym);
    return $dt ? $dt->format('F Y') : $ym;
}

function section_color(string $sec): string {
    $colors = ['vc'=>'#3a5a8a','ma'=>'#1a1a2e','pe'=>'#555','unicorn'=>'#7a4a9a','opinion'=>'#0e0e0e'];
    return $colors[$sec] ?? '#888';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DumbCapital Analytics</title>
<style>
  :root{--ink:#0e0e0e;--paper:#f5f2eb;--accent:#c0392b;--muted:#6b6560;--border:#ddd;--green:#2a7a3b;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Segoe UI',sans-serif;background:#f0ede6;color:var(--ink);font-size:15px;}
  .admin-header{background:var(--ink);color:#fff;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;}
  .admin-header .logo{font-family:Georgia,serif;font-size:22px;font-weight:700;}
  .admin-header .logo em{color:var(--accent);font-style:italic;}
  .header-nav{display:flex;align-items:center;gap:16px;}
  .header-nav a{color:#888;font-size:12px;text-decoration:none;}
  .header-nav a:hover{color:var(--accent);}
  .header-nav a.active{color:#fff;}
  .wrap{max-width:1100px;margin:32px auto;padding:0 24px;}

  /* STATS CARDS */
  .stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px;}
  .stat-card{background:#fff;border:1px solid var(--border);padding:20px 24px;}
  .stat-card .label{font-family:'Courier New',monospace;font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
  .stat-card .value{font-family:Georgia,serif;font-size:36px;font-weight:700;color:var(--ink);}
  .stat-card .sub{font-size:12px;color:var(--muted);margin-top:4px;}

  /* MONTH SELECTOR */
  .controls{display:flex;align-items:center;gap:16px;margin-bottom:24px;}
  .controls label{font-family:'Courier New',monospace;font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);}
  .controls select{padding:8px 12px;border:1px solid var(--border);font-size:13px;font-family:inherit;background:#fff;cursor:pointer;}

  /* SECTION HEADERS */
  .section-title{font-family:Georgia,serif;font-size:22px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--ink);}

  /* TABLE */
  .analytics-table{width:100%;border-collapse:collapse;background:#fff;margin-bottom:32px;}
  .analytics-table th{text-align:left;padding:10px 14px;background:var(--ink);color:#fff;font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-family:'Courier New',monospace;}
  .analytics-table td{padding:0;border-bottom:1px solid var(--border);vertical-align:top;}
  .analytics-table tr:last-child td{border-bottom:none;}

  /* EXPANDABLE ROW */
  .row-main{display:flex;align-items:center;padding:12px 14px;cursor:pointer;transition:background .1s;}
  .row-main:hover{background:#faf8f4;}
  .expand-icon{font-size:10px;color:var(--muted);margin-right:10px;transition:transform .2s;flex-shrink:0;}
  .expanded .expand-icon{transform:rotate(90deg);}
  .row-headline{flex:1;font-size:14px;font-weight:600;line-height:1.3;}
  .row-headline a{color:var(--ink);text-decoration:none;}
  .row-headline a:hover{color:var(--accent);}
  .row-section{flex-shrink:0;width:90px;}
  .row-views{flex-shrink:0;width:80px;text-align:right;font-family:'Courier New',monospace;font-size:14px;font-weight:700;}
  .row-bar-wrap{flex-shrink:0;width:120px;padding:0 14px;}
  .row-bar{height:6px;background:var(--accent);border-radius:3px;transition:width .3s;}

  /* DAILY BREAKDOWN */
  .daily-breakdown{display:none;background:#f9f7f2;border-top:1px solid #eee;padding:16px 14px 16px 34px;}
  .daily-breakdown.open{display:block;}
  .daily-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;}
  .day-cell{background:#fff;border:1px solid var(--border);padding:8px 10px;}
  .day-cell .day-date{font-family:'Courier New',monospace;font-size:10px;color:var(--muted);margin-bottom:3px;}
  .day-cell .day-count{font-family:Georgia,serif;font-size:20px;font-weight:700;}
  .day-cell .day-label{font-size:10px;color:var(--muted);}

  /* EMPTY */
  .empty{text-align:center;padding:60px;color:var(--muted);font-family:'Courier New',monospace;font-size:13px;background:#fff;border:1px dashed var(--border);}

  @media(max-width:768px){
    .stat-cards{grid-template-columns:1fr 1fr;}
    .row-bar-wrap{display:none;}
    .wrap{padding:0 16px;}
  }
</style>
</head>
<body>

<div class="admin-header">
  <div>
    <span class="logo">Dumb<em>Capital</em></span>
    <span style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#888;margin-left:12px;">Admin</span>
  </div>
  <div class="header-nav">
    <a href="/admin/">← Posts</a>
    <a href="/admin/analytics.php" class="active">Analytics</a>
    <a href="/" target="_blank">↗ View Site</a>
    <a href="/admin/?logout=1" style="color:#c0392b;">Sign Out</a>
  </div>
</div>

<div class="wrap">

  <!-- STAT CARDS -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="label">Total Views (All Time)</div>
      <div class="value"><?= number_format($alltime_grand) ?></div>
      <div class="sub">Across all pages</div>
    </div>
    <div class="stat-card">
      <div class="label">Views This Month</div>
      <div class="value"><?= number_format($grand_total) ?></div>
      <div class="sub"><?= month_label($selected_month) ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Pages Tracked</div>
      <div class="value"><?= count($analytics) ?></div>
      <div class="sub">Homepage + articles</div>
    </div>
    <div class="stat-card">
      <div class="label">Months of Data</div>
      <div class="value"><?= count($all_months) ?></div>
      <div class="sub"><?= !empty($all_months) ? month_label(end($all_months)) . ' – ' . month_label($all_months[0]) : 'No data yet' ?></div>
    </div>
  </div>

  <?php if (empty($analytics)): ?>
  <div class="empty">
    📊 No view data yet.<br><br>
    Views are recorded when real visitors spend time on your pages.<br>
    Check back after some traffic comes in.
  </div>
  <?php else: ?>

  <!-- MONTH SELECTOR -->
  <div class="controls">
    <label>Showing month:</label>
    <select onchange="window.location='?month='+this.value">
      <?php foreach ($all_months as $m): ?>
      <option value="<?= $m ?>" <?= $m===$selected_month?'selected':'' ?>><?= month_label($m) ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:12px;color:var(--muted);"><?= number_format($grand_total) ?> total views in <?= month_label($selected_month) ?></span>
  </div>

  <!-- MONTHLY TABLE -->
  <div class="section-title"><?= month_label($selected_month) ?> — Views by Page</div>

  <?php if (empty($page_totals)): ?>
  <div class="empty">No views recorded for <?= month_label($selected_month) ?>.</div>
  <?php else: ?>

  <table class="analytics-table">
    <thead>
      <tr>
        <th style="width:32px;"></th>
        <th>Page / Article</th>
        <th style="width:90px;">Section</th>
        <th style="width:80px;text-align:right;">Views</th>
        <th style="width:148px;">Relative</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $max_views = max(array_column($page_totals, 'total'));
    $row_idx = 0;
    foreach ($page_totals as $page_key => $pdata):
        $bar_pct = $max_views > 0 ? round($pdata['total'] / $max_views * 100) : 0;
        $sec = $pdata['section'];
        $sec_color = section_color($sec);
        $article_url = $page_key === '__home__' ? '/' : '/' . $sec . '/' . $pdata['slug'] . '/';

        // Sort days descending
        $days = $pdata['days'];
        krsort($days);
        $row_id = 'row_' . $row_idx++;
    ?>
    <tr id="<?= $row_id ?>_wrap">
      <td colspan="5" style="padding:0;">
        <div class="row-main" onclick="toggleRow('<?= $row_id ?>')">
          <span class="expand-icon" id="<?= $row_id ?>_icon">▶</span>
          <div class="row-headline">
            <?php if ($page_key === '__home__'): ?>
              🏠 Homepage
            <?php else: ?>
              <a href="<?= e($article_url) ?>" target="_blank" onclick="event.stopPropagation()">
                <?= e($pdata['label']) ?>
              </a>
            <?php endif; ?>
          </div>
          <div class="row-section">
            <?php if ($sec): ?>
            <span style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:<?= $sec_color ?>;font-family:'Courier New',monospace;"><?= strtoupper($sec) ?></span>
            <?php endif; ?>
          </div>
          <div class="row-views"><?= number_format($pdata['total']) ?></div>
          <div class="row-bar-wrap">
            <div class="row-bar" style="width:<?= $bar_pct ?>%;background:<?= $sec_color ?: 'var(--accent)' ?>;"></div>
          </div>
        </div>

        <!-- Daily breakdown -->
        <div class="daily-breakdown" id="<?= $row_id ?>_detail">
          <?php if (empty($days)): ?>
          <p style="font-size:12px;color:var(--muted);">No daily data available.</p>
          <?php else: ?>
          <div style="font-family:'Courier New',monospace;font-size:10px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:10px;">Daily breakdown — click dates to expand</div>
          <div class="daily-grid">
            <?php foreach ($days as $day => $count): ?>
            <?php
              $dt = DateTime::createFromFormat('Y-m-d', $day);
              $day_label = $dt ? $dt->format('M j') : $day;
              $day_name  = $dt ? $dt->format('D') : '';
            ?>
            <div class="day-cell">
              <div class="day-date"><?= $day_name ?>, <?= $day_label ?></div>
              <div class="day-count"><?= number_format($count) ?></div>
              <div class="day-label"><?= $count === 1 ? 'view' : 'views' ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ALL TIME TABLE -->
  <div class="section-title" style="margin-top:16px;">All-Time Top Pages</div>
  <table class="analytics-table">
    <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th>Page / Article</th>
        <th style="width:90px;">Section</th>
        <th style="width:100px;text-align:right;">Total Views</th>
        <th style="width:148px;">Relative</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $alltime_max = max($alltime_totals ?: [1]);
    $rank = 1;
    foreach ($alltime_totals as $page_key => $total):
        if ($total === 0) continue;
        $pdata = $analytics[$page_key] ?? [];
        $sec = $pdata['section'] ?? '';
        $slug = $pdata['slug'] ?? '';
        $sec_color = section_color($sec);
        $article_url = $page_key === '__home__' ? '/' : '/' . $sec . '/' . $slug . '/';
        $headline = $page_key === '__home__' ? '🏠 Homepage' : ($post_headlines[$page_key] ?? $page_key);
        $bar_pct = round($total / $alltime_max * 100);
    ?>
    <tr>
      <td><div class="row-main" style="cursor:default;padding:12px 14px;">
        <span style="font-family:'Courier New',monospace;font-size:13px;color:var(--muted);"><?= $rank++ ?></span>
      </div></td>
      <td><div class="row-main" style="cursor:default;padding:12px 14px;">
        <div class="row-headline">
          <?php if ($page_key === '__home__'): ?>
            🏠 Homepage
          <?php else: ?>
            <a href="<?= e($article_url) ?>" target="_blank"><?= e($headline) ?></a>
          <?php endif; ?>
        </div>
      </div></td>
      <td><div style="padding:12px 14px;">
        <?php if ($sec): ?>
        <span style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:<?= $sec_color ?>;font-family:'Courier New',monospace;"><?= strtoupper($sec) ?></span>
        <?php endif; ?>
      </div></td>
      <td><div style="padding:12px 14px;text-align:right;font-family:'Courier New',monospace;font-weight:700;"><?= number_format($total) ?></div></td>
      <td><div style="padding:12px 20px 12px 14px;">
        <div class="row-bar" style="width:<?= $bar_pct ?>%;background:<?= $sec_color ?: 'var(--accent)' ?>;"></div>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php endif; ?>

</div>

<script>
function toggleRow(id) {
  var detail = document.getElementById(id + '_detail');
  var icon = document.getElementById(id + '_icon');
  var wrap = document.getElementById(id + '_wrap');
  if (detail.classList.contains('open')) {
    detail.classList.remove('open');
    icon.textContent = '▶';
    wrap.querySelector('.row-main').classList.remove('expanded');
  } else {
    detail.classList.add('open');
    icon.textContent = '▼';
    wrap.querySelector('.row-main').classList.add('expanded');
  }
}
</script>

</body>
</html>
