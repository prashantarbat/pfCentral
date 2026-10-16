<?php

/**
 * The "Install Device Agent" modal, identical on both configuration pages.
 */
?>
<!-- =========================================
     INSTALL AGENT MODAL
========================================== -->
<div class="modal fade" id="installAgentModal" tabindex="-1" role="dialog" aria-labelledby="installAgentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title" id="installAgentModalLabel"><i class="fas fa-terminal mr-2"></i> Install Device Agent</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 24px !important;">
                <p style="color: var(--text-sub); font-size: 13.5px; margin-bottom: 16px;">Run the following command as <strong>root</strong> on the target device to install or reinstall the pfCentral agent:</p>
                <div class="command-box" id="installCommandContainer">
<code>fetch --no-verify-peer -q -o install.sh <?= base_url('assets/endpoint/install.sh') ?> && /bin/sh install.sh <?= rtrim(base_url(), '/') ?></code>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--form-control-border) !important; padding: 16px 24px !important;">
                <button type="button" class="btn btn-modal-copy" id="copyApiBtn">
                    <i class="fas fa-copy mr-1"></i> Copy Command
                </button>
                <button type="button" class="btn btn-modal-close" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

