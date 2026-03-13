# Payroll

Single-company internal payroll application. Monthly pay runs, federal income tax withholding, FICA (Social Security and Medicare), Additional Medicare Tax, configurable tax brackets via JSON, pay stubs, and W-2 generation. LEMP-compatible PHP, no `.htaccess`. Built to the [Product Requirements Document](docs/PRD.md).

**License:** Source code is [GNU AGPL v3](LICENSE). Documentation and other non-code material are [CC BY-SA 4.0](LICENSE-DOCS).

---

## Features

- **Tax config:** Upload JSON tax bracket config per year (federal brackets, SS/Medicare rates, Additional Medicare thresholds). See [docs/TAX-CONFIG.md](docs/TAX-CONFIG.md).
- **Employees:** CRUD via API; filing status (Single, Married filing jointly/separately, Head of Household), W-4 step 4(a)(b)(c), I-9 date, address for W-2.
- **Payroll runs:** Compute and store payroll for a period; federal withholding, SS, Medicare, Additional Medicare; YTD tracking for W-2.
- **Pay stubs:** HTML pay stub per run (print to PDF in browser); optional company logo.
- **W-2:** Generate W-2-style HTML for a tax year (download; print to PDF). Employer and employee addresses required.
- **Admin UI:** Session-based login, dashboard, API key management, admin users, employees list, payroll run form, tax config upload, logo upload, company (employer) settings, W-2 generation. Lightweight user management (no roles).
- **REST API:** All operations available via API; script-per-endpoint style, `X-API-Key` auth, JSON envelope. See [docs/API.md](docs/API.md) and [docs/API-QUICK-REFERENCE.md](docs/API-QUICK-REFERENCE.md).
- **SMCP plugin:** MCP tools for agents (Letta, Claude Desktop, etc.): employees, payroll runs, tax config. See [smcp_plugin/README.md](smcp_plugin/README.md) and [docs/SMCP-PLUGIN.md](docs/SMCP-PLUGIN.md).

---

## Requirements

- **PHP** 7.4+ with extensions: `sqlite3`, `json`, `mbstring`, `curl`, `fileinfo`
- **Web server:** Nginx or Apache; document root = `public/`
- **Writable:** `db/`, `storage/`, and optionally `logs/`

---

## Quick start

1. **Install:** Point the web server document root at `public/`. Ensure `db/` and `storage/` exist and are writable. See [docs/INSTALL.md](docs/INSTALL.md).
2. **Configure:** Set `SITE_URL` in `public/includes/config.php` if needed. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md).
3. **First run:** Open `/admin/login.php`. Log in with **admin** / **admin**. You will be redirected to change the password on first login (default is insecure); then create an API key, set company, upload tax config, add employees, and run payroll. Create an API key (Admin → API Keys). Set company name and EIN (Admin → Company). Upload tax config (Admin → Tax config or API). Add employees via API. Run payroll (Admin → Payroll).

**Tests:** `composer install` then `./vendor/bin/phpunit`. See [docs/TESTING.md](docs/TESTING.md). Target 90% coverage, all green.

---

## Documentation

| Document | Description |
|----------|-------------|
| [docs/README.md](docs/README.md) | Documentation index |
| [docs/INSTALL.md](docs/INSTALL.md) | Installation and server setup |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Config options and paths |
| [docs/API.md](docs/API.md) | Full API reference |
| [docs/API-QUICK-REFERENCE.md](docs/API-QUICK-REFERENCE.md) | Endpoint summary |
| [docs/ADMIN.md](docs/ADMIN.md) | Admin UI guide |
| [docs/DATA-MODEL.md](docs/DATA-MODEL.md) | Database schema |
| [docs/TAX-CONFIG.md](docs/TAX-CONFIG.md) | Tax bracket JSON format |
| [docs/TESTING.md](docs/TESTING.md) | PHPUnit test suite (unit, integration, API) |
| [docs/PRD.md](docs/PRD.md) | Product requirements (authoritative) |
| [docs/SMCP-PLUGIN.md](docs/SMCP-PLUGIN.md) | SMCP plugin for MCP agents |
| [docs/VISUAL-WALKTHROUGH.md](docs/VISUAL-WALKTHROUGH.md) | Visual walkthrough of the admin UI |

---

## SDK

A **Python SDK** is provided in `SDK/python/`. Install with `pip install -e SDK/python` and use the `PayrollClient` for all API operations. See [SDK/python/README.md](SDK/python/README.md).

## SMCP plugin

An **SMCP plugin** in `smcp_plugin/` exposes the Payroll API as MCP tools for agents (Letta, Claude Desktop, Sanctum/Animus). It uses the Python SDK and requires `PAYROLL_BASE_URL` and `PAYROLL_API_KEY`. Full setup, command reference, and test instructions: [smcp_plugin/README.md](smcp_plugin/README.md). Docs index: [docs/SMCP-PLUGIN.md](docs/SMCP-PLUGIN.md).

## Repository layout

```
payroll/
├── public/           ← Web root
│   ├── api/          ← API endpoints
│   ├── admin/        ← Admin UI
│   ├── includes/     ← config, functions, auth, csrf
│   └── css/
├── SDK/
│   └── python/       ← Python SDK (payroll_sdk)
├── smcp_plugin/      ← SMCP plugin (cli.py, tests); 100% coverage
├── db/               ← SQLite DB (created at runtime)
├── storage/          ← Uploaded logo
├── docs/             ← Documentation
├── .coveragerc       ← Coverage config for smcp_plugin (omit tests)
├── LICENSE           ← GNU AGPL v3 (code)
├── LICENSE-DOCS      ← CC BY-SA 4.0 (docs and non-code)
└── CHANGELOG.md
```

---

## License

- **Code (PHP, CSS, and other executable/source code):** [GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0). You must disclose source for modified versions run over the network; see the license for full terms.
- **Documentation and other non-code material (docs/, README, CHANGELOG, etc.):** [Creative Commons Attribution-ShareAlike 4.0 International](LICENSE-DOCS) (CC BY-SA 4.0).

---

## Version

See [CHANGELOG.md](CHANGELOG.md) for release history. Current release: **0.3.17**.
