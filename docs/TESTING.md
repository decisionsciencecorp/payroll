# Testing

The project uses **PHPUnit 9** for unit and integration tests. The suite includes unit tests (pure functions, CSRF), integration tests (API keys, rate limit, auth), and HTTP-based API tests (using PHP’s built-in web server).

## Requirements

- PHP 7.4+ with extensions: `sqlite3`, `json`, `mbstring`
- Composer (to install PHPUnit)

For **coverage** (optional): `pcov` or Xdebug so PHPUnit can report line coverage. Target: 90%.

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

## Coverage gaps

- **Logo upload:** `testUploadLogoSuccess` (in ApiIntegrationTest) POSTs a file to `/api/upload-logo.php` and verifies 200 and that `GET /api/logo-file.php` returns 200. It runs only when the built-in server starts (port 8765). There is no automated test for the **admin** logo form (session + multipart); manual test that flow.
- **Admin UI:** No automated tests for admin pages (login, dashboard, employees form, logo form, W-2, etc.). Rely on manual or browser tests.
- **W-2 / PDF:** No test for W-2 generation or PDF pay stub content; API response codes are exercised.

## Environment

When running PHPUnit, `tests/bootstrap.php` sets:

- `PAYROLL_TEST=1`
- `DB_PATH` and `STORAGE_PATH` to temp directories

`public/includes/config.php` uses these when `PAYROLL_TEST` is set, so the app (and the built-in server) use the test database. No production data is used.
