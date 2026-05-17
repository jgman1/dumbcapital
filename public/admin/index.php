<?php
// ─────────────────────────────────────────────────────────────
//  DumbCapital Admin Portal
//  Access at: www.dumbcapital.com/admin/
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

session_start();

// ── AUTH ─────────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['auth'] = SESSION_SECRET;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Incorrect password.';
    }
}
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
$authed = isset($_SESSION['auth']) && $_SESSION['auth'] === SESSION_SECRET;

// ── HELPERS ──────────────────────────────────────────────────
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
function load_posts(): array {
    // Load share counts from analytics
$share_counts = [];
$analytics_file = POSTS_DIR . 'analytics.json';
if (file_exists($analytics_file)) {
    $analytics_data = json_decode(file_get_contents($analytics_file), true) ?? [];
    foreach ($analytics_data as $page_key => $pdata) {
        if (!empty($pdata['slug'])) {
            $sec = $pdata['section'] ?? '';
            $key = $sec . '/' . $pdata['slug'];
            $share_counts[$key] = (int)($pdata['shares'] ?? 0);
        }
    }
}

$posts = [];
    foreach (glob(POSTS_DIR . '*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) { $data['_file'] = basename($file); $posts[] = $data; }
    }
    usort($posts, fn($a,$b) => strcmp($b['date']??'',$a['date']??''));
    return $posts;
}
function save_post(array $data, string $filename): void {
    file_put_contents(POSTS_DIR . $filename, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function delete_post(string $filename): void {
    $path = POSTS_DIR . $filename;
    if (file_exists($path)) unlink($path);
}

// ── ACTIONS ───────────────────────────────────────────────────
$action = $_GET['action'] ?? 'list';

if ($authed) {
    if ($action === 'delete' && !empty($_GET['file'])) {
        delete_post(basename($_GET['file']));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1'); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
        $date     = $_POST['date'] ?? date('Y-m-d');
        $headline = trim($_POST['headline'] ?? '');
        $slug     = slugify($headline);
        $oldfile  = $_POST['old_file'] ?? '';
        $newfile  = $date . '-' . $slug . '.json';
        $post = [
            'date'                => $date,
            'slug'                => $slug,
            'published'           => isset($_POST['published']),
            'section'             => $_POST['section']             ?? 'vc',
            'tag'                 => $_POST['tag']                 ?? 'vc',
            'kicker'              => $_POST['kicker']              ?? '',
            'headline'            => $headline,
            'subheadline'         => $_POST['subheadline']         ?? '',
            'body_html'           => $_POST['body_html']           ?? '',
            'dumb_rating'         => (int)($_POST['dumb_rating']   ?? 3),
            'dumb_rating_label'   => $_POST['dumb_rating_label']   ?? '',
            'source_headline'     => $_POST['source_headline']     ?? '',
            'source_url'          => $_POST['source_url']          ?? '',
            'source_name'         => $_POST['source_name']         ?? '',
            'glossary_term'       => $_POST['glossary_term']       ?? '',
            'glossary_definition' => $_POST['glossary_definition'] ?? '',
        ];
        if ($oldfile && $oldfile !== $newfile) delete_post($oldfile);
        save_post($post, $newfile);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1'); exit;
    }
}

$posts   = $authed ? load_posts() : [];
$editing = null;
if ($authed && $action === 'edit' && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = POSTS_DIR . $file;
    if (file_exists($path)) { $editing = json_decode(file_get_contents($path), true); $editing['_file'] = $file; }
}

$sections = ['vc'=>'VC Deals','ma'=>'M&A Morgue','pe'=>'PE Corner','unicorn'=>'Unicorn Watch','opinion'=>'Opinion'];
$tags     = ['vc'=>'VC','ma'=>'M&A','pe'=>'PE','flop'=>'Flop','unicorn'=>'Unicorn','opinion'=>'Opinion'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DumbCapital Admin</title>
<style>
  :root{--ink:#0e0e0e;--paper:#f5f2eb;--accent:#c0392b;--muted:#6b6560;--border:#ddd;--green:#2a7a3b;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Segoe UI',sans-serif;background:#f0ede6;color:var(--ink);font-size:15px;}
  .admin-header{background:var(--ink);color:#fff;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;}
  .admin-header .logo{font-family:Georgia,serif;font-size:22px;font-weight:700;}
  .admin-header .logo em{color:var(--accent);font-style:italic;}
  .badge{font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:#888;margin-left:12px;}
  .btn-logout{background:none;border:1px solid #555;color:#bbb;padding:6px 14px;cursor:pointer;font-size:12px;letter-spacing:.1em;text-transform:uppercase;}
  .btn-logout:hover{border-color:var(--accent);color:var(--accent);}
  .admin-wrap{max-width:1100px;margin:32px auto;padding:0 24px;}
  .alert{padding:12px 18px;margin-bottom:24px;font-size:13px;border-left:4px solid var(--green);background:#eafaef;color:var(--green);}
  .alert-red{border-color:var(--accent);background:#fdf0ef;color:var(--accent);}
  .login-box{max-width:380px;margin:100px auto;background:#fff;border:1px solid var(--border);padding:40px;}
  .login-box h2{font-family:Georgia,serif;font-size:24px;margin-bottom:8px;}
  .login-box .sub{color:var(--muted);font-size:13px;margin-bottom:28px;}
  .login-box .error{color:var(--accent);font-size:13px;margin-bottom:16px;}
  .form-group{margin-bottom:18px;}
  label{display:block;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
  input[type=text],input[type=password],input[type=date],input[type=url],select,textarea{width:100%;padding:9px 12px;border:1px solid var(--border);font-size:14px;font-family:inherit;background:#fff;outline:none;transition:border-color .15s;}
  input:focus,select:focus,textarea:focus{border-color:var(--ink);}
  textarea{resize:vertical;line-height:1.5;}
  .btn{display:inline-block;padding:9px 20px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;border:none;text-decoration:none;font-family:inherit;}
  .btn-primary{background:var(--ink);color:#fff;}
  .btn-primary:hover{background:#333;}
  .btn-red{background:var(--accent);color:#fff;}
  .btn-red:hover{background:#a93226;}
  .btn-outline{background:none;border:1px solid var(--ink);color:var(--ink);}
  .btn-outline:hover{background:var(--ink);color:#fff;}
  .btn-sm{padding:5px 12px;font-size:11px;}
  .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
  .top-bar h1{font-family:Georgia,serif;font-size:26px;}
  .post-table{width:100%;border-collapse:collapse;background:#fff;}
  .post-table th{text-align:left;padding:10px 14px;background:var(--ink);color:#fff;font-size:11px;letter-spacing:.1em;text-transform:uppercase;}
  .post-table td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;}
  .post-table tr:last-child td{border-bottom:none;}
  .post-table tr:hover td{background:#faf8f4;}
  .headline-cell{font-weight:600;font-size:14px;}
  .meta{font-size:12px;color:var(--muted);margin-top:3px;}
  .pill{display:inline-block;font-size:10px;letter-spacing:.1em;text-transform:uppercase;padding:2px 8px;border:1px solid currentColor;}
  .pill-vc{color:#3a5a8a;}.pill-ma{color:#1a1a2e;}.pill-pe{color:#555;}
  .pill-flop{background:var(--accent);color:#fff;border-color:var(--accent);}
  .pill-opinion{background:var(--ink);color:#fff;border-color:var(--ink);}
  .pill-unicorn{color:#7a4a9a;border-color:#7a4a9a;}
  .unpublished{opacity:.5;}
  .actions{display:flex;gap:8px;}
  .editor-card{background:#fff;border:1px solid var(--border);padding:32px;}
  .editor-card h2{font-family:Georgia,serif;font-size:22px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border);}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
  .form-section{margin-top:28px;padding-top:20px;border-top:1px solid var(--border);}
  .form-section h3{font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:16px;}
  .rating-row{display:flex;align-items:center;gap:16px;}
  .rating-row input[type=number]{width:70px;}
  .checkbox-row{display:flex;align-items:center;gap:10px;margin-bottom:18px;}
  .checkbox-row input[type=checkbox]{width:18px;height:18px;}
  .editor-actions{margin-top:28px;padding-top:20px;border-top:1px solid var(--border);display:flex;gap:12px;}
  .empty-state{text-align:center;padding:64px 32px;color:var(--muted);background:#fff;border:1px dashed var(--border);}
  .section-count{display:inline-block;background:#ede9e0;color:var(--muted);font-size:11px;padding:2px 8px;margin-left:8px;vertical-align:middle;}
</style>
</head>
<body>

<?php if (!$authed): ?>
<div class="login-box">
  <h2>Dumb<em style="color:var(--accent);font-style:italic;">Capital</em></h2>
  <div class="sub">Admin portal. Enter your password to continue.</div>
  <?php if ($error): ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" autofocus required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">Sign In</button>
  </form>
</div>

<?php else: ?>
<div class="admin-header">
  <div>
    <span class="logo">Dumb<em>Capital</em></span>
    <span class="badge">Admin Portal</span>
  </div>
  <div style="display:flex;align-items:center;gap:16px;">
    <a href="/admin/analytics.php" style="color:#888;font-size:12px;text-decoration:none;">📊 Analytics</a>
    <a href="/" target="_blank" style="color:#888;font-size:12px;text-decoration:none;">↗ View Site</a>
    <form method="POST"><button name="logout" class="btn-logout">Sign Out</button></form>
  </div>
</div>

<div class="admin-wrap">

<?php if (isset($_GET['saved'])): ?>
  <div class="alert">✅ Post saved successfully.</div>
<?php elseif (isset($_GET['deleted'])): ?>
  <div class="alert alert-red">🗑 Post deleted.</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<div class="top-bar">
  <h1>All Posts <span class="section-count"><?= count($posts) ?></span></h1>
  <a href="?action=new" class="btn btn-primary">+ New Post</a>
</div>

<?php if (empty($posts)): ?>
<div class="empty-state">
  <div style="font-size:48px;margin-bottom:12px;">📰</div>
  <p>No posts yet. The bot will create them automatically on Tue/Fri,<br>or <a href="?action=new">add one manually</a>.</p>
</div>
<?php else: ?>
<table class="post-table">
  <thead><tr><th>Date</th><th>Headline</th><th>Tag</th><th>Rating</th><th>Shares</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($posts as $p): ?>
  <tr class="<?= empty($p['published']) ? 'unpublished' : '' ?>">
    <td style="white-space:nowrap;font-size:13px;"><?= htmlspecialchars($p['date']??'') ?></td>
    <td>
      <div class="headline-cell"><?= htmlspecialchars($p['headline']??'(no headline)') ?></div>
      <div class="meta"><?= htmlspecialchars(substr($p['subheadline']??'',0,80)) ?>...</div>
    </td>
    <td><?php $t=$p['tag']??'vc'; ?><span class="pill pill-<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($tags[$t]??$t) ?></span></td>
    <td style="font-size:13px;"><?= str_repeat('💀',max(1,min(5,(int)($p['dumb_rating']??3)))) ?> <span style="color:var(--muted);font-size:11px;"><?= (int)($p['dumb_rating']??3) ?>/5</span></td>
    <td style="text-align:center;">
      <?php
        $sec = $p['section'] ?? $p['tag'] ?? 'vc';
        $share_key = $sec . '/' . ($p['slug'] ?? '');
        $sc = $share_counts[$share_key] ?? 0;
      ?>
      <?php if ($sc > 0): ?>
        <span style="font-family:'Courier New',monospace;font-size:12px;font-weight:700;color:var(--accent);">↗ <?= $sc ?></span>
      <?php else: ?>
        <span style="font-family:'Courier New',monospace;font-size:11px;color:#ccc;">—</span>
      <?php endif; ?>
    </td>
    <td><span style="font-size:12px;color:<?= !empty($p['published'])?'var(--green)':'var(--muted)' ?>"><?= !empty($p['published'])?'● Live':'○ Draft' ?></span></td>
    <td>
      <div class="actions">
        <a href="?action=edit&file=<?= urlencode($p['_file']) ?>" class="btn btn-outline btn-sm">Edit</a>
        <a href="?action=delete&file=<?= urlencode($p['_file']) ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this post permanently?')">Delete</a>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="top-bar">
  <h1><?= $action==='new'?'New Post':'Edit Post' ?></h1>
  <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline">← Back to Posts</a>
</div>
<form method="POST">
<input type="hidden" name="old_file" value="<?= htmlspecialchars($editing['_file']??'') ?>">
<div class="editor-card">
  <h2><?= $action==='new'?'✏️ Create New Post':'✏️ Editing Post' ?></h2>

  <div class="checkbox-row">
    <input type="checkbox" name="published" id="pub" value="1" <?= !empty($editing['published']??true)?'checked':'' ?>>
    <label for="pub" style="text-transform:none;letter-spacing:0;font-size:14px;">Published (visible on site)</label>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Date</label>
      <input type="date" name="date" value="<?= htmlspecialchars($editing['date']??date('Y-m-d')) ?>" required>
    </div>
    <div class="form-group">
      <label>Section</label>
      <select name="section">
        <?php foreach($sections as $v=>$l): ?>
        <option value="<?= $v ?>" <?= ($editing['section']??'vc')===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Tag</label>
      <select name="tag">
        <?php foreach($tags as $v=>$l): ?>
        <option value="<?= $v ?>" <?= ($editing['tag']??'vc')===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Kicker</label>
      <input type="text" name="kicker" value="<?= htmlspecialchars($editing['kicker']??'') ?>" placeholder="e.g. Deal of the Week">
    </div>
  </div>

  <div class="form-group">
    <label>Headline *</label>
    <input type="text" name="headline" value="<?= htmlspecialchars($editing['headline']??'') ?>" required>
  </div>
  <div class="form-group">
    <label>Subheadline / Dek</label>
    <input type="text" name="subheadline" value="<?= htmlspecialchars($editing['subheadline']??'') ?>">
  </div>
  <div class="form-group">
    <label>Article Body (HTML — wrap paragraphs in &lt;p&gt; tags)</label>
    <textarea name="body_html" rows="12"><?= htmlspecialchars($editing['body_html']??'') ?></textarea>
  </div>

  <div class="form-section">
    <h3>Dumb Rating</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Rating (1–5)</label>
        <div class="rating-row">
          <input type="number" name="dumb_rating" min="1" max="5" value="<?= (int)($editing['dumb_rating']??3) ?>">
          <span id="skulls" style="font-size:20px;"><?= str_repeat('💀',max(1,min(5,(int)($editing['dumb_rating']??3)))) ?></span>
        </div>
      </div>
      <div class="form-group">
        <label>Rating Label</label>
        <input type="text" name="dumb_rating_label" value="<?= htmlspecialchars($editing['dumb_rating_label']??'') ?>" placeholder="e.g. Criminally Optimistic">
      </div>
    </div>
  </div>

  <div class="form-section">
    <h3>Source</h3>
    <div class="form-group"><label>Original Headline</label><input type="text" name="source_headline" value="<?= htmlspecialchars($editing['source_headline']??'') ?>"></div>
    <div class="form-row">
      <div class="form-group"><label>Publication Name</label><input type="text" name="source_name" value="<?= htmlspecialchars($editing['source_name']??'') ?>"></div>
      <div class="form-group"><label>Source URL</label><input type="url" name="source_url" value="<?= htmlspecialchars($editing['source_url']??'') ?>"></div>
    </div>
  </div>

  <div class="form-section">
    <h3>Glossary Entry (optional)</h3>
    <div class="form-row">
      <div class="form-group"><label>Term</label><input type="text" name="glossary_term" value="<?= htmlspecialchars($editing['glossary_term']??'') ?>"></div>
      <div class="form-group"><label>Satirical Definition</label><input type="text" name="glossary_definition" value="<?= htmlspecialchars($editing['glossary_definition']??'') ?>"></div>
    </div>
  </div>

  <div class="editor-actions">
    <button type="submit" name="save_post" class="btn btn-primary">💾 Save Post</button>
    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline">Cancel</a>
  </div>
</div>
</form>
<?php endif; ?>
</div>
<script>
const ri = document.querySelector('input[name="dumb_rating"]');
const sp = document.getElementById('skulls');
if(ri&&sp) ri.addEventListener('input',()=>{sp.textContent='💀'.repeat(Math.max(1,Math.min(5,parseInt(ri.value)||1)));});
</script>
<?php endif; ?>
</body>
</html>
