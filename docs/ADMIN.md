# Admin UI guide

The admin interface is session-based. All admin pages live under `/admin/` and require login except `login.php`.

## Login

- **URL:** `/admin/login.php`
- **Default credentials:** username **admin**, password **admin**. Change these immediately after first login.
- After login you are redirected to the dashboard. Use **Logout** to end the session.

## Dashboard (`/admin/index.php`)

- Shows employee count and last payroll date.
- Links to: Employees, Payroll, Tax config, API Keys, Logo, Company, W-2, Users, Change password, Logout.

## Employees

- **URL:** `/admin/employees.php`
- Lists all employees (name, masked SSN, filing status, hire date, monthly gross) with **Edit** and **Delete** actions.
- **Add employee:** Click **Add employee** to open the form; submit to create via the API.
- **Edit:** Click **Edit** on a row to change name, SSN, filing status, hire date, salary, address, and W-4 step 4 options.
- **Delete:** Shown only for employees with no payroll history; employees who have payroll runs cannot be deleted.

## Payroll

- **URL:** `/admin/payroll.php`
- **Run payroll:** Fill in Pay period start, Pay period end, and Pay date, then click Run. The form calls the run-payroll API. At least one API key must exist.
- **Recent payroll:** Table of recent runs (date, employee, gross, net). **Stub** opens the pay stub (HTML) for that run in a new tab; the link uses the first API key for auth.

## Tax config

- **URL:** `/admin/tax-config.php`
- Lists configured tax years.
- **Upload:** Paste JSON in the text area (see [TAX-CONFIG.md](TAX-CONFIG.md) and [PRD.md](PRD.md) §6) and click Upload. Uses the first API key to call the upload API.

## API Keys

- **URL:** `/admin/api-keys.php`
- **Create:** Enter a name and click Create. The key is shown once; copy and store it securely.
- **List:** Shows key name, truncated key, created date, last used. **Delete** removes a key (cannot be undone).

## Logo

- **URL:** `/admin/logo.php`
- Upload a company logo (PNG or JPEG, max 2 MB). Replaces any existing logo. Used on pay stubs and in the admin logo preview.

## Company

- **URL:** `/admin/company-settings.php`
- Set employer name, EIN (9 digits), and address. Required for W-2 generation. EIN is validated (9 digits).

## W-2

- **URL:** `/admin/w2.php`
- Choose a tax year and click **Generate W-2s**. Downloads an HTML file with one W-2-style section per employee who has payroll and a complete address for that year. Open in a browser and use Print → Save as PDF if you need PDFs.
- Requires company (employer) settings and employee addresses to be set for included employees.

## Users

- **URL:** `/admin/users.php`
- **Add user:** Username and password (with confirmation). New admins have full access (no roles).
- **List:** All admin users. **Delete** removes a user; you cannot delete the last remaining admin.

## Change password

- **URL:** `/admin/change-password.php`
- Change the current user’s password. Requires current password and new password (min 8 characters) with confirmation.

## Security

- All state-changing actions (create key, delete key, add/delete user, change password, run payroll, upload tax config, upload logo, save company settings, generate W-2) use POST with a CSRF token. The token is in the session and included in forms via a hidden field.
- Session cookie is HTTP-only and tied to the session name `payroll_admin`. Use HTTPS in production.
- **Admin login:** There is no brute-force protection (no lockout or captcha after failed attempts). For internal use this is often acceptable; if the admin UI is exposed to the internet, consider adding rate limiting or lockout for the login endpoint.
