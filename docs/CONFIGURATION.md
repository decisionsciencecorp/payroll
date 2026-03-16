# Configuration

Payroll keeps **all config and variables in the database**. Path constants (DB, storage, logs) are set in `public/includes/config.php`; everything else is in DB.

## Constants (config.php, paths only)

| Constant | Description | Default |
|----------|-------------|---------|
| `DB_PATH` | Full path to the SQLite database file | `__DIR__ . '/../../db/payroll.db'` |
| `STORAGE_PATH` | Directory for uploaded files (logo, future W-4/I-9) | `public/uploads/` (path from includes: `__DIR__ . '/../uploads'`) |
| `DB_TIMEOUT` | SQLite busy timeout (seconds) | `30` |
| `SESSION_NAME` | PHP session name for admin | `payroll_admin` |
| `PASSWORD_COST` | bcrypt cost for admin passwords | `12` |
| `SITE_NAME` | Application name (e.g. for UI) | `Payroll` |
| `LOG_PATH` | Path to application log file | `__DIR__ . '/../../logs/app.log'` |

## SITE_URL (from database or request)

`SITE_URL` is used for admin server-side API calls (tax config upload, run payroll), pay stub links, and logo URL in stubs.

- **If set in Admin → Company settings → Site URL:** that value is used (lets you override when behind a proxy or using a different public URL).
- **If blank:** the app derives it from the current request (`https` or `http` + the `Host` header). So visiting `https://payroll.example.com` makes SITE_URL `https://payroll.example.com` with no config.
- **CLI / no request (e.g. tests):** falls back to `http://localhost`.

## Development vs production

Config checks for “development” to turn on error display:

- `$_SERVER['HTTP_HOST']` is `localhost` or contains `127.0.0.1`
- Or `$_ENV['APP_ENV'] === 'development'`

In development: `display_errors = 1`.  
In production: `display_errors = 0`, `log_errors = 1`, and errors go to `logs/php-errors.log` (path relative to `includes/`: `__DIR__ . '/../../logs/php-errors.log'`).

**Production:** Set `APP_ENV=production` (or ensure `HTTP_HOST` is not localhost) so error display is off. Create the `logs/` directory and make it writable by the web server; it is used for `php-errors.log` and for the application log `logs/app.log` (see `LOG_PATH` in config).

## Database

The app uses a single SQLite file. To use a different path (e.g. for multiple instances), change `DB_PATH`:

```php
define('DB_PATH', '/var/lib/payroll/production.db');
```

## Writable paths

- **db/** — SQLite database and journal files.
- **public/uploads/** — All user uploads (company logo, and future W-4/I-9 documents). **Must** be at `public/uploads/` to conform to our LEMP host: uploads are preserved across deployments only when under `public/uploads/`. Do not commit uploaded files; serve them via authenticated scripts (e.g. `/api/logo-file.php`) so they are not directly browsable.
- **logs/** — PHP error log and application log (`app.log`). Create the directory and make it writable in production.

## Sensitive data (SSN and database)

- **SSN:** Stored in plaintext in the `employees` table. Restrict filesystem access to the database file (e.g. `chmod 640`, owner/group so only the web server and backup process can read). Consider encrypting backups and any copies of the DB. For stricter compliance, application-level encryption of the SSN column (key from environment, not in repo) can be added.
- **Database file:** Protect `db/payroll.db` from unauthorized read access; it contains SSNs, password hashes, and API keys.
