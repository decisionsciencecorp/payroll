"""Tests for payroll category: run_payroll, list_payroll, get_payroll."""
from tests.conftest import make_json_response


def test_run_payroll(client):
    client._session.request.return_value = make_json_response(
        {
            "success": True,
            "message": "Payroll run complete",
            "pay_period_start": "2026-01-01",
            "pay_period_end": "2026-01-31",
            "pay_date": "2026-01-31",
            "records": 3,
        }
    )
    out = client.run_payroll(
        pay_period_start="2026-01-01",
        pay_period_end="2026-01-31",
        pay_date="2026-01-31",
    )
    assert out["success"] is True
    assert out["records"] == 3
    payload = client._session.request.call_args[1]["json"]
    assert payload["pay_period_start"] == "2026-01-01"
    assert payload["pay_period_end"] == "2026-01-31"
    assert payload["pay_date"] == "2026-01-31"


def test_run_payroll_with_employee_ids(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "records": 2}
    )
    client.run_payroll(
        pay_period_start="2026-02-01",
        pay_period_end="2026-02-28",
        pay_date="2026-02-28",
        employee_ids=[1, 2],
    )
    payload = client._session.request.call_args[1]["json"]
    assert payload["employee_ids"] == [1, 2]


def test_list_payroll(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "payroll": [{"id": 1, "pay_date": "2026-01-31"}], "count": 1, "total": 1}
    )
    out = client.list_payroll(
        employee_id=1,
        pay_date_from="2026-01-01",
        pay_date_to="2026-12-31",
        limit=50,
        offset=0,
    )
    assert out["success"] is True
    params = client._session.request.call_args[1]["params"]
    assert params["employee_id"] == 1
    assert params["pay_date_from"] == "2026-01-01"
    assert params["pay_date_to"] == "2026-12-31"
    assert params["limit"] == 50
    assert params["offset"] == 0


def test_list_payroll_default_params(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "payroll": [], "count": 0, "total": 0}
    )
    client.list_payroll()
    params = client._session.request.call_args[1]["params"]
    assert params == {"limit": 100, "offset": 0}


def test_get_payroll(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "payroll": {"id": 1, "employee_id": 1, "gross_pay": 5000}}
    )
    out = client.get_payroll(1)
    assert out["payroll"]["id"] == 1
    assert client._session.request.call_args[1]["params"] == {"id": 1}
