<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $this->renderSection('title') ?></title>
  <link rel="stylesheet" href="<?= base_url('assets/css/') ?>stylelogin.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/fontawesome-free/css/all.min.css">
  <!-- Include SweetAlert2 CSS -->
  <link rel="stylesheet" href="<?= base_url('assets/css/') ?>sweetalert2.min.css">

  <!-- SweetAlert2 -->
  <script src="<?= base_url('assets/js/') ?>sweetalert2.all.min.js"></script>
  <script src="<?= base_url('assets/') ?>js/gototop.js"></script>
</head>

<body>
  <header>
    <a href="#" class="logo">
      <!-- Replace with your logo path -->
      <img src="<?= base_url('assets/images/') ?>pfsense_rb.png"" alt=" App Logo">
      <!-- OR -->
      pfCentral
    </a>
  </header>

  <section>
    <section>

      <?php for ($i = 0; $i < 120; $i++): ?>
        <span></span>
      <?php endfor; ?>
      <!-- Render the main content of the specific page -->
      <?= $this->renderSection('content') ?>
    </section>

</body>

</html>