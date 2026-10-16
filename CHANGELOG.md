# Changelog

All notable changes to **pfCentral** are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-16

### Initial Open-Source Release

#### Added
- **Fleet Inventory & Telemetry**:
  - Live heartbeat ingestion via `POST /endpoint` with bearer `DEVICEKEY` authentication.
  - Telemetry tracking for CPU, RAM, disk, load averages, interfaces, and WireGuard tunnels.
  - Device CRUD with encrypted credential storage at rest.
- **Desired-State Configuration Push**:
  - Declarative job queue with preview, verification window, and auto-rollback.
  - Phased deployments with canary testing support.
  - Multi-person approval controls for fleet-wide pushes and shell scripts.
- **Fleet Configuration Search (`/configsearch`)**:
  - Stored `config.xml` full-text search and XPath indexing.
  - Filterable slice exports in CSV format.
- **Automated Backups & Disaster Recovery**:
  - Automated daily XML backup retrieval via `POST /backup`.
  - Visual side-by-side XML diff viewer.
  - One-Click Disaster Recovery (DR) package generator.
- **Branch Availability & Monitoring (`/availability`)**:
  - Multi-WAN link latency and status tracking (MPLS, WireGuard, Internet).
  - Flap detection with threshold alarms.
  - PDF, XLSX, and CSV compliance export reports.
- **Compliance & Security Matrix (`/compliance`)**:
  - pfSense release tracking and CVE vulnerability cross-referencing.
  - Custom mitigation notes per appliance.
- **Security & Identity**:
  - Native CodeIgniter Shield RBAC (`superadmin`, `admin`, `operator`, `viewer`).
  - Optional Active Directory / LDAP authentication module with local database fallback.
  - Cryptographically minted 32-byte per-device credentials (`/var/pfcentral/devicekey`).
- **Developer & Self-Hosting Tools**:
  - Built-in Mock pfSense Device Simulator (`php spark pfcentral:mock-device`).
  - 1-Command Automated Platform Installer (`php spark pfcentral:install`).
  - Official Dockerfile and `docker-compose.yml` deployment profiles.
  - Full PHPUnit automated test suite.
