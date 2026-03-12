"""Tests for employees category: create_employee, list_employees, get_employee, update_employee, delete_employee."""
import pytest
from tests.conftest import make_json_response


def test_create_employee_minimal(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Employee created", "employee": {"id": 1, "full_name": "Jane Doe"}}
    )
    out = client.create_employee(
        full_name="Jane Doe",
        ssn="123-45-6789",
        filing_status="Single",
        hire_date="2026-01-01",
        monthly_gross_salary=5000.00,
    )
    assert out["success"] is True
    assert out["employee"]["full_name"] == "Jane Doe"
    call = client._session.request.call_args
    assert call[0][0] == "POST"
    payload = call[1]["json"]
    assert payload["full_name"] == "Jane Doe"
    assert payload["ssn"] == "123-45-6789"
    assert payload["filing_status"] == "Single"
    assert payload["hire_date"] == "2026-01-01"
    assert payload["monthly_gross_salary"] == 5000.0


def test_create_employee_with_optionals(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Employee created", "employee": {"id": 2}}
    )
    client.create_employee(
        full_name="John Smith",
        ssn="987654321",
        filing_status="Married filing jointly",
        hire_date="2025-06-15",
        monthly_gross_salary=6000,
        address_line1="123 Main St",
        city="Boston",
        state="MA",
        zip="02101",
        step4c_extra_withholding=50.0,
    )
    payload = client._session.request.call_args[1]["json"]
    assert payload["address_line1"] == "123 Main St"
    assert payload["city"] == "Boston"
    assert payload["state"] == "MA"
    assert payload["zip"] == "02101"
    assert payload["step4c_extra_withholding"] == 50.0


def test_list_employees(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "employees": [{"id": 1, "full_name": "Jane"}], "count": 1, "total": 1}
    )
    out = client.list_employees(limit=50, offset=0)
    assert out["success"] is True
    assert len(out["employees"]) == 1
    assert client._session.request.call_args[1]["params"] == {"limit": 50, "offset": 0}


def test_list_employees_default_params(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "employees": [], "count": 0, "total": 0}
    )
    client.list_employees()
    assert client._session.request.call_args[1]["params"] == {"limit": 100, "offset": 0}


def test_get_employee(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "employee": {"id": 1, "full_name": "Jane", "ssn": "123456789"}}
    )
    out = client.get_employee(1)
    assert out["employee"]["id"] == 1
    assert client._session.request.call_args[1]["params"] == {"id": 1}


def test_update_employee(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Employee updated", "employee": {"id": 1, "full_name": "Jane Updated"}}
    )
    out = client.update_employee(1, full_name="Jane Updated", monthly_gross_salary=5500)
    assert out["success"] is True
    payload = client._session.request.call_args[1]["json"]
    assert payload["id"] == 1
    assert payload["full_name"] == "Jane Updated"
    assert payload["monthly_gross_salary"] == 5500


def test_delete_employee(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Employee deleted"}
    )
    out = client.delete_employee(2)
    assert out["success"] is True
    assert client._session.request.call_args[0][0] == "DELETE"
    assert client._session.request.call_args[1]["params"] == {"id": 2}
