<!-- Install Agent Modal -->
<div class="modal fade" id="installAgentModal" tabindex="-1" role="dialog" aria-labelledby="installAgentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title font-weight-bold mb-0" id="installAgentModalLabel">
                    <i class="fa fa-download mr-1"></i> INSTALL DEVICE AGENT
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-2 small text-muted">
                    Run the following command as <strong>root</strong> to install or reinstall the device agent:
                </p>
                <pre id="installCommandContainer" class="mb-0"><code id="installCommand"></code></pre>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-danger btn-sm" id="copyInstallCmdBtn">
                    <i class="fa fa-copy"></i> Copy
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #installCommandContainer {
        background-color: #282c34;
        color: #ffffff;
        border: 1px solid #ffc107;
        padding: 10px 12px;
        border-radius: 5px;
        font-size: 13px;
        font-family: monospace;
        white-space: pre-wrap;
        word-break: break-all;
        overflow-x: auto;
        margin: 0;
    }
    #installCommand {
        display: block;
        white-space: pre-wrap;
        word-break: break-all;
    }
    #installAgentModal .modal-header,
    #installAgentModal .modal-footer {
        border-color: #e9ecef;
    }
</style>

<script>
// Shared clipboard helper (window.nocCopyToClipboard). navigator.clipboard exists only
// in secure contexts, so on plain-HTTP hosts (e.g. http://pfcentral.local.test)
// it is undefined — fall back to the hidden-textarea + execCommand path there.
// containerEl: attach the temporary textarea inside an open Bootstrap modal so its
// enforceFocus handler cannot steal focus and silently break execCommand('copy').
window.nocCopyToClipboard = function(text, containerEl) {
    var execFallback = function() {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.opacity = '0';
        (containerEl || document.body).appendChild(textarea);

        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);

        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            console.error('Copy failed:', e);
        }

        textarea.parentNode.removeChild(textarea);
        return ok;
    };

    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text)
            .then(function() { return true; })
            .catch(function() { return execFallback(); });
    }
    return Promise.resolve(execFallback());
};

$(document).ready(function() {
    var appUrl = "<?= esc(base_url()) ?>";
    var sanitizedAppUrl = appUrl.replace(/\/$/, '');

    $('#installAgentModal').on('show.bs.modal', function() {
        var installCommand =
            'fetch --no-verify-peer -q -o install.sh ' + sanitizedAppUrl + '/assets/endpoint/install.sh' +
            ' && /bin/sh install.sh ' + sanitizedAppUrl;

        $('#installCommand').text(installCommand);
    });

    $(document).on('click', '#copyInstallCmdBtn', function() {
        var el = document.getElementById('installCommand');
        var installCommand = el ? el.textContent.trim() : '';
        var swalParams = typeof getSwalParams === 'function' ? getSwalParams() : {};

        if (!installCommand) {
            Swal.fire({
                ...swalParams,
                title: "Error!",
                text: "No command available to copy!",
                icon: "error"
            });
            return;
        }

        window.nocCopyToClipboard(installCommand, document.getElementById('installAgentModal'))
            .then(function(copied) {
                if (copied) {
                    Swal.fire({
                        ...swalParams,
                        title: "Copied!",
                        text: "Installation command copied successfully!",
                        icon: "success",
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        ...swalParams,
                        title: "Copy failed",
                        html: "Please select the command text and copy manually.<br><code>" +
                            installCommand.replace(/</g, "&lt;") + "</code>",
                        icon: "error"
                    });
                }
            });
    });
});
</script>