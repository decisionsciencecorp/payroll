# Payroll SMCP Plugin

SMCP plugin that exposes the [Payroll REST API](https://github.com/decisionsciencecorp/payroll) as MCP tools using the [payroll-sdk](https://github.com/decisionsciencecorp/payroll/tree/main/SDK/python). Use it with the Sanctum/Animus SMCP server so agents (e.g. Letta, Claude Desktop) can list employees, run payroll, manage tax config, and more.

## Setup

1. **Install the Payroll SDK** (from the payroll repo root):
   ```bash
   pip install -e SDK/python
   ```

2. **Install plugin deps** (optional; SDK already depends on `requests`):
   ```bash
   pip install -r smcp_plugin/requirements.txt
   ```

3. **Configure SMCP** to load this plugin:
   - Copy or symlink this directory into your SMCP `plugins/` directory, e.g.:
     ```bash
     ln -s /path/to/payroll/smcp_plugin /path/to/smcp/plugins/payroll
     ```
   - Or set `MCP_PLUGINS_DIR` to a directory that contains a `payroll` folder with this plugin’s `cli.py`.

4. **Environment variables** (required for all commands):
   - `PAYROLL_BASE_URL` – Base URL of your Payroll app (e.g. `https://payroll.example.com`).
   - `PAYROLL_API_KEY` – API key from the Payroll admin (Admin → API Keys).

   These can be set in the environment before starting SMCP, or via Letta agent env (if SMCP loads them).

## Commands (tools)

| Command | Description |
|--------|-------------|
| `list-employees` | List employees (SSN masked); optional limit, offset |
| `get-employee` | Get one employee by ID |
| `create-employee` | Create employee (name, SSN, filing status, hire date, salary, optional address) |
| `update-employee` | Update employee by ID (any subset of fields) |
| `delete-employee` | Delete employee (fails if they have payroll history) |
| `list-payroll` | List payroll records (optional filters) |
| `get-payroll` | Get one payroll record by ID |
| `run-payroll` | Run payroll for a period (start, end, pay date; optional employee_ids) |
| `list-tax-brackets` | List tax years with config |
| `get-tax-brackets` | Get tax config for a year |
| `upload-tax-brackets` | Upload/replace tax config (JSON string; see docs/TAX-CONFIG.md) |

Tool names in MCP are `payroll__list-employees`, `payroll__get-employee`, etc. (SMCP uses double underscore).

## Test locally

```bash
# Describe (for SMCP discovery)
python smcp_plugin/cli.py --describe

# Example (requires PAYROLL_BASE_URL and PAYROLL_API_KEY)
export PAYROLL_BASE_URL=https://payroll.decisionsciencecorp.com
export PAYROLL_API_KEY=your-key
python smcp_plugin/cli.py list-employees --limit 5
python smcp_plugin/cli.py list-tax-brackets
```

## License

GNU AGPL v3.0 (same as the Payroll repo).
