# Tax config JSON format

Tax bracket configuration is uploaded via `POST /api/upload-tax-brackets.php` as JSON. One config per tax year. The structure below matches the PRD and the payroll calculation logic.

## Top-level fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| year | integer | Yes | Tax year (e.g. 2026) |
| ss_wage_base | number | Yes | Social Security wage base (dollars) |
| fica_ss_rate | number | Yes | Employee and employer SS rate (e.g. 0.062) |
| fica_medicare_rate | number | Yes | Employee and employer Medicare rate (e.g. 0.0145) |
| additional_medicare_rate | number | Yes | Additional Medicare rate, employee only (e.g. 0.009) |
| additional_medicare_thresholds | object | Yes | Annual wage thresholds by filing type |
| brackets | object | Yes | Withholding brackets by filing status |

## additional_medicare_thresholds

Object with keys:

- **single** — Annual wage threshold (dollars) for Single filers
- **married_filing_jointly** — For Married filing jointly
- **married_filing_separately** — For Married filing separately

Example:

```json
"additional_medicare_thresholds": {
  "single": 200000,
  "married_filing_jointly": 250000,
  "married_filing_separately": 125000
}
```

## brackets

Object with three keys: **single**, **married**, **head_of_household**. Each value is an array of bracket objects. Employee filing status is mapped as:

- "Single" → **single**
- "Married filing jointly" or "Married filing separately" → **married**
- "Head of Household" → **head_of_household**

Each bracket object:

| Field | Type | Description |
|-------|------|-------------|
| min | number | Lower bound of taxable wage for this bracket (monthly, dollars) |
| max | number | Upper bound (monthly, dollars) |
| rate | number | Marginal rate (e.g. 0.10 for 10%) |

Brackets should be in ascending order by `min`/`max`. Federal withholding is computed as the sum over brackets of (amount in bracket) × rate. Monthly gross is used (no per-check standard deduction in this MVP).

## Example (minimal)

```json
{
  "year": 2026,
  "ss_wage_base": 184500,
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
    "married": [
      {"min": 0, "max": 15000, "rate": 0.00},
      {"min": 15000, "max": 39800, "rate": 0.10}
    ],
    "head_of_household": [
      {"min": 0, "max": 11250, "rate": 0.00},
      {"min": 11250, "max": 27900, "rate": 0.10}
    ]
  }
}
```

## Validation (upload API)

- **year** must be 2000–2100.
- **brackets** must have non-empty arrays for `single`, `married`, and `head_of_household`.
- Each bracket must have **min**, **max**, and **rate**.
- **additional_medicare_thresholds** must include `single`, `married_filing_jointly`, and `married_filing_separately`.

Monetary values are stored as provided; the app uses dollars with two decimals for display and calculations.
