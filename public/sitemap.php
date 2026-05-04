<?php
define('POSTS_DIR', __DIR__ . '/posts/');
define('SITE_URL',  'https://dumbcapital.com');

header('Content-Type: application/xml; charset=utf-8');

function load_published(): array {
    $posts = [];
    if (!is_dir(POSTS_DIR)) return $posts;
    foreach (glob(POSTS_DIR . '*.json') as $f) {
        if (in_array(basename($f), ['seen_cache.json','analytics.json'])) continue;
        $d = json_decode(file_get_contents($f), true);
        if ($d && !empty($d['published'])) $posts[] = $d;
    }
    usort($posts, fn($a,$b) => strcmp($b['date']??'',$a['date']??''));
    return $posts;
}

$posts       = load_published();
$latest_date = $posts[0]['date'] ?? date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Homepage
echo "  <url><loc>".SITE_URL."/</loc><lastmod>{$latest_date}</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>\n";

// Section pages
foreach (['vc','ma','pe','unicorn','opinion'] as $sec) {
    $sec_posts = array_filter($posts, fn($p) => ($p['section']??$p['tag']??'vc') === $sec);
    $sec_date  = count($sec_posts) ? reset($sec_posts)['date'] : date('Y-m-d');
    echo "  <url><loc>".SITE_URL."/{$sec}/</loc><lastmod>{$sec_date}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
}

// Static pages
foreach (['about','contact','privacy'] as $pg) {
    echo "  <url><loc>".SITE_URL."/{$pg}/</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>\n";
}

// Search
echo "  <url><loc>".SITE_URL."/search/</loc><changefreq>never</changefreq><priority>0.3</priority></url>\n";

// Individual articles
foreach ($posts as $p) {
    $sec  = $p['section'] ?? $p['tag'] ?? 'vc';
    $slug = $p['slug'] ?? '';
    $date = $p['date'] ?? date('Y-m-d');
    if (!$slug) continue;
    echo "  <url><loc>".SITE_URL."/{$sec}/{$slug}/</loc><lastmod>{$date}</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>\n";
}

echo '</urlset>';
