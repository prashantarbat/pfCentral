<!--phpfunctions.php-->
<?php
// Function to check port status (Online/Offline)
function checkPortStatus($ip, $port, $timeout = 2)
{
    // Validate IP address or hostname
    if (empty($ip) || (!filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
        return false;
    }

    // Ensure port is a valid number (1-65535)
    if (empty($port) || !is_numeric($port) || $port < 1 || $port > 65535) {
        return false;
    }

    $port = (int)$port;

    // Use stream_socket_client instead of fsockopen for better error handling
    $connection = @stream_socket_client("tcp://$ip:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

    if ($connection) {
        fclose($connection);
        return true;
    }

    return false;
}


function categorizeDevices($devices)
{
    $categorized = [
        'All' => [],
        'Running' => [],
        'Bypassed' => [],
        'Stopped' => [],
        'Unknown' => []
    ];

    foreach ($devices as $device) {
        $status = "Stopped";

        // Check for Unknown status safely
        if (empty($device['name']) || $device['name'] === 'UNKNOWN') {
            $status = "Unknown";
        }
        // Check if the device is bypassed
        elseif (!empty($device['bypass']) && $device['bypass'] == 1) {
            $status = "Bypassed";
        } 
        // Check if the device is running based on DB status
        elseif (isset($device['status']) && in_array(strtolower(trim((string)$device['status'])), ['1', 'online', 'synced', 'running'])) {
            $status = "Running";
        }
        // Check if the device has an IP and port
        elseif (!empty($device['man_ip']) || !empty($device['ip'])) {
            $ip = $device['man_ip'] ?? $device['ip'];
            $port = $device['man_port'] ?? $device['port'];

            if (!empty($ip) && !empty($port) && function_exists('checkPortStatus') && checkPortStatus($ip, $port)) {
                $status = "Running";
            }
        }

        // Assign color based on status (using switch for compatibility)
        switch ($status) {
            case 'Running': $statusColor = 'green'; break;
            case 'Bypassed': $statusColor = 'blue'; break;
            case 'Stopped': $statusColor = 'red'; break;
            case 'Unknown': $statusColor = 'red'; break;
            default: $statusColor = 'black';
        }

        // Add category and status color
        $device['category'] = $status;
        $device['statusColor'] = $statusColor;

        // Categorize device
        $categorized[$status][] = $device;
        $categorized['All'][] = $device;
    }

    return $categorized;
}


function categorizeDevicesFast($devices)
{
    $categorized = [
        'All' => [],
        'Running' => [],
        'Bypassed' => [],
        'Stopped' => [],
        'Unknown' => []
    ];

    foreach ($devices as $device) {
        $status = "Stopped";

        // Check for Unknown status safely
        if (empty($device['name']) || $device['name'] === 'UNKNOWN') {
            $status = "Unknown";
        } 
        // Check if the device is bypassed
        elseif (!empty($device['bypass']) && $device['bypass'] == 1) {
            $status = "Bypassed";
        }
        // Check if the device is running based on fast DB status
        elseif (isset($device['status']) && in_array(strtolower(trim((string)$device['status'])), ['1', 'online', 'synced', 'running'])) {
            $status = "Running";
        }

        // Assign color based on status (using switch for compatibility)
        switch ($status) {
            case 'Running': $statusColor = 'green'; break;
            case 'Bypassed': $statusColor = 'blue'; break;
            case 'Stopped': $statusColor = 'red'; break;
            case 'Unknown': $statusColor = 'red'; break;
            default: $statusColor = 'black';
        }

        // Add category and status color
        $device['category'] = $status;
        $device['statusColor'] = $statusColor;

        // Categorize device
        $categorized[$status][] = $device;
        $categorized['All'][] = $device;

        // Track devices missing compliance banner
        if (isset($device['banner']) && $device['banner'] !== null && (string)$device['banner'] === '0') {
            $categorized['NoHeader'][] = $device;
        }
    }

    return $categorized;
}


?>

