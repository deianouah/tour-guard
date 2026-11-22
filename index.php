<?php
require __DIR__ . '/lib.php';
$tours = cfg('tours');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>tour-guard</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.7; }
    .card { border: 1px solid #ccc; border-radius: 10px; padding: 1rem; margin: 1rem 0; }
    code { background: #f1f1f1; padding: 0 4px; border-radius: 3px; }
  </style>
</head>
<body>
  <h1>tour-guard</h1>
  <p>360 tours that only your own site (and sites you allow) can display. No accounts needed.</p>

  <div class="card">
    <h3>Your tours (owner view)</h3>
    <ul>
      <?php foreach ($tours as $id => $t): ?>
        <li><a href="tour.php?id=<?= urlencode($id) ?>"><?= htmlspecialchars($t['title']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <h3>Try to steal it</h3>
    <p>Open <a href="attacker.html"><code>attacker.html</code></a> through the <b>other</b> host name
    (<code>127.0.0.1</code> if you are on <code>localhost</code>, and vice versa). It plays the role of a foreign
    website that embeds your tour. The frame should stay empty.</p>
  </div>
</body>
</html>
