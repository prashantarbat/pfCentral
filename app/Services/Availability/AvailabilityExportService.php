<?php

namespace App\Services\Availability;

use App\Models\BranchAvailabilityEventModel;
use ZipArchive;

/**
 * High-performance, zero-dependency OpenXML (.xlsx) generator for Branch Availability & WAN SLA Telemetry.
 *
 * Emits genuine Microsoft Excel OpenXML (.xlsx) binary workbooks with formatted headers, frozen panes,
 * proper data typing (numeric, float percentages, strings), and multiple sheets (Device Matrix + Outage Log).
 */
class AvailabilityExportService
{
    /**
     * Builds a comprehensive multi-sheet XLSX workbook containing:
     *  Sheet 1: Branch Device Availability & Multi-WAN Status
     *  Sheet 2: Outage & Incident Log with Change Correlation
     *
     * @param array<string, mixed> $report Full report data from AvailabilityService::report()
     * @param array<string, mixed> $exportRows Correlated outage rows from AvailabilityService::exportRows()
     * @return string Binary contents of the .xlsx file
     */
    public static function buildFleetXlsx(array $report, array $exportRows): string
    {
        $branches = $report['branches'] ?? [];
        $outages  = $exportRows['rows'] ?? [];

        // Sheet 1: Branch Availability Columns
        $branchHeaders = [
            'Sr.',
            'Branch Name',
            'Location',
            'Management IP',
            'Overall Reachability',
            'Multi-WAN Link Health',
            'Flapping State',
            'Total Flaps',
            'MPLS Interface',
            'MPLS Status',
            'MPLS Flaps',
            'ILL Interface',
            'ILL Status',
            'ILL Flaps',
            'BDR Interface',
            'BDR Status',
            'BDR Flaps',
            'SLA Uptime (%)',
            'Downtime (Minutes)',
            'Total Outages',
            'Observed Window (Hrs)',
            'Last Probe Time',
            'Probe Target Address',
            'Probe Latency (ms)',
            'Last Check-in',
            'Agent Status',
            'Monitored Since',
        ];

        $branchRowsData = [];
        $sr = 1;
        foreach ($branches as $b) {
            $mpls = $b['links']['mpls'] ?? [];
            $ill  = $b['links']['ill'] ?? [];
            $bdr  = $b['links']['bdr'] ?? [];

            $mplsStatusStr = isset($mpls['status']) ? ($mpls['status'] === 1 ? 'UP' : 'DOWN') : 'NOT CONFIGURED';
            $illStatusStr  = isset($ill['status']) ? ($ill['status'] === 1 ? 'UP' : 'DOWN') : 'NOT CONFIGURED';
            $bdrStatusStr  = isset($bdr['status']) ? ($bdr['status'] === 1 ? 'UP' : 'DOWN') : 'NOT CONFIGURED';

            $linkHealth = match ($b['link_health'] ?? '') {
                'healthy'  => 'All Links OK',
                'degraded' => 'Failover (Backup Active)',
                'down'     => 'All Links Down',
                default    => 'Not Observed',
            };

            $agentStatus = !empty($b['silent_since']) ? 'SILENT (Past Threshold)' : 'Active / Normal';
            $observedHrs = isset($b['observed_seconds']) ? round(((int) $b['observed_seconds']) / 3600, 1) : 0;
            $downtimeMin = isset($b['downtime_seconds']) ? round(((int) $b['downtime_seconds']) / 60, 1) : 0;

            $branchRowsData[] = [
                $sr++,
                (string) ($b['name'] ?? ''),
                (string) ($b['location'] ?? ''),
                (string) (!empty($b['man_ip']) ? $b['man_ip'] : ($b['status']['ip'] ?? '')),
                strtoupper((string) ($b['state'] ?? 'UNKNOWN')),
                $linkHealth,
                !empty($b['is_flapping']) ? 'FLAPPING' : 'STABLE',
                (int) ($b['total_flaps'] ?? 0),
                (string) ($mpls['interface'] ?? '—'),
                $mplsStatusStr,
                (int) ($mpls['flap_count'] ?? 0),
                (string) ($ill['interface'] ?? '—'),
                $illStatusStr,
                (int) ($ill['flap_count'] ?? 0),
                (string) ($bdr['interface'] ?? '—'),
                $bdrStatusStr,
                (int) ($bdr['flap_count'] ?? 0),
                $b['uptime'] !== null ? (float) $b['uptime'] : 'N/A',
                $downtimeMin,
                (int) ($b['outages'] ?? 0),
                $observedHrs,
                (string) ($b['last_probe_at'] ?? 'Never'),
                (string) ($b['last_probe_target'] ?? '—'),
                $b['last_probe_ms'] !== null ? (int) $b['last_probe_ms'] : '—',
                (string) ($b['last_checkin_at'] ?? 'Never'),
                $agentStatus,
                (string) ($b['monitored_since'] ?? '—'),
            ];
        }

        // Sheet 2: Outages & Incident Log Columns
        $outageHeaders = [
            'Sr.',
            'Branch Name',
            'Location',
            'Outage Detection (How)',
            'Started At',
            'Ended At',
            'Duration (Minutes)',
            'Diagnostic Evidence / Reason',
            'Preceding Config Change',
            'Change Gap (Min)',
            'Branch SLA Uptime (%)',
            'Branch Downtime (Min)',
            'Branch Total Outages',
            'MPLS State',
            'ILL State',
            'BDR State',
            'Total Link Flaps',
        ];

        $outageRowsData = [];
        $sr = 1;
        foreach ($outages as $o) {
            $mplsStatus = $o['links']['mpls']['status'] ?? null;
            $illStatus  = $o['links']['ill']['status'] ?? null;
            $bdrStatus  = $o['links']['bdr']['status'] ?? null;

            $mplsStr = $mplsStatus !== null ? ($mplsStatus === 1 ? 'UP' : 'DOWN') : '—';
            $illStr  = $illStatus !== null ? ($illStatus === 1 ? 'UP' : 'DOWN') : '—';
            $bdrStr  = $bdrStatus !== null ? ($bdrStatus === 1 ? 'UP' : 'DOWN') : '—';

            $changeText = isset($o['job_id']) && (int) $o['job_id'] > 0
                ? 'Job #' . (int) $o['job_id']
                : 'No change recorded';

            $durationMin = $o['duration_seconds'] !== null
                ? round(((int) $o['duration_seconds']) / 60, 1)
                : 'STILL DOWN (Active)';

            $outageRowsData[] = [
                $sr++,
                (string) ($o['device_name'] ?? ''),
                (string) ($o['location'] ?? ''),
                BranchAvailabilityEventModel::kindLabel((string) ($o['kind'] ?? '')),
                (string) ($o['started_at'] ?? ''),
                !empty($o['ended_at']) ? (string) $o['ended_at'] : 'STILL DOWN',
                $durationMin,
                (string) ($o['evidence'] ?? ''),
                $changeText,
                isset($o['job_gap_minutes']) && $o['job_gap_minutes'] !== null ? (int) $o['job_gap_minutes'] : '—',
                $o['uptime'] !== null ? (float) $o['uptime'] : 'N/A',
                isset($o['downtime_seconds']) ? round(((int) $o['downtime_seconds']) / 60, 1) : 0,
                isset($o['outages']) ? (int) $o['outages'] : 0,
                $mplsStr,
                $illStr,
                $bdrStr,
                (int) ($o['total_flaps'] ?? 0),
            ];
        }

        $sheets = [
            [
                'name'    => 'Branch Availability',
                'headers' => $branchHeaders,
                'rows'    => $branchRowsData,
            ],
            [
                'name'    => 'Outage History',
                'headers' => $outageHeaders,
                'rows'    => $outageRowsData,
            ],
        ];

        return self::packageZip($sheets);
    }

    /**
     * Builds a single-device XLSX workbook containing:
     *  Sheet 1: Device Telemetry & SLA Profile
     *  Sheet 2: Device Outage & Flap Incident History
     *
     * @param array<string, mixed> $deviceData Data from AvailabilityService::getDeviceAvailabilityDetail()
     * @return string Binary contents of the .xlsx file
     */
    public static function buildDeviceXlsx(array $deviceData): string
    {
        $device  = $deviceData['device'] ?? [];
        $links   = $deviceData['links'] ?? [];
        $outages = $deviceData['outages'] ?? [];

        // Sheet 1: Single Device Profile
        $profileHeaders = ['Parameter', 'Value', 'Notes / Thresholds'];
        $profileRows = [
            ['Device Name', (string) ($device['name'] ?? ''), 'Branch Firewall'],
            ['Management IP', (string) ($device['ip'] ?? ''), 'Primary Central Access'],
            ['Location', (string) ($device['location_name'] ?? 'Unassigned'), 'Branch / Office'],
            ['Overall State', strtoupper((string) ($device['state'] ?? 'UNKNOWN')), 'Based on central probe & silence logic'],
            ['Link Health', AvailabilityService::linkHealthLabel((string) ($device['link_health'] ?? '')), 'Multi-link redundancy status'],
            ['SLA Uptime (%)', $device['uptime'] !== null ? (float) $device['uptime'] . '%' : 'Not Observed', 'Calculated across watched period'],
            ['Total Downtime (Min)', (string) round(((int) ($device['downtime_seconds'] ?? 0)) / 60, 1), 'Cumulative outage downtime in window'],
            ['Total Outages', (string) (int) ($device['outages'] ?? 0), 'Number of outage incidents'],
            ['Total Link Flaps', (string) (int) ($device['total_flaps'] ?? 0), 'Link transitions across all uplinks'],
            ['MPLS Link Interface', (string) ($links['mpls']['interface'] ?? '—'), 'Status: ' . ($links['mpls']['status'] === 1 ? 'UP' : ($links['mpls']['status'] === 0 ? 'DOWN' : 'Not Configured'))],
            ['MPLS Flap Count', (string) (int) ($links['mpls']['flap_count'] ?? 0), 'MPLS state transitions'],
            ['ILL Link Interface', (string) ($links['ill']['interface'] ?? '—'), 'Status: ' . ($links['ill']['status'] === 1 ? 'UP' : ($links['ill']['status'] === 0 ? 'DOWN' : 'Not Configured'))],
            ['ILL Flap Count', (string) (int) ($links['ill']['flap_count'] ?? 0), 'Internet leased line flaps'],
            ['BDR Link Interface', (string) ($links['bdr']['interface'] ?? '—'), 'Status: ' . ($links['bdr']['status'] === 1 ? 'UP' : ($links['bdr']['status'] === 0 ? 'DOWN' : 'Not Configured'))],
            ['BDR Flap Count', (string) (int) ($links['bdr']['flap_count'] ?? 0), 'Backup disaster recovery flaps'],
            ['Last Probe Time', (string) ($device['last_probe_at'] ?? 'Never'), 'Central probe timestamp'],
            ['Probe Target', (string) ($device['last_probe_target'] ?? '—'), 'TCP destination'],
            ['Probe Latency (RTT)', $device['last_probe_ms'] !== null ? $device['last_probe_ms'] . ' ms' : '—', 'TCP handshake round-trip time'],
            ['Last Agent Check-in', (string) ($device['last_checkin_at'] ?? 'Never'), 'Device telemetry check-in'],
            ['Monitored Since', (string) ($device['monitored_since'] ?? '—'), 'Observation start timestamp'],
        ];

        // Sheet 2: Device Outage Log
        $outageHeaders = [
            'Sr.',
            'Outage Detection (How)',
            'Started At',
            'Ended At',
            'Duration (Minutes)',
            'Diagnostic Evidence',
            'Preceding Config Job',
            'Change Gap (Min)',
        ];

        $outageRowsData = [];
        $sr = 1;
        foreach ($outages as $o) {
            $changeText = isset($o['job_id']) && (int) $o['job_id'] > 0
                ? 'Job #' . (int) $o['job_id']
                : 'No change recorded';

            $durationMin = $o['duration_seconds'] !== null
                ? round(((int) $o['duration_seconds']) / 60, 1)
                : 'STILL DOWN';

            $outageRowsData[] = [
                $sr++,
                BranchAvailabilityEventModel::kindLabel((string) ($o['kind'] ?? '')),
                (string) ($o['started_at'] ?? ''),
                !empty($o['ended_at']) ? (string) $o['ended_at'] : 'STILL DOWN',
                $durationMin,
                (string) ($o['evidence'] ?? ''),
                $changeText,
                isset($o['job_gap_minutes']) && $o['job_gap_minutes'] !== null ? (int) $o['job_gap_minutes'] : '—',
            ];
        }

        $sheets = [
            [
                'name'    => 'Device SLA Profile',
                'headers' => $profileHeaders,
                'rows'    => $profileRows,
            ],
            [
                'name'    => 'Incident Outages',
                'headers' => $outageHeaders,
                'rows'    => $outageRowsData,
            ],
        ];

        return self::packageZip($sheets);
    }

    /**
     * Packages worksheet arrays into an OpenXML XLSX zip stream.
     *
     * @param list<array{name:string, headers:list<string>, rows:list<list<mixed>>}> $sheets
     * @return string
     */
    protected static function packageZip(array $sheets): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'noc_xlsx_');
        $zip = new ZipArchive();

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to initialize ZipArchive for XLSX generation.');
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n";
        $contentTypes .= '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n";
        $contentTypes .= '  <Default Extension="xml" ContentType="application/xml"/>' . "\n";
        $contentTypes .= '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n";
        $contentTypes .= '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n";
        foreach ($sheets as $idx => $sheet) {
            $sheetId = $idx + 1;
            $contentTypes .= "  <Override PartName=\"/xl/worksheets/sheet{$sheetId}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>\n";
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $rootRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
        $rootRels .= '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n";
        $rootRels .= '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
        foreach ($sheets as $idx => $sheet) {
            $sheetId = $idx + 1;
            $wbRels .= "  <Relationship Id=\"rId{$sheetId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$sheetId}.xml\"/>\n";
        }
        $styleRelId = count($sheets) + 1;
        $wbRels .= "  <Relationship Id=\"rId{$styleRelId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>\n";
        $wbRels .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $wb .= '  <sheets>' . "\n";
        foreach ($sheets as $idx => $sheet) {
            $sheetId = $idx + 1;
            $sheetName = htmlspecialchars(mb_substr($sheet['name'], 0, 31), ENT_XML1, 'UTF-8');
            $wb .= "    <sheet name=\"{$sheetName}\" sheetId=\"{$sheetId}\" r:id=\"rId{$sheetId}\"/>\n";
        }
        $wb .= '  </sheets>' . "\n";
        $wb .= '</workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // 5. xl/styles.xml
        $styles = self::buildStylesXml();
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet*.xml
        foreach ($sheets as $idx => $sheet) {
            $sheetId = $idx + 1;
            $sheetXml = self::buildSheetXml($sheet['headers'], $sheet['rows']);
            $zip->addFromString("xl/worksheets/sheet{$sheetId}.xml", $sheetXml);
        }

        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return (string) $content;
    }

    /**
     * Builds the OpenXML stylesheet with header colors, fonts, borders, and number formats.
     */
    protected static function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="#,##0"/>
    <numFmt numFmtId="165" formatCode="0.00"/>
  </numFmts>
  <fonts count="3">
    <font><name val="Calibri"/><sz val="10.5"/><color rgb="FF0F172A"/></font>
    <font><b/><name val="Calibri"/><sz val="10.5"/><color rgb="FFFFFFFF"/></font>
    <font><b/><name val="Calibri"/><sz val="10.5"/><color rgb="FF0F172A"/></font>
  </fonts>
  <fills count="4">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="5">
    <!-- 0: Standard Body Cell -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>
    <!-- 1: Header Cell (Navy bg, White bold text, centered) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <!-- 2: Integer Cell -->
    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyBorder="1"/>
    <!-- 3: Float / Decimal Cell -->
    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyBorder="1"/>
    <!-- 4: Centered Cell -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
  </cellXfs>
</styleSheet>';
    }

    /**
     * Builds worksheet XML with column headers, auto-filter, frozen top row, and typed cells.
     *
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     * @return string
     */
    protected static function buildSheetXml(array $headers, array $rows): string
    {
        $colCount = count($headers);
        $rowCount = count($rows) + 1;
        $lastColLetter = self::colLetter($colCount);
        $dimension = "A1:{$lastColLetter}{$rowCount}";

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
        $xml .= "  <dimension ref=\"{$dimension}\"/>\n";

        // Freeze top header pane
        $xml .= '  <sheetViews>' . "\n";
        $xml .= '    <sheetView tabSelected="1" workbookViewId="0">' . "\n";
        $xml .= "      <pane ySplit=\"1\" topLeftCell=\"A2\" activePane=\"bottomLeft\" state=\"frozen\"/>\n";
        $xml .= '    </sheetView>' . "\n";
        $xml .= '  </sheetViews>' . "\n";

        // Column default widths
        $xml .= '  <cols>' . "\n";
        for ($c = 1; $c <= $colCount; $c++) {
            $width = ($c === 1) ? 6 : (($c === 2 || $c === 3) ? 28 : 18);
            $xml .= "    <col min=\"{$c}\" max=\"{$c}\" width=\"{$width}\" customWidth=\"1\"/>\n";
        }
        $xml .= '  </cols>' . "\n";

        $xml .= '  <sheetData>' . "\n";

        // Row 1: Headers (style index 1)
        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        foreach ($headers as $colIdx => $headerText) {
            $colLetter = self::colLetter($colIdx + 1);
            $safeText  = htmlspecialchars($headerText, ENT_XML1, 'UTF-8');
            $xml .= "      <c r=\"{$colLetter}1\" s=\"1\" t=\"inlineStr\"><is><t>{$safeText}</t></is></c>\n";
        }
        $xml .= '    </row>' . "\n";

        // Data Rows
        $rowNum = 2;
        foreach ($rows as $row) {
            $xml .= "    <row r=\"{$rowNum}\" ht=\"20\" customHeight=\"1\">\n";
            foreach ($row as $colIdx => $val) {
                $colLetter = self::colLetter($colIdx + 1);
                $cellRef   = "{$colLetter}{$rowNum}";

                if (is_int($val)) {
                    $xml .= "      <c r=\"{$cellRef}\" s=\"2\"><v>{$val}</v></c>\n";
                } elseif (is_float($val)) {
                    $xml .= "      <c r=\"{$cellRef}\" s=\"3\"><v>{$val}</v></c>\n";
                } elseif ($val === null || $val === '') {
                    $xml .= "      <c r=\"{$cellRef}\" s=\"0\"/>\n";
                } else {
                    $strVal = (string) $val;
                    $safeStr = htmlspecialchars($strVal, ENT_XML1, 'UTF-8');
                    $style = 0;
                    if ($strVal === 'UP' || $strVal === 'PASSED' || $strVal === 'REACHABLE' || $strVal === 'All Links OK') {
                        $style = 4; // Centered
                    } elseif ($strVal === 'DOWN' || $strVal === 'STILL DOWN') {
                        $style = 4;
                    }
                    $xml .= "      <c r=\"{$cellRef}\" s=\"{$style}\" t=\"inlineStr\"><is><t>{$safeStr}</t></is></c>\n";
                }
            }
            $xml .= "    </row>\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";

        // Auto-filter
        $xml .= "  <autoFilter ref=\"A1:{$lastColLetter}1\"/>\n";
        $xml .= '</worksheet>';

        return $xml;
    }

    /**
     * Converts a 1-based column number into an Excel column letter (e.g. 1 -> A, 27 -> AA).
     */
    protected static function colLetter(int $col): string
    {
        $colLetter = '';
        while ($col > 0) {
            $modulo = ($col - 1) % 26;
            $colLetter = chr(65 + $modulo) . $colLetter;
            $col = (int) (($col - $modulo) / 26);
        }

        return $colLetter;
    }
}
