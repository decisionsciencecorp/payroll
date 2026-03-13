# Changelog

All notable changes to this project are documented in this file.

## [0.3.14] — 2026-03-12

### Validation and data

- **create-employee validation (fixes #12).** SSN must be exactly 9 digits; hire_date valid Y-m-d; monthly_gross_salary >= 0.
- **update-employee validation (fixes #13).** Same rules for updated SSN, hire_date, and monthly_gross_salary when provided.
- **API key name and company fields (fixes #14).** createApiKey: trim and max length 255 for key_name. Company settings: max lengths for employer name (255), address (255), city (100), state (50), zip (20).
- **Bind LIMIT/OFFSET (fixes #15).** list-employees and list-payroll use bound parameters for LIMIT and OFFSET.
- **getAllApiKeys mask; list-payroll integer (fixes #16).** getAllApiKeys() returns masked api_key (first 8 chars + …) for listing. list-payroll already binds employee_id as INTEGER; count query uses correct param types.

## [0.3.13] — 2026-03-12

### Validation

- **run-payroll and list-payroll date validation (fixes #11).** Added `validateDateYmd()` in functions.php. run-payroll: validate pay_period_start, pay_period_end, pay_date as Y-m-d; reject if start > end. list-payroll: validate pay_date_from and pay_date_to when provided.

## [0.3.12] — 2026-03-12

### Documentation

- **Rate limit and brute-force (fixes #10).** API.md: note that rate limit is per key+IP so one key from many IPs can multiply load. ADMIN.md: note that admin login has no brute-force protection and recommend rate limiting or lockout if exposed to the internet.

## [0.3.11] — 2026-03-12

### Security

- **Session cookie flags (fixes #9).** Before `session_start()`, set `session_set_cookie_params()` with `httponly => true`, `secure => true` when SITE_URL is https, and `samesite => 'Lax'`.

## [0.3.10] — 2026-03-12

### Security / Documentation

- **API key in query string (fixes #8).** API.md now recommends using the X-API-Key header and warns that query-string keys may appear in logs, referrers, and browser history.

## [0.3.9] — 2026-03-12

### Security

- **Security headers (fixes #7).** New `includes/security-headers.php` sets X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, and a minimal CSP; HSTS when SITE_URL is https. Included from config so all responses get them.

## [0.3.8] — 2026-03-12

### Security

- **Default admin password and first-login flow (fixes #6).** Document that the default admin/admin password must be changed immediately (INSTALL, README). Added `first_login_done` to `admin_users`; on first login (flag=0) the user is redirected to Change password and must change before using the app. Successful password change sets the flag. CLI (e.g. tests) skips redirect so `login()` still returns. Schema migration: new column in CREATE TABLE and ALTER for existing installs.

## [0.3.7] — 2026-03-12

### Security

- **Database failure no longer leaks to client (fixes #5).** `getDbConnection()` now logs the exception server-side with `error_log()` and returns HTTP 500 with a generic "Service unavailable." message to the client, without exposing path or driver details.

## [0.3.6] — 2026-03-12

### Security

- **Logout via POST with CSRF (fixes #4).** Logout is no longer triggered by GET (`?logout=1`). New `admin/logout.php` accepts only POST and validates CSRF; dashboard Logout is now a form that POSTs to it. Prevents CSRF-based logout (e.g. image tag to logout URL).

## [0.3.5] — 2026-03-12

### Security

- **Login CSRF (fixes #2).** Login form now includes a CSRF token and validates it on POST. Prevents login CSRF attacks. Integration test updated to fetch token from login page and send it with the POST.
- **Session fixation on login (fixes #3).** `session_regenerate_id(true)` is called in `login()` immediately after validating credentials and before setting session vars, invalidating any pre-set session ID.

## [0.3.4] — 2026-03-12

### Security

- **logo-file.php authentication (fixes #1).** Logo endpoint now requires either a valid API key or an admin session. Unauthenticated requests receive 401. Pay stub HTML passes the API key in the logo URL so the embedded image request is authenticated. Integration test `testLogoFileRequiresAuth` added.

## [0.3.3] — 2026-03-12

### Added

- **Visual walkthrough.** [docs/VISUAL-WALKTHROUGH.md](docs/VISUAL-WALKTHROUGH.md) — screenshot-based walkthrough of the full admin UI (login, dashboard, employees, payroll, tax config, API keys, logo, company, W-2, users, change password). Screenshots captured with Playwright via [scripts/capture_admin_walkthrough.py](scripts/capture_admin_walkthrough.py); instructions for re-capturing included.
- **SMCP plugin docs.** [docs/SMCP-PLUGIN.md](docs/SMCP-PLUGIN.md) — doc index entry and quick link for the Payroll SMCP plugin. [smcp_plugin/README.md](smcp_plugin/README.md) expanded with full parameter reference, testing, and coverage details.

### Changed

- **.gitignore:** Added `.venv-playwright/` so the Playwright capture venv is not committed.

## [0.3.2] — 2026-03-11

### Fixed

- **Test paths:** Integration tests use `PHP_BINARY` when starting the built-in server so the suite runs when PHP is not on PATH. Project root for the server is now `dirname(__DIR__, 2)` (repo root). Added `scripts/run-tests.php` to run PHPUnit with the same PHP binary; documented in TESTING.md.

## [0.3.1] — 2026-03-11

### Fixed

- **Admin Users page fatal (fixes #19).** `public/admin/users.php` called `formatDate()` without requiring `functions.php`, causing a fatal error when opening Admin → Users. Added `require_once` for `functions.php`. Regression test: `testAdminUsersPageLoadsWithAuth` in `ApiIntegrationTest` (login then GET `/admin/users.php`, assert 200 and no undefined function in body).

## [0.3.0] — 2026-03-11

### Added

- **Python SDK** in `SDK/python/`. Package `payroll_sdk` with `PayrollClient` and `PayrollAPIError`. Covers all API endpoints: tax brackets (upload, list, get, delete), employees (CRUD), payroll (run, list, get), logo upload, pay stub HTML, W-2 HTML. Install with `pip install -e SDK/python`. See [SDK/python/README.md](SDK/python/README.md).

## [0.2.0] — 2026-03-11

### Added

- **Licenses.** Code licensed under GNU AGPL v3.0 (see [LICENSE](LICENSE)). Documentation and other non-code material licensed under CC BY-SA 4.0 (see [LICENSE-DOCS](LICENSE-DOCS)).
- **Full documentation.** [docs/README.md](docs/README.md) (index); [docs/INSTALL.md](docs/INSTALL.md) (installation, server setup, first run); [docs/CONFIGURATION.md](docs/CONFIGURATION.md) (config options, SITE_URL, paths); [docs/API.md](docs/API.md) (full API reference with request/response and errors); [docs/ADMIN.md](docs/ADMIN.md) (admin UI guide); [docs/DATA-MODEL.md](docs/DATA-MODEL.md) (database schema and tables); [docs/TAX-CONFIG.md](docs/TAX-CONFIG.md) (tax bracket JSON format and validation).
- **README.** Full README with features, requirements, quick start, documentation index, repo layout, and license summary.

### Changed

- README expanded to point to all docs and to state AGPL v3 for code and CC BY-SA 4.0 for non-code.

## [0.1.0] — 2026-03-11

### Added

- **Project layout.** LEMP-compatible PHP app: `public/` docroot (api/, admin/, includes/, css/), `db/`, `storage/`, `logs/` outside web root. No `.htaccess` dependency.
- **Database (SQLite).** Schema: api_keys, api_rate_limits, employees, payroll_history, tax_config, company_settings, admin_users. Foreign keys and indexes for payroll_history. Seed default admin (admin/admin) when empty.
- **Config and shared code.** `config.php`: DB path, session, STORAGE_PATH, `getDbConnection()`, `initializeDatabase()`. `functions.php`: API key (get/validate/name), rate limiting (per key+IP), jsonSuccess/jsonError, maskSsn, API key CRUD, formatDate, `calculatePayrollForEmployee()` (federal brackets, SS, Medicare, additional Medicare, net, YTD). `auth.php`: session login, requireAuth, changePassword, add/list/delete admin users. `csrf.php`: token generation and validation for admin forms.
- **Tax bracket API.** POST upload-tax-brackets (JSON body), GET list-tax-brackets, GET get-tax-brackets?year=, DELETE delete-tax-brackets?year=. Validation for year, brackets (single/married/head_of_household), additional_medicare_thresholds.
- **Employee API.** POST create-employee, GET list-employees (SSN masked), GET get-employee?id=, POST update-employee, DELETE delete-employee?id= (409 if payroll history exists). Filing status: Single, Married filing jointly, Married filing separately, Head of Household. Optional W-2 address fields.
- **Payroll run.** POST run-payroll (pay_period_start/end, pay_date; optional employee_ids). Loads tax config for pay year, computes per-employee (federal, SS, Medicare, additional Medicare), YTD from last run, transaction. GET list-payroll (filters), GET get-payroll?id=.
- **Logo and pay stub.** POST upload-logo (multipart, PNG/JPEG, 2MB). GET logo-file.php serves stored logo. GET pdf-stub?id= returns HTML pay stub (print to PDF in browser).
- **W-2 generation.** GET generate-w2.php?year= returns HTML download (one section per employee with payroll and address). Employer/employee address validation.
- **Admin UI.** Login (session), dashboard with links, API Keys (create/delete), Admin users (add/delete, change password), Employees list, Payroll (run form + list + stub link), Tax config (paste JSON upload), Logo upload, Company settings (employer name, EIN, address), W-2 (year → download). All POST actions protected with CSRF. Lightweight user management (no roles).
- **Docs.** README (setup, first run, API, admin). docs/PRD.md (authoritative product requirements, data model, tax config format). docs/API-QUICK-REFERENCE.md (all endpoints, auth, errors).
- **composer.json.** Optional TCPDF dependency for future server-side PDF (stubs/W-2 currently HTML).

### Security

- API key required for all API endpoints (X-API-Key or api_key). Rate limiting per action:apiKey:ip. SSN masked in list-employees. EIN validation (9 digits) on company settings.
