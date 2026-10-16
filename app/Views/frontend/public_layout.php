<?php
/**
 * Public page layout.
 *
 * The whole shell for pages that are reachable without logging in — currently the
 * /live branch-status dashboard. It sits next to the authenticated chrome
 * (header.php / sidebar.php / footer.php) and deliberately shares none of it.
 *
 * Why a separate layout instead of flags inside header.php: the authenticated
 * shell is navbar + sidebar + user panel + logout. Hiding those one at a time
 * means every future addition to the admin chrome lands on the public page by
 * default and has to be opted back out — and anything that is forgotten leaks.
 * Here there is no admin element to forget, because none is rendered.
 *
 * What it does keep, so the page still looks like the rest of the app:
 *   - fontawesome (the dashboard's status icons)
 *   - adminlte.min.css, which bundles Bootstrap 4, the utility classes the
 *     dashboard markup uses (container-fluid, table, btn, row/col) and the theme
 *   - the CSRF bootstrap, so any future form/fetch on a public page works
 *   - the scroll-to-top helper, which needs a jQuery-loaded .wrapper to attach to
 *
 * Expects:
 *   $content  rendered page body (required)
 *   $title    browser tab title
 *   $app_name / $app_version  footer branding, as the admin footer uses
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Live Status') ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style (bundles Bootstrap) -->
    <link rel="stylesheet" href="<?= base_url('assets/template/') ?>dist/css/adminlte.min.css">

    <!-- jQuery is loaded before the scroll-to-top helper that depends on it -->
    <script src="<?= base_url('assets/js/') ?>jquery-3.7.1.min.js"></script>
    <script src="<?= base_url('assets/') ?>js/gototop.js"></script>

    <?= view('frontend/csrf') ?>

    <style>
        /*
         * Public shell. No layout-* body classes and no .main-header, so nothing
         * reserves navbar height and the content starts flush at the top of the page.
         */
        body.live-public-body {
            margin: 0;
            background: #f6f8fb;
        }
        .live-public-shell {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .live-public-main { flex: 1 1 auto; }
        .live-public-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #64748b;
            background: #ffffff;
            border-top: 1px solid #e6ebf2;
        }
        .live-public-footer a { color: inherit; }
    </style>
</head>
<body class="live-public-body">
    <div class="wrapper live-public-shell">
        <div class="live-public-main">
            <?= $content ?>
        </div>

        <footer class="live-public-footer">
            <span>
                &copy; <?= date('Y') ?> <?= esc($app_name ?? 'My Application') ?>
                &middot; <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a>
            </span>
            <?php if (! empty($app_version)): ?>
                <span>Version <?= esc($app_version) ?></span>
            <?php endif; ?>
        </footer>
    </div>
</body>
</html>
