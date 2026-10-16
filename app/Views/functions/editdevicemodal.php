<!-- Edit Device Modal -->
<div class="modal fade" id="editDeviceModal" tabindex="-1" role="dialog" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning font-weight-bold" id="editDeviceModalLabel">
                    <i class="fas fa-edit mr-2"></i> UPDATE DEVICE CONFIGURATION
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editDeviceForm">
                <div class="modal-body">
                    <!-- Hidden field for Device ID -->
                    <input type="hidden" id="editDeviceId" name="id">

                    <!-- Basic Details -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="deviceName" style="color: var(--text-strong); font-weight: 600;">Device Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="deviceName" name="name" required placeholder="e.g. SANYOGITAGANJ Office Firewall">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="deviceSerial" style="color: var(--text-strong); font-weight: 600;">Asset/Tag Serial</label>
                                <input type="text" class="form-control" id="deviceSerial" name="serial" placeholder="Serial Number">
                            </div>
                        </div>
                    </div>

                    <!-- Location & Group -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deviceLocation" style="color: var(--text-strong); font-weight: 600;">Location <span class="text-danger">*</span></label>
                                <select class="form-control" id="deviceLocation" name="location" required>
                                    <option value="" disabled>Select Location</option>
                                    <?php if (!empty($locations)): ?>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?= esc($loc['id']) ?>"><?= esc($loc['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deviceGroup" style="color: var(--text-strong); font-weight: 600;">Group</label>
                                <select class="form-control" id="deviceGroup" name="groups">
                                    <option value="">Select Group</option>
                                    <?php 
                                        $groupsList = isset($all_groups) ? $all_groups : (isset($groups) && is_array($groups) ? $groups : []);
                                        if (!empty($groupsList)): 
                                    ?>
                                        <?php foreach ($groupsList as $g): ?>
                                            <option value="<?= esc($g['name'] ?? $g['id']) ?>"><?= esc($g['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Platform, IP & Port -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="devicePlatform" style="color: var(--text-strong); font-weight: 600;">Platform</label>
                                <select class="form-control" id="devicePlatform" name="platform">
                                    <option value="pfSenseCE">pfSense CE</option>
                                    <option value="pfSense+">pfSense +</option>
                                    <option value="OPNsense">OPNsense</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="deviceIP" style="color: var(--text-strong); font-weight: 600;">Management IP / Hostname <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="deviceIP" name="ip" required placeholder="192.168.1.1">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="devicePort" style="color: var(--text-strong); font-weight: 600;">Port <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="devicePort" name="port" required placeholder="443" min="1" max="65535">
                            </div>
                        </div>
                    </div>

                    <!-- Exclude / Bypass Option -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-group p-3 mb-0" style="background: rgba(255, 193, 7, 0.04); border: 1px dashed rgba(255, 193, 7, 0.2); border-radius: 8px;">
                                <div class="custom-control custom-switch d-flex align-items-center">
                                    <input type="checkbox" class="custom-control-input" id="deviceBypass" name="bypass" value="1">
                                    <label class="custom-control-label ml-2 cursor-pointer mb-0" for="deviceBypass" style="color: var(--text-strong); font-weight: 600; font-size: 13.5px; user-select: none;">
                                        <i class="fas fa-ban text-warning" style="filter: drop-shadow(0 0 3px rgba(255,193,7,0.3)); margin-right: 6px;"></i>
                                        Bypass Device (Exclude from connectivity checks & alerts)
                                    </label>
                                </div>
                                <small class="form-text text-muted mt-2" style="margin-left: 28px;">
                                    Enabling this option will mark the device as <strong>Bypassed</strong>, excluding it from automatic agent polling, connectivity alerts, and down-time alert notifications.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Credentials -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deviceUsername" style="color: var(--text-strong); font-weight: 600;">API Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editDeviceUsername" name="username" required placeholder="admin">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="devicePassword" style="color: var(--text-strong); font-weight: 600;">API Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="editDevicePassword" name="password" required placeholder="Password">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-warning" type="button" id="editTogglePassword">
                                            <i class="fas fa-eye" id="editEyeIcon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Token/Key -->
                    <div class="form-group">
                        <label for="deviceAPi" style="color: var(--text-strong); font-weight: 600;">REST API Token / Key</label>
                        <input type="text" class="form-control" id="editDeviceAPi" name="api" placeholder="Enter optional API authorization token...">
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label for="deviceNotes" style="color: var(--text-strong); font-weight: 600;">Notes / Descriptions</label>
                        <textarea class="form-control" id="deviceNotes" name="notes" rows="3" placeholder="Provide context, ISP link info, backup contacts..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn bg-gradient-warning btn-sm text-dark font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
