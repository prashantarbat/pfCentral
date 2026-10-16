<?php
/**
 * One account's profile.
 *
 * `$user` here is the account being looked at, not the person looking: this file used to open with
 * `$user = auth()->user()`, which silently replaced the account it had been handed, so every "USER"
 * link on the list showed the administrator their own profile and posted the form back to their own
 * account. The signed-in account is `$actor`.
 */
$actor = auth()->user();
?>
<?php if ($actor && $actor->can('users.manage')): ?>
<?php $isAd = (bool) ($user->is_ad ?? false); ?>
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Modern Glassmorphic Card -->
            <div class="card" style="background: var(--card-bg); backdrop-filter: blur(16px); border: 1px solid var(--card-border); border-radius: 16px; box-shadow: var(--card-shadow); overflow: hidden;">
                
                <!-- Vivid Header Gradient -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; position: relative;">
                    <div class="text-center position-relative" style="z-index: 2; margin-top: 20px;">
                        <img src="http://ssl.gstatic.com/accounts/ui/avatar_2x.png" class="rounded-circle shadow-lg mb-3" alt="avatar" style="width: 120px; border: 4px solid rgba(255,255,255,0.3);">
                        <h2 class="text-white font-weight-bold mb-1"><?= esc($user->first_name . ' ' . $user->last_name) ?></h2>
                        <h6 class="text-light mb-3"><?= esc($user->email) ?></h6>

                        <!-- Role and state, because "who is this and what may they do" is the question this page answers. -->
                        <div class="mb-3">
                            <?php foreach ($roles as $role): ?>
                                <span class="badge" style="background-color: rgba(23,162,184,0.85); color: #fff; font-size: 13px; padding: 6px 12px; border-radius: 30px;"><?= esc(strtoupper($role)) ?></span>
                            <?php endforeach; ?>
                            <?php if ($roles === []): ?>
                                <span class="badge" style="background-color: rgba(0,0,0,0.35); color: #fff; font-size: 13px; padding: 6px 12px; border-radius: 30px;" title="Nobody gave this account a role, so it can sign in and reach nothing.">NO ROLE</span>
                            <?php endif; ?>
                            <span class="badge" style="background-color: <?= $user->active ? 'rgba(40,167,69,0.85)' : 'rgba(220,53,69,0.85)' ?>; color: #fff; font-size: 13px; padding: 6px 12px; border-radius: 30px;">
                                <i class="fas <?= $user->active ? 'fa-check' : 'fa-times' ?>"></i> <?= $user->active ? 'ACTIVE' : 'INACTIVE' ?>
                            </span>
                            <?php if ($user->isBanned()): ?>
                                <span class="badge" style="background-color: rgba(220,53,69,0.85); color: #fff; font-size: 13px; padding: 6px 12px; border-radius: 30px;">
                                    <i class="fas fa-ban"></i> BANNED
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- AD Badge -->
                        <?php if ($isAd): ?>
                            <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; font-size: 14px; padding: 8px 16px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.5); backdrop-filter: blur(4px);">
                                <i class="fas fa-server mr-1"></i> ACTIVE DIRECTORY USER
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(0,0,0,0.2); color: #fff; font-size: 14px; padding: 8px 16px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(4px);">
                                <i class="fas fa-user mr-1"></i> LOCAL USER
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-5">
                    <h4 class="mb-4 text-primary font-weight-bold border-bottom pb-2"><i class="fas fa-user-edit"></i> Profile Details</h4>
                    <form class="form" action="<?= base_url('users/update/' . $user->id) ?>" method="post" id="registrationForm">
                        <?= csrf_field() ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="font-weight-bold text-muted">Username</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-at"></i></span>
                                    </div>
                                    <input type="text" class="form-control form-control-lg" name="username" value="<?= esc($user->username) ?>" placeholder="Username" pattern="[A-Za-z0-9.]{3,30}" title="3 to 30 characters of letters, numbers and dots." <?= ($isAd) ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label class="font-weight-bold text-muted">Mobile Number</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-phone"></i></span>
                                    </div>
                                    <input type="text" class="form-control form-control-lg" name="mobile_number" value="<?= esc($user->mobile_number) ?>" placeholder="Mobile number" <?= ($isAd) ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <label class="font-weight-bold text-muted">First Name</label>
                                <input type="text" class="form-control form-control-lg" name="first_name" value="<?= esc($user->first_name) ?>" placeholder="First name" <?= ($isAd) ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label class="font-weight-bold text-muted">Middle Name</label>
                                <input type="text" class="form-control form-control-lg" name="middle_name" value="<?= esc($user->middle_name) ?>" placeholder="Middle name" <?= ($isAd) ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label class="font-weight-bold text-muted">Last Name</label>
                                <input type="text" class="form-control form-control-lg" name="last_name" value="<?= esc($user->last_name) ?>" placeholder="Last name" <?= ($isAd) ? 'readonly' : '' ?>>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="font-weight-bold text-muted">Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-map-marker-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control form-control-lg" name="location" value="<?= esc($user->location) ?>" placeholder="Location" <?= ($isAd) ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="font-weight-bold text-muted">Department</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control form-control-lg" name="department" value="<?= esc($user->department) ?>" placeholder="Department" <?= ($isAd) ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-center">
                            <?php if ($isAd): ?>
                                <p class="text-danger small"><i class="fas fa-info-circle"></i> Profile details for AD users are synchronized automatically and cannot be edited locally.</p>
                            <?php else: ?>
                                <button class="btn btn-lg btn-success px-5 rounded-pill shadow-sm" type="submit"><i class="fas fa-save mr-2"></i> Save Changes</button>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left mr-2"></i> All users</a>
                                <a href="<?= base_url('roles') ?>" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-user-shield mr-2"></i> Roles &amp; permissions</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="alert alert-danger m-4" role="alert">
        <i class="fas fa-exclamation-triangle"></i> You are not authorized to access this page.
    </div>
<?php endif; ?>