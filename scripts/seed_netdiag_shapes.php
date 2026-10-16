<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'pfcentral');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

$settings = [
    'netdiag_enable_shapes'     => '0', // 0 = disabled (circles removed by default)
    'netdiag_node_shape'        => 'circle',
    'netdiag_hub_shape'         => 'default',
    'netdiag_branch_shape'      => 'default',
    'netdiag_down_branch_shape' => 'default',
    'netdiag_shape_style'       => 'solid',
];

$stmt = $mysqli->prepare("INSERT INTO core_config (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
foreach ($settings as $name => $value) {
    $stmt->bind_param('ss', $name, $value);
    $stmt->execute();
    echo "Set {$name} = {$value}\n";
}
echo "Seeding completed successfully.\n";
