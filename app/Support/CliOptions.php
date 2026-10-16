<?php

namespace App\Support;

use CodeIgniter\CLI\CLI;

/**
 * Reading spark options the way the usage strings say to write them — and the way people actually type.
 *
 * CodeIgniter 4.6's command-line parser never learned `--name=value`. `CLI::parseCommandLine()` strips the
 * leading dashes and stores the whole remaining string as the option *key* with a null value, so for
 * `--limit=5` there is no `$params['limit']` and `CLI::getOption('limit')` returns null. Nobody notices,
 * because the command still runs: it just runs with its default.
 *
 * That silence is the problem. Every usage string in this project documents the `--name=<value>` form, and
 * it is the form every other CLI accepts, so:
 *
 *   php spark jobs:run --job=42      sweeps five jobs instead of running job 42
 *   php spark config:index --device=25   indexes the whole fleet
 *   php spark availability:probe --device=YAVATMAL   probes every branch
 *
 * An operator who believes they have scoped a command to one device is the operator who most needs it
 * scoped. This reads both spellings, so the documented form works and `--name value` keeps working exactly
 * as before.
 */
final class CliOptions
{
    /** The value of an option, however it was spelled. */
    public static function value(array $params, string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $params) && $params[$name] !== null && $params[$name] !== '') {
            return (string) $params[$name];
        }

        foreach ($params as $key => $value) {
            $key = (string) $key;

            if (str_starts_with($key, $name . '=')) {
                $inline = substr($key, strlen($name) + 1);

                return $inline === '' ? $default : $inline;
            }
        }

        $option = CLI::getOption($name);

        return $option === null || $option === '' ? $default : (string) $option;
    }

    /** Whether a flag was given, in either spelling (`--dry`, `--dry=1`). */
    public static function flag(array $params, string $name): bool
    {
        if (array_key_exists($name, $params)) {
            return true;
        }

        foreach ($params as $key => $value) {
            if (str_starts_with((string) $key, $name . '=')) {
                return true;
            }
        }

        return CLI::getOption($name) !== null;
    }

    /** An option read as a positive integer, or the default when it was absent or nonsensical. */
    public static function int(array $params, string $name, ?int $default = null): ?int
    {
        $value = self::value($params, $name);

        if ($value === null || !is_numeric($value)) {
            return $default;
        }

        return max(1, (int) $value);
    }
}
