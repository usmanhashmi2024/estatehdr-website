<?php
require_once __DIR__ . '/../includes/client-return.php';
require_once __DIR__ . '/../includes/jsonld.php';
$checks = 0;
function check($actual, $expected, string $label): void {
    global $checks;
    $checks++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
$quote = '/order-new?service=HDR%20Photo%20Retouching&count=25&est=44.63&curr=USD';
check(clientReturnPath($quote), $quote, 'quote return path retained');
check(clientReturnPath('/order-view.php?id=example'), '/order-view.php?id=example', 'existing portal link accepted');
foreach (['https://example.com', '//example.com', '/\\example.com', "/order-new\r\nLocation: https://example.com", '/order-new%0d%0aInjected', '/order-new/../admin', '/%2forder-new', '/admin/users', '/logout', '/login?redirect=//example.com', '/dashboard#fragment', ['redirect' => '/order-new'], null] as $bad) {
    check(clientReturnPath($bad), '/dashboard', 'untrusted redirect rejected');
}
$schema = '{"@context":"https://schema.org","@type":"Article","headline":"A < B & C"}';
$normalized = normalizeCustomJsonLd($schema);
check(json_decode($normalized, true)['headline'], 'A < B & C', 'valid JSON round trips');
check(str_contains($normalized, '<'), false, 'HTML characters escaped');
$wrapped = '<script type="application/ld+json">' . $schema . '</script>';
check(normalizeCustomJsonLd($wrapped), $normalized, 'pasted script wrapper normalized');
$breakout = json_encode(['@context' => 'https://schema.org', 'text' => '</script><script>alert(1)</script>']);
check(str_contains(normalizeCustomJsonLd($breakout), '</script>'), false, 'script breakout escaped');
foreach (['', '<script>{invalid}</script>', '{broken', 'null', '123', '"string"', '<script>{}</script><script>alert(1)</script>'] as $bad) {
    check(normalizeCustomJsonLd($bad), null, 'invalid custom schema triggers fallback');
}
echo "PASS: {$checks} redirect and JSON-LD checks\n";
