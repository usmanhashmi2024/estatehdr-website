<?php
/** Keep client acquisition intent on a local, allowlisted portal route. */
function clientReturnPath($candidate): string {
    $fallback = '/dashboard';
    if (!is_string($candidate) || $candidate === '' || strlen($candidate) > 4096) {
        return $fallback;
    }
    if ($candidate[0] !== '/' || str_starts_with($candidate, '//') ||
        str_contains($candidate, '\\') || preg_match('/[\x00-\x1F\x7F]/', $candidate) ||
        preg_match('/%(?:0a|0d|00|5c)/i', $candidate)) {
        return $fallback;
    }
    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['host']) || isset($parts['scheme']) || isset($parts['fragment'])) {
        return $fallback;
    }
    $path = $parts['path'] ?? '';
    $routes = ['dashboard', 'order', 'order-new', 'order-view', 'orders', 'billing',
        'profile', 'preferences', 'support', 'completed-jobs', 'pending-jobs', 'review'];
    foreach ($routes as $route) {
        if (in_array($path, ['/' . $route, '/' . $route . '.php', '/' . $route . '/'], true)) {
            return $candidate;
        }
    }
    return $fallback;
}
