<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */
if (! function_exists('format_datetime')) {
    /**
     * Formats any date/time into dd/mm/yyyy hh:mm AM/PM in Asia/Kolkata timezone.
     */
    function format_datetime($val, string $timezone = 'Asia/Kolkata', string $format = 'd/m/Y h:i A'): string
    {
        if (empty($val) || $val === '—' || $val === 'never' || $val === 'null') {
            return '—';
        }
        try {
            if ($val instanceof \DateTimeInterface) {
                $dt = (clone $val)->setTimezone(new \DateTimeZone($timezone));
            } elseif (is_numeric($val)) {
                $dt = new \DateTime('@' . $val);
                $dt->setTimezone(new \DateTimeZone($timezone));
            } else {
                $str = trim((string) $val);
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}\s+(?:AM|PM)$/i', $str)) {
                    return $str;
                }
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $str, $m)) {
                    return "{$m[3]}/{$m[2]}/{$m[1]}";
                }
                $dt = new \DateTime($str, new \DateTimeZone($timezone));
            }
            return $dt->format($format);
        } catch (\Throwable $e) {
            return (string) $val;
        }
    }
}
