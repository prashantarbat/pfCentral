<?php

namespace App\Services\ConfigPush;

/**
 * How a config payload actually reaches a device.
 *
 * Phase 1 ships only the SSH transport, but the job runner picks a transport by
 * preference, so the Phase 2 agent transport (device-initiated pickup over the
 * pfCentral agent channel) plugs in here without touching the queue or the UI.
 */
interface ConfigTransportInterface
{
    /**
     * Short machine name, e.g. 'ssh' or 'agent'. Recorded nowhere critical, used for logs.
     */
    public function name(): string;

    /**
     * Whether this transport can act on the given device record right now.
     *
     * When a job is supplied, this also answers whether the transport can carry *that* job to this
     * device. The distinction matters: a device agent older than the fragment-merge support would
     * install a section fragment as the whole configuration, so a merge job must not be armed for it
     * — it has to fall through to a transport that can merge.
     *
     * @param array $device pf_inventory row
     * @param array $job    the job about to be delivered, or [] when only reachability is being asked
     */
    public function supports(array $device, array $job = []): bool;

    /**
     * Apply a job payload to one device.
     *
     * Implementations MUST snapshot the device's current config before changing it and
     * MUST refuse to change anything if that snapshot cannot be taken.
     *
     * @param array    $device pf_inventory row
     * @param array    $job    ['id' => int, 'batch_id' => string, 'payload' => string, 'payload_type' => 'xml'|'script',
     *                          'payload_mode' => 'replace'|'merge']
     * @param callable $emit   function (string $status, string $message): void  — 'info'|'success'|'error'
     *
     * @return array{status:'success'|'failed'|'pending', message:string, ip:?string, backup_id:?int, backup_file:?string}
     */
    public function deliver(array $device, array $job, callable $emit): array;

    /**
     * Reads the configuration the device is running *now*.
     *
     * This exists for post-apply verification: "applied" is a report about a write, and the only way
     * to know the device is still running what the job intended is to look at the device. A transport
     * that cannot read a configuration must say so rather than guess — the agent channel is
     * device-initiated, so pfCentral never opens a connection to it and only the device itself can
     * report what it now runs.
     *
     * Implementations must never throw: an unreachable device is a normal, expected answer that the
     * verification layer records as 'unavailable'.
     *
     * @param array $device pf_inventory row
     *
     * @return array{ok:bool, contents:?string, message:string}
     */
    public function readConfig(array $device): array;

    /**
     * Restore a device from a previously captured snapshot.
     *
     * @param array    $device pf_inventory row
     * @param array    $backup ['id' => ?int, 'file_name' => ?string, 'path' => string]
     * @param callable $emit   function (string $status, string $message): void
     *
     * @return array{status:'success'|'failed', message:string, ip:?string}
     */
    public function restore(array $device, array $backup, callable $emit): array;
}
