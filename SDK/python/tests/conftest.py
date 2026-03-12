# Payroll SDK tests - fixtures and shared helpers.
import pytest
from unittest.mock import Mock, patch

from payroll_sdk import PayrollClient


@pytest.fixture
def client():
    """PayrollClient with mocked session.request so no real HTTP calls are made."""
    c = PayrollClient("https://payroll.example.com", "test-api-key")
    c._session.request = Mock()
    return c


def make_json_response(body, status_code=200):
    """Build a mock response with application/json and optional status."""
    resp = Mock()
    resp.ok = 200 <= status_code < 300
    resp.status_code = status_code
    resp.headers = {"Content-Type": "application/json"}
    resp.text = ""
    resp.reason = "OK" if resp.ok else "Error"
    resp.content = b""
    resp.json = lambda: body
    return resp


def make_binary_response(content, status_code=200, content_type="text/html"):
    """Build a mock response for binary/HTML body."""
    resp = Mock()
    resp.ok = 200 <= status_code < 300
    resp.status_code = status_code
    resp.headers = {"Content-Type": content_type}
    resp.content = content if isinstance(content, bytes) else content.encode()
    resp.text = resp.content.decode("utf-8", errors="replace")
    resp.reason = "OK" if resp.ok else "Error"

    def _json():
        raise ValueError("not json")

    resp.json = _json
    return resp
