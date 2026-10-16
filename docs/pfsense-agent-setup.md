# pfSense Agent Installation & Configuration

pfCentral utilizes lightweight POSIX shell and PHP agent scripts running natively on FreeBSD / pfSense to report health telemetry, transmit backups, and pull configuration jobs.

---

## 1. Quick Onboarding

Log into the target pfSense appliance via SSH (or Diagnostics > Command Prompt) and run the installer:

```sh
# Fetch and run the pfCentral installer
fetch -o /tmp/install.sh "https://pfcentral.yourdomain.com/assets/endpoint/install.sh"
sh /tmp/install.sh "https://pfcentral.yourdomain.com"
```

The installer will:
1. Verify required FreeBSD binaries (`curl`, `openssl`, `sed`, `awk`).
2. Generate a unique 32-byte cryptographically random device secret stored at `/var/pfcentral/devicekey` with file mode `600`.
3. Register the appliance's initial identity with pfCentral.
4. Install `/usr/local/bin/pfcentral-agent` and configure periodic cron jobs (`/etc/crontab`).

---

## 2. Agent Components

All agent files reside under `/var/pfcentral/` on the appliance:

| File | Purpose | Execution |
|---|---|---|
| `devicekey` | Per-device secret key (`mode 600`) | Used on all HTTP requests |
| `agent.sh` | Telemetry probe (CPU, memory, uptime, WAN IP) | Hourly cron |
| `backup.sh` | Exports active `/cf/conf/config.xml` to pfCentral | Daily cron |
| `agent_jobs.php` | Pulls and applies queued desired-state config jobs | Scheduled job runner |
| `agent_watch.php`| Detects post-job configuration drift | Change window watcher |

---

## 3. Removing the Agent

To completely remove the agent from a pfSense appliance:

```sh
fetch -o /tmp/uninstall.sh "https://pfcentral.yourdomain.com/assets/endpoint/uninstall.sh"
sh /tmp/uninstall.sh
```
This removes cron entries, agent executables, and the local secret directory.
