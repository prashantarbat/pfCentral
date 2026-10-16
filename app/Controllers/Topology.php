<?php

namespace App\Controllers;

use App\Models\CoreConfigModel;
use App\Models\LocationModel;
use App\Models\WgIllModel;
use App\Models\WgMplsModel;

class Topology extends BaseController
{
    private const HUBS = [
        [
            'id' => 'HUB-PRIM-CORE01',
            'name' => 'HUB-PRIM-CORE01 - MPLS',
            'ip' => '172.25.1.1',
            'lat' => 19.9959795,
            'lng' => 73.7444891,
            'mapLat' => 20.0759795,
            'mapLng' => 73.6644891,
            'linkType' => 'MPLS',
            'os' => 'VyOS 1.4-rolling-202306190317',
            'uptime' => '10 months 3 weeks 5 hours 22 minutes',
            'location' => 'Primary Core DC',
        ],
        [
            'id' => 'HUB-SEC-CORE02',
            'name' => 'HUB-SEC-CORE02 - ILL',
            'ip' => '172.25.1.2',
            'lat' => 19.9959795,
            'lng' => 73.7444891,
            'mapLat' => 19.9159795,
            'mapLng' => 73.8244891,
            'linkType' => 'ILL',
            'os' => 'VyOS 1.4-rolling-202306190317',
            'uptime' => '7 months 1 week 2 days 4 hours',
            'location' => 'Primary Core DC',
        ],
    ];

    public function index()
    {
        return view('topology', [
            'title' => 'Live Branch Topology',
            'hubs' => self::HUBS,
        ]);
    }

    public function diagram()
    {
        return view('topology_diagram', [
            'title' => 'Live Network Diagram',
            'hubs' => self::HUBS,
        ]);
    }

    /**
     * The network diagram page, served from the same URL it always had
     * (`graphify-out/network_diagram.html`) but through PHP rather than as a static
     * file in public/.
     *
     * The reason is the script tag: network_diagram_live.js is edited on its own, and
     * whatever version was written into the markup at authoring time went stale the
     * moment the JS was changed - the browser kept the copy it had already cached,
     * against freshly rendered HTML. Deriving the version from the file's own mtime
     * means the URL changes whenever the script does, so a cache hit can only ever be
     * the current script.
     *
     * The fallback is time() rather than a fixed number: if mtime cannot be read we
     * know nothing about the file, and "always re-fetch" is the safe side of that.
     */
    public function networkDiagram()
    {
        $script = FCPATH . 'graphify-out/network_diagram_live.js';

        // Load custom diagram styling saved in core_config
        $configModel = new CoreConfigModel();
        $configs = $configModel->whereIn('name', [
            'netdiag_mpls_color',
            'netdiag_ill_color',
            'netdiag_down_mpls_color',
            'netdiag_down_ill_color',
            'netdiag_hub_color',
            'netdiag_branch_color',
            'netdiag_down_branch_color',
            'netdiag_mpls_line_type',
            'netdiag_ill_line_type',
            'netdiag_line_width',
            'netdiag_line_smooth',
            'netdiag_hub_icon',
            'netdiag_branch_icon',
            'netdiag_down_branch_icon',
            'netdiag_hub_image',
            'netdiag_branch_image',
            'netdiag_down_branch_image',
            'netdiag_enable_shapes',
            'netdiag_node_shape',
            'netdiag_hub_shape',
            'netdiag_branch_shape',
            'netdiag_down_branch_shape',
            'netdiag_shape_style',
        ])->findAll();

        $diagramSettings = [
            'mpls_color'        => '#16a34a',
            'ill_color'         => '#f59e0b',
            'down_mpls_color'   => '#dc2626',
            'down_ill_color'    => '#c2410c',
            'hub_color'         => '#2563eb',
            'branch_color'      => '#16a34a',
            'down_branch_color' => '#dc2626',
            'mpls_line_type'    => 'solid',
            'ill_line_type'     => 'dashed',
            'line_width'        => 3,
            'line_smooth'       => 'curvedCW',
            'hub_icon'          => 'f233',
            'branch_icon'       => 'f1ad',
            'down_branch_icon'  => 'f1ad',
            'hub_image'         => '',
            'branch_image'      => '',
            'down_branch_image' => '',
            'enable_shapes'     => '0',
            'node_shape'        => 'circle',
            'hub_shape'         => 'default',
            'branch_shape'      => 'default',
            'down_branch_shape' => 'default',
            'shape_style'       => 'solid',
        ];

        foreach ($configs as $cfg) {
            $key = str_replace('netdiag_', '', $cfg['name']);
            if (isset($cfg['value']) && $cfg['value'] !== '') {
                $val = $cfg['value'];
                // Auto-correct erroneous/legacy globe code
                if ($val === 'f092') {
                    $val = 'f0ac';
                }
                if (in_array($key, ['hub_image', 'branch_image', 'down_branch_image'], true)) {
                    $diagramSettings[$key] = base_url($val);
                } else {
                    $diagramSettings[$key] = $val;
                }
            }
        }

        return view('network_diagram', [
            'title'           => 'Network Diagram - Enterprise Operations View',
            'assetVersion'    => @filemtime($script) ?: time(),
            'diagramSettings' => $diagramSettings,
        ]);
    }

    public function devices()
    {
        $locations = (new LocationModel())->findAll();
        $pfRecords = (new \App\Models\PfInventoryModel())->findAll();

        $pfByLocation = [];
        foreach ($pfRecords as $pf) {
            if (!empty($pf['location'])) {
                // If multiple devices per location, we just take the first for the branch identity
                $pfByLocation[$pf['location']] = $pf;
            }
        }

        $branches = [];

        foreach ($locations as $loc) {
            $pf = $pfByLocation[$loc['id']] ?? null;

            // Only show active branches that are not bypassed
            if ($pf) {
                if (isset($pf['active']) && (int)$pf['active'] === 0) continue;
                if (isset($pf['bypass']) && (int)$pf['bypass'] === 1) continue;
            }

            $links = $this->buildLinks($loc);

            $branches[] = [
                'device_id' => $loc['id'],
                'hostname' => $pf['name'] ?? $loc['name'] ?? '',
                'display' => $loc['name'] ?? '',
                'location' => $loc['name'] ?? '',
                'name' => $loc['name'] ?? '',
                'lat' => (float)($loc['latitude'] ?? 0),
                'lng' => (float)($loc['longitude'] ?? 0),
                'status' => $this->branchStatus($links),
                'links' => $links,
            ];
        }

        return $this->response->setJSON([
            'hubs' => self::HUBS,
            'branches' => $branches,
        ]);
    }

    public function status()
    {
        return $this->response->setJSON([
            'mpls' => $this->latestInterfaceStatuses(new WgMplsModel()),
            'ill' => $this->latestInterfaceStatuses(new WgIllModel()),
        ]);
    }

    private function getLibreNmsDevices(): array
    {
        $config = new CoreConfigModel();
        $settings = array_column(
            $config->whereIn('name', ['librenms_api_url', 'librenms_api_token'])->findAll(),
            'value',
            'name'
        );

        $apiUrl = rtrim($settings['librenms_api_url'] ?? env('LIBRENMS_API_URL') ?? '', '/');
        $token = $settings['librenms_api_token'] ?? env('LIBRENMS_API_TOKEN') ?? '';

        if ($apiUrl === '' || $token === '') {
            return [];
        }

        $ch = curl_init($apiUrl . '/devices');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['X-Auth-Token: ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $raw === false) {
            return [];
        }

        $payload = json_decode($raw, true);

        return is_array($payload['devices'] ?? null) ? $payload['devices'] : [];
    }

    private function getLocalLocationMap(): array
    {
        $map = [];

        foreach ((new LocationModel())->findAll() as $location) {
            $map[$this->normalizeName($location['name'] ?? '')] = $location;
        }

        return $map;
    }

    private function buildLinks(?array $location): array
    {
        if ($location === null) {
            return [
                $this->linkPayload('MPLS', null, null, self::HUBS[0]['id']),
                $this->linkPayload('ILL', null, null, self::HUBS[1]['id']),
            ];
        }

        return [
            $this->linkPayload(
                'MPLS',
                $location['wg_mpls'] ?? null,
                $this->latestStatus(new WgMplsModel(), $location['wg_mpls'] ?? null),
                self::HUBS[0]['id']
            ),
            $this->linkPayload(
                'ILL',
                $location['wg_ill'] ?? null,
                $this->latestStatus(new WgIllModel(), $location['wg_ill'] ?? null),
                self::HUBS[1]['id']
            ),
        ];
    }

    private function linkPayload(string $type, ?string $interface, ?int $status, string $hubId): array
    {
        return [
            'type' => $type,
            'interface' => $interface,
            'status' => $status,
            'hubId' => $hubId,
        ];
    }

    private function latestStatus($model, ?string $interface): ?int
    {
        $interface = trim((string) $interface);

        if ($interface === '') {
            return null;
        }

        $row = $model->where('interface', $interface)->orderBy('id', 'DESC')->first();

        return $row === null ? null : (int) $row['status'];
    }

    private function latestInterfaceStatuses($model): array
    {
        $rows = $model->select('interface, status, updated_at')->orderBy('id', 'DESC')->findAll();
        $statuses = [];

        foreach ($rows as $row) {
            $interface = $row['interface'] ?? '';

            if ($interface === '' || isset($statuses[$interface])) {
                continue;
            }

            $statuses[$interface] = [
                'interface' => $interface,
                'status' => (int) $row['status'],
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }

        return array_values($statuses);
    }

    private function branchStatus(array $links): string
    {
        foreach ($links as $link) {
            if (($link['status'] ?? null) === 1) {
                return 'up';
            }
        }

        foreach ($links as $link) {
            if (($link['status'] ?? null) === 0) {
                return 'down';
            }
        }

        return 'unknown';
    }

    private function parseCoordinates(string $location): ?array
    {
        if (!preg_match('/\[(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\]/', $location, $matches)) {
            return null;
        }

        return [
            'lat' => (float) $matches[1],
            'lng' => (float) $matches[2],
        ];
    }

    private function stripCoordinates(string $location): string
    {
        return trim(preg_replace('/\s*\[-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\]\s*/', '', $location));
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }
}
