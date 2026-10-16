# Mock pfSense Device Simulator

pfCentral provides a native **Mock pfSense Device Simulator** designed for local software development, automated CI pipeline testing, and offline feature demonstrations without needing physical Netgate firewalls.

---

## 1. Overview

The simulator reproduces:
- Real-time heartbeat telemetry XML parsing.
- Dynamic CPU, RAM, disk, load averages, and interface statistics.
- Legitimate pfSense `config.xml` backup generation and storage.
- Multi-WAN link latency and failure simulations (online/offline transitions).

---

## 2. CLI Usage

The simulator is invoked via CodeIgniter Spark:

```bash
# Run simulation for default device (ID: 1)
php spark pfcentral:mock-device

# Target a specific device by ID
php spark pfcentral:mock-device --device=2

# Ingest telemetry metrics only
php spark pfcentral:mock-device --device=1 --mode=telemetry

# Generate a backup XML snapshot only
php spark pfcentral:mock-device --device=1 --mode=backup

# Simulate a network outage / link down state
php spark pfcentral:mock-device --device=1 --status=down --latency=250ms
```

---

## 3. Integration in Automated Tests

You can invoke the mock device programmatically in PHPUnit tests or deployment verification scripts to assert that controllers properly update device status rows and availability logs.
