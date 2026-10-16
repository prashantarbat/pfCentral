<?php
/**
 * ⚠️  LIBRENMS SETTINGS PROVISIONING & MANAGER SCRIPT — ONE-SHOT USE
 * 
 * Injects and manages LibreNMS integration rows inside the core_config database.
 * This file is highly secure and runs locally.
 * 
 * Usage: Visit http://pfcentral.local/seed_librenms_settings.php
 */

$DB_HOST = 'localhost';
$DB_NAME = 'pfcentral';
$DB_USER = 'root';
$DB_PASS = '';
$DB_PORT = 3306;

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>❌ DB Connection Failed: " . htmlspecialchars($e->getMessage()) . "</h2>");
}

// 1. Core Config row structures
$defaultConfigs = [
    'librenms_api_url'       => 'http://localhost/api/v0',
    'librenms_api_token'     => 'YOUR_LIBRENMS_API_TOKEN_HERE',
    'librenms_snmp_community'=> 'your-secure-snmp-community',
    'librenms_snmp_version'  => 'v2c'
];

// 2. Ensure rows exist in database
foreach ($defaultConfigs as $name => $val) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM core_config WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() == 0) {
        $insert = $pdo->prepare("INSERT INTO core_config (name, value) VALUES (?, ?)");
        $insert->execute([$name, $val]);
    }
}

// 3. Handle POST settings updates
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        foreach ($defaultConfigs as $name => $val) {
            if (isset($_POST[$name])) {
                $update = $pdo->prepare("UPDATE core_config SET value = ? WHERE name = ?");
                $update->execute([trim($_POST[$name]), $name]);
            }
        }
        $message = '✅ LibreNMS configuration settings saved to database successfully!';
    }
    
    if (isset($_POST['self_destruct'])) {
        if (@unlink(__FILE__)) {
            header("Location: /");
            exit;
        } else {
            $message = '❌ Failed to auto-delete script. Please delete public/seed_librenms_settings.php manually.';
        }
    }
}

// 4. Fetch current values for inputs
$currentValues = [];
foreach ($defaultConfigs as $name => $val) {
    $stmt = $pdo->prepare("SELECT value FROM core_config WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $currentValues[$name] = $stmt->fetchColumn() ?: $val;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibreNMS Core Settings Manager — pfCentral</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #090d16 0%, #111827 50%, #090d16 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f3f4f6;
            padding: 20px;
        }
        .card {
            background: rgba(17, 24, 39, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6);
        }
        .badge {
            display: inline-block;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            color: white;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
        h1 { font-size: 26px; font-weight: 700; margin-bottom: 8px; color: #f9fafb; }
        .subtitle { font-size: 13px; color: #9ca3af; margin-bottom: 30px; line-height: 1.5; }
        
        .alert {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 13px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #a5b4fc;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input, select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.2s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .btn {
            display: inline-block;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        
        .btn-destruct {
            background: transparent;
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.3);
            padding: 10px 20px;
        }
        .btn-destruct:hover {
            background: rgba(248, 113, 113, 0.1);
            border-color: #f87171;
        }
        .note {
            font-size: 11px;
            color: #9ca3af;
            line-height: 1.5;
            margin-top: 20px;
            background: rgba(255,255,255,0.02);
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #6366f1;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="badge">⚙️ Database Configurations</div>
    <h1>LibreNMS Settings Engine</h1>
    <p class="subtitle">Directly seed and manage the core integration variables stored within the <code>core_config</code> production database.</p>

    <?php if ($message): ?>
        <div class="alert"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="librenms_api_url">LibreNMS API Endpoint URL</label>
            <input type="url" name="librenms_api_url" id="librenms_api_url" value="<?= htmlspecialchars($currentValues['librenms_api_url']) ?>" placeholder="e.g. http://10.0.0.5/api/v0" required>
        </div>

        <div class="form-group">
            <label for="librenms_api_token">LibreNMS API Access Token</label>
            <input type="text" name="librenms_api_token" id="librenms_api_token" value="<?= htmlspecialchars($currentValues['librenms_api_token']) ?>" placeholder="Generate in LibreNMS Global Settings -> API" required>
        </div>

        <div class="form-group">
            <label for="librenms_snmp_community">Global SNMP Community String</label>
            <input type="text" name="librenms_snmp_community" id="librenms_snmp_community" value="<?= htmlspecialchars($currentValues['librenms_snmp_community']) ?>" placeholder="e.g. public or custom_secure_string" required>
        </div>

        <div class="form-group">
            <label for="librenms_snmp_version">SNMP Protocol Version</label>
            <select name="librenms_snmp_version" id="librenms_snmp_version">
                <option value="v2c" <?= $currentValues['librenms_snmp_version'] === 'v2c' ? 'selected' : '' ?>>v2c (Community-based)</option>
                <option value="v3" <?= $currentValues['librenms_snmp_version'] === 'v3' ? 'selected' : '' ?>>v3 (User-based Sec/Auth)</option>
                <option value="v1" <?= $currentValues['librenms_snmp_version'] === 'v1' ? 'selected' : '' ?>>v1 (Legacy)</option>
            </select>
        </div>

        <div class="note">
            💡 <strong>Integration Architecture:</strong> The Spark Command <code>php spark librenms:sync</code> reads these keys directly from your database. If you do not configure database rows, it falls back to the static keys defined in your <code>.env</code> file.
        </div>

        <div class="actions">
            <button type="submit" name="self_destruct" class="btn btn-destruct" onclick="return confirm('⚠️ This will permanently delete this settings script from disk. Make sure you have saved all changes first!');">🔒 Self-Destruct Script</button>
            <button type="submit" name="update_settings" class="btn">💾 Save Settings</button>
        </div>
    </form>
</div>
</body>
</html>
