<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.useMagicLink') ?> <?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="signin">
  <div class="content">
    <h2><?= lang('Auth.useMagicLink') ?></h2>

    <!-- Check Your Email Message -->
    <p style="color: #fff; font-size: 1.2em; font-weight: bold; margin-bottom: 20px; text-align: center;">
      <?= lang('Auth.checkYourEmail') ?>
    </p>

    <!-- Magic Link Details -->
    <p style="color: #fff; font-size: 1em; margin-bottom: 20px; text-align: center;">
      <?= lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60]) ?>
    </p>

    <!-- Back to Login Button -->
    <div class="inputBox">
      <a href="<?= url_to('login') ?>" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #fff; font-weight: bold; background-color: #007bff; border-radius: 5px;">
        <?= lang('Auth.backToLogin') ?>
      </a>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
