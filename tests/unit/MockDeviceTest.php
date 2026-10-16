<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MockDeviceTest extends CIUnitTestCase
{
    public function testMockTelemetryXmlStructure()
    {
        $hostname = 'fw-unit-test.pfcentral.local';
        $xmlPayload = <<<XML
<?xml version="1.0"?>
<pfsense>
    <system>
        <hostname>{$hostname}</hostname>
        <version>23.09.1-RELEASE</version>
        <uptime>10 days</uptime>
        <cpu_usage>15%</cpu_usage>
        <memory_usage>35%</memory_usage>
        <disk_usage>20%</disk_usage>
        <load_averages>0.10, 0.15, 0.12</load_averages>
    </system>
    <interfaces>
        <wan><status>up</status><ip>198.51.100.1</ip></wan>
        <lan><status>up</status><ip>192.168.1.1</ip></lan>
    </interfaces>
</pfsense>
XML;

        $xml = simplexml_load_string($xmlPayload);
        $this->assertNotFalse($xml, 'XML should be valid pfSense telemetry');
        $this->assertSame($hostname, (string) $xml->system->hostname);
        $this->assertSame('up', (string) $xml->interfaces->wan->status);
        $this->assertSame('198.51.100.1', (string) $xml->interfaces->wan->ip);
    }

    public function testDeviceKeyHeaderIntegrity()
    {
        // Device keys must be 64-char hex strings (32 random bytes)
        $rawBytes = random_bytes(32);
        $deviceKey = bin2hex($rawBytes);

        $this->assertSame(64, strlen($deviceKey));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $deviceKey);
    }
}
