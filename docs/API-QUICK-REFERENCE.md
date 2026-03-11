# Payroll API — Quick Reference

Base URL: `/api/` (relative to your domain).

**Auth:** All endpoints require an API key. Send via:
- **Header:** `X-API-Key: YOUR_API_KEY` (preferred)
- **Query:** `?api_key=YOUR_API_KEY`
- **POST body:** `api_key` field (JSON or form)

**Responses:** JSON envelope `{ "success": true|false, "error": "..." }`. HTTP 400/401/404/405/409/429 for errors.

---

## Endpoints summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | upload-tax-brackets.php | Upload tax config (JSON body) |
| GET | list-tax-brackets.php | List tax years |
| GET | get-tax-brackets.php | Get config (`?year=YYYY`) |
| DELETE | delete-tax-brackets.php | Delete config (`?year=YYYY`) |
| POST | create-employee.php | Create employee |
| GET | list-employees.php | List employees (SSN masked) |
| GET | get-employee.php | Get employee (`?id=`) |
| POST | update-employee.php | Update employee |
| DELETE | delete-employee.php | Delete employee (`?id=`) |
| POST | run-payroll.php | Run payroll (JSON: pay_period_start, pay_period_end, pay_date) |
| GET | list-payroll.php | List payroll (`?employee_id=`, `?pay_date_from=`, `?pay_date_to=`) |
| GET | get-payroll.php | Get payroll (`?id=`) |
| POST | upload-logo.php | Upload logo (multipart: `logo`) |
| GET | pdf-stub.php | Pay stub HTML (`?id=`) |
| GET | generate-w2.php | W-2s for year (`?year=YYYY`) — returns HTML download |

---

## Tax config

**Upload:** `POST /api/upload-tax-brackets.php` with JSON body (see PRD §6). Required: year, ss_wage_base, fica_ss_rate, fica_medicare_rate, additional_medicare_rate, additional_medicare_thresholds, brackets (single, married, head_of_household arrays of {min, max, rate}).

**List:** `GET /api/list-tax-brackets.php` → `{ "success": true, "years": [...], "count": N }`

**Get:** `GET /api/get-tax-brackets.php?year=2026` → `{ "success": true, "year": 2026, "config": {...} }`

**Delete:** `DELETE /api/delete-tax-brackets.php?year=2026`

---

## Employees

**Create:** `POST /api/create-employee.php` — JSON: full_name, ssn, filing_status, hire_date, monthly_gross_salary; optional: step4a_other_income, step4b_deductions, step4c_extra_withholding, i9_completed_at, address_line1, address_line2, city, state, zip. filing_status: Single, Married filing jointly, Married filing separately, Head of Household.

**List:** `GET /api/list-employees.php?limit=100&offset=0` — SSN masked as ***-**-1234.

**Get:** `GET /api/get-employee.php?id=1`

**Update:** `POST /api/update-employee.php` — JSON: id (required) + any fields to update.

**Delete:** `DELETE /api/delete-employee.php?id=1` — 409 if employee has payroll history.

---

## Payroll

**Run:** `POST /api/run-payroll.php` — JSON: `{"pay_period_start":"2026-01-01","pay_period_end":"2026-01-31","pay_date":"2026-01-31"}`. Optional: `employee_ids`: [1,2]. Requires tax config for pay date year.

**List:** `GET /api/list-payroll.php?employee_id=1&pay_date_from=2026-01-01&pay_date_to=2026-12-31&limit=100&offset=0`

**Get:** `GET /api/get-payroll.php?id=1`

---

## Logo and stubs

**Upload logo:** `POST /api/upload-logo.php` — multipart/form-data, field name `logo`. PNG or JPEG, max 2MB.

**Pay stub:** `GET /api/pdf-stub.php?id=1` — returns HTML pay stub (print to PDF in browser).

**W-2:** `GET /api/generate-w2.php?year=2026` — returns HTML file download (one page per employee). Employer and employee addresses must be set.

---

## Error codes

- **400** — Missing/invalid parameters, validation error
- **401** — Invalid or missing API key
- **404** — Resource not found
- **405** — Wrong HTTP method
- **409** — Conflict (e.g. delete employee with payroll)
- **429** — Rate limit exceeded
