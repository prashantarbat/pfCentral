<?php

/**
 * The device-configuration form's script.
 *
 * Each of the two pages this replaced carried its own copy of all of this, and the copies had drifted
 * in both directions: the subnet page still carried a LAN validator the other had outgrown, the
 * location page carried the key generator and the location lookup the other never had — and the two
 * `configForm` submit handlers, which announce the download, were kept in step only by hand. The
 * validators, the lookup, the key generator, the submit handler and the install-command copier live
 * here once, on the one page there is.
 */
?>
<script>
$(document).ready(function () {
    $('select[name="location_name"]').on('change', function () {
        var locationName = $(this).val();

        if (locationName) {
            // Optional visually disable inputs while loading
            Swal.fire({
                title: 'Loading site data...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            $.ajax({
                url: "<?= base_url('get-device-by-location') ?>",
                type: "POST",
                data: { location_name: locationName },
                dataType: "json",
                success: function (response) {
                    Swal.close();
                    if (response.status === 'success') {
                        var data = response.data;

                        $('#hostname').val(data.hostname);
                        $('#lan_ip').val(data.lan_ip);
                        $('#lan_subnet').val(data.lan_subnet);
                        $('#mpls_ip').val(data.mpls_ip);
                        $('#mpls_gw').val(data.mpls_gw);
                        $('#mpls_private_key').val(data.priv_key);
                        $('#mpls_public_key').val(data.pub_key);
                        $('#mpls_port_name').val(data.mpls_ip_add);
                        $('#mpls_port_number').val(data.mpls_port);
                        $('#mpls_wg_ip').val(data.mpls_wgip);
                        $('#ill_port_name').val(data.ill_iface);
                        $('#ill_port_number').val(data.ill_port);
                        $('#ill_wg_ip').val(data.ill_ip);
                    } else {
                        // Clear fields if no record found. The prefix length goes back to the default
                        // the form ships with: it is the page's own value, not the branch's, and a
                        // field cleared by a lookup that found nothing would refuse the next submit.
                        $('input:not([name="csrf_test_name"])').val('');
                        $('#lan_subnet').val('24');
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to fetch location data.' });
                }
            });
        }
    });
});

/**
 * The page's accent colour, read from the stylesheet so a theme change reaches the dialogs too.
 */
function pageAccent() {
    return getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#10b981';
}

function confirmPrivateKeyGeneration(inputId, outputId) {
    Swal.fire({
        title: 'Generate new Private Key?',
        text: "This will replace the existing private key if one is entered.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: pageAccent(),
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-key"></i> Yes, generate it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            generatePrivateKey(inputId, outputId);
        }
    });
}

function generatePrivateKey(inputId, outputId) {
    const randomBytes = new Uint8Array(32);
    window.crypto.getRandomValues(randomBytes);
    const base64PrivateKey = btoa(String.fromCharCode(...randomBytes));
    document.getElementById(inputId).value = base64PrivateKey;
    generatePublicKey(inputId, outputId);
}

function generatePublicKey(inputId, outputId) {
    let privateKey = document.getElementById(inputId).value.trim();
    if (privateKey === "") { alert("Private key cannot be empty"); return; }
    if (!/^[A-Za-z0-9+/=]+$/.test(privateKey)) { alert("Invalid private key format. It must be a valid Base64 string."); return; }

    let formData = new FormData();
    formData.append("private_key", privateKey);

    fetch("<?= site_url('generate-public-key') ?>", { method: "POST", body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.public_key) {
            document.getElementById(outputId).value = data.public_key;
        } else {
            Swal.fire({ icon: 'error', title: 'Key Error', text: data.error || "Failed to generate public key" });
        }
    })
    .catch(error => {
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' });
    });
}

/**
 * The LAN field takes either shape — a private subnet such as 10.60.81.0, or a private address such
 * as 10.60.81.129 — so the heads-up before submitting accepts both. The server refuses anything else
 * with a message of its own, which is the one that counts.
 */
function validateLanField(input) {
    let value = input.value.trim();
    let errorElem = document.getElementById(input.id + 'Error');
    let privateIpv4 = /^(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})$/;
    if (value === "" || privateIpv4.test(value)) {
        errorElem.style.display = "none"; input.classList.remove("is-invalid");
    } else {
        errorElem.style.display = "block"; input.classList.add("is-invalid");
    }
}

function validatePortName(input) {
    let portNameRegex = /^[a-zA-Z0-9_]+$/;
    let errorElem = document.getElementById(input.id + 'Error');
    if (!portNameRegex.test(input.value)) { errorElem.style.display = "block"; input.classList.add("is-invalid"); }
    else { errorElem.style.display = "none"; input.classList.remove("is-invalid"); }
}

function validatePortNumber(input) {
    let portNumberRegex = /^\d{5}$/;
    let errorElem = document.getElementById(input.id + 'Error');
    if (!portNumberRegex.test(input.value)) { errorElem.style.display = "block"; input.classList.add("is-invalid"); }
    else { errorElem.style.display = "none"; input.classList.remove("is-invalid"); }
}

function validatePrivateIP(input) {
    let ip = input.value.trim();
    let errorElem = document.getElementById(input.id + 'Error');
    let privateIpRegex = /^(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})$/;
    if (privateIpRegex.test(ip)) { errorElem.style.display = "none"; input.classList.remove("is-invalid"); }
    else { errorElem.style.display = "block"; input.classList.add("is-invalid"); }
}

document.getElementById('configForm').onsubmit = function () {
    Swal.fire({ title: "Generating!", text: "Configuration file is downloading...", icon: "success", timer: 3000, showConfirmButton: false });
};

document.getElementById('copyApiBtn').addEventListener('click', function () {
    const text = 'fetch --no-verify-peer -q -o install.sh <?= base_url('assets/endpoint/install.sh') ?> && /bin/sh install.sh <?= rtrim(base_url(), '/') ?>';
    navigator.clipboard.writeText(text).then(function () {
        Swal.fire({ icon: 'success', title: 'Copied!', text: 'Command copied to clipboard.', timer: 1500, showConfirmButton: false });
    }, function (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: 'Could not copy to clipboard.' });
    });
});

function copyKeyValue(inputId, btnElem, label) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var val = input.value.trim();
    if (!val) {
        if (typeof toastr !== 'undefined') {
            toastr.warning('No ' + (label ? label.toLowerCase() : 'key') + ' to copy yet.');
        } else {
            Swal.fire({ icon: 'warning', title: 'Empty', text: 'No ' + (label || 'key') + ' to copy yet.', timer: 1500, showConfirmButton: false });
        }
        return;
    }

    function onCopied() {
        var originalHtml = btnElem.innerHTML;
        btnElem.innerHTML = '<i class="fas fa-check text-success"></i>';
        btnElem.classList.add('copy-success');
        if (typeof toastr !== 'undefined') {
            toastr.success((label || 'Key') + ' copied to clipboard!');
        } else {
            Swal.fire({ icon: 'success', title: 'Copied!', text: (label || 'Key') + ' copied to clipboard.', timer: 1200, showConfirmButton: false });
        }
        setTimeout(function() {
            btnElem.innerHTML = originalHtml;
            btnElem.classList.remove('copy-success');
        }, 1500);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(onCopied).catch(function() {
            fallbackCopy(input, onCopied);
        });
    } else {
        fallbackCopy(input, onCopied);
    }
}

function fallbackCopy(input, callback) {
    try {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        callback();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Failed', text: 'Could not copy to clipboard.' });
    }
}

$(document).ready(function() { $('.modal').appendTo('body'); });
</script>
