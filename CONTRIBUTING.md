# Contributing to pfCentral

Thank you for your interest in contributing to **pfCentral**! We welcome community contributions including bug fixes, security improvements, documentation updates, and feature enhancements.

## Code of Conduct
By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## How to Contribute

### 1. Reporting Bugs
- Search existing issues on GitHub before opening a new report.
- Use the **Bug Report** issue template.
- Clearly describe the steps to reproduce the issue, your operating environment (PHP version, database type, pfSense release), expected behavior, and actual behavior.
- **NEVER** include real firewall credentials, IP addresses, private keys, or actual configuration dumps in issue reports. Use sanitized placeholders (e.g. `198.51.100.1`, `example.com`).

### 2. Suggesting Enhancements
- Open an issue using the **Feature Request** template.
- Explain the practical use case, why this feature benefits self-hosters and network operators, and any potential security implications.

### 3. Submitting Pull Requests
1. Fork the repository and create your feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
2. Follow PSR-12 coding standards and existing architectural conventions.
3. Preserve backwards compatibility where feasible.
4. Ensure all automated tests pass:
   ```bash
   composer test
   ```
5. If introducing database changes, create clean CodeIgniter 4 migrations with both `up()` and `down()` methods.
6. Submit your pull request with a descriptive title and summary referencing any related issues.

## Licensing of Contributions
All contributions to pfCentral are made under the **Apache License 2.0**. By submitting a pull request, you agree that your work will be licensed under this agreement.
