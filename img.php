<?php
// Delivers a panorama only for a valid, unexpired signed link, and only when the browser
// context is our own page (or an allowed partner). Used when storage = 'local'.
require __DIR__ . '/lib.php';

$tourId  = (string)($_GET['t'] ?? '');
$sceneId = (string)($_GET['s'] ?? '');
$exp     = (int)($_GET['exp'] ?? 0);
$sig     = (string)($_GET['sig'] ?? '');

$tours = cfg('tours');
if (!isset($tours[$tourId])) {
    deny(404, 'not found');
}
$file = null;
foreach ($tours[$tourId]['scenes'] as $sc) {
    if ($sc['id'] === $sceneId) {
        $file = $sc['file'];
    }
}
if ($file === null) {
    deny(404, 'not found');
}

if ($exp === 0 || $sig === '') {
    deny(403, 'missing token');
}
if (!hash_equals(sig($tourId, $sceneId, $exp), $sig)) {
    deny(403, 'invalid signature');
}
if ($exp < time()) {
    deny(403, 'link expired');
}

reject_foreign_context();

// The viewer loads panoramas as images. Opening the URL as a page (address bar, new tab,
// iframe) is never legitimate, so refuse it.
if (in_array(sec_fetch('dest'), ['document', 'iframe', 'frame', 'embed', 'object'], true)) {
    deny(403, 'direct viewing is not allowed');
}

$path = __DIR__ . '/private/tours/' . $tourId . '/' . basename($file);
if (!is_file($path)) {
    deny(404, 'file missing on disk');
}

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('Cross-Origin-Resource-Policy: ' . cfg('corp'));
header('X-Content-Type-Options: nosniff');
readfile($path);
