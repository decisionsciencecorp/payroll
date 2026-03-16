# Testing

The project uses **PHPUnit 9** for unit and integration tests. The suite includes unit tests (pure functions, CSRF), integration tests (API keys, rate limit, auth), and HTTP-based API tests (using PHP’s built-in web server).

## Requirements

- PHP 7.4+ with extensions: `sqlite3`, `json`, `mbstring`
- Composer (to install PHPUnit)

For **coverage**: install `pcov` or Xdebug, then run `./vendor/bin/phpunit --coverage-text` (or `--coverage-html build/coverage/html`). Target: **100%** for `public/includes` (except config), `public/api`, and `public/admin`.

## Install test dependencies

From the project root:

```bash
composer install
```

This installs `phpunit/phpunit` as a dev dependency.

## Run tests

From the project root:

```bash
./vendor/bin/phpunit
```

If PHP is not on your PATH, use the same PHP binary to run the test runner (so the built-in server uses that binary too):

```bash
/path/to/php scripts/run-tests.php
```

Or set `PHP_BINARY` and run phpunit; the integration tests use `PHP_BINARY` when starting the server.

This will:

1. Use a temporary SQLite database (and temp storage) per run (`PAYROLL_TEST=1`, `DB_PATH` in temp).
2. Run **Unit** tests: `maskSsn`, `formatDate`, `calculatePayrollForEmployee`, `getApiKey`, CSRF.
3. Run **Integration** tests: API key CRUD, rate limit, auth (login, add/delete admin, change password, cannot delete last admin).
4. Start PHP’s built-in server on `127.0.0.1:8765` for **API integration** tests, then run requests against it (tax brackets, employees, payroll run, pay stub, error paths).

All tests should pass (green). No network or existing database is required beyond the temporary one.

**ApiIntegrationTest (HTTP tests):** These start PHP’s built-in server on 127.0.0.1:9876. The server must use the same test DB as the test process (so API key validation succeeds). The server inherits the process environment; if you see 401 on all API requests, the server may not have `PAYROLL_TEST`, `DB_PATH`, and `STORAGE_PATH` (e.g. some CI or container setups). Run `./vendor/bin/phpunit` from the same process that loaded the bootstrap, or ensure those env vars are set before starting PHPUnit.

## Run with coverage

If you have `pcov` or Xdebug installed:

```bash
./vendor/bin/phpunit --coverage-text
```

For HTML report (output in `build/coverage/html/`):

```bash
./vendor/bin/phpunit --coverage-html build/coverage/html
```

Coverage is reported for `public/includes/*.php` (except `config.php`) and `public/api/*.php`. The suite is written to reach **90%+** coverage.

## Layout

```
tests/
├── bootstrap.php       # Sets PAYROLL_TEST, temp DB, creates test API key
├── Unit/               # Pure functions, getApiKey (superglobals), CSRF
│   ├── MaskSsnTest.php
│   ├── FormatDateTest.php
│   ├── CalculatePayrollForEmployeeTest.php
│   ├── GetApiKeyTest.php
│   └── CsrfTest.php
└── Integration/        # DB + HTTP
    ├── ApiKeyAndRateLimitTest.php
    ├── AuthTest.php
    └── ApiIntegrationTest.php  # Starts server, hits /api/* endpoints
```

## Coverage (target: 100%)

- **Unit:** All pure functions in `functions.php` (getApiKey, validateDateYmd, maskSsn, formatDate, app_log, calculatePayrollForEmployee), `auth.php` (login, logout, changePassword, addAdminUser, deleteAdminUser, etc.), `csrf.php` (generateCsrfToken, verifyCsrfToken, csrfField), and API key/rate limit (createApiKey, validateApiKey, getApiKeyName, getAllApiKeys, deleteApiKey, checkRateLimit branches including limit exceeded and window reset).
- **Integration:** API key CRUD, rate limit, auth (login, change password, add/delete admin, cannot delete last admin). When the built-in server starts (port 8765): all API endpoints (tax, employees, payroll, list-payroll, get-payroll, pdf-stub, upload-logo, logo-file, generate-w2, delete-tax-brackets), validation error paths (400/404), and admin page loads (login then GET each of index, employees, payroll, tax-config, api-keys, logo, company-settings, w2, users, change-password) for E2E-style coverage.
- **E2E:** Implemented as integration tests that require the server: `testAllAdminPagesLoadAfterLogin` (login then assert 200 on every admin page), `testAdminUsersPageLoadsWithAuth`, and full API flows (tax upload/list/get/delete, employee CRUD, run payroll, get stub). For full browser E2E (Playwright/Selenium), add a separate suite and run with a real browser.

## Environment

When running PHPUnit, `tests/bootstrap.php` sets:

- `PAYROLL_TEST=1`
- `DB_PATH` and `STORAGE_PATH` to temp directories

`public/includes/config.php` uses these when `PAYROLL_TEST` is set, so the app (and the built-in server) use the test database. No production data is used.
