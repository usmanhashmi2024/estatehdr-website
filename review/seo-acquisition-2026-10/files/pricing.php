<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo-helper.php';
require_once __DIR__ . '/includes/pricing-engine.php';

$seo = getPageSeo('pricing') ?: [
    'meta_title'       => 'Transparent Real Estate Photo Editing Pricing & Instant Calculator — Estate HDR',
    'meta_description' => 'Interactive real estate photo editing pricing calculator. HDR from $0.50/photo, 3D virtual staging from $8/room, floor plans from $12. Guaranteed 24h delivery, zero subscriptions, volume discounts.',
    'canonical_url'    => rtrim(BASE_URL, '/') . '/pricing',
    'og_image'         => BASE_URL . '/assets/images/portfolio/hdr/1-after.jpg',
    'robots'           => 'index, follow',
    'schema_json'      => ''
];

$pageTitle = htmlspecialchars($seo['meta_title'] ?? 'Real Estate Photo Editing Pricing — Estate HDR');
$pageDesc  = htmlspecialchars($seo['meta_description'] ?? '');
$canonUrl  = htmlspecialchars(!empty($seo['canonical_url']) && !str_ends_with($seo['canonical_url'], '.php') ? $seo['canonical_url'] : (rtrim(BASE_URL, '/') . '/pricing'));
$ogImage   = htmlspecialchars(!empty($seo['og_image']) ? $seo['og_image'] : (BASE_URL . '/assets/images/portfolio/hdr/1-after.jpg'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<meta name="description" content="<?= $pageDesc ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= $canonUrl ?>">

<!-- Open Graph / Social -->
<meta property="og:type"        content="website">
<meta property="og:url"         content="<?= $canonUrl ?>">
<meta property="og:title"       content="<?= $pageTitle ?>">
<meta property="og:description" content="<?= $pageDesc ?>">
<meta property="og:image"       content="<?= $ogImage ?>">
<meta name="twitter:card"       content="summary_large_image">

<?= renderSeoHeadTags() ?>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/assets/css/bundle.css?v=<?= file_exists(ROOT_PATH . '/assets/css/bundle.css') ? filemtime(ROOT_PATH . '/assets/css/bundle.css') : time() ?>">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= file_exists(ROOT_PATH . '/assets/css/app.css') ? filemtime(ROOT_PATH . '/assets/css/app.css') : time() ?>">
</head>
<body class="bg-dark text-white overflow-x-hidden min-h-screen flex flex-col font-sans">

<?php require_once __DIR__ . '/includes/public-header.php'; ?>

<main id="main-content" class="flex-1 pt-28 pb-20 px-6">
  <div class="max-w-7xl mx-auto space-y-16">

    <!-- Hero Header -->
    <div class="text-center max-w-3xl mx-auto space-y-4">
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-gold/30 bg-gold/10 text-gold text-xs font-semibold uppercase tracking-widest">
        <span class="w-1.5 h-1.5 rounded-full bg-gold animate-pulse"></span>
        Zero Subscriptions &bull; Pay-Per-Job &bull; Guaranteed 24h SLA
      </div>
      <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white">
        Simple, Transparent <span class="text-gradient">Pricing Studio</span>
      </h1>
      <p class="text-gray-300 text-base sm:text-lg leading-relaxed">
        Calculate your exact project cost in real-time. Enjoy volume discounts, multi-currency conversion, and 100% free unlimited amendments.
      </p>
    </div>

    <!-- ── EMBEDDED LUXURY PRICING CALCULATOR ── -->
    <section id="interactive-calculator" class="relative">
      <?php require __DIR__ . '/includes/pricing-calculator.php'; ?>
    </section>

    <!-- ── VALUE PROPOSITIONS & INCLUSIONS ── -->
    <section class="grid grid-cols-1 md:grid-cols-4 gap-6 pt-6">
      <div class="p-6 rounded-2xl bg-dark-card border border-dark-border text-center space-y-2 hover:border-gold/40 transition-colors">
        <div class="text-3xl">⏱️</div>
        <div class="text-base font-bold text-white">24h Turnaround</div>
        <div class="text-xs text-gray-400">Guaranteed next-day delivery on all standard orders.</div>
      </div>
      <div class="p-6 rounded-2xl bg-dark-card border border-dark-border text-center space-y-2 hover:border-gold/40 transition-colors">
        <div class="text-3xl">✨</div>
        <div class="text-base font-bold text-white">Free Revisions</div>
        <div class="text-xs text-gray-400">100% free unlimited amendments until you are satisfied.</div>
      </div>
      <div class="p-6 rounded-2xl bg-dark-card border border-dark-border text-center space-y-2 hover:border-gold/40 transition-colors">
        <div class="text-3xl">📦</div>
        <div class="text-base font-bold text-white">Dual Deliverables</div>
        <div class="text-xs text-gray-400">Web-optimized 2048px MLS files + 300 DPI high-res print.</div>
      </div>
      <div class="p-6 rounded-2xl bg-dark-card border border-dark-border text-center space-y-2 hover:border-gold/40 transition-colors">
        <div class="text-3xl">🛡️</div>
        <div class="text-base font-bold text-white">No Commitments</div>
        <div class="text-xs text-gray-400">No monthly locks or hidden fees. Pay only for what you edit.</div>
      </div>
    </section>

    <!-- ── FREQUENTLY ASKED QUESTIONS ── -->
    <section class="max-w-4xl mx-auto space-y-6 pt-10">
      <div class="text-center space-y-2">
        <div class="section-tag">Got Questions?</div>
        <h2 class="text-3xl font-bold text-white">Pricing &amp; Billing FAQ</h2>
      </div>

      <div class="space-y-4">
        <div class="p-6 rounded-2xl bg-dark-card border border-dark-border space-y-2">
          <div class="font-bold text-white text-base">How does volume discount pricing work?</div>
          <p class="text-sm text-gray-300 leading-relaxed">
            The calculator above shows the current service and quantity tiers. Review your selected service, quantity, turnaround and add-ons before requesting an estimate. Your final order scope and billing should match the confirmed quote.
          </p>
        </div>

        <div class="p-6 rounded-2xl bg-dark-card border border-dark-border space-y-2">
          <div class="font-bold text-white text-base">What payment methods do you accept?</div>
          <p class="text-sm text-gray-300 leading-relaxed">
            We accept all major payment methods including PayPal (<code class="text-gold font-mono">ashrafnadeem283@gmail.com</code>), Payoneer, Wire Transfer, and all major Credit/Debit Cards.
          </p>
        </div>

        <div class="p-6 rounded-2xl bg-dark-card border border-dark-border space-y-2">
          <div class="font-bold text-white text-base">Can I test quality before paying?</div>
          <p class="text-sm text-gray-300 leading-relaxed">
            Yes! Submit up to 3 raw photos through our <a href="/#trial" class="text-gold underline font-bold">100% Free Trial</a>. We will edit and deliver finished architectural samples within 24 hours with zero payment required.
          </p>
        </div>
      </div>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/includes/public-footer.php'; ?>

</body>
</html>
