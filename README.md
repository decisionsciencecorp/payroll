# Payroll

Single-company internal payroll app. Monthly pay, federal withholding, FICA, Additional Medicare, configurable tax brackets, pay stubs, W-2 generation. LEMP-compatible PHP, no `.htaccess`.

## Setup

1. **Web root:** Point Nginx (or Apache with PHP-FPM) at `public/`. No document root above `public/`.
2. **PHP:** 7.4+ with SQLite3.
3. **Writable:** `db/` and `storage/` (and `logs/` if used) must be writable by the web server.
4. **Config:** Set `SITE_URL` in `public/includes/config.php` if needed (for links in pay stubs / admin).

## First run

1. Open `/admin/login.php`. Default login: **admin** / **admin**. Change password (Admin → Change password).
2. Create an API key (Admin → API Keys) for scripts or the API.
3. Set company (employer) name and EIN (Admin → Company) for W-2.
4. Upload tax config for the pay year (Admin → Tax config or `POST /api/upload-tax-brackets.php`). See `docs/PRD.md` §6 for JSON format.
5. Add employees via API (`POST /api/create-employee.php`) or integrate with your tools.
6. Run payroll (Admin → Payroll: set period start/end and pay date, then Run).

## API

All endpoints under `/api/`. Auth: `X-API-Key` header or `api_key` query/body. See **docs/API-QUICK-REFERENCE.md**.

## Admin

- **Dashboard** — Counts, last payroll.
- **Employees** — List (add/edit via API).
- **Payroll** — Run payroll, list runs, link to pay stub.
- **Tax config** — List years, paste JSON upload.
- **API Keys** — Create/delete keys.
- **Logo** — Upload company logo for stubs.
- **Company** — Employer name, EIN, address (W-2).
- **W-2** — Generate W-2 HTML for a tax year (download; print to PDF).
- **Users** — Add/delete admin users. Change password.

## Docs

- **docs/PRD.md** — Product requirements, data model, tax config format.
- **docs/API-QUICK-REFERENCE.md** — Endpoint list and examples.

## Pay stubs and W-2

- Pay stub: `GET /api/pdf-stub.php?id=<payroll_id>` returns HTML; use browser Print → Save as PDF.
- W-2: `GET /api/generate-w2.php?year=YYYY` or Admin → W-2. Returns HTML download (one page per employee).
