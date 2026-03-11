# Configuration

Payroll is configured via constants and optional environment variables in `public/includes/config.php`.

## Constants (config.php)

| Constant | Description | Default |
|----------|-------------|---------|
| `DB_PATH` | Full path to the SQLite database file | `__DIR__ . '/../../db/payroll.db'` |
| `STORAGE_PATH` | Directory for uploaded logo | `__DIR__ . '/../../storage'` |
| `DB_TIMEOUT` | SQLite busy timeout (seconds) | `30` |
| `SESSION_NAME` | PHP session name for admin | `payroll_admin` |
| `PASSWORD_COST` | bcrypt cost for admin passwords | `12` |
| `SITE_NAME` | Application name (e.g. for UI) | `Payroll` |
| `SITE_URL` | Base URL for links (pay stubs, admin) | `http://localhost` |

## SITE_URL

Set `SITE_URL` to the public base URL of your installation (e.g. `https://payroll.example.com`). It is used for:

- Links in the admin UI (e.g. to the API or pay stub)
- Pay stub HTML that references the logo (`/api/logo-file.php`)

If you serve the app in a subdirectory, include it: `https://example.com/payroll`.

## Overriding SITE_URL

You can define `SITE_URL` before loading config (e.g. in a bootstrap file or via `auto_prepend_file`):

```php
define('SITE_URL', 'https://payroll.mycompany.com');
require_once __DIR__ . '/includes/config.php';
```

Or set the environment variable `APP_ENV` to influence development vs production behavior (see below).

## Development vs production

Config checks for “development” to turn on error display:

- `$_SERVER['HTTP_HOST']` is `localhost` or contains `127.0.0.1`
- Or `$_ENV['APP_ENV'] === 'development'`

In development: `display_errors = 1`.  
In production: `display_errors = 0`, `log_errors = 1`, and errors go to `logs/php-errors.log` (path relative to `includes/`: `__DIR__ . '/../../logs/php-errors.log'`).

Ensure `logs/` exists and is writable if you rely on file logging.

## Database

The app uses a single SQLite file. To use a different path (e.g. for multiple instances), change `DB_PATH`:

```php
define('DB_PATH', '/var/lib/payroll/production.db');
```

## Writable paths

- **db/** — SQLite database and journal files.
- **storage/** — Uploaded company logo (single file, overwritten on new upload).
- **logs/** — Optional; used for PHP error log when not in development mode.
