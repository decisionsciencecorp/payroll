# Payroll API reference

Full reference for the REST API. All endpoints live under `/api/` and return JSON unless noted (pay stub and W-2 return HTML).

## Authentication

Every endpoint requires an API key. Provide it in one of these ways:

1. **Header (recommended):** `X-API-Key: YOUR_API_KEY` — Prefer this. Keys in the query string or URL can appear in server logs, referrer headers, and browser history; use the header when possible.
2. **Query string:** `?api_key=YOUR_API_KEY` — Avoid in production; keys may be logged or leaked via referrers.
3. **POST body:** For JSON bodies, include `"api_key": "YOUR_API_KEY"`; for form-encoded, include `api_key=YOUR_API_KEY`

Invalid or missing key returns **401** with `{ "success": false, "error": "Invalid or missing API key" }`.

## Response format

Successful JSON responses: `{ "success": true, ... }` with additional data as described per endpoint.

Errors: `{ "success": false, "error": "message" }` with HTTP status 400, 401, 404, 405, 409, or 429.

## Rate limiting

Requests are limited per API key and IP (e.g. 60 per minute for most endpoints). When exceeded, response is **429** with `{ "success": false, "error": "Rate limit exceeded" }`.

---

## Tax bracket configuration

### POST /api/upload-tax-brackets.php

Upload or replace tax config for a year.

**Request:** `Content-Type: application/json`, body as in [TAX-CONFIG.md](TAX-CONFIG.md). Required: `year`, `ss_wage_base`, `fica_ss_rate`, `fica_medicare_rate`, `additional_medicare_rate`, `additional_medicare_thresholds`, `brackets`.

**Response (200):** `{ "success": true, "message": "Tax bracket config saved for year 2026", "year": 2026 }`

**Errors:** 400 if validation fails (missing/invalid fields).

### GET /api/list-tax-brackets.php

List tax years that have config.

**Response (200):** `{ "success": true, "years": [2026, 2025], "count": 2 }`

### GET /api/get-tax-brackets.php

**Query:** `year` (required) — e.g. `?year=2026`

**Response (200):** `{ "success": true, "year": 2026, "config": { ... } }` with the full config object.

**Errors:** 400 if `year` missing; 404 if no config for that year.

### DELETE /api/delete-tax-brackets.php

**Query:** `year` (required) — e.g. `?year=2026`

**Response (200):** `{ "success": true, "message": "Tax bracket config removed for year 2026", "year": 2026 }`

**Errors:** 400 if `year` missing; 404 if no config for that year.

---

## Employees

### POST /api/create-employee.php

**Request:** JSON body. Required: `full_name`, `ssn`, `filing_status`, `hire_date`, `monthly_gross_salary`. Optional: `step4a_other_income`, `step4b_deductions`, `step4c_extra_withholding`, `i9_completed_at`, `address_line1`, `address_line2`, `city`, `state`, `zip`.

`filing_status` must be one of: `Single`, `Married filing jointly`, `Married filing separately`, `Head of Household`.

**Response (201):** `{ "success": true, "message": "Employee created", "employee": { ... } }` with the created row.

**Errors:** 400 if required fields missing or invalid.

### GET /api/list-employees.php

**Query:** `limit` (default 100, max 500), `offset` (default 0). SSN is masked (e.g. `***-**-1234`).

**Response (200):** `{ "success": true, "employees": [ ... ], "count": N, "total": M }`

### GET /api/get-employee.php

**Query:** `id` (required) — employee ID.

**Response (200):** `{ "success": true, "employee": { ... } }` with full row including unmasked SSN.

**Errors:** 400 if `id` missing; 404 if not found.

### POST /api/update-employee.php

**Request:** JSON body. Required: `id`. Any other employee fields to update (see create). Only provided fields are updated.

**Response (200):** `{ "success": true, "message": "Employee updated", "employee": { ... } }`

**Errors:** 400 if `id` missing or no updatable fields; 404 if employee not found.

### DELETE /api/delete-employee.php

**Query:** `id` (required).

**Response (200):** `{ "success": true, "message": "Employee deleted" }`

**Errors:** 400 if `id` missing; 404 if not found; **409** if employee has payroll history (delete not allowed).

---

## Payroll

### POST /api/run-payroll.php

Run payroll for a pay period. Creates one payroll_history row per employee (or per employee in `employee_ids` if provided). Uses tax config for the pay date’s year; YTD is taken from the last payroll row for that employee in that year.

**Request:** JSON. Required: `pay_period_start`, `pay_period_end`, `pay_date` (YYYY-MM-DD). Optional: `employee_ids` (array of integer IDs); if omitted, all employees are included.

**Response (200):** `{ "success": true, "message": "Payroll run completed", "pay_period_start": "...", "pay_period_end": "...", "pay_date": "...", "records": N }`

**Errors:** 400 if dates missing, no tax config for that year, or other validation failure.

### GET /api/list-payroll.php

**Query:** `employee_id`, `pay_date_from`, `pay_date_to`, `limit` (default 100, max 500), `offset`. All optional.

**Response (200):** `{ "success": true, "payroll": [ ... ], "count": N, "total": M }` — each item includes payroll fields plus `employee_name`.

### GET /api/get-payroll.php

**Query:** `id` (required) — payroll_history id.

**Response (200):** `{ "success": true, "payroll": { ... } }` with payroll row and employee info.

**Errors:** 400 if `id` missing; 404 if not found.

---

## Logo and outputs

### POST /api/upload-logo.php

**Request:** `multipart/form-data`, field name `logo`. PNG or JPEG, max 2 MB. Replaces any existing logo.

**Response (200):** `{ "success": true, "message": "Logo updated" }`

**Errors:** 400 if no file, wrong type, or too large.

### GET /api/pdf-stub.php

**Query:** `id` (required) — payroll_history id.

**Response:** HTML (text/html) — pay stub for that run. Use browser Print → Save as PDF. Requires API key.

**Errors:** 400 if `id` missing; 404 if not found; 401 if key missing/invalid.

### GET /api/generate-w2.php

**Query:** `year` (required) — tax year.

**Response:** HTML file download (Content-Disposition: attachment), one W-2–style section per employee who has payroll and a complete address for that year. Employer and employee addresses must be set.

**Errors:** 400 if `year` missing, employer not set, or no eligible employees; 401 if key missing/invalid.

---

## HTTP status summary

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created (e.g. create-employee) |
| 400 | Bad request (validation, missing params) |
| 401 | Unauthorized (invalid/missing API key) |
| 404 | Not found |
| 405 | Method not allowed |
| 409 | Conflict (e.g. delete employee with payroll) |
| 429 | Rate limit exceeded |
