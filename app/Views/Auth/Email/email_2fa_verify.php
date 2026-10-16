<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.email2FATitle') ?> <?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="signin">
  <div class="content">
    <!-- Page Title -->
    <h2><?= lang('Auth.emailEnterCode') ?></h2>

    <!-- Confirmation Message -->
    <p style="color: #fff; font-size: 1em; margin-bottom: 20px;">
      <?= lang('Auth.emailConfirmCode') ?>
    </p>

    <!-- Session Messages -->
    <?php if (session('error') !== null) : ?>
      <div class="alert" style="color: red; font-size: 14px;"><?= session('error') ?></div>
    <?php endif ?>

    <!-- Code Verification Form -->
    <div class="form">
      <form action="<?= url_to('auth-action-verify') ?>" method="post">
        <?= csrf_field() ?>

        <!-- Code Input -->
        <div class="inputBox">
          <input type="number" name="token" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder=" " required>
          <i><?= lang('Auth.token') ?></i>
        </div>
        <div style="height: 20px;"></div>

        <!-- Submit Button -->
        <div class="inputBox">
          <input type="submit" value="<?= lang('Auth.confirm') ?>">
        </div>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
