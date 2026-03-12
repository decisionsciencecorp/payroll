#!/usr/bin/env python3
"""
SMCP plugin: payroll – interact with the Payroll REST API via the payroll-sdk.

Requires PAYROLL_BASE_URL and PAYROLL_API_KEY in the environment (or set by Letta/SMCP).
Commands: list-employees, get-employee, create-employee, update-employee, delete-employee,
list-payroll, get-payroll, run-payroll, list-tax-brackets, get-tax-brackets, upload-tax-brackets.

Copyright (C) 2026 Decision Science Corp. Licensed under GNU AGPL v3.0.
"""

import argparse
import json
import os
import sys
from typing import Any, Dict, List, Optional, Tuple

# Prefer SDK from same repo if present
_plugin_dir = os.path.dirname(os.path.abspath(__file__))
_repo_root = os.path.dirname(_plugin_dir)
_sdk_path = os.path.join(_repo_root, "SDK", "python")


def _load_sdk():
    """Import payroll_sdk; add repo SDK to path if needed. Returns (PayrollClient, PayrollAPIError) or (None, None)."""
    if os.path.isdir(_sdk_path) and _sdk_path not in sys.path:
        sys.path.insert(0, _sdk_path)
    try:
        from payroll_sdk import PayrollClient as PC, PayrollAPIError as PAE
        return PC, PAE
    except ImportError:
        return None, None


PayrollClient, PayrollAPIError = _load_sdk()


def _get_client() -> Tuple[Optional["PayrollClient"], Optional[str]]:
    base_url = (os.environ.get("PAYROLL_BASE_URL") or "").strip().rstrip("/")
    api_key = (os.environ.get("PAYROLL_API_KEY") or "").strip()
    if not base_url or not api_key:
        return None, "Set PAYROLL_BASE_URL and PAYROLL_API_KEY in the environment."
    if not PayrollClient:
        return None, "payroll_sdk not installed. Install from this repo: pip install -e SDK/python"
    return PayrollClient(base_url, api_key), None


def get_plugin_description() -> Dict[str, Any]:
    """Return structured plugin description for SMCP --describe."""
    return {
        "plugin": {
            "name": "payroll",
            "version": "0.2.0",
            "description": "Interact with the Payroll REST API: employees, payroll runs, tax config. Requires PAYROLL_BASE_URL and PAYROLL_API_KEY.",
        },
        "commands": [
            {
                "name": "list-employees",
                "description": "List employees (SSN masked). Optional limit and offset.",
                "parameters": [
                    {"name": "limit", "type": "integer", "description": "Max number to return", "required": False, "default": 100},
                    {"name": "offset", "type": "integer", "description": "Offset for pagination", "required": False, "default": 0},
                ],
            },
            {
                "name": "get-employee",
                "description": "Get one employee by ID (full details including SSN).",
                "parameters": [
                    {"name": "employee_id", "type": "integer", "description": "Employee ID", "required": True, "default": None},
                ],
            },
            {
                "name": "create-employee",
                "description": "Create a new employee. Required: full_name, ssn, filing_status, hire_date, monthly_gross_salary.",
                "parameters": [
                    {"name": "full_name", "type": "string", "description": "Full name", "required": True, "default": None},
                    {"name": "ssn", "type": "string", "description": "SSN (digits or XXX-XX-XXXX)", "required": True, "default": None},
                    {"name": "filing_status", "type": "string", "description": "Single, Married filing jointly, Married filing separately, Head of Household", "required": True, "default": None},
                    {"name": "hire_date", "type": "string", "description": "Hire date YYYY-MM-DD", "required": True, "default": None},
                    {"name": "monthly_gross_salary", "type": "number", "description": "Monthly gross salary", "required": True, "default": None},
                    {"name": "address_line1", "type": "string", "description": "Address line 1", "required": False, "default": None},
                    {"name": "city", "type": "string", "description": "City", "required": False, "default": None},
                    {"name": "state", "type": "string", "description": "State (e.g. CA)", "required": False, "default": None},
                    {"name": "zip", "type": "string", "description": "ZIP code", "required": False, "default": None},
                ],
            },
            {
                "name": "update-employee",
                "description": "Update an employee. Pass employee_id and any fields to update.",
                "parameters": [
                    {"name": "employee_id", "type": "integer", "description": "Employee ID", "required": True, "default": None},
                    {"name": "full_name", "type": "string", "description": "Full name", "required": False, "default": None},
                    {"name": "monthly_gross_salary", "type": "number", "description": "Monthly gross salary", "required": False, "default": None},
                    {"name": "address_line1", "type": "string", "description": "Address line 1", "required": False, "default": None},
                    {"name": "city", "type": "string", "description": "City", "required": False, "default": None},
                    {"name": "state", "type": "string", "description": "State", "required": False, "default": None},
                    {"name": "zip", "type": "string", "description": "ZIP code", "required": False, "default": None},
                ],
            },
            {
                "name": "delete-employee",
                "description": "Delete an employee. Fails if employee has payroll history.",
                "parameters": [
                    {"name": "employee_id", "type": "integer", "description": "Employee ID", "required": True, "default": None},
                ],
            },
            {
                "name": "list-payroll",
                "description": "List payroll records. Optional filters: employee_id, pay_date_from, pay_date_to, limit, offset.",
                "parameters": [
                    {"name": "employee_id", "type": "integer", "description": "Filter by employee ID", "required": False, "default": None},
                    {"name": "pay_date_from", "type": "string", "description": "From date YYYY-MM-DD", "required": False, "default": None},
                    {"name": "pay_date_to", "type": "string", "description": "To date YYYY-MM-DD", "required": False, "default": None},
                    {"name": "limit", "type": "integer", "description": "Max records", "required": False, "default": 100},
                    {"name": "offset", "type": "integer", "description": "Offset", "required": False, "default": 0},
                ],
            },
            {
                "name": "get-payroll",
                "description": "Get one payroll record by ID.",
                "parameters": [
                    {"name": "payroll_id", "type": "integer", "description": "Payroll record ID", "required": True, "default": None},
                ],
            },
            {
                "name": "run-payroll",
                "description": "Run payroll for a period. Requires pay_period_start, pay_period_end, pay_date (YYYY-MM-DD). Optional employee_ids list.",
                "parameters": [
                    {"name": "pay_period_start", "type": "string", "description": "Period start YYYY-MM-DD", "required": True, "default": None},
                    {"name": "pay_period_end", "type": "string", "description": "Period end YYYY-MM-DD", "required": True, "default": None},
                    {"name": "pay_date", "type": "string", "description": "Pay date YYYY-MM-DD", "required": True, "default": None},
                    {"name": "employee_ids", "type": "array", "description": "Optional list of employee IDs to include", "required": False, "default": None},
                ],
            },
            {
                "name": "list-tax-brackets",
                "description": "List tax years that have config.",
                "parameters": [],
            },
            {
                "name": "get-tax-brackets",
                "description": "Get full tax config for a year.",
                "parameters": [
                    {"name": "year", "type": "integer", "description": "Tax year (e.g. 2026)", "required": True, "default": None},
                ],
            },
            {
                "name": "upload-tax-brackets",
                "description": "Upload or replace tax config for a year. Pass config as JSON string (see docs/TAX-CONFIG.md).",
                "parameters": [
                    {"name": "config_json", "type": "string", "description": "JSON string of tax config (year, ss_wage_base, brackets, etc.)", "required": True, "default": None},
                ],
            },
        ],
    }


def _run(cmd: str, **kwargs: Any) -> Dict[str, Any]:
    client, err = _get_client()
    if err:
        return {"status": "error", "error": err}
    try:
        if cmd == "list-employees":
            out = client.list_employees(limit=int(kwargs.get("limit", 100)), offset=int(kwargs.get("offset", 0)))
        elif cmd == "get-employee":
            out = client.get_employee(int(kwargs["employee_id"]))
        elif cmd == "create-employee":
            out = client.create_employee(
                full_name=kwargs["full_name"],
                ssn=kwargs["ssn"],
                filing_status=kwargs["filing_status"],
                hire_date=kwargs["hire_date"],
                monthly_gross_salary=float(kwargs["monthly_gross_salary"]),
                address_line1=kwargs.get("address_line1") or None,
                city=kwargs.get("city") or None,
                state=kwargs.get("state") or None,
                zip=kwargs.get("zip") or None,
            )
        elif cmd == "update-employee":
            fid = int(kwargs.pop("employee_id"))
            fields = {k: v for k, v in kwargs.items() if k != "employee_id" and v is not None and v != ""}
            if "monthly_gross_salary" in fields:
                fields["monthly_gross_salary"] = float(fields["monthly_gross_salary"])
            out = client.update_employee(fid, **fields)
        elif cmd == "delete-employee":
            out = client.delete_employee(int(kwargs["employee_id"]))
        elif cmd == "list-payroll":
            params = {"limit": int(kwargs.get("limit", 100)), "offset": int(kwargs.get("offset", 0))}
            if kwargs.get("employee_id") is not None:
                params["employee_id"] = int(kwargs["employee_id"])
            if kwargs.get("pay_date_from"):
                params["pay_date_from"] = kwargs["pay_date_from"]
            if kwargs.get("pay_date_to"):
                params["pay_date_to"] = kwargs["pay_date_to"]
            out = client.list_payroll(**params)
        elif cmd == "get-payroll":
            out = client.get_payroll(int(kwargs["payroll_id"]))
        elif cmd == "run-payroll":
            payload = {
                "pay_period_start": kwargs["pay_period_start"],
                "pay_period_end": kwargs["pay_period_end"],
                "pay_date": kwargs["pay_date"],
            }
            if kwargs.get("employee_ids"):
                payload["employee_ids"] = [int(x) for x in kwargs["employee_ids"]]
            out = client.run_payroll(**payload)
        elif cmd == "list-tax-brackets":
            out = client.list_tax_brackets()
        elif cmd == "get-tax-brackets":
            out = client.get_tax_brackets(int(kwargs["year"]))
        elif cmd == "upload-tax-brackets":
            config = json.loads(kwargs["config_json"])
            out = client.upload_tax_brackets(config)
        else:
            return {"status": "error", "error": f"Unknown command: {cmd}"}
        return {"status": "success", "data": out}
    except PayrollAPIError as e:
        return {"status": "error", "error": str(e), "status_code": getattr(e, "status_code", None)}
    except Exception as e:
        return {"status": "error", "error": str(e)}


def main() -> None:
    parser = argparse.ArgumentParser(description="Payroll SMCP plugin – SDK-backed tools for Payroll API.")
    parser.add_argument("--describe", action="store_true", help="Output plugin description JSON")
    subparsers = parser.add_subparsers(dest="command", help="Commands")

    def add(name: str, **args):
        return subparsers.add_parser(name, **args)

    p = add("list-employees")
    p.add_argument("--limit", type=int, default=100)
    p.add_argument("--offset", type=int, default=0)
    add("get-employee").add_argument("--employee-id", type=int, dest="employee_id", required=True)
    p = add("create-employee")
    p.add_argument("--full-name", required=True, dest="full_name")
    p.add_argument("--ssn", required=True)
    p.add_argument("--filing-status", required=True, dest="filing_status")
    p.add_argument("--hire-date", required=True, dest="hire_date")
    p.add_argument("--monthly-gross-salary", type=float, required=True, dest="monthly_gross_salary")
    p.add_argument("--address-line1", dest="address_line1", default="")
    p.add_argument("--city", default="")
    p.add_argument("--state", default="")
    p.add_argument("--zip", default="")
    p = add("update-employee")
    p.add_argument("--employee-id", type=int, dest="employee_id", required=True)
    p.add_argument("--full-name", dest="full_name", default="")
    p.add_argument("--monthly-gross-salary", type=float, dest="monthly_gross_salary", default=None)
    p.add_argument("--address-line1", dest="address_line1", default="")
    p.add_argument("--city", default="")
    p.add_argument("--state", default="")
    p.add_argument("--zip", default="")
    add("delete-employee").add_argument("--employee-id", type=int, dest="employee_id", required=True)
    p = add("list-payroll")
    p.add_argument("--employee-id", type=int, dest="employee_id", default=None)
    p.add_argument("--pay-date-from", dest="pay_date_from", default="")
    p.add_argument("--pay-date-to", dest="pay_date_to", default="")
    p.add_argument("--limit", type=int, default=100)
    p.add_argument("--offset", type=int, default=0)
    add("get-payroll").add_argument("--payroll-id", type=int, dest="payroll_id", required=True)
    p = add("run-payroll")
    p.add_argument("--pay-period-start", required=True, dest="pay_period_start")
    p.add_argument("--pay-period-end", required=True, dest="pay_period_end")
    p.add_argument("--pay-date", required=True)
    p.add_argument("--employee-ids", dest="employee_ids", nargs="*", type=int, default=None)
    add("list-tax-brackets")
    p = add("get-tax-brackets")
    p.add_argument("--year", type=int, required=True)
    p = add("upload-tax-brackets")
    p.add_argument("--config-json", required=True, dest="config_json")

    args = parser.parse_args()

    if args.describe:
        print(json.dumps(get_plugin_description(), indent=2))
        sys.exit(0)

    if not args.command:
        parser.print_help()
        sys.exit(1)

    # Build kwargs from args (argparse namespace -> dict; only non-None and not empty default strings)
    kwargs = {}
    for k, v in vars(args).items():
        if k == "command" or v is None:
            continue
        if isinstance(v, str) and v == "" and args.command not in ("update-employee",):
            continue
        kwargs[k] = v

    result = _run(args.command, **kwargs)
    print(json.dumps(result))
    sys.exit(0 if result.get("status") == "success" else 1)


if __name__ == "__main__":
    main()
