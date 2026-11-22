<?php
// The 360 tour page. This is the page other sites would try to put in an <iframe>.
require __DIR__ . '/lib.php';

$tours = cfg('tours');
$id = (string)($_GET['id'] ?? '');
if (!isset($tours[$id])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Tour not found');
}

send_embed_headers();   // CSP frame-ancestors (+ X-Frame-Options for old browsers)
enforce_embedder();     // 403 when a disallowed site frames us

header('Cache-Control: no-store');

// Fresh signed links for the viewer (called again before the links expire).
if (isset($_GET['json'])) {
    reject_foreign_context();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(build_scenes($id), JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$data = build_scenes($id) + ['id' => $id, 'title' => $tours[$id]['title']];
$flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($data['title']) ?> - 360 tour</title>
  <style>
    html, body { margin: 0; height: 100%; background: #111; overflow: hidden; font-family: system-ui, sans-serif; }
    #c { display: block; width: 100%; height: 100%; touch-action: none; cursor: grab; }
    #c:active { cursor: grabbing; }
    #bar { position: fixed; left: 0; right: 0; bottom: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: linear-gradient(transparent, rgba(0,0,0,.65)); }
    #bar button { font: inherit; color: #fff; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.4); border-radius: 999px; padding: 6px 16px; cursor: pointer; }
    #bar button.on { background: #fff; color: #111; }
    #title { position: fixed; top: 10px; left: 14px; color: #fff; font-weight: 600; text-shadow: 0 1px 4px #000; }
    #note { position: fixed; top: 10px; right: 14px; color: #fff; font-size: 13px; text-shadow: 0 1px 4px #000; max-width: 60%; text-align: right; }
  </style>
</head>
<body>
  <canvas id="c"></canvas>
  <div id="title"><?= htmlspecialchars($data['title']) ?></div>
  <div id="note">Drag to look around, scroll to zoom</div>
  <div id="bar"></div>
  <script>window.TOUR = <?= json_encode($data, $flags) ?>;</script>
  <script src="assets/viewer.js"></script>
</body>
</html>
