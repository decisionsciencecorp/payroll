# Payroll - Copyright (C) 2026 Decision Science Corp. - Licensed under GNU AGPL v3.0. See LICENSE.

import os
from typing import Any, Dict, List, Optional, Union

import requests

from payroll_sdk.exceptions import PayrollAPIError


class PayrollClient:
    """
    Client for the Payroll REST API. All methods raise PayrollAPIError on API errors.
    """

    def __init__(self, base_url: str, api_key: str, timeout: float = 30.0):
        """
        :param base_url: Base URL of the Payroll app (e.g. https://payroll.example.com). No trailing slash.
        :param api_key: API key for X-API-Key header.
        :param timeout: Request timeout in seconds.
        """
        self.base_url = base_url.rstrip("/")
        self.api_key = api_key
        self.timeout = timeout
        self._session = requests.Session()
        self._session.headers["X-API-Key"] = api_key
        self._session.headers["Accept"] = "application/json"

    def _request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        json: Optional[Dict[str, Any]] = None,
        data: Optional[Any] = None,
        files: Optional[Dict[str, Any]] = None,
        expect_json: bool = True,
    ) -> Union[Dict[str, Any], bytes]:
        url = f"{self.base_url}{path}"
        resp = self._session.request(
            method,
            url,
            params=params,
            json=json,
            data=data,
            files=files,
            timeout=self.timeout,
        )
        if expect_json and "application/json" in (resp.headers.get("Content-Type") or ""):
            try:
                body = resp.json()
            except ValueError:
                body = {}
            if not resp.ok:
                msg = body.get("error", resp.text or resp.reason or f"HTTP {resp.status_code}")
                raise PayrollAPIError(msg, status_code=resp.status_code, response_body=body)
            if isinstance(body, dict) and body.get("success") is False:
                msg = body.get("error", "Unknown API error")
                raise PayrollAPIError(msg, status_code=resp.status_code, response_body=body)
            return body
        # Binary/HTML response (e.g. pay stub, W-2)
        if not resp.ok:
            raise PayrollAPIError(
                resp.text or resp.reason or f"HTTP {resp.status_code}",
                status_code=resp.status_code,
                response_body=resp.content,
            )
        return resp.content

    # --- Tax bracket configuration ---

    def upload_tax_brackets(self, config: Dict[str, Any]) -> Dict[str, Any]:
        """Upload or replace tax config for a year. Config must match docs/TAX-CONFIG.md."""
        return self._request("POST", "/api/upload-tax-brackets.php", json=config)

    def list_tax_brackets(self) -> Dict[str, Any]:
        """List tax years that have config. Returns { success, years, count }."""
        return self._request("GET", "/api/list-tax-brackets.php")

    def get_tax_brackets(self, year: int) -> Dict[str, Any]:
        """Get full tax config for a year. Returns { success, year, config }."""
        return self._request("GET", "/api/get-tax-brackets.php", params={"year": year})

    def delete_tax_brackets(self, year: int) -> Dict[str, Any]:
        """Delete tax config for a year."""
        return self._request("DELETE", "/api/delete-tax-brackets.php", params={"year": year})

    # --- Employees ---

    def create_employee(
        self,
        full_name: str,
        ssn: str,
        filing_status: str,
        hire_date: str,
        monthly_gross_salary: Union[int, float],
        step4a_other_income: Optional[Union[int, float]] = None,
        step4b_deductions: Optional[Union[int, float]] = None,
        step4c_extra_withholding: Optional[Union[int, float]] = None,
        i9_completed_at: Optional[str] = None,
        address_line1: Optional[str] = None,
        address_line2: Optional[str] = None,
        city: Optional[str] = None,
        state: Optional[str] = None,
        zip: Optional[str] = None,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        """
        Create an employee. filing_status: Single, Married filing jointly, Married filing separately, Head of Household.
        Dates as YYYY-MM-DD. Returns { success, message, employee }.
        """
        payload = {
            "full_name": full_name,
            "ssn": ssn,
            "filing_status": filing_status,
            "hire_date": hire_date,
            "monthly_gross_salary": monthly_gross_salary,
        }
        optional = {
            "step4a_other_income": step4a_other_income,
            "step4b_deductions": step4b_deductions,
            "step4c_extra_withholding": step4c_extra_withholding,
            "i9_completed_at": i9_completed_at,
            "address_line1": address_line1,
            "address_line2": address_line2,
            "city": city,
            "state": state,
            "zip": zip,
        }
        for k, v in optional.items():
            if v is not None:
                payload[k] = v
        payload.update(kwargs)
        return self._request("POST", "/api/create-employee.php", json=payload)

    def list_employees(self, limit: int = 100, offset: int = 0) -> Dict[str, Any]:
        """List employees (SSN masked). Returns { success, employees, count, total }."""
        return self._request(
            "GET",
            "/api/list-employees.php",
            params={"limit": limit, "offset": offset},
        )

    def get_employee(self, employee_id: int) -> Dict[str, Any]:
        """Get one employee by ID (full SSN). Returns { success, employee }."""
        return self._request("GET", "/api/get-employee.php", params={"id": employee_id})

    def update_employee(self, employee_id: int, **fields: Any) -> Dict[str, Any]:
        """Update employee. Pass id and any fields to update. Returns { success, message, employee }."""
        payload = {"id": employee_id, **fields}
        return self._request("POST", "/api/update-employee.php", json=payload)

    def delete_employee(self, employee_id: int) -> Dict[str, Any]:
        """Delete employee. Raises PayrollAPIError 409 if employee has payroll history."""
        return self._request("DELETE", "/api/delete-employee.php", params={"id": employee_id})

    # --- Payroll ---

    def run_payroll(
        self,
        pay_period_start: str,
        pay_period_end: str,
        pay_date: str,
        employee_ids: Optional[List[int]] = None,
    ) -> Dict[str, Any]:
        """
        Run payroll for a period. Dates as YYYY-MM-DD. Optional employee_ids to limit to specific employees.
        Returns { success, message, pay_period_start, pay_period_end, pay_date, records }.
        """
        payload = {
            "pay_period_start": pay_period_start,
            "pay_period_end": pay_period_end,
            "pay_date": pay_date,
        }
        if employee_ids is not None:
            payload["employee_ids"] = employee_ids
        return self._request("POST", "/api/run-payroll.php", json=payload)

    def list_payroll(
        self,
        employee_id: Optional[int] = None,
        pay_date_from: Optional[str] = None,
        pay_date_to: Optional[str] = None,
        limit: int = 100,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """List payroll records. Dates as YYYY-MM-DD. Returns { success, payroll, count, total }."""
        params = {"limit": limit, "offset": offset}
        if employee_id is not None:
            params["employee_id"] = employee_id
        if pay_date_from is not None:
            params["pay_date_from"] = pay_date_from
        if pay_date_to is not None:
            params["pay_date_to"] = pay_date_to
        return self._request("GET", "/api/list-payroll.php", params=params)

    def get_payroll(self, payroll_id: int) -> Dict[str, Any]:
        """Get one payroll record by id. Returns { success, payroll }."""
        return self._request("GET", "/api/get-payroll.php", params={"id": payroll_id})

    # --- Logo ---

    def upload_logo(self, file: Union[str, Any]) -> Dict[str, Any]:
        """
        Upload company logo (PNG or JPEG, max 2MB). file: path string or file-like object.
        Returns { success, message }.
        """
        if isinstance(file, str):
            f = open(file, "rb")
            try:
                name = os.path.basename(file)
                files = {"logo": (name, f, "image/png")}
                return self._request(
                    "POST",
                    "/api/upload-logo.php",
                    files=files,
                    expect_json=True,
                )
            finally:
                f.close()
        name = getattr(file, "name", "logo.png")
        name = os.path.basename(name) if isinstance(name, str) else "logo.png"
        files = {"logo": (name, file, "image/png")}
        return self._request("POST", "/api/upload-logo.php", files=files, expect_json=True)

    def get_logo(self) -> bytes:
        """
        Fetch the current company logo (PNG or JPEG). Returns raw bytes.
        Raises PayrollAPIError with status 404 if no logo has been uploaded.
        """
        return self._request(
            "GET",
            "/api/logo-file.php",
            expect_json=False,
        )

    # --- Pay stub (HTML) ---

    def get_pay_stub_html(self, payroll_id: int) -> bytes:
        """Fetch pay stub HTML for a payroll record. Use for printing to PDF. Returns raw bytes."""
        return self._request(
            "GET",
            "/api/pdf-stub.php",
            params={"id": payroll_id},
            expect_json=False,
        )

    # --- W-2 (HTML download) ---

    def get_w2_html(self, year: int) -> bytes:
        """Fetch W-2 HTML for a tax year (one section per employee). Returns raw bytes."""
        return self._request(
            "GET",
            "/api/generate-w2.php",
            params={"year": year},
            expect_json=False,
        )
