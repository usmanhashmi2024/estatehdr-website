<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/jsonld.php';
if (file_exists(__DIR__ . '/includes/seo-helper.php')) {
    require_once __DIR__ . '/includes/seo-helper.php';
}

$allBlogs = getAllBlogsAdmin();
$slug = trim($_GET['slug'] ?? '');

// Robust fallback: If $_GET['slug'] is empty, extract from REQUEST_URI (e.g. /blog/my-slug)
if (empty($slug) && !empty($_SERVER['REQUEST_URI'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    if (preg_match('~^/blog/([^/?#]+)~', $path, $matches)) {
        $slug = urldecode($matches[1]);
    }
}

$post = null;
if (!empty($slug)) {
    foreach ($allBlogs as $b) {
        if ($b['slug'] === $slug || $b['id'] === $slug) {
            $post = $b;
            break;
        }
    }
}

if (!$post && empty($slug)) {
    // Fallback to first post only if no slug was requested
    $post = $allBlogs[0] ?? null;
}

if (!$post) {
    header('Location: /blog');
    exit;
}

$isDraft = (($post['status'] ?? 'published') === 'draft');
$canPreviewDraft = isAdminLoggedIn();

if ($isDraft && !$canPreviewDraft) {
    header('Location: /blog');
    exit;
}

$pageTitle = htmlspecialchars($post['meta_title'] ?? ($post['title'] . ' | Estate HDR Insights'));
$pageDesc  = htmlspecialchars($post['meta_description'] ?? $post['excerpt'] ?? '');
$canonUrl  = htmlspecialchars(!empty($post['canonical_url']) ? $post['canonical_url'] : (rtrim(BASE_URL, '/') . '/blog/' . urlencode($post['slug'])));
$featuredImg = htmlspecialchars(!empty($post['featured_image']) ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : (BASE_URL . $post['featured_image'])) : (BASE_URL . '/assets/images/portfolio/hdr/1-after.jpg'));
$robots = htmlspecialchars($post['robots'] ?? 'index, follow');

// Dynamic Author Resolution (Estate HDR Team for Senior SEO vs Hired SEO Manager)
$displayAuthor = getPostAuthorDisplayName($post);
$isSeniorOrTeam = ($displayAuthor === 'Estate HDR Team' || ($post['created_by'] ?? '') === 'adm-senior-seo');

if ($isSeniorOrTeam) {
    $authorBadge    = '✓ Verified Studio Specialists';
    $authorRole     = 'Architectural Retouching & Real Estate Media Experts';
    $authorBio      = !empty($post['author_bio']) ? $post['author_bio'] : 'The Estate HDR specialized studio team delivers master high-dynamic-range photo editing, photorealistic 3D virtual staging, and precision floor plan redraws for leading real estate photographers, brokerages, and marketing teams worldwide.';
    $authorAvatarBg = 'bg-gradient-to-br from-gold to-amber-600 text-dark';
    $authorInitial  = 'E';
} else {
    $authorBadge    = '✓ Verified Author';
    $authorRole     = !empty($post['author_title']) ? $post['author_title'] : 'Content Strategist & Real Estate Media Specialist';
    $authorBio      = !empty($post['author_bio']) ? $post['author_bio'] : ($displayAuthor . ' is a digital content strategist and architectural media researcher at Estate HDR, covering real estate photography workflows, property marketing trends, and MLS editing standards.');
    $authorAvatarBg = 'bg-gradient-to-br from-emerald-500 to-teal-700 text-white';
    $authorInitial  = strtoupper(mb_substr($displayAuthor, 0, 1));
}

// Related Articles
$related = array_filter($allBlogs, fn($b) => $b['slug'] !== $post['slug']);
$related = array_slice($related, 0, 3);

// Robust Markdown / HTML Content Formatter with Tag Detection & Spacing Normalization
if (!function_exists('renderArticleContent')) {
function renderArticleContent(string $rawContent): string {
    // Detect if content contains HTML tags (p, div, h1-h6, table, ul, ol, blockquote, section, article, img, etc.)
    if (preg_match('/<([a-z1-6]+)\b[^>]*>/i', $rawContent)) {
        $content = $rawContent;

        // 1. Remove Word and Gutenberg comments
        $content = preg_replace('/<!--\s*(?:Start|End)Fragment\s*-->/i', '', $content);
        $content = preg_replace('/<!--\s*\/?wp:[^>]*-->/i', '', $content);

        // 2. Remove broken phantom empty table shells inserted by Word around custom div tables
        $content = preg_replace('~<table\b[^>]*>\s*<tbody>\s*<tr[^>]*>\s*<td\s+width=["\']\d+["\'][^>]*>(?:(?!<table).)*?<\/td>\s*<\/tr>\s*<\/tbody>\s*<\/table>~is', '', $content);
        $content = preg_replace('~<table\b[^>]*>\s*<tbody>\s*<tr>\s*<td\s+width=["\']\d+["\'][^>]*>(?:(?!<table).)*?<\/td>\s*<\/tr>~is', '', $content);
        $content = preg_replace('~<tr[^>]*>\s*<td\s+width=["\']\d+["\'][^>]*>(?:(?!<table).)*?<\/td>\s*<\/tr>\s*<\/tbody>\s*<\/table>~is', '', $content);

        // 3. Remove inline style tags that were wrapped in <p> tags or free-floating inside content
        $content = preg_replace('~<p>\s*<style\b[^>]*>.*?<\/style>\s*<\/p>~is', '', $content);
        $content = preg_replace('~<style\b[^>]*>.*?<\/style>~is', '', $content);

        // 4. Strip Word / Mso classes, styles, and language attributes
        $content = preg_replace('/\s+class=["\'](?:MsoNormal|MsoListBullet[A-Za-z0-9]*|MsoListNumber[A-Za-z0-9]*)["\']/i', '', $content);
        $content = preg_replace('/\s+lang=["\'][^"\']+["\']/i', '', $content);
        $content = preg_replace('/\s+style=["\'][^"\']*mso-[^"\']*["\']/i', '', $content);
        $content = preg_replace('/<b\s+style=["\']mso-[^"\']*["\']>/i', '<b>', $content);
        $content = preg_replace('/bis_skin_checked=["\'][^"\']*["\']/i', '', $content);

        // 5. Unwrap ul and ol from invalid <p> wrappers: <p><ul>...</ul></p> -> <ul>...</ul>
        $content = preg_replace('~<p[^>]*>\s*(<(?:ul|ol)\b[^>]*>.*?<\/(?:ul|ol)>)\s*<\/p>~is', '$1', $content);

        // 6. Fix nested anchors: <a href="X">...<a href="Y">Z</a>...</a> -> flatten to clean link
        $content = preg_replace('~<a\b([^>]*)>\s*(?:<span>)?\s*<a\b[^>]*>(.*?)<\/a>\s*(.*?)(?:<\/span>)?\s*<\/a>~is', '<a $1>$2 $3</a>', $content);

        // 7. Strip unwanted font-size overrides on spans
        $content = preg_replace('~<span\s+style=["\'][^"\']*font-size:\s*0\.95rem[^"\']*["\']>(.*?)<\/span>~is', '$1', $content);

        // 8. Clean empty spans and excessive empty paragraphs
        $content = preg_replace('~<span\s*>\s*<\/span>~i', '', $content);
        $content = preg_replace('~<p\s*>\s*(?:<br\s*\/?>|&nbsp;|\s)*<\/p>~i', '', $content);
        $content = preg_replace('~<p>\s*&nbsp;\s*<\/p>~i', '', $content);

        // 9. Standardize H2 and H3 headings
        $content = preg_replace('~<h2\b[^>]*>(.*?)<\/h2>~is', '<h2>$1</h2>', $content);
        $content = preg_replace('~<h3\b[^>]*>(.*?)<\/h3>~is', '<h3>$1</h3>', $content);

        return trim($content);
    }
    // Markdown / plain text fallback: split on double newlines to separate true paragraphs
    $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawContent));
    $chunks = preg_split('/\n\s*\n/', $normalized);
    $html = '';
    foreach ($chunks as $chunk) {
        $trimmed = trim($chunk);
        if (empty($trimmed)) continue;

        if (str_starts_with($trimmed, '### ')) {
            $html .= '<h3 class="text-xl font-bold text-gold mt-6 mb-2">' . htmlspecialchars(substr($trimmed, 4)) . '</h3>' . "\n";
        } elseif (str_starts_with($trimmed, '## ')) {
            $html .= '<h2 class="text-2xl font-black text-[#F5D77F] mt-8 mb-3">' . htmlspecialchars(substr($trimmed, 3)) . '</h2>' . "\n";
        } elseif (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ')) {
            $lines = explode("\n", $trimmed);
            $html .= '<ul class="list-disc pl-6 space-y-1.5 text-gray-300 my-3">' . "\n";
            foreach ($lines as $l) {
                $l = trim(preg_replace('/^[\*\-•]\s+/', '', $l));
                if ($l) {
                    $itemFormatted = preg_replace('/\*\*(.*?)\*\*/', '<strong class="text-white">$1</strong>', htmlspecialchars($l));
                    $html .= '<li>' . $itemFormatted . '</li>' . "\n";
                }
            }
            $html .= "</ul>\n";
        } else {
            // Join soft-wrapped lines inside the paragraph so text flows naturally
            $flowed = preg_replace('/\s*\n\s*/', ' ', $trimmed);
            $lineFormatted = preg_replace('/\*\*(.*?)\*\*/', '<strong class="text-white">$1</strong>', htmlspecialchars($flowed));
            $lineFormatted = preg_replace('/\*([^*]+)\*/', '<em class="text-gold">$1</em>', $lineFormatted);
            $html .= '<p class="text-gray-300 leading-relaxed mb-3.5">' . $lineFormatted . '</p>' . "\n";
        }
    }
    return $html;
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Official Gold & White Favicons -->
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
<link rel="shortcut icon" href="/favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<meta name="description" content="<?= $pageDesc ?>">
<meta name="robots" content="<?= $robots ?>">
<link rel="canonical" href="<?= $canonUrl ?>">
<!-- Open Graph / Social Sharing -->
<meta property="og:type"        content="article">
<meta property="og:url"         content="<?= $canonUrl ?>">
<meta property="og:title"       content="<?= $pageTitle ?>">
<meta property="og:description" content="<?= $pageDesc ?>">
<meta property="og:image"       content="<?= $featuredImg ?>">
<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= $pageTitle ?>">
<meta name="twitter:description" content="<?= $pageDesc ?>">
<meta name="twitter:image"       content="<?= $featuredImg ?>">
<?= renderSeoHeadTags() ?>

<!-- Dynamic JSON-LD Schema Markup -->
<?php $customSchema = normalizeCustomJsonLd($post['schema_json'] ?? ''); ?>
<?php if ($customSchema !== null): ?>
<script type="application/ld+json" nonce="<?= \EstateHDR\Security\SecurityHeaders::nonce() ?>">
<?= $customSchema ?>
</script>
<?php else: ?>
<script type="application/ld+json" nonce="<?= \EstateHDR\Security\SecurityHeaders::nonce() ?>">
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": <?= json_encode($post['title']) ?>,
  "description": <?= json_encode($pageDesc) ?>,
  "image": <?= json_encode($featuredImg) ?>,
  "datePublished": "<?= !empty($post['created_at']) ? date('c', strtotime($post['created_at'])) : date('c') ?>",
  "dateModified": "<?= !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : date('c') ?>",
  "author": {
    "@type": "Person",
    "name": <?= json_encode($displayAuthor) ?>
  },
  "publisher": {
    "@type": "Organization",
    "name": "Estate HDR",
    "logo": {
      "@type": "ImageObject",
      "url": "<?= BASE_URL ?>/assets/images/favicon.svg"
    }
  }
}
</script>
<?php endif; ?>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap">
</noscript>
<link rel="stylesheet" href="/assets/css/bundle.css?v=<?= file_exists(ROOT_PATH . '/assets/css/bundle.css') ? filemtime(ROOT_PATH . '/assets/css/bundle.css') : time() ?>">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= file_exists(ROOT_PATH . '/assets/css/app.css') ? filemtime(ROOT_PATH . '/assets/css/app.css') : time() ?>">
<style nonce="<?= \EstateHDR\Security\SecurityHeaders::nonce() ?>">
/* ── Estate HDR Luxury Editorial Typography & Layout Engine ─────────── */
.article-content {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  color: #CBD5E1;
  line-height: 1.75;
  font-size: 1.05rem;
}

/* Paragraph spacing */
.article-content p {
  margin-top: 0 !important;
  margin-bottom: 1.25rem !important;
  line-height: 1.8 !important;
  color: #CBD5E1 !important;
  font-size: 1.05rem !important;
}

/* Headings with Signature Gold Accents */
.article-content h1 {
  font-size: 2.25rem !important;
  font-weight: 900 !important;
  color: #FFFFFF !important;
  line-height: 1.25 !important;
  margin-top: 2.5rem !important;
  margin-bottom: 1rem !important;
}

.article-content h2,
.article-content h2 span {
  font-size: 1.65rem !important;
  font-weight: 800 !important;
  color: #F5D77F !important;
  line-height: 1.3 !important;
  margin-top: 2.25rem !important;
  margin-bottom: 0.85rem !important;
  border-bottom: 1px solid rgba(245, 215, 127, 0.25) !important;
  padding-bottom: 0.4rem !important;
}

.article-content h3,
.article-content h3 span {
  font-size: 1.3rem !important;
  font-weight: 700 !important;
  color: #C9A84C !important;
  line-height: 1.35 !important;
  margin-top: 1.75rem !important;
  margin-bottom: 0.65rem !important;
}

.article-content h4,
.article-content h4 span {
  font-size: 1.12rem !important;
  font-weight: 700 !important;
  color: #E2E8F0 !important;
  margin-top: 1.25rem !important;
  margin-bottom: 0.4rem !important;
}

/* Respect explicit user-selected inline heading / text colors */
.article-content :is(h1, h2, h3, h4, h5, h6, span, p)[style*="color"] {
  color: inherit !important;
}

/* ── Luxury Responsive Table System ────────────────────────────────── */
.article-content .photo-pricing-table-wrap,
.article-content .hdr-editing-spec-table-wrap,
.article-content .pricing-model-table-wrap,
.article-content .table-wrap {
  width: 100% !important;
  max-width: 1000px !important;
  margin: 2rem auto !important;
  padding: 0 !important;
  border: 1px solid rgba(201, 168, 76, 0.4) !important;
  border-radius: 14px !important;
  overflow-x: auto !important;
  background: #0E0E16 !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(201, 168, 76, 0.08) !important;
}

.article-content .photo-pricing-table-wrap table,
.article-content .hdr-editing-spec-table-wrap table,
.article-content .pricing-model-table-wrap table,
.article-content table {
  width: 100% !important;
  border-collapse: collapse !important;
  margin: 0 !important;
  background: #0E0E16 !important;
  color: #F1F5F9 !important;
  border: 0 !important;
}

/* Base table styling for tables NOT inside custom wrappers */
.article-content > table {
  margin: 1.75rem 0 !important;
  border: 1px solid #2D2D3F !important;
  border-radius: 12px !important;
  overflow: hidden !important;
}

/* Table Headers */
.article-content thead th,
.article-content th {
  background: linear-gradient(135deg, #A97C39 0%, #8C6527 100%) !important;
  color: #FFFFFF !important;
  font-weight: 800 !important;
  font-size: 0.85rem !important;
  letter-spacing: 0.04em !important;
  padding: 0.85rem 1.15rem !important;
  text-align: left !important;
  vertical-align: middle !important;
  border: 0 !important;
  border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
}
.article-content thead th:last-child,
.article-content th:last-child {
  border-right: 0 !important;
}

/* Table Body Cells */
.article-content tbody td,
.article-content td {
  padding: 0.85rem 1.15rem !important;
  font-size: 0.9rem !important;
  line-height: 1.55 !important;
  vertical-align: top !important;
  border: 0 !important;
  border-right: 1px solid rgba(201, 168, 76, 0.15) !important;
  border-bottom: 1px solid rgba(201, 168, 76, 0.15) !important;
  background: #0E0E16 !important;
  color: #E2E8F0 !important;
}
.article-content tbody tr:nth-child(even) td {
  background: #13131F !important;
}
.article-content tbody td:last-child,
.article-content td:last-child {
  border-right: 0 !important;
}
.article-content tbody tr:last-child td {
  border-bottom: 0 !important;
}

/* First Column Gold Accent */
.article-content .photo-pricing-table-wrap tbody td:first-child,
.article-content .hdr-editing-spec-table-wrap tbody td:first-child,
.article-content .pricing-model-table-wrap tbody td:first-child,
.article-content table.accent-first tbody td:first-child {
  color: #F5D77F !important;
  font-weight: 700 !important;
  background: rgba(201, 168, 76, 0.08) !important;
}

/* Row Hover */
.article-content tbody tr:hover td {
  background: rgba(201, 168, 76, 0.06) !important;
}
.article-content .photo-pricing-table-wrap tbody tr:hover td:first-child,
.article-content .hdr-editing-spec-table-wrap tbody tr:hover td:first-child,
.article-content .pricing-model-table-wrap tbody tr:hover td:first-child {
  background: rgba(201, 168, 76, 0.14) !important;
}

/* Mobile responsive breakdown for data-label tables */
@media only screen and (max-width: 640px) {
  .article-content .photo-pricing-table-wrap,
  .article-content .hdr-editing-spec-table-wrap,
  .article-content .pricing-model-table-wrap {
    margin: 1.25rem 0 !important;
    border-radius: 10px !important;
  }
  .article-content .photo-pricing-table-wrap thead,
  .article-content .hdr-editing-spec-table-wrap thead,
  .article-content .pricing-model-table-wrap thead {
    display: none !important;
  }
  .article-content .photo-pricing-table-wrap table,
  .article-content .photo-pricing-table-wrap tbody,
  .article-content .photo-pricing-table-wrap tr,
  .article-content .photo-pricing-table-wrap td,
  .article-content .hdr-editing-spec-table-wrap table,
  .article-content .hdr-editing-spec-table-wrap tbody,
  .article-content .hdr-editing-spec-table-wrap tr,
  .article-content .hdr-editing-spec-table-wrap td,
  .article-content .pricing-model-table-wrap table,
  .article-content .pricing-model-table-wrap tbody,
  .article-content .pricing-model-table-wrap tr,
  .article-content .pricing-model-table-wrap td {
    display: block !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }
  .article-content .photo-pricing-table-wrap tbody tr,
  .article-content .hdr-editing-spec-table-wrap tbody tr,
  .article-content .pricing-model-table-wrap tbody tr {
    border-bottom: 2px solid rgba(201, 168, 76, 0.4) !important;
  }
  .article-content .photo-pricing-table-wrap tbody tr:last-child,
  .article-content .hdr-editing-spec-table-wrap tbody tr:last-child,
  .article-content .pricing-model-table-wrap tbody tr:last-child {
    border-bottom: 0 !important;
  }
  .article-content .photo-pricing-table-wrap tbody td,
  .article-content .hdr-editing-spec-table-wrap tbody td,
  .article-content .pricing-model-table-wrap tbody td {
    padding: 0.65rem 0.85rem !important;
    border-right: 0 !important;
    border-bottom: 1px solid rgba(201, 168, 76, 0.12) !important;
  }
  .article-content tbody td::before {
    content: attr(data-label) !important;
    display: block !important;
    margin-bottom: 0.25rem !important;
    color: #F5D77F !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
  }
}

/* ── Lists with Gold Bullets & Numbers ──────────────────────────────── */
.article-content ul {
  list-style-type: disc !important;
  padding-left: 1.5rem !important;
  margin: 1.25rem 0 !important;
  color: #CBD5E1 !important;
}
.article-content ol {
  list-style-type: decimal !important;
  padding-left: 1.5rem !important;
  margin: 1.25rem 0 !important;
  color: #CBD5E1 !important;
}
.article-content li {
  margin-bottom: 0.65rem !important;
  line-height: 1.7 !important;
  color: #CBD5E1 !important;
}
.article-content li::marker {
  color: #C9A84C !important;
  font-weight: 800 !important;
}
.article-content li strong,
.article-content li b {
  color: #FFFFFF !important;
}

/* Blockquotes */
.article-content blockquote {
  border-left: 4px solid #C9A84C !important;
  background: rgba(22, 22, 34, 0.5) !important;
  padding: 1.15rem 1.5rem !important;
  margin: 1.5rem 0 !important;
  font-style: italic !important;
  color: #E2E8F0 !important;
  border-radius: 0 0.85rem 0.85rem 0 !important;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
}

/* In-content Images */
.article-content img {
  max-width: 100% !important;
  height: auto !important;
  border-radius: 1rem !important;
  margin: 1.5rem auto !important;
  display: block !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
}
</style>
</head>
<body class="bg-dark text-white font-sans antialiased min-h-screen flex flex-col">

<?php if ($isDraft): ?>
<div class="bg-amber-500/90 text-dark px-6 py-2.5 text-center font-bold text-xs sticky top-0 z-[100] shadow-lg flex items-center justify-center gap-2">
  <span>⚠️</span> <span>DRAFT PREVIEW MODE — This article is currently unpublished and hidden from public visitors.</span>
  <a href="/admin/post-edit.php?id=<?= urlencode($post['id']) ?>" class="underline ml-2 text-dark font-black">Edit Article &rarr;</a>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/public-header.php'; ?>

<!-- ARTICLE HEADER -->
<header class="pt-36 pb-12 px-6 border-b border-dark-border/40 bg-gradient-to-b from-dark-card/60 to-dark">
  <div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4 text-xs">
      <a href="/blog" class="text-gold hover:underline">&larr; Back to Guides</a>
      <span class="text-gray-600">&bull;</span>
      <span class="px-2.5 py-0.5 rounded-full bg-gold/10 text-gold font-bold border border-gold/20"><?= htmlspecialchars($post['category'] ?? 'Real Estate') ?></span>
      <span class="text-gray-500 font-mono"><?= htmlspecialchars($post['read_time'] ?? '5 min read') ?></span>
    </div>

    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight mb-6">
      <?= htmlspecialchars($post['title']) ?>
    </h1>

    <div class="flex items-center gap-4 text-xs text-gray-400 font-mono">
      <span>By <strong class="text-white"><?= htmlspecialchars($displayAuthor) ?></strong></span>
      <span>&bull;</span>
      <span>Published: <?= date('F j, Y', strtotime($post['date'] ?? ($post['created_at'] ?? 'now'))) ?></span>
    </div>
  </div>
</header>

<!-- ARTICLE BODY -->
<main class="flex-1 px-6 py-12">
  <div class="max-w-3xl mx-auto">
    
    <?php if (!empty($post['featured_image'])): ?>
    <!-- Natural Auto-Sizing Featured Image Container (Never crops 1200x628 or any aspect ratio sides) -->
    <div class="rounded-2xl sm:rounded-3xl overflow-hidden mb-10 border border-dark-border shadow-2xl bg-dark-card/40 flex items-center justify-center p-1 sm:p-2">
      <img loading="lazy" decoding="async" src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-auto max-h-[650px] object-contain rounded-xl sm:rounded-2xl block mx-auto">
    </div>
    <?php endif; ?>

    <div class="article-content text-base sm:text-lg">
      <?php
        $rawArticle = $post['content'] ?? '';
        $bodyHtml = function_exists('renderArticleContent') ? renderArticleContent($rawArticle) : $rawArticle;
        if (function_exists('autoLinkKeywords')) {
            try {
                $bodyHtml = autoLinkKeywords($bodyHtml, $canonUrl);
            } catch (\Throwable $e) {
                error_log("autoLinkKeywords error: " . $e->getMessage());
            }
        }
        echo $bodyHtml;
      ?>
    </div>

    <!-- E-E-A-T Verified Author Bio Box -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 my-12 border border-gold/30 flex flex-col sm:flex-row items-center sm:items-start gap-6 bg-gradient-to-r from-gold/5 via-dark-card to-dark">
      <div class="w-20 h-20 rounded-2xl <?= $authorAvatarBg ?> flex items-center justify-center font-black text-2xl flex-shrink-0 shadow-lg border border-gold/40">
        <?= htmlspecialchars($authorInitial) ?>
      </div>
      <div class="space-y-2 text-center sm:text-left">
        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
          <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($displayAuthor) ?></h3>
          <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-black uppercase tracking-wider"><?= htmlspecialchars($authorBadge) ?></span>
        </div>
        <div class="text-xs font-mono text-gold font-semibold"><?= htmlspecialchars($authorRole) ?></div>
        <p class="text-xs text-gray-400 leading-relaxed max-w-xl">
          <?= htmlspecialchars($authorBio) ?>
        </p>
      </div>
    </div>

    <!-- CTA Box -->
    <div class="glass-card rounded-3xl p-8 sm:p-10 my-12 border border-gold/30 shadow-2xl text-center bg-gradient-to-br from-gold/10 via-dark-card to-dark">
      <h3 class="text-2xl font-bold text-white mb-2">Ready to Elevate Your Real Estate Listings?</h3>
      <p class="text-gray-400 text-sm max-w-lg mx-auto mb-6">
        Experience 24-hour turnaround HDR editing, virtual staging, and day-to-dusk conversions with zero risk.
      </p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="/register" class="btn-gold py-3 px-8 text-sm font-bold shadow-glow">Claim 3 Free Sample Edits</a>
        <a href="https://wa.me/447706068176" target="_blank" class="btn-ghost py-3 px-6 text-sm text-emerald-400 hover:text-emerald-300" rel="noopener noreferrer">💬 WhatsApp Support</a>
      </div>
    </div>

    <!-- Related Articles -->
    <?php if (!empty($related)): ?>
    <div class="pt-12 border-t border-dark-border">
      <h3 class="text-xl font-bold text-white mb-6">Related Guides &amp; Insights</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <?php foreach ($related as $rel): ?>
        <a href="/blog/<?= urlencode($rel['slug']) ?>" class="glass-card p-5 rounded-2xl border border-dark-border hover:border-gold/40 transition-all group block">
          <span class="text-xs text-gold font-bold uppercase tracking-wider block mb-2"><?= htmlspecialchars($rel['category'] ?? 'Guide') ?></span>
          <h4 class="text-sm font-bold text-white group-hover:text-gold transition-colors leading-snug line-clamp-2"><?= htmlspecialchars($rel['title']) ?></h4>
          <span class="text-xs text-gray-500 mt-3 block font-mono"><?= htmlspecialchars($rel['read_time'] ?? '5 min') ?> &rarr;</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<footer class="border-t border-dark-border py-12 px-6 bg-dark">
  <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500">
    <p>&copy; <?= date('Y') ?> Estate HDR. All rights reserved. Professional Real Estate Media Post-Processing.</p>
    <div class="flex items-center gap-5 text-gray-400">
      <a href="<?= FACEBOOK_LINK ?>" target="_blank" class="hover:text-blue-400" rel="noopener noreferrer">Facebook</a>
      <a href="<?= INSTAGRAM_LINK ?>" target="_blank" class="hover:text-pink-400" rel="noopener noreferrer">Instagram</a>
      <a href="<?= LINKEDIN_LINK ?>" target="_blank" class="hover:text-blue-300" rel="noopener noreferrer">LinkedIn</a>
      <a href="<?= WHATSAPP_LINK ?>" target="_blank" class="hover:text-emerald-400" rel="noopener noreferrer">WhatsApp</a>
      <a href="/terms" class="hover:text-gold">Terms</a>
      <a href="/privacy" class="hover:text-gold">Privacy</a>
      <a href="/sitemap.xml" class="hover:text-gold">Sitemap.xml</a>
      <a href="/contact" class="hover:text-gold">Contact</a>
    </div>
  </div>
</footer>

<?php require_once __DIR__ . '/includes/chat-widgets.php'; ?>
</body>
</html>
