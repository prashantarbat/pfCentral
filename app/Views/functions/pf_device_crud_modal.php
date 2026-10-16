<?php
$crudMasterPass = '';
if (isset($mast_pass)) {
    $crudMasterPass = $mast_pass;
} else {
    try {
        $coreConfigModel = new \App\Models\CoreConfigModel();
        $mpassRow = $coreConfigModel->where('name', 'mast_pass')->first();
        $crudMasterPass = $mpassRow['value'] ?? 'p@ssw0rd';
    } catch (\Throwable $e) {
        $crudMasterPass = 'p@ssw0rd';
    }
}
?>
<input type="hidden" id="pfCrudMasterPasswordFallback" value="<?= esc($crudMasterPass) ?>">

<!-- pf_devices CRUD Modal -->
<div class="modal fade" id="pfDeviceCrudModal" tabindex="-1" aria-labelledby="pfDeviceCrudModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content"
            style="background: var(--modal-bg) !important; backdrop-filter: blur(25px) saturate(160%) !important; border: 1px solid var(--modal-border) !important; border-radius: 20px !important; box-shadow: var(--modal-shadow) !important;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title font-weight-bold" id="pfDeviceCrudModalLabel"
                    style="color: var(--text-strong) !important;">
                    <i class="fas fa-network-wired mr-2 text-primary"></i> Device Configuration
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close"
                    style="outline: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <form id="pfDeviceCrudForm">
                    <input type="hidden" id="pf_device_id" name="id">
                    <input type="hidden" id="pf_device_br_name" name="br_name">

                    <div class="row form-group mb-2">
                        <div class="col-md-4">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">Hostname</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_hostname"
                                name="hostname" required placeholder="branch-fw-1" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-4">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">LAN IP</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_lan_ip" name="lan_ip"
                                required placeholder="192.168.1.1" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-4">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">LAN Subnet</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_lan_subnet"
                                name="lan_subnet" required placeholder="24" style="height:28px;font-size:12px;"
                                value="24">
                        </div>
                    </div>

                    <div class="row form-group mb-1">
                        <div class="col-md-12">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">Description / Notes</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_description"
                                name="description" placeholder="Device description or branch notes..."
                                style="height:28px;font-size:12px;">
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-info mt-2 mb-1 border-bottom pb-1" style="font-size:12px;"><i
                            class="fas fa-project-diagram mr-1"></i> MPLS Interface</h6>
                    <div class="row form-group mb-1">
                        <div class="col-md-3">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">MPLS IP</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_mpls_ip"
                                name="mpls_ip" placeholder="Address" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-3">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">MPLS Gateway</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_mpls_gw"
                                name="mpls_gw" placeholder="Gateway" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-2">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">MPLS Interface</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_mpls_ip_add"
                                name="mpls_ip_add" placeholder="wg1xx" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-2">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">WG IP</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_mpls_wgip"
                                name="mpls_wgip" placeholder="Tunnel IP" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-2">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">WG Port</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_mpls_port"
                                name="mpls_port" placeholder="500xx" style="height:28px;font-size:12px;">
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-info mt-2 mb-1 border-bottom pb-1" style="font-size:12px;"><i
                            class="fas fa-globe mr-1"></i> ILL Interface</h6>
                    <div class="row form-group mb-1">
                        <div class="col-md-4">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">ILL Interface</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_ill_iface"
                                name="ill_iface" placeholder="wg5xx" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-4">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">ILL WG IP</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_ill_ip" name="ill_ip"
                                placeholder="Tunnel IP" style="height:28px;font-size:12px;">
                        </div>
                        <div class="col-md-4">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">ILL WG Port</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_ill_port"
                                name="ill_port" placeholder="550xx" style="height:28px;font-size:12px;">
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-info mt-2 mb-1 border-bottom pb-1" style="font-size:12px;"><i
                            class="fas fa-key mr-1"></i> WireGuard Keys</h6>
                    <div class="row form-group mb-2">
                        <div class="col-md-6">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">Private Key</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="pf_device_priv_key"
                                    name="priv_key" style="height:28px;font-size:12px;">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="generateKeysPfCrud()"
                                        style="height:28px;font-size:11px;padding:2px 8px;"><i
                                            class="fas fa-sync-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="font-weight-bold text-muted mb-0"
                                style="font-size:10px;text-transform:uppercase;">Public Key</label>
                            <input type="text" class="form-control form-control-sm" id="pf_device_pub_key"
                                name="pub_key" readonly style="height:28px;font-size:12px;background:#f8f9fa;">
                            <small id="pf_device_pub_key_hint" class="d-block mt-1"
                                style="font-size:10px;color:var(--text-sub);">Type or paste a private key — the public
                                key is derived from it.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill font-weight-bold px-4"
                    data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm rounded-pill font-weight-bold px-4 d-none"
                    id="pfDeviceDeleteBtn" onclick="deletePfDevice()">Delete</button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-4"
                    onclick="savePfDevice()">Save Configuration</button>
            </div>
        </div>
    </div>
</div>

<script>
    /*
     * The WireGuard keypair here is really one key: a private key, and the public key that belongs to it.
     * Operators rarely generate a pair in this modal — they paste the private key of a branch that already
     * has one, taken from that branch's configuration — so the public key follows whatever is in the
     * private key field, and it follows by itself.
     *
     * Deriving it is the app's job (`generate-public-key`), never the browser's: an earlier version of
     * this modal did it client-side with TweetNaCl from a CDN, a copy that some networks refuse outright,
     * which left the button doing nothing and saying nothing.
     */
    let pfDeviceDerivedFor = null;

    function setPublicKeyHint(message, isError) {
        const hint = document.getElementById('pf_device_pub_key_hint');
        if (!hint) {
            return;
        }

        hint.textContent = message;
        hint.style.color = isError ? '#dc2626' : 'var(--text-sub)';
    }

    function privateKeyInField() {
        const field = document.getElementById('pf_device_priv_key');

        return field ? field.value.trim() : '';
    }

    /** A WireGuard private key is 32 bytes, base64: 44 characters ending in '='. */
    function looksLikePrivateKey(value) {
        return /^[A-Za-z0-9+/]{43}=$/.test(value);
    }

    /** True when the public key in the field does not belong to the private key beside it. */
    function publicKeyIsStale() {
        const typed = privateKeyInField();

        return looksLikePrivateKey(typed) && typed !== pfDeviceDerivedFor;
    }

    /**
     * Put the public key that belongs to the private key in the field, into the field beside it.
     *
     * Resolves with the public key, or with '' when there is nothing to derive or the server refused — and
     * in that case the hint under the field says so, because a silent failure looks exactly like a button
     * that does not work.
     */
    function derivePublicKeyFromPrivateKey() {
        const typed = privateKeyInField();
        if (!looksLikePrivateKey(typed) || typed === pfDeviceDerivedFor) {
            return Promise.resolve('');
        }

        setPublicKeyHint('Deriving the public key…');

        const formData = new FormData();
        formData.append('private_key', typed);

        return fetch('<?= base_url("generate-public-key") ?>', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data && data.public_key) {
                    document.getElementById('pf_device_pub_key').value = data.public_key;
                    pfDeviceDerivedFor = typed;
                    setPublicKeyHint('Public key derived from the private key beside it.');
                    return data.public_key;
                }

                setPublicKeyHint((data && data.error) || 'The server could not derive a public key from this private key.', true);
                return '';
            })
            .catch(() => {
                setPublicKeyHint('Could not reach the server to derive the public key — use the button to retry.', true);
                return '';
            });
    }

    /**
     * Follow the private key field: paste a key from a branch's existing configuration and the public key
     * appears on its own. Typing is debounced, because half a key is what every keystroke looks like.
     */
    (function watchPrivateKeyField() {
        const field = document.getElementById('pf_device_priv_key');
        if (!field || field.dataset.publicKeyWatch === 'on') {
            return;
        }

        field.dataset.publicKeyWatch = 'on';

        let timer = null;

        field.addEventListener('input', function () {
            window.clearTimeout(timer);

            const typed = this.value.trim();

            // Emptying the field empties the public key with it: one left behind would describe a private
            // key that is no longer there.
            if (typed === '') {
                document.getElementById('pf_device_pub_key').value = '';
                pfDeviceDerivedFor = null;
                setPublicKeyHint('Type or paste a private key — the public key is derived from it.');
                return;
            }

            if (!looksLikePrivateKey(typed)) {
                setPublicKeyHint('Waiting for a complete private key (44 characters)…');
                return;
            }

            setPublicKeyHint('Deriving the public key…');
            timer = window.setTimeout(derivePublicKeyFromPrivateKey, 350);
        });
    })();

    function openPfDeviceCrud(locationName) {
        window.pfDeviceActionSuccess = false;
        document.getElementById('pfDeviceCrudForm').reset();
        document.getElementById('pf_device_id').value = '';
        document.getElementById('pf_device_br_name').value = locationName;
        document.getElementById('pfDeviceDeleteBtn').classList.add('d-none');

        // Fetch existing data if any
        $.ajax({
            url: '<?= base_url("get-device-by-location") ?>',
            type: 'POST',
            data: { location_name: locationName, <?= csrf_token() ?>: '<?= csrf_hash() ?>' },
            success: function (response) {
                if (response.status === 'success' && response.data) {
                    const d = response.data;
                    document.getElementById('pf_device_id').value = d.id || '';
                    document.getElementById('pf_device_hostname').value = d.hostname || '';
                    document.getElementById('pf_device_priv_key').value = d.priv_key || '';
                    document.getElementById('pf_device_pub_key').value = d.pub_key || '';

                    // The stored pair is the baseline: it is what Save would store unchanged, so merely
                    // opening a branch does not re-derive anything behind the operator's back.
                    pfDeviceDerivedFor = (d.priv_key || '').trim();
                    setPublicKeyHint(pfDeviceDerivedFor === ''
                        ? 'Type or paste a private key — the public key is derived from it.'
                        : 'Public key as stored for this branch.');
                    document.getElementById('pf_device_lan_ip').value = d.lan_ip || '';
                    document.getElementById('pf_device_lan_subnet').value = d.lan_subnet || '';
                    document.getElementById('pf_device_mpls_ip').value = d.mpls_ip || '';
                    document.getElementById('pf_device_mpls_gw').value = d.mpls_gw || '';
                    document.getElementById('pf_device_mpls_ip_add').value = d.mpls_ip_add || '';
                    document.getElementById('pf_device_mpls_wgip').value = d.mpls_wgip || '';
                    document.getElementById('pf_device_mpls_port').value = d.mpls_port || '';
                    document.getElementById('pf_device_ill_iface').value = d.ill_iface || '';
                    document.getElementById('pf_device_ill_ip').value = d.ill_ip || '';
                    document.getElementById('pf_device_ill_port').value = d.ill_port || '';
                    document.getElementById('pf_device_description').value = d.description || '';

                    if (d.id) {
                        document.getElementById('pfDeviceDeleteBtn').classList.remove('d-none');
                    }
                }
            }
        });

        $('#pfDeviceCrudModal').modal('show');
    }

    function savePfDevice(retried) {
        const form = document.getElementById('pfDeviceCrudForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // A key pasted a moment before Save may not have its public key yet — deriving it is a round trip.
        // Wait for it once rather than storing a branch whose two halves do not match; if it cannot be
        // derived at all, the hint beside the field is what explains why nothing was saved.
        if (publicKeyIsStale()) {
            if (retried) {
                return;
            }

            derivePublicKeyFromPrivateKey().then(() => savePfDevice(true));
            return;
        }

        $('#pfDeviceCrudModal').modal('hide');
        const isDark = document.body.classList.contains('dark-mode');

        Swal.fire({
            title: 'Enter Master Password',
            text: 'Please confirm master password to save WireGuard configuration.',
            input: 'password',
            inputPlaceholder: 'Enter master password...',
            showCancelButton: true,
            background: isDark ? '#12161c' : '#ffffff',
            color: isDark ? '#f8fafc' : '#0f172a',
            confirmButtonColor: '#ffc107',
            confirmButtonText: '<span style="color:#0f172a;font-weight:700;"><i class="fas fa-save mr-1"></i> Save Configuration</span>',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            willClose: () => {
                if (!$('.swal2-container').length && !window.pfDeviceActionSuccess) {
                    $('#pfDeviceCrudModal').modal('show');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const enteredPassword = result.value;
                const secretPassword = $('#masterPassword').val() || $('#pfCrudMasterPasswordFallback').val();

                if (secretPassword && enteredPassword !== secretPassword) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Incorrect master password. Configuration not saved.',
                        background: isDark ? '#12161c' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a'
                    }).then(() => {
                        $('#pfDeviceCrudModal').modal('show');
                    });
                    return;
                }

                window.pfDeviceActionSuccess = true;
                const formData = new FormData(document.getElementById('pfDeviceCrudForm'));
                formData.append('master_password', enteredPassword);
                formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

                $.ajax({
                    url: '<?= base_url("device-config/save") ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Saved!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            $('#pfDeviceCrudModal').modal('hide');
                            if (typeof table !== 'undefined' && table && table.ajax) {
                                table.ajax.reload(null, false);
                            } else if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable('#pfListTable')) {
                                $('#pfListTable').DataTable().ajax.reload(null, false);
                            }
                            const selectedDeviceBtn = document.querySelector('.device-btn.selected');
                            if (selectedDeviceBtn && typeof selectDevice === 'function') {
                                selectDevice(selectedDeviceBtn);
                            }
                        } else {
                            window.pfDeviceActionSuccess = false;
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to save configuration.',
                                background: isDark ? '#12161c' : '#ffffff',
                                color: isDark ? '#f8fafc' : '#0f172a'
                            }).then(() => {
                                $('#pfDeviceCrudModal').modal('show');
                            });
                        }
                    },
                    error: function (xhr) {
                        window.pfDeviceActionSuccess = false;
                        let msg = 'Failed to save configuration.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg,
                            background: isDark ? '#12161c' : '#ffffff',
                            color: isDark ? '#f8fafc' : '#0f172a'
                        }).then(() => {
                            $('#pfDeviceCrudModal').modal('show');
                        });
                    }
                });
            } else {
                $('#pfDeviceCrudModal').modal('show');
            }
        });
    }

    function deletePfDevice() {
        const id = document.getElementById('pf_device_id').value;
        if (!id) return;

        $('#pfDeviceCrudModal').modal('hide');
        const isDark = document.body.classList.contains('dark-mode');

        Swal.fire({
            title: 'Enter master password to delete!',
            text: "This will permanently delete the WireGuard configuration for this branch.",
            icon: 'warning',
            input: 'password',
            inputPlaceholder: 'Enter master password...',
            showCancelButton: true,
            background: isDark ? '#12161c' : '#ffffff',
            color: isDark ? '#f8fafc' : '#0f172a',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            reverseButtons: true,
            willClose: () => {
                if (!$('.swal2-container').length && !window.pfDeviceActionSuccess) {
                    $('#pfDeviceCrudModal').modal('show');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const enteredPassword = result.value;
                const secretPassword = $('#masterPassword').val() || $('#pfCrudMasterPasswordFallback').val();

                if (secretPassword && enteredPassword !== secretPassword) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Incorrect master password. Configuration not deleted.',
                        background: isDark ? '#12161c' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a'
                    }).then(() => {
                        $('#pfDeviceCrudModal').modal('show');
                    });
                    return;
                }

                window.pfDeviceActionSuccess = true;
                $.ajax({
                    url: '<?= base_url("device-config/delete") ?>',
                    type: 'POST',
                    data: {
                        id: id,
                        master_password: enteredPassword,
                        <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            Swal.fire('Deleted!', response.message, 'success');
                            $('#pfDeviceCrudModal').modal('hide');
                            if (typeof table !== 'undefined' && table && table.ajax) {
                                table.ajax.reload(null, false);
                            } else if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable('#pfListTable')) {
                                $('#pfListTable').DataTable().ajax.reload(null, false);
                            }
                            const selectedDeviceBtn = document.querySelector('.device-btn.selected');
                            if (selectedDeviceBtn && typeof selectDevice === 'function') {
                                selectDevice(selectedDeviceBtn);
                            }
                        } else {
                            window.pfDeviceActionSuccess = false;
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to delete configuration.',
                                background: isDark ? '#12161c' : '#ffffff',
                                color: isDark ? '#f8fafc' : '#0f172a'
                            }).then(() => {
                                $('#pfDeviceCrudModal').modal('show');
                            });
                        }
                    },
                    error: function (xhr) {
                        window.pfDeviceActionSuccess = false;
                        let msg = 'Failed to delete configuration.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg,
                            background: isDark ? '#12161c' : '#ffffff',
                            color: isDark ? '#f8fafc' : '#0f172a'
                        }).then(() => {
                            $('#pfDeviceCrudModal').modal('show');
                        });
                    }
                });
            } else {
                $('#pfDeviceCrudModal').modal('show');
            }
        });
    }

    /**
     * Generate a new private key for the branch this modal is open on.
     *
     * The private key is 32 random bytes, base64-encoded — the same generator the config page uses. The
     * public key is derived by the app itself (`generate-public-key`, a Curve25519 base-point
     * multiplication in libsodium) rather than in the browser: this button used to do it with TweetNaCl
     * from a CDN, and that copy of the library is refused by the browser on some networks
     * (ERR_BLOCKED_BY_ORB). `nacl.util` was then undefined, so the button threw and did nothing at all —
     * no key, no error, nothing an operator could act on. Nothing here needs a third-party script now.
     */
    function generateKeysPfCrud() {
        const randomBytes = new Uint8Array(32);
        window.crypto.getRandomValues(randomBytes);

        document.getElementById('pf_device_priv_key').value = btoa(String.fromCharCode(...randomBytes));

        // Derived exactly the way a pasted key is derived: one path, so the button and the paste cannot
        // disagree about which public key belongs to a private key.
        pfDeviceDerivedFor = null;
        derivePublicKeyFromPrivateKey();
    }
</script>