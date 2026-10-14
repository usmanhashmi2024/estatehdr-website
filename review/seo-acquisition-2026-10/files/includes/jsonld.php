<?php
/** Accept JSON or one pasted JSON-LD script wrapper; emit script-safe JSON only. */
function normalizeCustomJsonLd($raw): ?string {
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $raw = trim($raw);
    if (preg_match('~^<script\b[^>]*>\s*(.*?)\s*</script>\s*$~is', $raw, $matches)) {
        $raw = $matches[1];
    }
    try {
        $schema = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return null;
    }
    if (!is_object($schema) && !is_array($schema)) {
        return null;
    }
    return json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}
