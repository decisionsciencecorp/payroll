# Payroll Software — Product Requirements Document (PRD)

**Status:** Authoritative (product decisions + Athena tax guidance applied)  
**Reference architecture:** [technonomicon-lore/technonomicon.net](https://github.com/technonomicon-lore/technonomicon.net) — project structure and API patterns.  
**Scope:** Single-company internal accounting tool (not SaaS). Monthly pay only. Legally compliant MVP.

---

## 1. Executive summary

Payroll application for calculating federal withholding, FICA (Social Security and Medicare), and net pay. Supports configurable tax brackets via JSON upload, customizable PDF pay stubs (with logo), and RESTful API coverage. Target stack: LEMP-compatible PHP, no `.htaccess` dependencies.

**Deliverables:** RESTful API for all operations; employee and payroll history data model; tax bracket configuration via JSON; PDF pay stubs with logo upload.

---

## 2. Research summary

*(Research requested in the PRD — completed for authoritative PRD.)*

### 2.1 IRS / FICA (2026)

- **Social Security:** 6.2% each (employee + employer); wage base **$184,500** (2026).
- **Medicare:** 1.45% each; no wage base.
- **Additional Medicare tax:** 0.9% on wages above threshold (employee only; no employer match). Included in tax config for MVP compliance.
- **Federal income tax:** Brackets and standard deduction updated annually (e.g. IRS inflation adjustments). Withholding is typically wage-bracket or percentage method per IRS Pub 15-T; bracket tables vary by filing status and pay frequency.

**Source:** IRS inflation adjustments for tax year 2026; SSA/IRS wage base and rate announcements. Bracket format in this PRD should align with IRS annual withholding tables (e.g. wage-bracket tables by filing status and pay period).

### 2.2 Reference architecture (technonomicon.net)

- **API style:** Script-per-endpoint under `/api/` (e.g. `create-article.php`, `list-articles.php`, `get-article.php`, `delete-article.php`). HTTP method + script name convey action; identifiers in query or JSON body.
- **Auth:** `X-API-Key` header or `api_key` query/POST param.
- **Responses:** JSON envelope `{ "success": true|false, "error": "...", ... }`; 400/401/404/405/429 for errors.
- **Docs:** Table summary (Method | Endpoint | Description | Auth), then per-endpoint HTTP snippet, request/response bodies, query params.
- **Stack:** PHP, no `.htaccess`; DB and config in `includes/`; public entrypoints in `public/api/`.

---

## 3. Technical requirements

- **Stack:** LEMP-compatible PHP (no `.htaccess` dependencies).
- **API:** RESTful API for all operations; patterns and project structure from technonomicon-lore/technonomicon.net.
- **Features:**
  - Logo upload for customizable PDF pay stubs.
  - Tax bracket configuration via JSON upload.
- **Reference:** [technonomicon-lore/technonomicon.net](https://github.com/technonomicon-lore/technonomicon.net).

---

## 4. Data model (repositories / tables)

### 4.1 Employee table

| Field | Description |
|-------|-------------|
| Full name | |
| SSN | |
| Filing status | Single, Married filing jointly, Married filing separately, Head of Household |
| Step 4(a) other income | Optional |
| Step 4(b) deductions | Optional |
| Step 4(c) extra withholding | Optional |
| Hire date | |
| Monthly gross salary | |
| I-9 completed at | Date (required for compliance; within 3 business days of hire) |
| address_line1, address_line2, city, state, zip | Optional; required for W-2 generation |
| created_at | |
| updated_at | |

### 4.2 Payroll history table

| Field | Description |
|-------|-------------|
| employee_id | FK to employee |
| pay_period_start | |
| pay_period_end | |
| Pay date | |
| Gross pay for period | |
| Federal withholding | Calculated |
| Employee SS | Calculated |
| Employee Medicare | Calculated |
| Employer SS | Calculated |
| Employer Medicare | Calculated |
| Net pay | Calculated |
| YTD gross | For audit / W-2 |
| YTD federal withheld | |
| YTD SS | |
| YTD Medicare | |
| created_at | |
| updated_at | |

Each row references one employee via **employee_id** (FK). No multi-tenancy; single company only.

### 4.3 Company settings (single row)

- Employer name, EIN, address (for W-2 and pay stubs). Logo path for PDF stubs.

### 4.4 Tax config table

| Field | Description |
|-------|-------------|
| Tax year | |
| JSON configuration | Brackets, rates, wage bases |

---

## 5. API endpoints (full coverage; tax config specified)

API base: `/api/`. Auth and response style follow reference: `X-API-Key` or `api_key`; JSON envelope `success` / `error`.

Endpoints below are written in **reference architecture style** (script-per-endpoint, same doc structure as technonomicon.net API-QUICK-REFERENCE).

### 5.1 Tax bracket configuration (priority)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/upload-tax-brackets.php` | Upload tax bracket config (JSON body) | Yes |
| GET | `/api/list-tax-brackets.php` | List available tax years | Yes |
| GET | `/api/get-tax-brackets.php` | Get config for a year (`?year=YYYY`) | Yes |
| DELETE | `/api/delete-tax-brackets.php` | Remove config for a year (`?year=YYYY`) | Yes |

#### Upload tax bracket config

```http
POST /api/upload-tax-brackets.php
Content-Type: application/json
X-API-Key: YOUR_API_KEY

{
  "year": 2026,
  "ss_wage_base": 184500.00,
  "fica_ss_rate": 0.062,
  "fica_medicare_rate": 0.0145,
  "additional_medicare_rate": 0.009,
  "additional_medicare_thresholds": {
    "single": 200000,
    "married_filing_jointly": 250000,
    "married_filing_separately": 125000
  },
  "brackets": {
    "single": [{"min": 0, "max": 7500, "rate": 0.00}, {"min": 7500, "max": 19900, "rate": 0.10}, ...],
    "married": [...],
    "head_of_household": [...]
  }
}
```

*(All monetary values in API and JSON: **dollars, two decimals** e.g. 184500.00. Display as $00.00. Be consistent everywhere.)*

**Response:**

```json
{
  "success": true,
  "message": "Tax bracket config saved for year 2026",
  "year": 2026
}
```

#### List available tax years

```http
GET /api/list-tax-brackets.php?api_key=YOUR_API_KEY
```

**Response:**

```json
{
  "success": true,
  "years": [2026, 2025],
  "count": 2
}
```

#### Get config for a year

```http
GET /api/get-tax-brackets.php?api_key=YOUR_API_KEY&year=2026
```

**Response:**

```json
{
  "success": true,
  "year": 2026,
  "config": {
    "year": 2026,
    "ss_wage_base": 184500.00,
    "fica_ss_rate": 0.062,
    "fica_medicare_rate": 0.0145,
    "additional_medicare_rate": 0.009,
    "additional_medicare_thresholds": { "single": 200000, "married_filing_jointly": 250000, "married_filing_separately": 125000 },
    "brackets": { "single": [...], "married": [...], "head_of_household": [...] }
  }
}
```

#### Delete config for a year

```http
DELETE /api/delete-tax-brackets.php?year=2026
X-API-Key: YOUR_API_KEY
```
*(Query param preferred; DELETE with body not reliably supported everywhere.)*

**Response:**

```json
{
  "success": true,
  "message": "Tax bracket config removed for year 2026",
  "year": 2026
}
```

### 5.2 Other resources (full coverage)

*To be specified in same style as §5.1. Endpoint names below.*

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/create-employee.php` | Create employee | Yes |
| GET | `/api/list-employees.php` | List employees | Yes |
| GET | `/api/get-employee.php` | Get one (`?id=`) | Yes |
| POST | `/api/update-employee.php` | Update employee | Yes |
| DELETE | `/api/delete-employee.php` | Delete employee (`?id=`) | Yes |
| POST | `/api/run-payroll.php` | Calculate and store payroll for a period | Yes |
| GET | `/api/list-payroll.php` | List by employee and/or date range | Yes |
| GET | `/api/get-payroll.php` | Get one run (`?id=`) | Yes |
| POST | `/api/upload-logo.php` | Upload company logo (for PDF stubs) | Yes |
| GET | `/api/pdf-stub.php` | Generate PDF pay stub for a payroll run (`?id=`) | Yes |
| GET | `/api/generate-w2.php` | Generate W-2s for a tax year (`?year=YYYY`); returns ZIP of PDFs or single PDF | Yes |

*(Admin UI: login, dashboard, API keys, employees, payroll runs, tax config, logo upload, W-2 generation; lightweight admin user management like technonomicon.)*

---

## 6. Tax config JSON format (K.I.S.S. — Athena)

All monetary values: **dollars, two decimals** (e.g. 184500.00). Display as $00.00.

```json
{
  "year": 2026,
  "ss_wage_base": 184500.00,
  "fica_ss_rate": 0.062,
  "fica_medicare_rate": 0.0145,
  "additional_medicare_rate": 0.009,
  "additional_medicare_thresholds": {
    "single": 200000,
    "married_filing_jointly": 250000,
    "married_filing_separately": 125000
  },
  "brackets": {
    "single": [
      {"min": 0, "max": 7500, "rate": 0.00},
      {"min": 7500, "max": 19900, "rate": 0.10},
      {"min": 19900, "max": 57900, "rate": 0.12}
    ],
    "married": [...],
    "head_of_household": [...]
  }
}
```

- **year:** Tax year (integer).
- **ss_wage_base:** Social Security wage base (dollars).
- **fica_ss_rate:** Employee and employer SS rate (same).
- **fica_medicare_rate:** Employee and employer Medicare rate (same).
- **additional_medicare_rate:** 0.009 — employee only; no employer match. Apply on wages above threshold.
- **additional_medicare_thresholds:** Annual wage thresholds (dollars) by filing type. Use `married` filing status to key off `married_filing_jointly` or `married_filing_separately` as appropriate.
- **brackets:** One array per filing status. Each bracket: **min, max, rate** — **monthly** taxable wage bounds and marginal rate. Align with IRS Pub 15-T wage-bracket tables (monthly).

---

## 7. Product decisions (locked)

- **Money:** Dollars, two decimals ($00.00) in API, JSON, and PDF.
- **Additional Medicare:** Included in tax config for MVP legal compliance (rate + thresholds).
- **Crypto:** None. Accounting only; "paid" or "not paid" status. No crypto wallet field.
- **Scope:** Single company, internal tool only. Not SaaS; no multi-tenancy.
- **Pay frequency:** Monthly only.
- **Payroll ↔ employee:** Payroll history rows reference employee by **employee_id** (FK). One company.
- **Logo:** One global logo for the company (used on PDF stubs).

---

## 8. Document history

| Date | Change |
|------|--------|
| 2026-03-11 | Initial authoritative PRD: research summary, reference-style API (tax config only), data model, tax JSON format, clarifying questions for Athena and product owner. |
| 2026-03-11 | Locked product decisions (dollars $00.00, Additional Medicare in, no crypto, single company monthly, employee_id FK). Applied Athena K.I.S.S. tax schema: brackets { min, max, rate } monthly; additional_medicare_rate + additional_medicare_thresholds. Removed sections 7–8 questions; added §7 Product decisions. |
| 2026-03-11 | Athena PRD review: added I-9 completed at, pay_period_start/end, YTD fields, created_at/updated_at; filing status = Single, Married filing jointly, Married filing separately, Head of Household; DELETE tax brackets via ?year= query param; §5.2 concrete endpoint table; monetary consistency 184500.00. |
| 2026-03-11 | In scope: W-2 generation (generate-w2.php, employer/employee address); admin UI and login (technonomicon pattern); lightweight admin user management. Company settings table; employee address fields. |
