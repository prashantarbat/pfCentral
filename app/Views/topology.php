<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= esc($title ?? 'Network Topology') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #181c2a; color: #fff; margin: 0; padding: 0; }
    .center { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; }
    a { color: #4E79A7; text-decoration: none; font-weight: bold; }
    a:hover { text-decoration: underline; }
    .btn { background: #4E79A7; color: #fff; padding: 12px 28px; border-radius: 8px; font-size: 1.2em; margin-top: 24px; display: inline-block; }
    .btn:hover { background: #37608a; }
  </style>
</head>
<body>
  <div class="center">
    <h1>Network Topology Map</h1>
    <p>This is the main topology landing page.<br>
    <a class="btn" href="<?= base_url('topology/diagram') ?>">View Logical Network Diagram</a></p>
    <p style="margin-top:40px;opacity:0.7;">You can customize this page to show a geo map or other summary.</p>
  </div>
</body>
</html>
