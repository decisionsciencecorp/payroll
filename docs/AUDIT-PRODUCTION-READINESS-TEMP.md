# Payroll — Production-Readiness Audit (TEMP)

**Auditor persona:** Neckbeardiest auditor. SQLite retained; no pre-commit/CI-CD recommendations. Everything else is fair game.

**Scope:** Code organization, security, hardening, and anything else needed to make this production-ready.

---

## 1. Security

### 1.1 Critical / High

- **logo-file.php has no authentication.** Any unauthenticated request can fetch the company logo if the URL is known. **Fix:** Require API key or session (e.g. same as pdf-stub), or serve logo only from admin-authenticated context (e.g. redirect to a script that checks session and then reads the file).

- **Login form has no CSRF protection.** A malicious site can POST to `/admin/login.php` and log the victim in as the attacker’s account (login CSRF). **Fix:** Add CSRF token to the login form and validate it on POST (same pattern as other admin forms).

- **Session fixation.** On successful login there is no `session_regenerate_id(true)`. An attacker who can set the session id (e.g. via link) can reuse it after the victim logs in. **Fix:** Call `session_regenerate_id(true)` immediately after validating credentials and before setting `$_SESSION['user_id']`.

- **Logout via GET.** `index.php?logout=1` performs a state-changing action (logout) on GET. Trivially CSRF-able (e.g. image src). **Fix:** Use POST for logout (e.g. form with CSRF token) or at least require POST and CSRF for logout.

- **Database failure leaks to client.** `getDbConnection()` on failure does `die('Database connection failed: ' . $e->getMessage())`. In production this can expose path or driver details. **Fix:** Log the exception (and optionally a generic ID), send a generic 500 response (e.g. `jsonError('Service unavailable', 500)` for API, or a generic error page for admin), and do not echo the exception message to the client.

### 1.2 Medium

- **No security headers.** There are no `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, or `Strict-Transport-Security` (when TLS is used). **Fix:** Add a small security-headers include or central response hook (e.g. in config or a front controller) that sets at least: `X-Frame-Options: DENY` (or SAMEORIGIN), `X-Content-Type-Options: nosniff`, and a minimal CSP. Add HSTS when the app is served over HTTPS.

- **API key in query string.** `getApiKey()` accepts `api_key` in the query string. Keys can end up in server logs, referrers, and browser history. **Fix:** Prefer header-only for API key (document and optionally deprecate query/body for key); at least warn in docs and consider logging when key is passed via query.

- **Default admin password.** First-run seed uses `admin` / `admin`. If the app is ever exposed before first login, that’s a trivial compromise. **Fix:** Document prominently that the first action must be changing the password; optionally require password change on first login (e.g. redirect to change-password until a “first_login_done” flag is set).

- **Session cookie flags.** Session cookie is not explicitly configured for `HttpOnly`, `Secure` (when on HTTPS), or `SameSite`. **Fix:** Before `session_start()`, set `session_set_cookie_params()` with `httponly => true`, `secure => true` when SITE_URL is https, and `samesite => 'Lax'` (or 'Strict').

### 1.3 Lower

- **Rate-limit key includes API key.** Rate key is `action:apiKey:ip`. A compromised key could be used from many IPs; rate limit is per key+IP, so an attacker with one key can multiply load by using many IPs. Acceptable for internal tool; document or optionally add a per-key global limit.

- **No brute-force protection on admin login.** No lockout or captcha after failed attempts. For internal use this may be acceptable; if exposed to the internet, consider rate limiting or lockout for the login endpoint.

---

## 2. Input validation and data integrity

### 2.1 API payloads

- **create-employee:** No validation that SSN has exactly 9 digits after stripping non-digits. No validation that `hire_date` is a valid date (e.g. `DateTime::createFromFormat('Y-m-d', $date)`). No check that `monthly_gross_salary` is non-negative (or > 0). **Fix:** Validate SSN length (9), validate hire_date format and range, and enforce salary >= 0 (or > 0 if business rule).

- **update-employee:** Same as above for any provided SSN, hire_date, or monthly_gross_salary. **Fix:** Apply the same validation to updated fields.

- **run-payroll:** `pay_period_start`, `pay_period_end`, `pay_date` are not validated as valid dates; invalid strings could lead to odd behavior in `strtotime`/`date('Y', ...)`. **Fix:** Parse and validate as Y-m-d; reject invalid or nonsensical ranges (e.g. end before start).

- **list-payroll:** `pay_date_from` and `pay_date_to` are bound as text; no format validation. **Fix:** Validate as date strings (e.g. Y-m-d) or reject.

### 2.2 Admin / storage

- **API key name (key_name):** No max length or sanitization. Long or special strings could affect UI or storage. **Fix:** Trim and enforce a reasonable max length (e.g. 255) and optionally restrict character set.

- **Company settings / employer fields:** EIN is validated (9 digits). Address/city/state/zip have no length limits in code (DB may have implicit limits). **Fix:** Enforce max lengths consistent with schema or add schema constraints; validate ZIP format if desired.

- **Logo upload:** MIME type and extension are checked; size is capped at 2MB. **Fix:** Consider verifying image dimensions (e.g. reject huge dimensions) to avoid memory issues when serving; optional second-pass re-encode to strip metadata.

---

## 3. SQL and data access

- **list-employees.php:** `LIMIT $limit OFFSET $offset` are concatenated into the query. Values are integers from the code, so risk is low, but the pattern is fragile. **Fix:** Use bound parameters for LIMIT/OFFSET (SQLite3 supports this) so the pattern is consistent and future-proof.

- **list-payroll.php:** Same: `LIMIT $limit OFFSET $offset` concatenated. **Fix:** Same as above.

- **getAllApiKeys():** Returns full `api_key` in the array. Admin UI only shows a substring; ensure no other code (e.g. debug, exports) dumps the full key. **Fix:** Either reduce the returned array to a masked key for listing, or document that the full key must never be logged or sent to the client except on create.

- **params type in list-payroll:** All dynamic params are bound as `SQLITE3_TEXT`; `employee_id` is an integer. **Fix:** Bind integer params as `SQLITE3_INTEGER` for clarity and consistency.

---

## 4. Error handling and logging

- **No structured logging.** Errors go to `ini_set('error_log', ...)` (when not in development) or to the client. There is no application-level log (e.g. failed logins, API errors, payroll run failures). **Fix:** Introduce a simple log helper (e.g. write to `logs/app.log` with timestamp and level) and use it for auth failures, API validation failures, and unexpected exceptions; avoid logging sensitive data (passwords, full SSN, API keys).

- **jsonSuccess/jsonError:** After calling these, scripts often `exit;` but some paths might fall through. **Fix:** Consistently exit after sending a response (or use a single “send and exit” helper).

- **API exception handling:** run-payroll and other endpoints catch `Exception` and return a message to the client. **Fix:** Log the full exception server-side; return a generic message to the client (e.g. “Payroll run failed”) to avoid leaking internals.

---

## 5. Code organization and maintainability

- **Duplicated API auth/rate-limit pattern.** Every API script repeats: require config/functions, initializeDatabase, method check, getApiKey, validateApiKey, checkRateLimit. **Fix:** Extract a small “API bootstrap” (e.g. `require_api_request(['GET','POST'], 'action_name')`) that sets JSON header, inits DB, validates key, applies rate limit, and returns the key or exits with the appropriate error. Reduces duplication and ensures no endpoint forgets a step.

- **Mixed concerns in config.php.** Config defines constants, starts session, sets error reporting, and defines `getDbConnection()` and `initializeDatabase()`. **Fix:** Consider splitting: one file for constants and environment (no session), one for DB init, and have a single “bootstrap” that loads both and starts session where needed. Optional but improves testability and clarity.

- **admin/users.php missing require for formatDate.** `users.php` uses `formatDate($u['created_at'])` but does not `require_once` `functions.php`. **Fix:** Add `require_once __DIR__ . '/../includes/functions.php';` (or ensure it’s pulled in via a shared admin bootstrap).

- **getApiKeyForAdmin() in payroll.php.** Function is defined inline in the script. **Fix:** Move to `functions.php` or a small admin-helper include so it can be reused and tested.

- **Scattered magic numbers.** Rate limits (60, 10, 30), 2MB logo size, 500 limit, etc. **Fix:** Define named constants (e.g. in config) and use them everywhere so tuning is in one place.

---

## 6. Configuration and deployment

- **SITE_URL default.** Default is `http://localhost`; if not overridden in production, links (e.g. in pay stub) will be wrong. **Fix:** Document that SITE_URL must be set (e.g. in a pre-config include or env) for production; optionally fail or warn when SITE_URL is localhost in non-development.

- **Development detection.** `$isDevelopment` uses `HTTP_HOST` and `APP_ENV`. In some setups HTTP_HOST can be spoofed. **Fix:** Prefer `APP_ENV` (or a dedicated env var) for “development” and use HTTP_HOST only as a fallback for local dev; document that production must set APP_ENV=production (or similar).

- **logs/ directory.** Error log path is under project root; ensure `logs/` exists and is writable. **Fix:** In bootstrap or install docs, create `logs/` if missing and document permissions; optionally make the log path configurable.

---

## 7. Sensitive data and crypto

- **SSN storage.** SSN is stored in plaintext. For compliance (e.g. PCI-style or internal policy), consider encryption at rest or at least document that the DB file must be protected (permissions, backup encryption). **Fix:** Document; optionally add application-level encryption for SSN column (key from env, not in repo).

- **Password hashing.** Bcrypt with cost 12 is good. **No change needed.**

- **API key generation.** `bin2hex(random_bytes(32))` is appropriate. **No change needed.**

---

## 8. File and path safety

- **Logo path.** Upload writes to `STORAGE_PATH . '/' . 'logo.' . $ext` (fixed filename). logo-file.php uses `basename($logoPath)` before concatenating with STORAGE_PATH. If `logo_path` in DB were ever user-controlled, path traversal would be a risk. **Fix:** Keep logo_path strictly under app control (as it is now); if ever loading by name from DB, validate that the name is exactly `logo.png` or `logo.jpg` (whitelist).

- **Storage path.** STORAGE_PATH is from config; no check that it’s under a designated base path. **Fix:** Optional: resolve realpath and ensure it’s under a defined base (e.g. project root) to avoid misconfiguration writing outside intended directory.

---

## 9. API design and consistency

- **JSON responses.** Some endpoints send `Content-Type: application/json` only after a branch (e.g. generate-w2, pdf-stub set it only on error). **Fix:** Set JSON header for all JSON responses in one place (e.g. in the shared API bootstrap) so no branch forgets it.

- **HTTP status codes.** Mostly consistent (401, 404, 400, 409, 429). **Fix:** Audit that 404 is used for “resource not found” and 400 for “bad request/validation”; 500 for unexpected server errors.

- **Error message consistency.** Some errors are user-friendly, some are technical. **Fix:** For production, prefer user-facing messages in the `error` field and log technical details server-side.

---

## 10. Summary checklist (production-ready)

| Area | Action |
|------|--------|
| Security | Protect logo-file; add login CSRF; session_regenerate_id on login; logout via POST; no DB message leak; security headers; session cookie flags; optional key-in-query deprecation. |
| Validation | SSN length; date validation (hire_date, pay dates, date filters); salary >= 0; key_name length; optional ZIP/address rules. |
| SQL | Bind LIMIT/OFFSET in list-employees and list-payroll; bind integer params as INTEGER; consider masking API key in getAllApiKeys response. |
| Logging | Application log for auth failures, API errors, exceptions; never log secrets. |
| Code org | API bootstrap helper; users.php require functions.php; move getApiKeyForAdmin; constants for magic numbers. |
| Config | Document SITE_URL and APP_ENV; ensure logs/ exists and is writable. |
| Sensitive data | Document SSN storage and DB file protection; optional SSN encryption. |
| Files | Whitelist logo filename when reading from DB; optional STORAGE_PATH realpath check. |

This document is temporary and can be removed or merged into a permanent “Production readiness” or “Security” doc after fixes are tracked.
