<?= $this->extend(config('Auth')->views['layout']) ?> 

<?= $this->section('title') ?><?= lang('Auth.useMagicLink') ?> <?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="signin">
  <div class="content">
    <!-- Session messages -->
    <?php if (session('error') !== null) : ?>
      <div class="alert" style="color: red; font-size: 14px;"><?= session('error') ?></div>
    <?php elseif (session('errors') !== null) : ?>
      <div class="alert" style="color: red; font-size: 14px;">
        <?php if (is_array(session('errors'))) : ?>
          <?php foreach (session('errors') as $error) : ?>
            <?= $error ?><br>
          <?php endforeach ?>
        <?php else : ?>
          <?= session('errors') ?>
        <?php endif ?>
      </div>
    <?php endif ?>

    <h2><?= lang('Auth.useMagicLink') ?></h2>
    <div class="form">
      <form action="<?= url_to('magic-link') ?>" method="post">
        <?= csrf_field() ?>

        <!-- Email -->
        <div class="inputBox">
          <input type="email" name="email" autocomplete="email" placeholder=" " value="<?= old('email', auth()->user()->email ?? null) ?>" required>
          <i><?= lang('Auth.email') ?></i>
        </div>
        <div style="height: 20px;"></div>

        <!-- Submit Button -->
        <div class="inputBox">
          <input type="submit" value="<?= lang('Auth.send') ?>">
        </div>
    <!-- Back to Login Link -->
    <div class="links" style="margin-top: 20px;">
      <a href="<?= url_to('login') ?>">
        <?= lang('Auth.backToLogin') ?></a>
    </div>
    </form>
    </div>
    </div>
</div>

<?= $this->endSection() ?>
