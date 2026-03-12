# Payroll SMCP Plugin

The Payroll app can be driven from MCP (Model Context Protocol) agents via an **SMCP plugin** that exposes the REST API as tools. This document points to the full plugin documentation.

## Full documentation

**[smcp_plugin/README.md](../smcp_plugin/README.md)** – Complete reference:

- Plugin layout and version (0.2.0)
- Setup: SDK install, SMCP config, environment variables (`PAYROLL_BASE_URL`, `PAYROLL_API_KEY`)
- All 11 commands with full parameter reference (list/get/create/update/delete employee, list/get/run payroll, list/get/upload tax brackets)
- SMCP tool naming (`payroll__<command>`)
- CLI usage (`--describe`, running commands)
- Testing: pytest, 100% coverage, `.coveragerc`, conftest fixtures

## Quick summary

| Item | Description |
|------|-------------|
| **Location** | `smcp_plugin/` in the payroll repo |
| **Entrypoint** | `smcp_plugin/cli.py` (invoked by SMCP with `--describe` or `<command> [--arg value ...]`) |
| **Dependencies** | [Payroll Python SDK](../SDK/python/README.md) (`pip install -e SDK/python`) |
| **Env vars** | `PAYROLL_BASE_URL`, `PAYROLL_API_KEY` (required for API calls) |
| **Tests** | `pytest smcp_plugin/tests/` from repo root; 100% coverage on plugin source |

For agents (Letta, Claude Desktop, etc.) using Sanctum/Animus SMCP: add the plugin to SMCP’s `plugins/` directory and set the env vars; tools appear as `payroll__list-employees`, `payroll__run-payroll`, etc.
