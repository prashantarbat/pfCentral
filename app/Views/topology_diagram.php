<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= esc($title ?? 'Network Diagram') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>body { margin:0; padding:0; }</style>
</head>
<body style="margin:0;padding:0;">
  <iframe src="<?= base_url('graphify-out/network_diagram.html') ?>" style="border:none;width:100vw;height:100vh;"></iframe>
</body>
</html>
