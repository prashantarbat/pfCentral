<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.email2FATitle') ?> <?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="signin">
  <div class="content">
    <!-- Session Messages -->
    <?php if (session('error')) : ?>
      <div class="alert" style="color: red; font-size: 14px;"><?= session('error') ?></div>
    <?php endif ?>

    <!-- Hidden Form -->
    <div class="form">
      <form id="myForm" action="<?= url_to('auth-action-handle') ?>" method="post" style="display: none;">
        <?= csrf_field() ?>

        <!-- Hidden Email Input -->
        <input type="hidden" name="email" value="<?= old('email', $user->email) ?>">

        <!-- Submit Button -->
        <div class="inputBox">
          <input type="submit" value="<?= lang('Auth.send') ?>">
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Auto-submit the form after 3 seconds -->
<script>
  setTimeout(function() {
    document.getElementById('myForm').submit();
  }, 3000); // Adjust the delay as needed (currently set to 3 seconds)
</script>

<?= $this->endSection() ?>
