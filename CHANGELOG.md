# Changelog

This project increments versions by operation size, following the ONC convention:

- Large operation: `+0.1`
- Medium operation: `+0.01`
- Small operation: `+0.001`

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.12] - 2026-06-16

### Added

- Added the client-area CVM control panel design prompt at `docs/design/client-area-prompt.md`.
- Documented the external design-tool workflow in `README.md`.

### Changed

- Refined the client-area prompt after reviewing the first `docs/design/tx-cvm.zip` output, emphasizing a production-like WHMCS embedded module over a design-showcase page.
- Reworked the client-area prompt for pure design AI output after reviewing `docs/design/tx-cvm 2`, limiting the next iteration to one customer-facing desktop main panel.

## [0.11] - 2026-06-16

### Added

- Added `ROADMAP.md` with staged implementation plan, PR/version rules, hard boundaries, and acceptance criteria.
- Documented that customer/admin page design is produced as external design-tool prompts before implementation.
- Linked the roadmap from `README.md`.

## [0.1] - 2026-06-16

### Added

- Initial public repository baseline.
- ONC-style `README.md`, `VERSION`, `CHANGELOG.md`, `.gitignore`, and MIT license.
- Defined first implementation target: paired WHMCS server module and addon module for Tencent Cloud CVM provisioning and management.
