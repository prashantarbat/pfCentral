# pfCentral Development Roadmap

This roadmap outlines planned features, architectural improvements, and milestones for upcoming releases of pfCentral.

---

## Current Status: Version 1.0.0 (Stable Release)
- Centralized fleet telemetry and inventory management.
- Desired-state configuration push pipeline with canary rollouts and auto-rollback.
- Automated versioned backups, side-by-side XML diffs, and One-Click DR packages.
- Fleet-wide configuration XML search and XPath querying.
- Branch availability monitoring, flap detection, and compliance reporting.
- Mock device simulator for testing without physical Netgate hardware.

---

## Near-Term: Version 1.1.0 (Q1 2027)
- [ ] **Multi-Tenant MSP Organizations**: Support for partitioned organizations allowing Managed Service Providers to segregate multiple client fleets within a single pfCentral instance.
- [ ] **Out-of-the-Box Webhook Notifications**: Native Slack, Microsoft Teams, Discord, and generic webhook dispatchers for config drift and link down alerts.
- [ ] **Native OPNsense Support**: Experimental driver support for managing OPNsense firewall fleets using the same desired-state architecture.
- [ ] **Enhanced WireGuard Tunnel Orchestrator**: Automated mesh key exchange and peer provisioning across distributed branch firewalls.

---

## Medium-Term: Version 1.2.0 (Q2 2027)
- [ ] **GitOps Bi-Directional Synchronization**: Direct Git repository integration (GitHub/GitLab) where commits to a repository trigger automated config jobs with review approvals.
- [ ] **Prometheus / OpenTelemetry Exporter**: Expose standardized metrics endpoints for enterprise Grafana and Prometheus observability stacks.
- [ ] **Pre-Flight Schema Validation**: Stricter XML syntax and pfSense schema validation engine before dispatching jobs to remote appliances.

---

## Future Considerations
- Hardware performance benchmarking across Netgate appliance models.
- Automated CARP High-Availability cluster health verification.
- Mobile-responsive push notification app for on-call network engineers.
