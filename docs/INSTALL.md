# Installation and setup

This guide covers installing Payroll, configuring the web server, and completing first-run setup.

## Requirements

- **PHP** 7.4 or later with extensions: `sqlite3`, `json`, `mbstring`, `curl` (for admin calls to API), `fileinfo` (for logo upload)
- **Web server:** Nginx (recommended) or Apache with PHP-FPM. Document root must point to the `public/` directory.
- **Writable directories:** The web server user must be able to create and write files in `db/`, `storage/`, and optionally `logs/`.

## Directory layout

After cloning or unpacking:

```
payroll/
├── public/          ← Web server document root
│   ├── api/         ← API endpoints (*.php)
│   ├── admin/       ← Admin UI (*.php)
│   ├── includes/    ← config.php, functions.php, auth.php, csrf.php
│   └── css/
├── db/              ← SQLite database (created on first run)
├── storage/         ← Uploaded logo (created on first upload)
├── logs/            ← Optional PHP error log
└── docs/
```

**Important:** Do not expose `db/`, `storage/`, or the project root above `public/` to the web. Only `public/` should be the document root.

## Nginx

Example server block:

```nginx
server {
    listen 80;
    server_name payroll.example.com;
    root /var/www/payroll/public;

    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

No `.htaccess` is used; all routing is via direct PHP scripts.

## Apache

Set `DocumentRoot` to `public/`. Enable `mod_rewrite` only if you add custom rewrite rules later; the app does not require it for basic operation.

## Permissions

Create directories and set ownership so the web server user can write:

```bash
cd /path/to/payroll
mkdir -p db storage logs
chown -R www-data:www-data db storage logs   # replace www-data with your server user
chmod 755 db storage logs
```

## First run

1. Open `https://your-domain/admin/login.php` in a browser.
2. Log in with default credentials: **admin** / **admin**.
3. **Change the password immediately:** Admin → Change password.
4. Create an API key: Admin → API Keys → Create (e.g. name "Scripts"). Copy and store the key; it is shown only once.
5. Set company (employer) info: Admin → Company. Enter employer name, EIN (9 digits), and address. Required for W-2 generation.
6. Upload tax config for the current (or upcoming) tax year: Admin → Tax config, or `POST /api/upload-tax-brackets.php` with JSON (see [TAX-CONFIG.md](TAX-CONFIG.md)).
7. Add employees via the API (see [API.md](API.md) — `POST /api/create-employee.php`).
8. Run payroll: Admin → Payroll → set pay period start/end and pay date → Run.

## Optional: Composer (TCPDF)

For server-generated PDFs (instead of HTML + “Print to PDF”), install Composer and the TCPDF dependency:

```bash
cd /path/to/payroll
composer install
```

Then you can integrate TCPDF in `public/api/pdf-stub.php` and W-2 generation. Out of the box, pay stubs and W-2s are HTML suitable for browser Print → Save as PDF.

## Troubleshooting

- **Blank page or 500:** Enable PHP error display temporarily in `public/includes/config.php` (development mode) or check `logs/` and the web server error log.
- **Database error:** Ensure `db/` exists and is writable. The app creates `db/payroll.db` on first request.
- **Logo upload fails:** Ensure `storage/` exists and is writable.
- **Admin “Run payroll” or “Upload tax config” fails:** Create at least one API key in Admin → API Keys; the admin UI uses the first available key to call the API.
