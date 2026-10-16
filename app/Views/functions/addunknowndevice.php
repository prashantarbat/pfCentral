<!-- Modal for Unknown Device -->
<div class="modal fade" id="updateDeviceModal" tabindex="-1" role="dialog" aria-labelledby="updateDeviceLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="updateDeviceLabel">UPDATE DEVICE DETAILS</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="updateDeviceForm">
                <div class="modal-body">
                    <input type="hidden" id="deviceId" name="id">

                    <!-- First Row for Device Name and Asset Tag -->
                    <div class="form-row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="name" style="color: var(--text-strong); font-weight: 600;">Device/Location
                                    Name *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    placeholder="For easy identification." required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="serial"
                                    style="color: var(--text-strong); font-weight: 600;">Asset/Tag</label>
                                <input type="text" class="form-control" id="serial" name="serial"
                                    placeholder="Device Serial Number/Tag">
                            </div>
                        </div>
                    </div>

                    <!-- Second Row for Group and Platform -->
                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="group_id" style="color: var(--text-strong); font-weight: 600;">Group</label>
                                <select class="form-control select2" id="group_id" name="group_id" required>
                                    <?php foreach ($groups as $group) { ?>
                                        <option value="<?= htmlspecialchars($group['id']); ?>">


                                            <?= htmlspecialchars($group['name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="platform"
                                    style="color: var(--text-strong); font-weight: 600;">Platform</label>
                                <select class="form-control select2" id="platform" name="platform" required>
                                    <option value="pfSenseCE">pfSense CE</option>
                                    <option value="pfSense +">pfSense +</option>
                                    <option value="OPNsense">OPNsense</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Location Field -->
                    <div class="form-group row">
                        <label for="location_id" class="col-md-3 col-form-label"
                            style="color: var(--text-strong); font-weight: 600;">Location *</label>
                        <div class="col-md-9">
                            <select class="form-control select2" id="location_id" name="location_id" required>
                                <option value="" disabled selected>Select location</option>
                                <!-- Default empty option -->
                                <?php foreach ($locations as $location) { ?>


                                    <option value="<?= htmlspecialchars($location['id']); ?>">
                                        <?= htmlspecialchars($location['name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>


                    <!-- Third Row for Username and Password -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="deviceUsername" style="color: var(--text-strong); font-weight: 600;">Username
                                *</label>
                            <input type="text" class="form-control" id="deviceUsername" name="username">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="devicePassword" style="color: var(--text-strong); font-weight: 600;">Password
                                *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="devicePassword" name="password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-warning" type="button" id="togglePassword">
                                        <i class="fas fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API and Notes Fields
                    <div class="form-group">
                        <label for="deviceAPi" style="color: var(--text-strong); font-weight: 600;">REST API</label>
                        <input type="text" class="form-control" id="deviceAPi" name="api">
                    </div>
                     -->

                    <div class="form-group">
                        <label for="notes"
                            style="color: var(--text-strong); font-weight: 600;">Notes/Description</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div> <!-- Closing modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn bg-gradient-warning btn-sm text-dark font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Save Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- END Modal for Updating Device -->

<script>
    // Safe SweetAlert parameter getter fallback if not already globally defined in footer
    if (typeof window.getSwalParams !== 'function') {
        window.getSwalParams = function () {
            const isDark = document.body.classList.contains('dark-mode');
            return {
                background: isDark ? '#12161c' : '#ffffff',
                color: isDark ? '#f8fafc' : '#0f172a'
            };
        };
    }
    if (typeof getSwalParams !== 'function') {
        var getSwalParams = window.getSwalParams;
    }

    $('#updateDeviceForm').on('submit', function (event) {
        event.preventDefault(); // Prevent default form submission

        var formData = $(this).serialize(); // Serialize the form data
        const swalParams = getSwalParams();

        $.ajax({
            url: "<?= base_url('devices/add-unknown') ?>",
            type: 'POST',
            data: formData,  // Send serialized form data
            success: function (response) {
                if (response.success) {
                    // Success SweetAlert2
                    Swal.fire({
                        ...swalParams,
                        icon: 'success',
                        title: 'Device updated successfully!',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        $('#updateDeviceModal').modal('hide'); // Hide modal on success

                        // Show loading animation before page reload
                        const overlayBg = document.body.classList.contains('dark-mode') ? 'rgba(11, 15, 25, 0.85)' : 'rgba(255, 255, 255, 0.85)';
                        $('body').append(`<div id="loadingOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: ${overlayBg}; z-index: 9999; display: flex; justify-content: center; align-items: center;"><img src="<?= htmlspecialchars(base_url('assets/images/loading.gif')) ?>" alt="Loading..." width="300"></div>`);

                        // Reload the window after a delay
                        setTimeout(function () {
                            location.reload(); // Reload the page
                        }, 1500); // Adjust the delay as needed
                    });
                } else {
                    // Display validation errors if present
                    if (response.errors) {
                        let errorMessages = '';
                        for (let field in response.errors) {
                            errorMessages += response.errors[field] + '\n';
                        }
                        Swal.fire({
                            ...swalParams,
                            icon: 'error',
                            title: 'Validation Failed!',
                            text: errorMessages || 'Something went wrong, please try again.',
                            showConfirmButton: true
                        });
                    } else {
                        // General failure message if no validation errors
                        Swal.fire({
                            ...swalParams,
                            icon: 'error',
                            title: 'Failed to update device',
                            text: response.message || 'Something went wrong.',
                            showConfirmButton: true
                        });
                    }
                }
            },
            error: function () {
                // Handle error
                Swal.fire({
                    ...swalParams,
                    icon: 'error',
                    title: 'Error!',
                    text: 'Unable to submit the form. Please try again.',
                    showConfirmButton: true
                });
            }
        });
    });
</script>

<script>
    $(document).on('click', '#togglePassword', function () {
        // Toggle the type between password and text
        const passwordField = $('#devicePassword');
        const eyeIcon = $('#eyeIcon');

        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');  // Show password
            eyeIcon.removeClass('fa-eye').addClass('fa-eye-slash');  // Change eye icon to eye-slash
        } else {
            passwordField.attr('type', 'password');  // Hide password
            eyeIcon.removeClass('fa-eye-slash').addClass('fa-eye');  // Change eye-slash to eye icon
        }
    });
</script>
<script>
    // Ensure modal is filled with correct device ID using delegated event listener for dynamically loaded AJAX elements
    $(document).on('click', '.update-device-btn', function () {
        var deviceId = $(this).data('device-id');
        $('#deviceId').val(deviceId); // Set the device ID in the modal's hidden field
        $('#updateDeviceModal').modal('show'); // Show the modal manually
    });
</script>