<?php
declare(strict_types=1);

require_once __DIR__ . '/s3.php';

function cfg(string $key)
{
    static $config = null;
    if ($config === null) {
        $file = is_file(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.example.php';
        $config = require $file;
    }
    return $config[$key];
}

function deny(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['error' => $message]);
    exit;
}

/* ---------- origins ---------- */

function own_origin(): string
{
    $fixed = (string)cfg('site_origin');
    if ($fixed !== '') {
        return strtolower(rtrim($fixed, '/'));
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// "https://Host:8443/path?x" -> "https://host:8443"; returns '' when not a valid absolute URL.
function origin_of(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host'])) {
        return '';
    }
    $o = strtolower($p['scheme']) . '://' . strtolower($p['host']);
    if (!empty($p['port'])) {
        $o .= ':' . $p['port'];
    }
    return $o;
}

function is_allowed_origin(string $origin): bool
{
    if ($origin === '') {
        return false;
    }
    $list = [own_origin()];
    foreach (cfg('allowed_embedders') as $o) {
        $list[] = strtolower(rtrim((string)$o, '/'));
    }
    return in_array(strtolower($origin), $list, true);
}

/* ---------- Fetch Metadata (sent automatically by modern browsers) ---------- */

function sec_fetch(string $name): string
{
    $key = 'HTTP_SEC_FETCH_' . strtoupper(str_replace('-', '_', $name));
    return strtolower((string)($_SERVER[$key] ?? ''));
}

/**
 * For image and JSON requests: refuse anything the browser says comes from another site,
 * or whose Origin/Referer is not ours (or an allowed partner).
 * Non-browser clients send none of these headers and pass this check; they are limited
 * only by the signature and its short lifetime.
 */
function reject_foreign_context(): void
{
    if (!cfg('block_cross_site')) {
        return;
    }
    if (sec_fetch('site') === 'cross-site') {
        deny(403, 'cross-site request blocked');
    }
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && !is_allowed_origin($origin)) {
        deny(403, 'origin not allowed');
    }
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '' && !is_allowed_origin(origin_of($referer))) {
        deny(403, 'referer not allowed');
    }
}

/* ---------- embedding control for the tour page ---------- */

function send_embed_headers(): void
{
    $ancestors = ["'self'"];
    foreach (cfg('allowed_embedders') as $o) {
        $ancestors[] = rtrim((string)$o, '/');
    }
    header('Content-Security-Policy: frame-ancestors ' . implode(' ', $ancestors));
    if (count($ancestors) === 1) {
        // Legacy browsers that do not understand frame-ancestors.
        header('X-Frame-Options: SAMEORIGIN');
    }
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
}

// Server-side second layer: do not even hand out the page (and its tokens) to a
// disallowed site that frames it. The browser would refuse to display it anyway.
function enforce_embedder(): void
{
    if (!in_array(sec_fetch('dest'), ['iframe', 'frame', 'embed', 'object'], true)) {
        return;
    }
    if (sec_fetch('site') === 'same-origin') {
        return;
    }
    if (is_allowed_origin(origin_of((string)($_SERVER['HTTP_REFERER'] ?? '')))) {
        return;
    }
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    deny(403, 'embedding is not allowed');
}

/* ---------- signed image links ---------- */

function sig(string $tour, string $scene, int $exp): string
{
    return hash_hmac('sha256', implode('|', [$tour, $scene, (string)$exp]), cfg('secret'));
}

// Returns ['exp' => unix time, 'scenes' => [ ['id','title','src'], ... ]]
function build_scenes(string $tourId): array
{
    $tour = cfg('tours')[$tourId];
    $ttl = (int)cfg('ttl');
    $exp = time() + $ttl;
    $s3 = cfg('s3');
    $scenes = [];

    foreach ($tour['scenes'] as $sc) {
        if (cfg('storage') === 's3') {
            $src = s3_presign_get(
                $s3['endpoint'], $s3['region'], $s3['bucket'], $s3['access_key'], $s3['secret_key'],
                $s3['prefix'] . $tourId . '/' . $sc['file'], $ttl, (bool)$s3['path_style']
            );
        } else {
            $src = 'img.php?' . http_build_query([
                't' => $tourId, 's' => $sc['id'], 'exp' => $exp, 'sig' => sig($tourId, $sc['id'], $exp),
            ]);
        }
        $scenes[] = ['id' => $sc['id'], 'title' => $sc['title'], 'src' => $src];
    }
    return ['exp' => $exp, 'scenes' => $scenes];
}
