<!-- Shared Action Modal Structure -->
<div class="modal fade" id="sharedActionModal" tabindex="-1" role="dialog" aria-labelledby="sharedActionModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 350px;" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="sharedActionModalLabel"> Loading... </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="sharedActionModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-warning" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-xs" data-dismiss="modal"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Safe SweetAlert parameter getter fallback if not already globally defined in footer
if (typeof window.getSwalParams !== 'function') {
    window.getSwalParams = function() {
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

window.APP_BASE_URL = window.APP_BASE_URL || (function() {
    const path = window.location.pathname;
    if (path.includes('/pfcentral.local/')) {
        return window.location.origin + '/pfcentral.local/';
    }
    return window.location.origin + '/';
})();

function openPfWebGUI(id) {
    fetch(window.APP_BASE_URL + 'pfsense/pfopen/' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                const errParams = getSwalParams();
                Swal.fire({
                    ...errParams,
                    title: 'Error',
                    text: data.error,
                    icon: 'error'
                });
            } else {
                let a = document.createElement('a');
                a.href = data.url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
        })
        .catch(error => {
            const errParams = getSwalParams();
            Swal.fire({
                ...errParams,
                title: 'Error',
                text: 'Error opening WebGUI.',
                icon: 'error'
            });
        });
}

$(document).on('click', '.action-btn', function (e) {
    e.preventDefault();
    const deviceId = $(this).data('id');
    const deviceName = $(this).data('name');
    
    $('#sharedActionModalLabel').text(deviceName);
    $('#sharedActionModalBody').html('<div class="text-center py-4"><div class="spinner-border text-warning" role="status"><span class="sr-only">Loading...</span></div></div>');
    $('#sharedActionModal').modal('show');
    
    $.get(window.APP_BASE_URL + 'pfsense/actionModal/' + deviceId, function(response) {
        $('#sharedActionModalBody').html(response);
    }).fail(function() {
        $('#sharedActionModalBody').html('<div class="alert alert-danger">Failed to load actions.</div>');
    });
});

$(document).ready(function () {
    $(document).on('click', '.delete_btn', function () {
        const deviceId = $(this).data('id');
        const swalParams = getSwalParams();

        Swal.fire({
            ...swalParams,
            title: 'Enter master password to delete!',
            input: 'password',
            inputPlaceholder: 'Enter master password...',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            reverseButtons: true,
            willClose: () => {
                if (!$('.swal2-container').length) {
                    $('#sharedActionModal').modal('show');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const enteredPassword = result.value;
                const secretPassword = $('#masterPassword').val();

                if (enteredPassword === secretPassword) {
                    // Ensure the modal is hidden
                    $('#sharedActionModal').modal('hide');
                    
                    fetch(window.APP_BASE_URL + `pfsense/delete/${deviceId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'master_password=' + encodeURIComponent(enteredPassword)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' || data.success) {
                            const successParams = getSwalParams();
                            Swal.fire({
                                ...successParams,
                                title: 'Deleted!',
                                text: data.message || 'Device deleted',
                                icon: 'success'
                            }).then(() => {
                                location.reload(); 
                            });
                        } else {
                            const errorParams = getSwalParams();
                            Swal.fire({
                                ...errorParams,
                                title: 'Error',
                                text: data.message || 'Failed to delete',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Delete failed', error);
                        const errorParams = getSwalParams();
                        Swal.fire({
                            ...errorParams,
                            title: 'Error',
                            text: 'Failed to delete device.',
                            icon: 'error'
                        });
                    });
                } else {
                    const errorParams = getSwalParams();
                    Swal.fire({
                        ...errorParams,
                        title: 'Error!',
                        text: 'Incorrect master password. Device not deleted.',
                        icon: 'error'
                    }).then(() => {
                        $('#sharedActionModal').modal('show');
                    });
                }
            }
        });
    });

    // Centralized Edit Device Click Handler
    $(document).on('click', '.edit_btn', function (e) {
        e.preventDefault();
        const deviceId = $(this).data('id');
        const editUrl = window.APP_BASE_URL + "pfsense/edit/" + deviceId;

        // Hide action modal if open
        $('#sharedActionModal').modal('hide');

        $.ajax({
            url: editUrl,
            method: 'GET',
            success: function(response) {
                if (response) {
                    $('#editDeviceId').val(response.id);
                    $('#deviceLocation').val(response.location);
                    $('#deviceIP').val(response.man_ip || response.ip);
                    $('#devicePort').val(response.man_port || response.port);
                    $('#deviceName').val(response.name);
                    $('#deviceSerial').val(response.serial);
                    $('#devicePlatform').val(response.platform || 'pfSenseCE');
                    $('#deviceGroup').val(response.groups);
                    $('#editDeviceAPi').val(response.api);
                    $('#editDeviceUsername').val(response.username);
                    $('#editDevicePassword').val(response.password);
                    $('#deviceNotes').val(response.notes);
                    
                    // Set check state for bypass
                    $('#deviceBypass').prop('checked', response.bypass == 1);

                    // Show edit modal
                    $('#editDeviceModal').modal('show');
                } else {
                    const failParams = getSwalParams();
                    Swal.fire({
                        ...failParams,
                        title: 'Error!',
                        text: 'Failed to fetch device data.',
                        icon: 'error'
                    });
                }
            },
            error: function() {
                const errParams = getSwalParams();
                Swal.fire({
                    ...errParams,
                    title: 'Error!',
                    text: 'Failed to connect to server.',
                    icon: 'error'
                });
            }
        });
    });

    // Centralized Edit Device Submit Handler
    $(document).on('submit', '#editDeviceForm', function (e) {
        e.preventDefault();
        
        // Hide edit modal for SweetAlert
        $('#editDeviceModal').modal('hide');

        const promptParams = getSwalParams();
        Swal.fire({
            ...promptParams,
            title: 'Enter master password to save!',
            input: 'password',
            inputPlaceholder: 'Enter master password...',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: '<span style="color:#000;"><strong>Yes, save it!</strong></span>',
            cancelButtonColor: '#d33',
            willClose: () => {
                // Re-show edit modal if not confirmed or complete
                if (!$('.swal2-container').length) {
                    $('#editDeviceModal').modal('show');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const enteredPassword = result.value;
                const secretPassword = $('#masterPassword').val();

                if (enteredPassword === secretPassword) {
                    const formData = $(this).serialize();
                    const deviceId = $('#editDeviceId').val();

                    $.ajax({
                        url: window.APP_BASE_URL + "pfsense/update/" + deviceId,
                        method: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                const okParams = getSwalParams();
                                Swal.fire({
                                    ...okParams,
                                    title: 'Updated!',
                                    text: 'Device updated successfully.',
                                    icon: 'success'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                const failParams = getSwalParams();
                                Swal.fire({
                                    ...failParams,
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error'
                                }).then(() => {
                                    $('#editDeviceModal').modal('show');
                                });
                            }
                        },
                        error: function() {
                            const errParams = getSwalParams();
                            Swal.fire({
                                ...errParams,
                                title: 'Error!',
                                text: 'Failed to update device.',
                                icon: 'error'
                            }).then(() => {
                                $('#editDeviceModal').modal('show');
                            });
                        }
                    });
                } else {
                    const passParams = getSwalParams();
                    Swal.fire({
                        ...passParams,
                        title: 'Error!',
                        text: 'Incorrect master password. Configuration not saved.',
                        icon: 'error'
                    }).then(() => {
                        $('#editDeviceModal').modal('show');
                    });
                }
            } else {
                $('#editDeviceModal').modal('show');
            }
        });
    });

    // Toggle API password visibility inside the edit modal. Scoped with [id^=editTogglePassword]
    // instead of the plain #id so it only ever touches this modal's field — the Update (unknown
    // device) modal has its own toggle with its own id, and the old shared #togglePassword id made
    // both handlers fire at once, cancelling each other out.
    $(document).on('click', '[id^=editTogglePassword]', function() {
        const passwordField = $('#editDevicePassword');
        const eyeIcon = $('#editEyeIcon');
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            eyeIcon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            eyeIcon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
</script>
