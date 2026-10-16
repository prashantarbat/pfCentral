# Security Architecture & Best Practices

## 1. Threat Model & Mitigations

Managing perimeter firewalls centrally introduces specific threats that pfCentral mitigates by design:

### Threat 1: Malicious or Unvalidated Configuration Push
- **Risk**: An administrator pushes a corrupted XML fragment, locking out an entire branch.
- **Mitigation**: Pre-flight diff generation, canary testing on non-critical branches, automated rollback triggers if the management IP becomes unreachable, and required 2-person approvals for fleet-wide changes.

### Threat 2: Credential Exposure at Rest
- **Risk**: Database leak exposes root credentials of managed firewalls.
- **Mitigation**: Stored device passwords and API secrets are encrypted at rest using AES-256-GCM authenticated encryption tied to the master application encryption key. Plaintext credentials are never returned over API endpoints.

### Threat 3: Spoofed Telemetry Injection
- **Risk**: An attacker floods pfCentral with fabricated telemetry to mask an ongoing intrusion.
- **Mitigation**: All machine-to-machine calls require the random, high-entropy `DEVICEKEY` minted on the appliance. Requests with invalid or unmapped keys are rejected immediately and logged.

### Threat 4: Unauthorized Privilege Escalation
- **Risk**: An operator account modifies core security policies.
- **Mitigation**: Fine-grained RBAC with Shield groups (`viewer`, `operator`, `admin`, `superadmin`). Only accounts holding `users.manage` and `config.approve` can approve production config jobs or alter administrator accounts.

---

## 2. Cryptographic Standards
- **Symmetric Encryption**: AES-256-GCM via CodeIgniter `Encrypter`.
- **Password Hashing**: PHP `password_hash()` utilizing native `PASSWORD_BCRYPT` with cost 12 or `PASSWORD_ARGON2ID`.
- **Agent Keys**: 32-byte cryptographic random bytes (`openssl rand -hex 32`).
- **Session Tokens**: 256-bit cryptographically secure random session cookies with `HttpOnly`, `SameSite=Lax`, and `Secure` attributes enabled.
