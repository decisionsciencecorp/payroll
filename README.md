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

---

## Requirements

- **PHP** 7.4+ with extensions: `sqlite3`, `json`, `mbstring`, `curl`, `fileinfo`
- **Web server:** Nginx or Apache; document root = `public/`
- **Writable:** `db/`, `storage/`, and optionally `logs/`

---

## Quick start

1. **Install:** Point the web server document root at `public/`. Ensure `db/` and `storage/` exist and are writable. See [docs/INSTALL.md](docs/INSTALL.md).
2. **Configure:** Set `SITE_URL` in `public/includes/config.php` if needed. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md).
3. **First run:** Open `/admin/login.php`. Log in with **admin** / **admin**, then change the password. Create an API key (Admin → API Keys). Set company name and EIN (Admin → Company). Upload tax config (Admin → Tax config or API). Add employees via API. Run payroll (Admin → Payroll).

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
| [docs/PRD.md](docs/PRD.md) | Product requirements (authoritative) |

---

## Repository layout

```
payroll/
├── public/           ← Web root
│   ├── api/          ← API endpoints
│   ├── admin/        ← Admin UI
│   ├── includes/     ← config, functions, auth, csrf
│   └── css/
├── db/               ← SQLite DB (created at runtime)
├── storage/          ← Uploaded logo
├── docs/             ← Documentation
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

See [CHANGELOG.md](CHANGELOG.md) for release history. Current release: **0.2.0**.
