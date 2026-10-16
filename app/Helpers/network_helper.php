<?php

if (!function_exists('checkPortStatus')) {
    function checkPortStatus($ip, $port)
    {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (empty($port) || !is_numeric($port) || $port < 1 || $port > 65535) {
            return false;
        }
        $port = (int)$port;
        $timeout = 2;
        $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }
}