# Data model

The application uses SQLite and the following tables. All monetary amounts are stored in dollars with two decimal places unless noted.

## Tables

### api_keys

Stores API keys for authenticating API requests.

| Column     | Type    | Description                    |
|-----------|---------|--------------------------------|
| id        | INTEGER | Primary key                    |
| key_name  | TEXT    | Descriptive name               |
| api_key   | TEXT    | Unique key (e.g. 64-char hex)  |
| created_at| DATETIME| When created                   |
| last_used | DATETIME| Last use (updated on request)  |

### api_rate_limits

Per-key (and IP) rate limiting windows.

| Column       | Type    | Description              |
|-------------|---------|--------------------------|
| rate_key   | TEXT    | Primary key (e.g. action:key:ip) |
| window_start| INTEGER | Unix timestamp of window start   |
| count      | INTEGER | Requests in current window       |

### employees

One row per employee.

| Column               | Type    | Description |
|----------------------|---------|-------------|
| id                   | INTEGER | Primary key |
| full_name            | TEXT    | Required    |
| ssn                  | TEXT    | Required (stored digits only) |
| filing_status        | TEXT    | One of: Single, Married filing jointly, Married filing separately, Head of Household |
| step4a_other_income  | REAL    | Optional (W-4 step 4a) |
| step4b_deductions    | REAL    | Optional (W-4 step 4b) |
| step4c_extra_withholding | REAL | Optional (W-4 step 4c) |
| hire_date            | TEXT    | Date (YYYY-MM-DD) |
| monthly_gross_salary | REAL    | Required    |
| i9_completed_at      | TEXT    | Optional date (compliance) |
| address_line1        | TEXT    | Optional; required for W-2 |
| address_line2        | TEXT    | Optional    |
| city                 | TEXT    | Optional; required for W-2 |
| state                | TEXT    | Optional; required for W-2 |
| zip                  | TEXT    | Optional; required for W-2 |
| created_at           | DATETIME| Set on insert |
| updated_at           | DATETIME| Set on update |

### payroll_history

One row per employee per pay run.

| Column                | Type    | Description |
|-----------------------|---------|-------------|
| id                    | INTEGER | Primary key |
| employee_id           | INTEGER | FK → employees.id |
| pay_period_start      | TEXT    | Date       |
| pay_period_end        | TEXT    | Date       |
| pay_date              | TEXT    | Date       |
| gross_pay             | REAL    |            |
| federal_withholding   | REAL    |            |
| employee_ss           | REAL    | Social Security (employee) |
| employee_medicare     | REAL    | Medicare (employee, incl. additional) |
| employer_ss           | REAL    | Social Security (employer) |
| employer_medicare     | REAL    | Medicare (employer) |
| net_pay               | REAL    |            |
| ytd_gross             | REAL    | Year-to-date gross after this run |
| ytd_federal_withheld  | REAL    | YTD federal |
| ytd_ss                | REAL    | YTD Social Security |
| ytd_medicare          | REAL    | YTD Medicare |
| created_at            | DATETIME|            |
| updated_at            | DATETIME|            |

**Unique constraint:** `(employee_id, pay_date)` — one payroll record per employee per pay date.

**Indexes:** `employee_id`, `pay_date`, and unique on `(employee_id, pay_date)` for YTD lookups and duplicate prevention.

### tax_config

One row per tax year. Stores the full JSON config for that year.

| Column     | Type    | Description     |
|------------|---------|-----------------|
| tax_year   | INTEGER | Primary key     |
| config_json| TEXT    | JSON (see TAX-CONFIG.md) |

### company_settings

Single row (id = 1) for the one company (employer) and app config.

| Column                  | Type    | Description        |
|-------------------------|---------|--------------------|
| id                      | INTEGER | Always 1           |
| logo_path               | TEXT    | Filename in storage (e.g. logo.png) |
| site_url                | TEXT    | Base URL for this installation (e.g. https://payroll.example.com). Used for admin→API calls and pay stub links. Blank = localhost. |
| employer_name           | TEXT    | For W-2 and stubs  |
| employer_ein            | TEXT    | 9 digits           |
| employer_address_line1 | TEXT    |                    |
| employer_address_line2 | TEXT    | Optional           |
| employer_city           | TEXT    |                    |
| employer_state         | TEXT    |                    |
| employer_zip           | TEXT    |                    |
| updated_at              | DATETIME|                    |

### admin_users

Admin accounts for the web UI.

| Column        | Type    | Description  |
|---------------|---------|--------------|
| id            | INTEGER | Primary key  |
| username      | TEXT    | Unique       |
| password_hash | TEXT    | bcrypt       |
| created_at    | DATETIME|              |

No roles; all admins have full access.
