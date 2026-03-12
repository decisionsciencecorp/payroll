"""Tests for client init and base_url normalization."""
from unittest.mock import Mock, patch

from payroll_sdk import PayrollClient


def test_client_strips_trailing_slash():
    with patch("payroll_sdk.client.requests.Session") as Session:
        session_instance = Mock()
        session_instance.headers = {}
        Session.return_value = session_instance
        client = PayrollClient("https://api.example.com/", "key")
        assert client.base_url == "https://api.example.com"


def test_client_sets_headers():
    with patch("payroll_sdk.client.requests.Session") as Session:
        session_instance = Mock()
        session_instance.headers = {}
        Session.return_value = session_instance
        client = PayrollClient("https://api.example.com", "my-key")
        assert session_instance.headers["X-API-Key"] == "my-key"
        assert session_instance.headers["Accept"] == "application/json"
