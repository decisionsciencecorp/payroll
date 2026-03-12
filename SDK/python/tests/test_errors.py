"""Tests for API error handling: PayrollAPIError on HTTP error, success=false, binary error."""
import pytest
from tests.conftest import make_json_response, make_binary_response


def test_json_error_raises_payroll_api_error(client):
    from payroll_sdk.exceptions import PayrollAPIError

    client._session.request.return_value = make_json_response(
        {"success": False, "error": "Invalid year"},
        status_code=400,
    )
    with pytest.raises(PayrollAPIError) as exc_info:
        client.get_tax_brackets(1999)
    assert exc_info.value.message == "Invalid year"
    assert exc_info.value.status_code == 400


def test_http_error_uses_body_error_message(client):
    from payroll_sdk.exceptions import PayrollAPIError

    client._session.request.return_value = make_json_response(
        {"error": "Employee not found"},
        status_code=404,
    )
    with pytest.raises(PayrollAPIError) as exc_info:
        client.get_employee(999)
    assert "not found" in exc_info.value.message.lower() or exc_info.value.message == "Employee not found"
    assert exc_info.value.status_code == 404


def test_binary_response_error_raises(client):
    from payroll_sdk.exceptions import PayrollAPIError

    client._session.request.return_value = make_binary_response(
        b"Not found",
        status_code=404,
    )
    with pytest.raises(PayrollAPIError) as exc_info:
        client.get_pay_stub_html(999)
    assert exc_info.value.status_code == 404


def test_payroll_api_error_str_with_status(client):
    from payroll_sdk.exceptions import PayrollAPIError

    err = PayrollAPIError("Not found", status_code=404, response_body=None)
    assert str(err) == "[404] Not found"


def test_payroll_api_error_str_without_status(client):
    from payroll_sdk.exceptions import PayrollAPIError

    err = PayrollAPIError("Bad request")
    assert str(err) == "Bad request"


def test_success_false_raises(client):
    from payroll_sdk.exceptions import PayrollAPIError

    client._session.request.return_value = make_json_response(
        {"success": False, "error": "Cannot delete employee with payroll history"},
        status_code=409,
    )
    with pytest.raises(PayrollAPIError) as exc_info:
        client.delete_employee(1)
    assert "payroll" in exc_info.value.message.lower()
    assert exc_info.value.status_code == 409


def test_success_false_no_error_key_uses_unknown_message(client):
    """Covers body.get('error', 'Unknown API error') when error key missing (HTTP 200 so we hit success=false branch)."""
    from payroll_sdk.exceptions import PayrollAPIError

    client._session.request.return_value = make_json_response(
        {"success": False},
        status_code=200,
    )
    with pytest.raises(PayrollAPIError) as exc_info:
        client.list_tax_brackets()
    assert exc_info.value.message == "Unknown API error"


def test_json_parse_error_falls_back_to_empty_body(client):
    """Covers except ValueError: body = {}; then client returns {} (body.get('success') is False is identity, so no raise)."""
    from unittest.mock import Mock

    resp = Mock()
    resp.ok = True
    resp.status_code = 200
    resp.headers = {"Content-Type": "application/json"}
    resp.text = ""
    resp.reason = "OK"
    resp.content = b""
    resp.json.side_effect = ValueError("invalid json")
    client._session.request.return_value = resp
    out = client.list_tax_brackets()
    assert out == {}
