<table id="example1" class="table table-bordered table-striped display nowrap">
    <thead>
        <tr>
            <th class="text-center" style="width: 5px;">#</th>
            <th class="text-center">Status</th>
            <th class="text-center">Name</th>
            <th class="text-center">Action</th>
            <th class="text-center">Location</th>
            <th class="text-center">IP Address</th>
            <th class="text-center">MPLS IP</th>
            <th class="text-center">ILL IP</th>
            <th class="text-center">WAN IP</th>
            <th class="text-center">Last Synced</th>
            <th class="text-center">Credentials</th>
            <th class="text-center">Notes</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($pf)): ?>
            <?php $no = 1; ?>
            <?php foreach ($pf as $row): ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td>
                        <?php if (checkPortStatus($row['man_ip'] ?? $row['ip'], $row['man_port'] ?? $row['port'])): ?>
                            Status <i class="fas fa-circle fa-lg" style="color: green;"></i> <!-- Green circle for open port -->
                        <?php else: ?>
                            Status <i class="fas fa-circle fa-lg" style="color: red;"></i>  <!-- Red circle for closed port -->
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="#" 
                        data-toggle="modal" 
                        data-target="#installAgentModal" 
                        data-id="<?= $row['id'] ?>" 
                        data-name="<?= $row['name'] ?>" 
                        data-device-key="<?= $row['devicekey'] ?>" 
                        data-app-url="<?= esc(base_url()) ?>"
                        >
                        <?= $row['name'] ?>
                        </a>
                    </td>
                    <td class="text-center">
                        <!-- ACTION Button Modal -->
                        <button class="btn bg-gradient-dark btn-sm d-flex align-items-center" data-toggle="modal" data-target="#actionModal<?= $row['id'] ?>">
                        <img src="<?= base_url('assets/images/pfsense_zn.png') ?>" alt="pflogo" width="20"> &nbsp;Actions</button>
                        <?= view('functions/buttonactionmodal', ['row' => $row]) ?>
                    </td>
                    <td><?= htmlspecialchars($row['location'] ?? '---') ?></td>
                    <td>
                        <?= $row['man_ip'] ?? $row['ip'] ?? '---' ?>
                        <?php if (!empty($row['man_ip'] ?? $row['ip']) && ($row['man_ip'] ?? $row['ip']) !== '---'): ?>
                            <?php $pingClass = checkPing($row['man_ip'] ?? $row['ip']); ?>
                            <button class="btn <?= $pingClass ?> btn-sm" style="font-size: 11px;" data-toggle="modal" data-target="#pingModal" onclick="startPing('<?= $row['ip'] ?>')">Ping</button>
                            <button class="btn btn-outline-info btn-sm" style="font-size: 11px;" data-toggle="modal" data-target="#traceModal" onclick="startTrace('<?= $row['ip'] ?>')">Trace</button>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['mpls_ip'] ?? '---' ?></td>
                    <td><?= $row['ill_ip'] ?? '---' ?></td>
                    <td><?= $row['wan_ip'] ?? '---' ?></td>
                    <td><?= $row['last_synced'] ?? '---' ?></td>
                    <td><?= $row['credentials'] ?? '---' ?></td>
                    <td><?= $row['notes'] ?? '---' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="12" class="text-center">No results found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>