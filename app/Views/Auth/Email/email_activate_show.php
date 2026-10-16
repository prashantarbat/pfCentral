<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.emailActivateTitle') ?> <?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="signin">
  <div class="content">
    <!-- Page Title -->
    <h2><?= lang('Auth.emailActivateTitle') ?></h2>

    <!-- Session Messages -->
    <?php if (session('error') !== null) : ?>
      <div class="alert" style="color: red; font-size: 14px;"><?= session('error') ?></div>
    <?php endif ?>

    <!-- Activation Instructions -->
    <p style="color: #fff; font-size: 1em; margin-bottom: 20px;">
      <?= lang('Auth.emailActivateBody') ?>
    </p>

    <!-- Activation Form -->
    <div class="form">
      <form action="<?= url_to('auth-action-verify') ?>" method="POST">
        <?= csrf_field() ?>

        <!-- Token Input -->
        <div class="inputBox">
          <input type="text" name="token" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder=" " value="<?= old('token') ?>" required>
          <i><?= lang('Auth.token') ?></i>
        </div>
        <div style="height: 20px;"></div>

        <!-- Submit Button -->
        <div class="inputBox">
          <input type="submit" value="<?= lang('Auth.send') ?>">
        </div>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
