<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>
Login
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="signin" style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.3); padding: 20px 25px; width: 100%; max-width: 380px;">
  <div class="content" style="gap: 5px;">
    
    <!-- External Links / Quick Access Buttons -->
    <div style="display: flex; gap: 8px; justify-content: center; margin-bottom: 5px; flex-wrap: wrap;">
        <a href="<?= url_to('live') ?>" style="font-size: 13px; color: #fff; background: linear-gradient(45deg, #ff9a44, #fc6076); padding: 8px 15px; text-decoration: none; border-radius: 20px; font-weight: bold; box-shadow: 0 4px 15px rgba(252, 96, 118, 0.4); transition: transform 0.2s;">
            <i class="fas fa-tachometer-alt"></i> DASHBOARD
        </a>
        <?php if (\App\Support\Monitoring::librenmsEnabled()): ?>
        <a href="http://nms.pfcentral.local/login" target="_blank" style="font-size: 13px; color: #fff; background: linear-gradient(45deg, #11998e, #38ef7d); padding: 8px 15px; text-decoration: none; border-radius: 20px; font-weight: bold; box-shadow: 0 4px 15px rgba(56, 239, 125, 0.4); transition: transform 0.2s;">
            <i class="fas fa-network-wired"></i> LibreNMS
        </a>
        <?php endif; ?>
        <!-- Always-light page: ask the diagram for the light palette explicitly rather
             than letting it fall back to a dark theme stored from a previous session. -->
        <a href="<?= base_url('graphify-out/network_diagram.html') . '?theme=light' ?>" target="_blank" style="font-size: 13px; color: #fff; background: linear-gradient(45deg, #4facfe, #00f2fe); padding: 8px 15px; text-decoration: none; border-radius: 20px; font-weight: bold; box-shadow: 0 4px 15px rgba(0, 242, 254, 0.4); transition: transform 0.2s;">
            <i class="fas fa-project-diagram"></i> Network Links
        </a>
    </div>

    <!-- Session messages -->
    <?php if (session('error')): ?>
      <div class="alert" style="color: #ff4757; font-size: 14px; background: rgba(255, 71, 87, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; border: 1px solid rgba(255, 71, 87, 0.3);"><?= session('error') ?></div>
    <?php elseif (session('errors')): ?>
      <div class="alert" style="color: #ff4757; font-size: 14px; background: rgba(255, 71, 87, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; border: 1px solid rgba(255, 71, 87, 0.3);">
        <?php foreach ((array) session('errors') as $error): ?>
          <?= $error ?><br>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <?php if (session('message')): ?>
      <div class="alert" style="color: #2ed573; font-size: 14px; background: rgba(46, 213, 115, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; border: 1px solid rgba(46, 213, 115, 0.3);"><?= session('message') ?></div>
    <?php endif ?>

    <h2 style="color: #fff; text-align: center; margin-bottom: 5px; margin-top: 0; font-size: 24px; font-weight: 600; letter-spacing: 1px;">
        <i class="fas fa-fingerprint" style="color: #00f2fe; margin-right: 8px; font-size: 26px;"></i> Sign In
    </h2>
    
    <div class="form" style="gap: 10px;">
      <form action="<?= url_to('login') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="inputBox">
          <input type="text" name="email" required placeholder=" " value="<?= old('email') ?>">
          <i>Username or Email</i>
        </div>

        <div class="inputBox">
          <input type="password" name="password" required placeholder=" ">
          <i><?= lang('Auth.password') ?></i>
        </div>

        <!-- Remember Me (Optional) -->
        <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
          <div style="margin-bottom: 20px; display: flex; align-items: center;">
            <label style="color: #fff; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" name="remember" style="accent-color: #00f2fe; width: 16px; height: 16px;"> <?= lang('Auth.rememberMe') ?>
            </label>
          </div>
        <?php endif ?>

        <div class="links" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
          <a href="<?= url_to('magic-link') ?>" style="color: #00f2fe; text-decoration: none; font-size: 14px; transition: color 0.3s;">Forgot Password?</a>
          <?php if (setting('Auth.allowRegistration')): ?>
            <?php $disabled = true; ?>
            <a href="<?= $disabled ? '#' : url_to('register') ?>"
               style="font-size: 14px; color: <?= $disabled ? 'rgba(255,255,255,0.4)' : '#00f2fe' ?>; text-decoration: none; <?= $disabled ? 'pointer-events:none; cursor:not-allowed;' : '' ?>">
                Sign Up
            </a>
          <?php endif ?>
        </div>

        <div style="width: 100%; margin: 0;">
          <input type="submit" value="Login" style="width: 100%; background: #fff; color: #333; max-width: 150px; border: none; padding: 12px 20px; border-radius: 30px; font-size: 16px; font-weight: bold; letter-spacing: 1px; cursor: pointer; transition: 0.3s; margin: 0 auto; display: block; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
        </div>
      </form>
    </div>

    <div class="login-footer" style="position: relative; margin-top: 5px; text-align: center; font-size: 11px; color: #ffc107; line-height: 1.4; border-top: 1px solid rgba(255, 255, 255, 0.15); padding-top: 5px;">
      <p style="margin: 0;">
Unauthorised access or use shall render the user liable to criminal and/or civil prosecution.<br/>&copy; <?= date("Y") ?> 
      </p>
    </div>

  </div>
</div>

<style>
/* Login Input Styling */
.inputBox {
    position: relative;
    margin-bottom: 15px;
    margin-top: 5px;
}
.inputBox input[type="text"],
.inputBox input[type="password"] {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: none;
    border-bottom: 2px solid #fff;
    outline: none;
    padding: 24px 10px 8px 10px; /* Increased top padding to push text down */
    color: #fff;
    font-size: 16px;
    letter-spacing: 1px;
    border-radius: 8px 8px 0 0;
}
.inputBox i {
    position: absolute;
    left: 10px;
    top: 18px; /* Start position inside the input */
    font-size: 16px;
    color: rgba(255, 255, 255, 0.7);
    pointer-events: none;
    transition: 0.3s ease;
}

/* Floating Label Animation */
.inputBox input:focus ~ i,
.inputBox input:not(:placeholder-shown) ~ i,
.inputBox input:valid ~ i {
    top: 6px; /* Move it to the very top edge inside the input */
    font-size: 11px;
    color: #00f2fe;
    font-weight: bold;
    letter-spacing: 1px;
}

.inputBox input[type="submit"]:hover {
    background: #00f2fe;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 242, 254, 0.4);
}
.signin a:hover {
    filter: brightness(1.1);
}
</style>
<?= $this->endSection() ?>
