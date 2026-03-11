# Changelog

All notable changes to this project are documented in this file.

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
