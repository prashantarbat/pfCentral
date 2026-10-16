<?php

/**
 * The device-configuration page.
 *
 * One page, one form: an operator describes a branch — picking a site, which fills the fields in from
 * the branch record, or typing them — and downloads the pfSense export that describes it.
 *
 * It used to be two pages over this same view, distinguished by a `FORMS` table entry each: an
 * `/newconfig` described from a subnet typed in directly, and a `/newdevice` driven by a location
 * lookup, each carrying its own hero, its own panel line and a link to the other. Both of those are
 * now what this one page does, so the only thing left to render is its description — and that lives
 * in the controller's `PAGE` constant, so the page cannot say something the controller does not.
 *
 * @var array{
 *     title: string,
 *     hero: array{icon: string, title: string, copy: string},
 *     panel: string,
 * } $form      The page's description, from NewConfigController::PAGE
 * @var array    $locations Site rows for the location selector
 */
$user = auth()->user();
?>
<?php if ($user && ($user->can('devices.manage'))): ?>

<?= view('config/partials/config_page_styles') ?>

<div class="config-page-container">

    <!-- HERO HEADER -->
    <div class="glass-panel config-hero-card">
        <div class="hero-title-box">
            <div class="hero-icon-glow">
                <i class="fas <?= $form['hero']['icon'] ?>"></i>
            </div>
            <div class="hero-text">
                <h2><?= esc($form['hero']['title']) ?></h2>
                <p><?= esc($form['hero']['copy']) ?></p>
            </div>
        </div>

        <div class="hero-actions">
            <a href="<?= base_url('location') ?>" class="btn btn-action-ghost" title="Sites & Locations Management">
                <i class="fas fa-map-marker-alt mr-1"></i> Site & Location
            </a>
            <button type="button" class="btn btn-action-ghost" data-toggle="modal" data-target="#installAgentModal">
                <i class="fas fa-terminal mr-1"></i> Post Install
            </button>
        </div>
    </div>

    <!-- MAIN FORM CARD -->
    <div class="glass-panel form-panel">
        <?= view('config/partials/config_form', [
            'panelDescription' => $form['panel'],
            'locations'        => $locations,
            'cryptoSettings'   => $crypto_settings ?? null,
        ]) ?>
    </div>

</div>

<?= view('config/partials/config_install_modal') ?>

<?= view('config/partials/config_form_script') ?>

<?php else: ?>
    <div class="alert alert-danger" role="alert">You are not authorized to access this page.</div>
<?php endif; ?>
