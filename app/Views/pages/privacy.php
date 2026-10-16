<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Privacy Policy</h1>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card p-4 border-0" style="background: var(--card-bg) !important; backdrop-filter: blur(25px); border-radius: 12px; box-shadow: var(--card-shadow) !important;">
                <div class="card-body" style="color: var(--text-strong) !important;">
                    <h3 class="mb-4">Privacy Policy for PF-CENTRAL</h3>
                    
                    <p class="text-muted mb-4"><strong>Effective Date:</strong> <?= date('F j, Y') ?></p>
                    
                    <h5>1. Introduction</h5>
                    <p>Welcome to PF-CENTRAL ("we," "our," or "us"). We are committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit or use our central firewall management system.</p>
                    
                    <h5 class="mt-4">2. Information We Collect</h5>
                    <p>We collect information that your devices, agents, and administrators provide directly to us when using PF-CENTRAL. This includes:</p>
                    <ul>
                        <li><strong>Administrative Data:</strong> Usernames, authentication tokens, and IP addresses of administrators accessing the dashboard.</li>
                        <li><strong>Device Telemetry:</strong> CPU usage, memory utilization, disk space, active tunnels, and system logs from managed firewalls.</li>
                        <li><strong>Network Configuration:</strong> IP routes, VPN configurations, interface definitions, and firewall rules synchronised via the management portal.</li>
                    </ul>

                    <h5 class="mt-4">3. How We Use Your Information</h5>
                    <p>The information we collect is strictly used to:</p>
                    <ul>
                        <li>Monitor the health, availability, and performance of connected network infrastructure.</li>
                        <li>Facilitate the deployment of configuration changes (push configs) to managed firewalls.</li>
                        <li>Maintain audit logs of administrative actions for security and compliance tracking.</li>
                        <li>Generate alerts and reports regarding network outages or performance degradation.</li>
                    </ul>

                    <h5 class="mt-4">4. Data Security</h5>
                    <p>We use administrative, technical, and physical security measures to help protect your data. All communication between the central server and agents uses encrypted channels. Access to the dashboard is protected by robust authentication protocols, and administrative actions are logged.</p>

                    <h5 class="mt-4">5. Contact Us</h5>
                    <p>If you have questions or comments about this Privacy Policy, please contact your network administration team or the designated Data Protection Officer.</p>
                </div>
            </div>
        </div>
    </div>
</div>
