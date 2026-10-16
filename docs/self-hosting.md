# Self-Hosting pfCentral

Self-hosting is pfCentral's core value proposition. You retain 100% control over firewall credentials, change history, and telemetry without relying on third-party cloud infrastructure.

---

## 1. Deployment Topologies

pfCentral supports multiple deployment topologies based on network architecture:

### Topology A: Single-Site Internal Server (LAN / Management VLAN)
- **Best For**: Single enterprise headquarters or private data center.
- **Connectivity**: Remote branches connect over private MPLS, site-to-site IPsec, or WireGuard tunnels to reach pfCentral's internal IP.
- **Exposure**: Zero public internet exposure.

### Topology B: Reverse-Proxy DMZ Server
- **Best For**: Distributed branches connecting over public broadband internet.
- **Connectivity**: pfCentral runs behind a hardened reverse proxy (Nginx or HAProxy) terminating TLS.
- **Hardening**: Only `/endpoint`, `/backup`, and `/configpush/pickup` are allowed from branch public IPs; management WebGUI paths (`/login`, `/dashboard`) are restricted to internal admin IPs via firewall ACLs.

---

## 2. Production Hardening Checklist

Before exposing pfCentral to branch firewalls:

- [ ] **HTTPS Enforced**: Configure SSL/TLS certificates and set `app.forceGlobalSecureRequests = true` in `.env`.
- [ ] **Restrict WebGUI Access**: Use Nginx `allow` / `deny` blocks or firewall rules to limit `/login` access to authorized admin jumpboxes.
- [ ] **Database Isolation**: Run the database on a private network socket or non-default port.
- [ ] **Backup Directory Security**: Ensure `writable/pfcentral_backup` is outside the web server document root or protected with `deny all`.
- [ ] **Rotate Master Password**: Use `php spark settings:master-password` to establish strong encryption for stored device credentials.
- [ ] **Cron Automation**: Schedule background health sweeps and drift verification:
  ```bash
  # Check config drift hourly
  0 * * * * php /var/www/pfcentral/spark jobs:verify --limit=25 >> /var/www/pfcentral/writable/logs/config_verify.log 2>&1
  # Probe link availability every 5 minutes
  */5 * * * * php /var/www/pfcentral/spark availability:probe >> /var/www/pfcentral/writable/logs/probe.log 2>&1
  ```
