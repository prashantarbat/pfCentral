# Security Policy for pfCentral

Because **pfCentral** centralizes the operational management and configuration of perimeter firewalls, security is our highest engineering priority.

## Supported Versions

Only the latest release receive security patches and updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0.0 | :x:                |

## Reporting a Security Vulnerability

If you discover a security vulnerability in pfCentral, **DO NOT** open a public GitHub issue.

Please report all security vulnerabilities via responsible disclosure by emailing:
**[security@microninfo.com](mailto:security@microninfo.com)**

Please include:
1. A detailed description of the vulnerability.
2. Step-by-step reproduction instructions or proof-of-concept (POC) scripts.
3. Affected components, endpoints, or versions.
4. An assessment of potential impact (e.g. privilege escalation, remote code execution, unauthorized data access).

We acknowledge receipt within 48 hours and work with you to triage and release a patch before public disclosure.

## Security Architecture & Hardening Best Practices

When deploying pfCentral in production environments:

1. **Enforce HTTPS Everywhere**: Always terminate TLS using valid certificates (Let's Encrypt, internal corporate CA). Set `app.forceGlobalSecureRequests = true` in `.env`.
2. **Isolate Management Networks**: Restrict web portal access (`/login`, `/dashboard`, `/configpush`) to internal administrative subnets, VPNs, or jump hosts.
3. **Dedicated Agent Credentialing (`DEVICEKEY`)**:
   - Each pfSense firewall mints a cryptographically secure 32-byte hex secret (`/var/pfcentral/devicekey`) on installation.
   - Never use predictable values or IP derivations for device keys.
4. **Least-Privilege Database Access**: Create a dedicated database user with permissions strictly limited to the `pfcentral` database.
5. **Role-Based Access Control**: Assign users the minimal role required (`viewer`, `operator`, `admin`). Protect all superadministrator accounts with complex passwords and multi-factor authentication where supported.
6. **Encrypted Backups**: Ensure directory permissions on `writable/pfcentral_backup` are strictly restricted (`0700` / owned by the web server process).
