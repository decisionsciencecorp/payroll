# Payroll Python SDK

Python client for the [Payroll](https://github.com/decisionsciencecorp/payroll) REST API. Supports tax config, employees, payroll runs, logo upload, pay stubs, and W-2 generation.

**License:** GNU AGPL v3.0 (see repository [LICENSE](../../LICENSE)).

## Requirements

- Python 3.7+
- `requests`

## Install

From the repo root (or from `SDK/python`):

```bash
pip install -e SDK/python
```

Or add to your project:

```toml
# pyproject.toml
dependencies = ["payroll-sdk @ git+https://github.com/decisionsciencecorp/payroll.git#subdirectory=SDK/python"]
```

## Quick start

```python
from payroll_sdk import PayrollClient, PayrollAPIError

client = PayrollClient(
    base_url="https://payroll.example.com",
    api_key="your-api-key",
)

# Tax config
client.upload_tax_brackets({ "year": 2026, "ss_wage_base": 184500, ... })
years = client.list_tax_brackets()

# Employees
emp = client.create_employee(
    full_name="Jane Doe",
    ssn="123-45-6789",
    filing_status="Single",
    hire_date="2026-01-01",
    monthly_gross_salary=5000.00,
)
employees = client.list_employees(limit=50)

# Payroll
client.run_payroll(
    pay_period_start="2026-01-01",
    pay_period_end="2026-01-31",
    pay_date="2026-01-31",
)
payroll = client.list_payroll(pay_date_from="2026-01-01", pay_date_to="2026-12-31")

# Pay stub HTML (e.g. save or print to PDF)
html = client.get_pay_stub_html(payroll_id=1)
with open("stub.html", "wb") as f:
    f.write(html)

# W-2 HTML for a year
w2_html = client.get_w2_html(year=2026)
```

## Error handling

On API errors (4xx/5xx or `success: false`), the SDK raises `PayrollAPIError` with `message`, `status_code`, and `response_body`:

```python
from payroll_sdk import PayrollClient, PayrollAPIError

try:
    client.delete_employee(1)
except PayrollAPIError as e:
    print(e.message, e.status_code)  # e.g. 409 Conflict
```

## API coverage

| Area | Methods |
|------|--------|
| Tax config | `upload_tax_brackets`, `list_tax_brackets`, `get_tax_brackets`, `delete_tax_brackets` |
| Employees | `create_employee`, `list_employees`, `get_employee`, `update_employee`, `delete_employee` |
| Payroll | `run_payroll`, `list_payroll`, `get_payroll` |
| Logo | `upload_logo`, `get_logo` |
| Outputs | `get_pay_stub_html`, `get_w2_html` |

See the [API docs](../../docs/API.md) and [quick reference](../../docs/API-QUICK-REFERENCE.md) for request/response details.

## Version

SDK version is aligned with the repository release. Current: **0.4.0**.
