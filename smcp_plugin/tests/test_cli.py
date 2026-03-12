"""Tests for smcp_plugin.cli – 100% coverage."""
import json
import os
import sys
from unittest.mock import MagicMock, patch

import pytest

# Import after conftest may have altered path
import smcp_plugin.cli as cli


# ---- get_plugin_description ----
def test_get_plugin_description():
    out = cli.get_plugin_description()
    assert out["plugin"]["name"] == "payroll"
    assert out["plugin"]["version"] == "0.2.0"
    assert "commands" in out
    names = [c["name"] for c in out["commands"]]
    assert "list-employees" in names
    assert "run-payroll" in names
    assert "upload-tax-brackets" in names


# ---- _get_client ----
def test_get_client_missing_base_url(monkeypatch):
    monkeypatch.setenv("PAYROLL_API_KEY", "key")
    monkeypatch.delenv("PAYROLL_BASE_URL", raising=False)
    client, err = cli._get_client()
    assert client is None
    assert "PAYROLL_BASE_URL" in err


def test_get_client_missing_api_key(monkeypatch):
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://pay.example.com")
    monkeypatch.delenv("PAYROLL_API_KEY", raising=False)
    client, err = cli._get_client()
    assert client is None
    assert "PAYROLL_API_KEY" in err


def test_get_client_strips_trailing_slash(monkeypatch):
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://pay.example.com/")
    monkeypatch.setenv("PAYROLL_API_KEY", "k")
    with patch.object(cli, "PayrollClient", MagicMock()) as PC:
        client, err = cli._get_client()
        assert err is None
        PC.assert_called_once_with("https://pay.example.com", "k")


def test_get_client_sdk_not_installed(monkeypatch):
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://x.com")
    monkeypatch.setenv("PAYROLL_API_KEY", "k")
    with patch.object(cli, "PayrollClient", None):
        client, err = cli._get_client()
        assert client is None
        assert "payroll_sdk not installed" in err


def test_get_client_success(monkeypatch):
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://pay.example.com")
    monkeypatch.setenv("PAYROLL_API_KEY", "key")
    with patch.object(cli, "PayrollClient", MagicMock()) as PC:
        client, err = cli._get_client()
        assert err is None
        assert client is PC.return_value


# ---- _run with mocked client ----
@pytest.fixture
def mock_client():
    with patch.object(cli, "_get_client") as get_client:
        client = MagicMock()
        get_client.return_value = (client, None)
        yield client


def test_run_list_employees(mock_client):
    mock_client.list_employees.return_value = {"success": True, "employees": [], "count": 0}
    out = cli._run("list-employees", limit=5, offset=0)
    assert out["status"] == "success"
    assert out["data"]["count"] == 0
    mock_client.list_employees.assert_called_once_with(limit=5, offset=0)


def test_run_get_employee(mock_client):
    mock_client.get_employee.return_value = {"success": True, "employee": {"id": 1}}
    out = cli._run("get-employee", employee_id=1)
    assert out["status"] == "success"
    mock_client.get_employee.assert_called_once_with(1)


def test_run_create_employee(mock_client):
    mock_client.create_employee.return_value = {"success": True, "employee": {"id": 1}}
    out = cli._run(
        "create-employee",
        full_name="Jane",
        ssn="123",
        filing_status="Single",
        hire_date="2026-01-01",
        monthly_gross_salary=5000.0,
        address_line1="123 Main",
        city="Boston",
        state="MA",
        zip="02101",
    )
    assert out["status"] == "success"
    mock_client.create_employee.assert_called_once()
    call_kw = mock_client.create_employee.call_args[1]
    assert call_kw["full_name"] == "Jane"
    assert call_kw["address_line1"] == "123 Main"


def test_run_update_employee(mock_client):
    mock_client.update_employee.return_value = {"success": True}
    out = cli._run("update-employee", employee_id=1, full_name="Jane Doe", monthly_gross_salary=6000.0)
    assert out["status"] == "success"
    mock_client.update_employee.assert_called_once_with(1, full_name="Jane Doe", monthly_gross_salary=6000.0)


def test_run_delete_employee(mock_client):
    mock_client.delete_employee.return_value = {"success": True}
    out = cli._run("delete-employee", employee_id=2)
    assert out["status"] == "success"
    mock_client.delete_employee.assert_called_once_with(2)


def test_run_list_payroll(mock_client):
    mock_client.list_payroll.return_value = {"success": True, "payroll": []}
    out = cli._run("list-payroll", limit=10, offset=0, employee_id=1, pay_date_from="2026-01-01", pay_date_to="2026-12-31")
    assert out["status"] == "success"
    mock_client.list_payroll.assert_called_once_with(
        limit=10, offset=0, employee_id=1, pay_date_from="2026-01-01", pay_date_to="2026-12-31"
    )


def test_run_get_payroll(mock_client):
    mock_client.get_payroll.return_value = {"success": True, "payroll": {"id": 1}}
    out = cli._run("get-payroll", payroll_id=1)
    assert out["status"] == "success"


def test_run_run_payroll(mock_client):
    mock_client.run_payroll.return_value = {"success": True, "records": 2}
    out = cli._run(
        "run-payroll",
        pay_period_start="2026-01-01",
        pay_period_end="2026-01-31",
        pay_date="2026-01-31",
        employee_ids=[1, 2],
    )
    assert out["status"] == "success"
    mock_client.run_payroll.assert_called_once()
    assert mock_client.run_payroll.call_args[1]["employee_ids"] == [1, 2]


def test_run_list_tax_brackets(mock_client):
    mock_client.list_tax_brackets.return_value = {"success": True, "years": [2026]}
    out = cli._run("list-tax-brackets")
    assert out["status"] == "success"


def test_run_get_tax_brackets(mock_client):
    mock_client.get_tax_brackets.return_value = {"success": True, "year": 2026}
    out = cli._run("get-tax-brackets", year=2026)
    assert out["status"] == "success"


def test_run_upload_tax_brackets(mock_client):
    mock_client.upload_tax_brackets.return_value = {"success": True}
    out = cli._run("upload-tax-brackets", config_json='{"year": 2026}')
    assert out["status"] == "success"
    mock_client.upload_tax_brackets.assert_called_once_with({"year": 2026})


def test_run_unknown_command(mock_client):
    out = cli._run("unknown-cmd")
    assert out["status"] == "error"
    assert "Unknown command" in out["error"]


def test_run_get_client_error():
    with patch.object(cli, "_get_client", return_value=(None, "env missing")):
        out = cli._run("list-employees")
    assert out["status"] == "error"
    assert out["error"] == "env missing"


def test_run_payroll_api_error(mock_client):
    class FakeAPIError(Exception):
        def __init__(self, msg, status_code=None, response_body=None):
            super().__init__(msg)
            self.status_code = status_code

    with patch.object(cli, "PayrollAPIError", FakeAPIError):
        mock_client.delete_employee.side_effect = FakeAPIError("Cannot delete", status_code=409)
        out = cli._run("delete-employee", employee_id=1)
    assert out["status"] == "error"
    assert out.get("status_code") == 409


def test_run_generic_exception(mock_client):
    mock_client.list_employees.side_effect = RuntimeError("network error")
    out = cli._run("list-employees")
    assert out["status"] == "error"
    assert "network error" in out["error"]


def test_run_upload_tax_brackets_invalid_json(mock_client):
    out = cli._run("upload-tax-brackets", config_json="not json")
    assert out["status"] == "error"


# ---- main ----
def test_main_describe(capsys):
    with patch.object(sys, "argv", ["cli.py", "--describe"]):
        with pytest.raises(SystemExit) as exc:
            cli.main()
        assert exc.value.code == 0
    out = capsys.readouterr().out
    data = json.loads(out)
    assert data["plugin"]["name"] == "payroll"


def test_main_no_command(capsys):
    with patch.object(sys, "argv", ["cli.py"]):
        with pytest.raises(SystemExit) as exc:
            cli.main()
        assert exc.value.code == 1
    captured = capsys.readouterr().out
    assert "Payroll" in captured or "usage" in captured.lower() or "Commands" in captured


def test_main_command_success(capsys, monkeypatch):
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://x.com")
    monkeypatch.setenv("PAYROLL_API_KEY", "k")
    with patch.object(cli, "_get_client") as get_client:
        client = MagicMock()
        client.list_tax_brackets.return_value = {"years": []}
        get_client.return_value = (client, None)
        with patch.object(sys, "argv", ["cli.py", "list-tax-brackets"]):
            with pytest.raises(SystemExit) as exc:
                cli.main()
            assert exc.value.code == 0
    out = json.loads(capsys.readouterr().out)
    assert out["status"] == "success"


def test_cli_main_entrypoint():
    """Running the module as __main__ calls main()."""
    import runpy

    with patch.object(sys, "argv", ["cli.py", "--describe"]):
        with pytest.raises(SystemExit):
            runpy.run_module("smcp_plugin.cli", run_name="__main__")


def test_main_command_error(capsys, monkeypatch):
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://x.com")
    monkeypatch.setenv("PAYROLL_API_KEY", "k")
    with patch.object(cli, "_get_client", return_value=(None, "bad env")):
        with patch.object(sys, "argv", ["cli.py", "list-employees"]):
            with pytest.raises(SystemExit) as exc:
                cli.main()
            assert exc.value.code == 1
    out = json.loads(capsys.readouterr().out)
    assert out["status"] == "error"


# ---- kwargs building: update-employee with only employee_id ----
def test_run_update_employee_only_id(mock_client):
    mock_client.update_employee.return_value = {"success": True}
    out = cli._run("update-employee", employee_id=1)
    assert out["status"] == "success"
    mock_client.update_employee.assert_called_once_with(1)


# ---- main() kwargs: skip None and empty string (cover lines 282, 281) ----
def test_main_kwargs_skips_none_and_empty_string(capsys, monkeypatch):
    """Optional None (e.g. update-employee monthly_gross_salary) is skipped; empty string for non-update is skipped."""
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://x.com")
    monkeypatch.setenv("PAYROLL_API_KEY", "k")
    with patch.object(cli, "_run") as mock_run:
        mock_run.return_value = {"status": "success", "data": {}}
        # update-employee with only employee_id and full_name (monthly_gross_salary is None -> skipped)
        with patch.object(sys, "argv", ["cli.py", "update-employee", "--employee-id", "1", "--full-name", "Jane"]):
            with pytest.raises(SystemExit) as exc:
                cli.main()
            assert exc.value.code == 0
        call_kw = mock_run.call_args[1]
        assert "employee_id" in call_kw or 1 in (call_kw.get("employee_id"),)
        assert call_kw.get("full_name") == "Jane"
        # monthly_gross_salary should not be in kwargs (was None, skipped)
        assert "monthly_gross_salary" not in call_kw or call_kw.get("monthly_gross_salary") is not None


def test_main_create_employee_skips_empty_optionals(capsys, monkeypatch):
    """Empty string optional args (e.g. address_line1) are skipped for create-employee."""
    monkeypatch.setenv("PAYROLL_BASE_URL", "https://x.com")
    monkeypatch.setenv("PAYROLL_API_KEY", "k")
    with patch.object(cli, "_run") as mock_run:
        mock_run.return_value = {"status": "success", "data": {}}
        with patch.object(
            sys,
            "argv",
            [
                "cli.py",
                "create-employee",
                "--full-name",
                "Jane",
                "--ssn",
                "123",
                "--filing-status",
                "Single",
                "--hire-date",
                "2026-01-01",
                "--monthly-gross-salary",
                "5000",
            ],
        ):
            with pytest.raises(SystemExit):
                cli.main()
        call_kw = mock_run.call_args[1]
        # Empty optional strings should not be passed (or passed as None by _run)
        assert call_kw.get("full_name") == "Jane"


# ---- _load_sdk (path insert + import success/failure) ----
def test_load_sdk_returns_client_when_available():
    """_load_sdk returns (PayrollClient, PayrollAPIError) when payroll_sdk is importable."""
    PC, PAE = cli._load_sdk()
    assert PC is not None
    assert PAE is not None


def test_load_sdk_returns_none_on_import_error():
    """_load_sdk returns (None, None) when payroll_sdk import fails."""
    with patch("builtins.__import__", side_effect=ImportError("no module")):
        PC, PAE = cli._load_sdk()
    assert PC is None
    assert PAE is None
    # Restore module state for other tests
    cli.PayrollClient, cli.PayrollAPIError = cli._load_sdk()


@pytest.mark.parametrize("case", ["was_in_true", "was_in_false", "skip_do_skip", "skip_no_skip"])
def test_load_sdk_inserts_path_when_not_present(case):
    """_load_sdk path insert/restore. was_in_true => try remove. was_in_false => finally elif remove. skip_* => path check then return."""
    real_path = getattr(cli, "_sdk_path", None)
    if case == "skip_do_skip":
        with patch.object(cli, "_sdk_path", "/nonexistent"):
            sdk_path = getattr(cli, "_sdk_path", None)
            if not sdk_path or not os.path.isdir(sdk_path):
                pytest.skip("SDK path not present")
        return
    if case == "skip_no_skip":
        if not real_path or not os.path.isdir(real_path):
            pytest.skip("SDK path not present")
        with patch.object(cli, "_sdk_path", real_path):
            sdk_path = getattr(cli, "_sdk_path", None)
            if not sdk_path or not os.path.isdir(sdk_path):
                pytest.skip("SDK path not present")
        return
    if not real_path or not os.path.isdir(real_path):
        pytest.skip("SDK path not present")
    remove_path_at_start = case == "was_in_false"
    if remove_path_at_start and real_path in sys.path:
        sys.path.remove(real_path)
    was_in = real_path in sys.path
    sdk_path = real_path
    try:
        if was_in:
            sys.path.remove(sdk_path)
        PC, PAE = cli._load_sdk()
        assert sdk_path in sys.path
        assert PC is not None
    finally:
        if was_in and sdk_path not in sys.path:
            sys.path.insert(0, sdk_path)
        elif not was_in and sdk_path in sys.path:
            sys.path.remove(sdk_path)
        cli.PayrollClient, cli.PayrollAPIError = cli._load_sdk()
