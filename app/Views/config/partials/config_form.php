<?php

/**
 * The device-configuration form.
 *
 * One form for one page. It used to be rendered twice, with the differences as parameters: which
 * interface row the interface sat in, whether the location select was a search box at the top or a
 * plain one in row 1, whether the LAN was asked for as a subnet or as an address, and whether the
 * key generator was offered. Every one of those is now simply what the form does — the search box is
 * there, the generator is there, and the LAN is the one field that takes either shape, because a
 * subnet and an address are two ways of describing the same branch
 * (`DeviceConfigInput::lanFromField()`).
 *
 * @var string $panelDescription Intro line of the "Network Parameters" panel
 * @var array  $locations        Location rows for the site selector: [['id' => …, 'name' => …], …]
 */
?>
        <form action="<?= base_url('config-generator') ?>" method="POST" id="configForm">
            <?= csrf_field() ?>

            <!-- Location Search Box -->
            <div class="search-location-box">
                <div class="form-row align-items-center">
                    <div class="form-group col-md-8 mb-0">
                        <label class="form-label-modern d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fas fa-search mr-1" style="color: var(--accent);"></i> Select Location</span>
                            <span class="text-muted font-weight-normal" style="font-size: 10px; text-transform: none; letter-spacing: normal;">Auto-fills branch parameters</span>
                        </label>
                        <select class="form-control select2" name="location_name" required>
                            <option value="" disabled selected>Search for a site...</option>
                            <?php foreach ($locations as $location) { ?>
                                <option value="<?= htmlspecialchars($location['name']); ?>">
                                    <?= htmlspecialchars($location['name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group col-md-4 mb-0">
                        <label class="form-label-modern mb-1">Interface</label>
                        <select name="interface" class="form-control">
                            <option value="em">em</option>
                            <option value="iga">iga</option>
                            <option value="igb" selected>igb</option>
                            <option value="igc">igc</option>
                            <option value="vnet">vnet</option>
                            <option value="ue">ue</option>
                            <option value="eth">eth</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-panel-header">
                <div class="d-flex align-items-center justify-content-between w-100 flex-wrap">
                    <div class="form-panel-title"><i class="fas fa-network-wired mr-1" style="color: var(--accent);"></i>Network Parameters</div>
                    <span class="form-panel-desc"><?= esc($panelDescription) ?></span>
                </div>
            </div>

            <!-- Row 1: Basic Identity -->
            <div class="form-row">
                <div class="form-group col-md-3 mb-3">
                    <label class="form-label-modern">Hostname</label>
                    <input type="text" id="hostname" name="hostname" class="form-control" placeholder="e.g. AU026KSH53WWE" required>
                </div>

                <div class="form-group col-md-3 mb-3">
                    <label class="form-label-modern">LAN Subnet or Address</label>
                    <div class="input-group">
                        <input type="text" id="lan_ip" name="lan_ip" class="form-control" placeholder="10.60.81.0 or 10.60.81.129" oninput="validateLanField(this)">
                        <div class="input-group-append">
                            <input type="number" id="lan_subnet" name="lan_subnet" class="form-control lan-subnet-input" placeholder="24" value="24" min="1" max="32" title="Prefix length the LAN belongs to">
                        </div>
                    </div>
                    <small class="text-danger" id="lan_ipError" style="display: none; font-size: 10px;">Enter a private IPv4 subnet or address.</small>
                </div>

                <div class="form-group col-md-3 mb-3">
                    <label class="form-label-modern">MPLS IP Address</label>
                    <input type="text" id="mpls_ip" name="mpls_ip" class="form-control" placeholder="MPLS IP Address">
                </div>

                <div class="form-group col-md-3 mb-3">
                    <label class="form-label-modern">MPLS Gateway</label>
                    <input type="text" id="mpls_gw" name="mpls_gw" class="form-control" placeholder="MPLS Gateway">
                </div>
            </div>

            <!-- WireGuard Keys Section -->
            <div class="keys-section">
                <div class="keys-section-title">
                    <span class="wg-icon-badge"><i class="fas fa-key"></i></span>
                    WireGuard Keys
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-0">
                        <label class="form-label-modern">Private Key</label>
                        <div class="input-group">
                            <input type="text" name="private_key" id="mpls_private_key" class="form-control" placeholder="Enter or generate a private key" oninput="generatePublicKey('mpls_private_key', 'mpls_public_key')">
                            <div class="input-group-append">
                                <button class="btn btn-keygen" type="button" onclick="confirmPrivateKeyGeneration('mpls_private_key', 'mpls_public_key')">
                                    <i class="fas fa-sync-alt mr-1"></i> Generate
                                </button>
                                <button class="btn btn-key-copy" type="button" onclick="copyKeyValue('mpls_private_key', this, 'Private key')" title="Copy Private Key">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-md-6 mb-0">
                        <label class="form-label-modern">Public Key</label>
                        <div class="input-group">
                            <input type="text" name="public_key" id="mpls_public_key" class="form-control" placeholder="Auto-generated from private key" readonly style="background: rgba(var(--accent-rgb), 0.05) !important;">
                            <div class="input-group-append">
                                <button class="btn btn-key-copy" type="button" onclick="copyKeyValue('mpls_public_key', this, 'Public key')" title="Copy Public Key">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WireGuard Tunnels: MPLS + ILL side-by-side -->
            <div class="form-row mt-3">
                <!-- MPLS Tunnel -->
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="wg-section wg-section-mpls">
                        <div class="wg-section-title">
                            <span class="wg-icon-badge"><i class="fas fa-project-diagram"></i></span>
                            WireGuard — MPLS Tunnel
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4 mb-0">
                                <label class="form-label-modern">Port Name</label>
                                <input type="text" name="mpls_port_name" id="mpls_port_name" class="form-control" placeholder="e.g. wg_mpls" oninput="validatePortName(this)">
                                <small class="text-danger" id="mpls_port_nameError" style="display: none; font-size: 10px;">Letters, numbers, underscores only.</small>
                            </div>
                            <div class="form-group col-md-3 mb-0">
                                <label class="form-label-modern">Port #</label>
                                <input type="text" name="mpls_port_number" id="mpls_port_number" class="form-control" placeholder="5 digits" oninput="validatePortNumber(this)">
                                <small class="text-danger" id="mpls_port_numberError" style="display: none; font-size: 10px;">Must be exactly 5 digits.</small>
                            </div>
                            <div class="form-group col-md-5 mb-0">
                                <label class="form-label-modern">IP Address</label>
                                <div class="input-group">
                                    <input type="text" name="mpls_wg_ip" id="mpls_wg_ip" class="form-control" placeholder="Enter IP" oninput="validatePrivateIP(this)">
                                    <div class="input-group-append">
                                        <span class="input-group-text">/30</span>
                                    </div>
                                </div>
                                <small class="text-danger" id="mpls_wg_ipError" style="display: none; font-size: 10px;">Invalid private IP.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ILL Tunnel -->
                <div class="col-md-6">
                    <div class="wg-section wg-section-ill">
                        <div class="wg-section-title">
                            <span class="wg-icon-badge"><i class="fas fa-globe-americas"></i></span>
                            WireGuard — ILL Tunnel
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4 mb-0">
                                <label class="form-label-modern">Port Name</label>
                                <input type="text" name="ill_port_name" id="ill_port_name" class="form-control" placeholder="e.g. wg_ill" oninput="validatePortName(this)">
                                <small class="text-danger" id="ill_port_nameError" style="display: none; font-size: 10px;">Letters, numbers, underscores only.</small>
                            </div>
                            <div class="form-group col-md-3 mb-0">
                                <label class="form-label-modern">Port #</label>
                                <input type="text" name="ill_port_number" id="ill_port_number" class="form-control" placeholder="5 digits" oninput="validatePortNumber(this)">
                                <small class="text-danger" id="ill_port_numberError" style="display: none; font-size: 10px;">Must be exactly 5 digits.</small>
                            </div>
                            <div class="form-group col-md-5 mb-0">
                                <label class="form-label-modern">IP Address</label>
                                <div class="input-group">
                                    <input type="text" name="ill_wg_ip" id="ill_wg_ip" class="form-control" placeholder="Enter IP" oninput="validatePrivateIP(this)">
                                    <div class="input-group-append">
                                        <span class="input-group-text">/30</span>
                                    </div>
                                </div>
                                <small class="text-danger" id="ill_wg_ipError" style="display: none; font-size: 10px;">Invalid private IP.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit & Security Notice -->
            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 flex-wrap" style="border-top: 1px solid var(--form-control-border); gap: 12px;">
                <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                    <?php if (!empty($cryptoSettings['enabled'])): ?>
                        <span class="badge badge-success px-2 py-1" style="font-size: 11.5px; border-radius: 6px; background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3);">
                            <i class="fas fa-lock mr-1"></i> Auto-Encrypted with Global Password
                        </span>
                        <small class="text-muted" style="font-size: 11.5px;">Ready for pfSense encrypted restore.</small>
                        <?php if (!empty($cryptoSettings['allow_plain'])): ?>
                            <label class="mb-0 ml-2" style="font-size: 11.5px; cursor: pointer; color: var(--settings-text-soft);" title="Download raw unencrypted XML">
                                <input type="checkbox" name="plain_xml" value="1" style="vertical-align: middle;"> Plain XML
                            </label>
                        <?php endif; ?>
                    <?php else: ?>
                        <small class="text-muted" style="font-size: 11.5px;"><i class="fas fa-info-circle mr-1"></i> The generated XML will be downloaded automatically to your browser.</small>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-download-config">
                    <i class="fas fa-download mr-1"></i> Generate & Download
                </button>
            </div>
        </form>
