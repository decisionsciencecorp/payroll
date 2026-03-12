"""Tests for tax config category: upload_tax_brackets, list_tax_brackets, get_tax_brackets, delete_tax_brackets."""
from tests.conftest import make_json_response


def test_upload_tax_brackets(client):
    config = {
        "year": 2026,
        "ss_wage_base": 184500,
        "fica_ss_rate": 0.062,
        "fica_medicare_rate": 0.0145,
        "brackets": {"single": [], "married": [], "head_of_household": []},
    }
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Tax config saved"}
    )
    out = client.upload_tax_brackets(config)
    assert out["success"] is True
    assert out["message"] == "Tax config saved"
    client._session.request.assert_called_once()
    call = client._session.request.call_args
    assert call[0][0] == "POST"
    assert call[1]["json"] == config
    assert "/api/upload-tax-brackets.php" in call[0][1]


def test_list_tax_brackets(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "years": [2025, 2026], "count": 2}
    )
    out = client.list_tax_brackets()
    assert out["success"] is True
    assert out["years"] == [2025, 2026]
    assert out["count"] == 2
    client._session.request.assert_called_once_with(
        "GET",
        "https://payroll.example.com/api/list-tax-brackets.php",
        params=None,
        json=None,
        data=None,
        files=None,
        timeout=30.0,
    )


def test_get_tax_brackets(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "year": 2026, "config": {"ss_wage_base": 184500}}
    )
    out = client.get_tax_brackets(2026)
    assert out["success"] is True
    assert out["year"] == 2026
    assert out["config"]["ss_wage_base"] == 184500
    client._session.request.assert_called_once()
    assert client._session.request.call_args[1]["params"] == {"year": 2026}


def test_delete_tax_brackets(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Deleted"}
    )
    out = client.delete_tax_brackets(2025)
    assert out["success"] is True
    client._session.request.assert_called_once()
    assert client._session.request.call_args[0][0] == "DELETE"
    assert client._session.request.call_args[1]["params"] == {"year": 2025}
