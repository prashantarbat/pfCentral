<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $this->renderSection('title') ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?= base_url('assets/template/') ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="<?= base_url('assets/template/') ?>/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?= base_url('assets/template/') ?>/dist/css/adminlte.min.css">
    <!-- Custom styles -->
    <style>
        .image-column {
            flex: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-column {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        footer {
            text-align: right; /* Align the text to the left */
            padding: 5px 0;
        }

        body {
            height: 100vh; /* Set body height to 100% of viewport height */
            margin: 0; /* Remove default margin */
            overflow: hidden; /* Prevent scrolling */
        }
    </style>
</head>

<body class="hold-transition login-page">

    <main role="main" class="container">
        <div class="row">
            <div class="col image-column">
                <img src="<?= base_url('assets/images/Daco_1973910.png') ?>" alt="Image" class="img-fluid">
            </div>
            <div class="col login-column" style="background-color: #d2dae3;">
                <?= $this->renderSection('main') ?>
            </div>
        </div>
    </main>
    <!-- Footer -->
    <footer>
        <div style="color: #692B61; font-weight: bold;">&copy; <?= date('Y') ?> Your Company Name. All rights reserved.</div>
    </footer>
    <?= $this->renderSection('pageScripts') ?>
    <!-- jQuery -->
    <script src="<?= base_url('assets/template/') ?>/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="<?= base_url('assets/template/') ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="<?= base_url('assets/template/') ?>/dist/js/adminlte.min.js"></script>
</body>
</html>
