# Backup & Disaster Recovery (DR) Procedures

## 1. Automated Backup Pipeline

Every registered pfSense appliance runs `backup.sh` daily:

```sh
# Triggered by cron on pfSense:
curl -s -k -X POST \
  -H "DEVICEKEY: $(cat /var/pfcentral/devicekey)" \
  --data-binary "@/cf/conf/config.xml" \
  "https://pfcentral.yourdomain.com/backup"
```

1. **Ingestion & Validation**: pfCentral inspects the XML root element (`<pfsense>`) and verifies syntactic integrity.
2. **Snapshot Storage**: Files are saved with timestamps to `writable/pfcentral_backup/<device_name>/config_<YYYYMMDD_HHMMSS>.xml`.
3. **Database Indexing**: The snapshot is cataloged in `pf_backups` with metadata, line counts, and SHA-256 hashes.

---

## 2. One-Click Disaster Recovery (DR) Package

When a physical Netgate firewall or hardware server suffers catastrophic failure:

1. Open **Devices > View Backups** for the affected device.
2. Click **Download Disaster Recovery Package**.
3. pfCentral generates a `.zip` archive containing:
   - Latest verified `config.xml`.
   - Complete interface, VLAN, and WAN mapping sheet.
   - WireGuard / IPsec pre-shared keys and peer addresses.
   - Quick restore script for cold-spare hardware.
4. Flash standard pfSense firmware onto replacement hardware, plug in a USB stick containing `config.xml`, and boot. pfSense automatically restores the complete configuration during initial setup.
