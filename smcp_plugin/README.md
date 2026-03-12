# Payroll SMCP Plugin

SMCP plugin that exposes the [Payroll REST API](https://github.com/decisionsciencecorp/payroll) as MCP tools using the [payroll-sdk](https://github.com/decisionsciencecorp/payroll/tree/main/SDK/python). Use it with the Sanctum/Animus SMCP server so agents (e.g. Letta, Claude Desktop) can list employees, run payroll, manage tax config, and more.

**Plugin version:** 0.2.0

---

## Repository layout

```
smcp_plugin/
├── cli.py              # Entrypoint: --describe and command dispatch
├── __init__.py         # Package marker
├── requirements.txt   # Optional deps (SDK provides requests)
├── README.md           # This file
└── tests/
    ├── __init__.py
    ├── conftest.py     # Path setup, clean_env fixture, runpy warning filter
    └── test_cli.py     # Full coverage of cli.py (pytest)
```

The plugin expects to live inside the payroll repo so it can load the SDK from `../SDK/python` when that path exists.

---

## Setup

### 1. Install the Payroll SDK

From the payroll repo root:

```bash
pip install -e SDK/python
```

### 2. Plugin dependencies (optional)

The SDK already depends on `requests`. If you need a repeatable env:

```bash
pip install -r smcp_plugin/requirements.txt
```

### 3. Configure SMCP to load the plugin

- **Symlink** the plugin into your SMCP `plugins/` directory:

  ```bash
  ln -s /path/to/payroll/smcp_plugin /path/to/smcp/plugins/payroll
  ```

- Or set `MCP_PLUGINS_DIR` to a directory that contains a `payroll` folder with this plugin’s `cli.py`.

SMCP discovers the plugin by name (`payroll`) and invokes `cli.py` with `--describe` or with a command and arguments.

### 4. Environment variables

Required for all commands that call the API:

| Variable | Description |
|----------|-------------|
| `PAYROLL_BASE_URL` | Base URL of the Payroll app (e.g. `https://payroll.example.com`). Trailing slashes are stripped. |
| `PAYROLL_API_KEY` | API key from Payroll Admin → API Keys. |

Set these in the environment before starting SMCP, or via your agent runtime (e.g. Letta env).

---

## Commands (tools)

SMCP exposes each command as a tool named `payroll__<command>` (e.g. `payroll__list-employees`). Arguments are passed as `--arg-name value` on the CLI.

### Summary table

| Command | Description |
|--------|-------------|
| `list-employees` | List employees (SSN masked); optional limit, offset |
| `get-employee` | Get one employee by ID (full details including SSN) |
| `create-employee` | Create employee (required: name, SSN, filing status, hire date, monthly salary; optional address) |
| `update-employee` | Update employee by ID (any subset of fields) |
| `delete-employee` | Delete employee (fails if they have payroll history) |
| `list-payroll` | List payroll records with optional filters |
| `get-payroll` | Get one payroll record by ID |
| `run-payroll` | Run payroll for a period (start, end, pay date; optional employee_ids) |
| `list-tax-brackets` | List tax years that have config |
| `get-tax-brackets` | Get full tax config for a year |
| `upload-tax-brackets` | Upload/replace tax config (JSON string; see [docs/TAX-CONFIG.md](../docs/TAX-CONFIG.md)) |

### Full parameter reference

- **list-employees**  
  `limit` (integer, optional, default 100), `offset` (integer, optional, default 0)

- **get-employee**  
  `employee_id` (integer, required)

- **create-employee**  
  Required: `full_name`, `ssn`, `filing_status`, `hire_date` (YYYY-MM-DD), `monthly_gross_salary` (number).  
  Optional: `address_line1`, `city`, `state`, `zip`.  
  `filing_status`: one of Single, Married filing jointly, Married filing separately, Head of Household.

- **update-employee**  
  Required: `employee_id`.  
  Optional (any subset): `full_name`, `monthly_gross_salary`, `address_line1`, `city`, `state`, `zip`.  
  Omitted or empty optional args are not sent.

- **delete-employee**  
  `employee_id` (integer, required)

- **list-payroll**  
  Optional: `employee_id`, `pay_date_from` (YYYY-MM-DD), `pay_date_to` (YYYY-MM-DD), `limit` (default 100), `offset` (default 0)

- **get-payroll**  
  `payroll_id` (integer, required)

- **run-payroll**  
  Required: `pay_period_start`, `pay_period_end`, `pay_date` (YYYY-MM-DD).  
  Optional: `employee_ids` (array of integers; if omitted, all active employees).

- **list-tax-brackets**  
  No parameters.

- **get-tax-brackets**  
  `year` (integer, required, e.g. 2026)

- **upload-tax-brackets**  
  `config_json` (string, required): JSON string of tax config (year, ss_wage_base, brackets, etc.). See [docs/TAX-CONFIG.md](../docs/TAX-CONFIG.md).

---

## CLI usage

From the payroll repo (with `PAYROLL_BASE_URL` and `PAYROLL_API_KEY` set for commands that call the API):

```bash
# Plugin discovery (SMCP uses this)
python smcp_plugin/cli.py --describe

# Examples
python smcp_plugin/cli.py list-employees --limit 5 --offset 0
python smcp_plugin/cli.py get-employee --employee_id 1
python smcp_plugin/cli.py list-tax-brackets
python smcp_plugin/cli.py get-tax-brackets --year 2026
python smcp_plugin/cli.py list-payroll --pay_date_from 2026-01-01 --pay_date_to 2026-01-31
python smcp_plugin/cli.py run-payroll --pay_period_start 2026-01-01 --pay_period_end 2026-01-31 --pay_date 2026-02-01
```

`--describe` prints a JSON description of the plugin (name, version, commands and parameters). All other invocations are `cli.py <command> [--arg value ...]`; the process exits 0 on success and non-zero on error, with JSON on stdout for both.

---

## Testing

Tests live in `smcp_plugin/tests/` and target **100% coverage** of the plugin source (`cli.py`, `__init__.py`). Test code is excluded from the coverage report via `.coveragerc` at the repo root.

### Run tests (from payroll repo root)

```bash
PYTHONPATH=. SDK/python/.venv/bin/python -m pytest smcp_plugin/tests/ -v --cov=smcp_plugin --cov-report=term-missing
```

Use the same Python that has the SDK (e.g. `SDK/python/.venv` if you use the repo venv). `conftest.py` adds `SDK/python` to `sys.path` when present so the SDK is importable.

### Coverage config

Repo root `.coveragerc`:

- `source = smcp_plugin`
- `omit = smcp_plugin/tests/*`

So reported coverage is for the plugin package only, not the test files.

### Test fixtures (`conftest.py`)

- **Path:** If `SDK/python` exists and is not on `sys.path`, it is inserted so `payroll_sdk` can be imported.
- **clean_env:** Autouse fixture that removes `PAYROLL_BASE_URL` and `PAYROLL_API_KEY` so tests set them explicitly (or test missing-env behavior).
- **Warnings:** Runpy warning from running `smcp_plugin.cli` as `__main__` is filtered so the test suite stays quiet.

### What the tests cover

- `get_plugin_description()` output (name, version, commands).
- `_get_client()`: missing base URL, missing API key, trailing-slash stripping, SDK not installed, success.
- `_run()`: all 11 commands (success paths with mocked client), unknown command, client error, `PayrollAPIError` (status_code), generic exception, invalid JSON for upload-tax-brackets, update-employee with only `employee_id`.
- `main()`: `--describe`, no command (help + exit 1), command success, command error; kwargs building (skip `None`, skip empty string for non–update-employee).
- `_load_sdk()`: returns client when available, returns `(None, None)` on `ImportError`, path insert/restore when SDK path is present.
- Entrypoint: `if __name__ == "__main__"` via `runpy.run_module("smcp_plugin.cli", run_name="__main__")`.

---

## License

GNU AGPL v3.0 (same as the Payroll repo).
