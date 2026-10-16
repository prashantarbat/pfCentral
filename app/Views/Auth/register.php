<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>
Signup/Register
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="register">
  <div class="content">
    <!-- Session messages -->
    <?php if (session('error')): ?>
      <div class="alert" style="color: red; font-size: 14px;"><?= session('error') ?></div>
    <?php elseif (session('errors')): ?>
      <div class="alert" style="color: red; font-size: 14px;">
        <?php foreach ((array) session('errors') as $error): ?>
          <?= $error ?><br>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <?php if (session('message')): ?>
      <div class="alert" style="color: green; font-size: 14px;"><?= session('message') ?></div>
    <?php endif ?>

    <h2><i class="fas fa-file-contract fa-lg" style="color: #9e1010;"></i> SIGN UP</h2>
    <div class="form">
      <form action="<?= url_to('register') ?>" method="POST">
        <?= csrf_field() ?>

        <!-- First Name -->
        <div class="inputBox">
          <input type="text" name="first_name" autocomplete="given-name" value="<?= old('first_name') ?>" required>
          <i>First Name</i>
        </div>
        <div style="height: 10px;"></div>

        <!-- Last Name -->
        <div class="inputBox">
          <input type="text" name="last_name" autocomplete="family-name" value="<?= old('last_name') ?>" required>
          <i>Last Name</i>
        </div>
        <div style="height: 10px;"></div>

        <!-- Mobile Number -->
        <div class="inputBox">
          <input type="tel" name="mobile_number" autocomplete="tel" inputmode="numeric" value="<?= old('mobile_number') ?>" maxlength="10" pattern="[0-9]{10}" required>
          <i>10-digit Mobile Number</i>
        </div>
        <div style="height: 10px;"></div>

        <!-- Email -->
        <div class="inputBox">
          <input type="email" name="email" autocomplete="email" value="<?= old('email') ?>" required>
          <i>Email</i>
        </div>
        <div style="height: 10px;"></div>

        <!-- Password -->
        <div class="inputBox">
          <input type="password" name="password" autocomplete="new-password" required>
          <i><?= lang('Auth.password') ?></i>
        </div>
        <div style="height: 10px;"></div>

        <!-- Confirm Password -->
        <div class="inputBox">
          <input type="password" name="password_confirm" autocomplete="new-password" required>
          <i>Confirm Password</i>
        </div>
        <div style="height: 20px;"></div>

        <!-- Agree to Terms -->
        <div style="margin-bottom: 10px;">
          <div class="links">
            <label style="color: #fff; font-size: 14px;">
              <input type="checkbox" name="agreeTerms" required> I agree to the <a href="#">terms</a>
            </label>
          </div>
        </div>

        <!-- Already have a membership -->
        <div class="links">
          <a href="<?= base_url('login') ?>">I already have a membership</a>
        </div>
        <div style="height: 20px;"></div>

        <!-- Submit Button -->
        <div class="inputBox">
          <input type="submit" value="Register">
        </div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    
    form.addEventListener("submit", function (e) {
        e.preventDefault(); // Prevent default form submission

        let formData = new FormData(form);

        fetch("<?= url_to('register') ?>", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest" // Ensures it's treated as an AJAX request
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire({
                    icon: "success",
                    title: "Registration Successful!",
                    text: data.message,
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "<?= site_url('login') ?>"; // Redirect to login
                });
            } else {
                let errorMessage = data.errors ? Object.values(data.errors).join("\n") : "An error occurred.";
                Swal.fire({
                    icon: "error",
                    title: "Registration Failed",
                    text: errorMessage
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: "error",
                title: "Oops...",
                text: "Something went wrong. Please try again."
            });
        });
    });
});
</script>
</div>
<?= $this->endSection() ?>
