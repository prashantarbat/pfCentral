<div class="card">
    <div>
        <h4 class="text-center font-weight-bold">USER'S DETAILS</h4>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover">
            <tbody>
                <tr>
                    <td class="font-weight-bold" style="width: 250px;">Username:</td>
                    <td>
                        <?php if (empty($user->username)): ?>
                            <span class="text-danger">Please add username</span>
                        <?php else: ?>
                            <?= esc($user->username) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Email:</td>
                    <td><?= esc($user->email) ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Mobile Number:</td>
                    <td><?= esc($user->mobile_number) ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">First Name:</td>
                    <td><?= esc($user->first_name) ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Middle Name:</td>
                    <td><?= esc($user->middle_name) ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Last Name:</td>
                    <td><?= esc($user->last_name) ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Location:</td>
                    <td><?= esc($user->location) ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Active:</td>
                    <td><?= $user->active ? 'Yes' : 'No' ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Created At:</td>
                    <td><?= $user->created_at->humanize() ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold">Groups:</td>
                    <td>
                        <?php
                            $config = config('AuthGroups');
                            $userGroups = $user->getGroups();
                            $groupTitles = [];

                            foreach ($userGroups as $group) {
                                if (isset($config->groups[$group])) {
                                    $groupTitles[] = $config->groups[$group]['title'];
                                }
                            }

                            echo implode(", ", $groupTitles);
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="text-center">
            <a href="<?= base_url('users') ?>" class="btn btn-secondary btn-sm"><strong><< Back</strong></a>
            <?= form_open("users/" . $user->id . "/toggle-ban", ['class' => 'd-inline']) ?>
                <button type="submit" class="btn btn-sm <?= $user->isBanned() ? 'btn-success' : 'btn-dark' ?>" style="font-weight: bold;">
                    <i class="fas <?= $user->isBanned() ? 'fa-check' : 'fa-ban' ?>"></i>
                    <?= $user->isBanned() ? "Unban" : "Ban" ?>
                </button>
            <?= form_close() ?>
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#editUserModal<?= $user->id ?>">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#groupModal<?= $user->id ?>">
                <i class="fas fa-users"></i> Groups
            </button>
            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#passwordModal<?= $user->id ?>">
                <i class="fas fa-key"></i> Password
            </button>
        </div>

        <!-- Modal for Editing User Groups -->
        <div class="modal fade" id="groupModal<?= $user->id ?>" tabindex="-1" role="dialog" aria-labelledby="groupModalLabel<?= $user->id ?>" aria-hidden="true" style="z-index: 1060;">
            <div class="modal-dialog" role="document">
                <div class="modal-content bg-warning">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupModalLabel<?= $user->id ?>">EDIT USER'S GROUPS</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?= form_open("users/" . $user->id . "/groups") ?>
                        <div class="form-group">
                            <?php foreach ($groups as $group => $info): ?>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="groups[]" value="<?= $group ?>"
                                               <?= $user->inGroup($group) ? "checked" : "" ?>
                                               > <?= $info['title'] ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-save"></i> Save</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Changing User Password -->
        <div class="modal fade" id="passwordModal<?= $user->id ?>" tabindex="-1" role="dialog" aria-labelledby="passwordModalLabel<?= $user->id ?>" aria-hidden="true" style="z-index: 1060;">
            <div class="modal-dialog" role="document">
                <div class="modal-content bg-danger">
                    <div class="modal-header">
                        <h5 class="modal-title" id="passwordModalLabel<?= $user->id ?>">CHANGE PASSWORD</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?= form_open("users/changePassword/" . $user->id, ['method' => 'post']) ?>
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" name="new_password" class="form-control" id="newPassword" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-dark btn-sm"><i class="fas fa-save"></i> Save</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Editing User Details -->
        <div class="modal fade" id="editUserModal<?= $user->id ?>" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel<?= $user->id ?>" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content bg-secondary">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel<?= $user->id ?>">EDIT USER'S DETAILS</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?= form_open("users/edit/" . $user->id, ['id' => 'editUserForm']) ?>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?= esc($user->username) ?>">
                            </div>
                            <div class="form-group col-md-8">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= esc($user->email) ?>" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= esc($user->first_name) ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= esc($user->middle_name) ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= esc($user->last_name) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="mobile_number">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile_number" name="mobile_number" value="<?= esc($user->mobile_number) ?>" 
                                       pattern="^[6-9]\d{9}$" maxlength="10" required 
                                       title="Please enter a valid 10-digit Indian mobile number starting with 6-9.">
                                <small class="form-text text-muted">Enter a valid 10-digit Indian mobile number starting with 6-9.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger btn-sm">Save changes</button>
                            <button type="button" class="btn btn-dark btn-sm" data-dismiss="modal">Close</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>