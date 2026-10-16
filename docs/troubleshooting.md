# Troubleshooting & Diagnostics

## Common Issues & Solutions

### 1. Appliance Shows "Offline" or Red in Dashboard
- **Check DNS**: Can the pfSense appliance resolve your pfCentral domain? Run `host pfcentral.yourdomain.com` from pfSense SSH.
- **Check HTTPS Connectivity**: Run `curl -Iv https://pfcentral.yourdomain.com/endpoint` from the appliance.
- **Inspect Agent Secret**: Verify `/var/pfcentral/devicekey` exists and matches `pf_inventory.devicekey` in the pfCentral database.

### 2. Device Key Mismatch
- If an appliance's key was rotated or re-minted, update the key in pfCentral under **Devices > Edit Device** or run the rebind command:
  ```sh
  php spark devicekey:rebind <device_id> <new_key>
  ```

### 3. Database Connection Errors
- Verify database credentials in `.env`.
- Ensure MariaDB/MySQL is listening on the expected interface and port (`3306`).
- Test connectivity from the CLI:
  ```bash
  php spark db:status
  ```

### 4. Writable Storage Permissions
- If backups or logs fail to save, verify file permissions:
  ```bash
  chmod -R 775 writable/
  chown -R www-data:www-data writable/
  ```

---

## Logging & Auditing

- **Application Logs**: `writable/logs/log-<YYYY-MM-DD>.log`
- **Config Job Audit**: Query `/configjobs` or inspect `config_push_logs`
- **Drift Alerts**: Query `/configjobs/alerts` or review `config_alerts`
