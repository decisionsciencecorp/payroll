"""Tests for outputs category: get_pay_stub_html, get_w2_html."""
from tests.conftest import make_binary_response


def test_get_pay_stub_html(client):
    html = b"<html><body>Pay stub for #1</body></html>"
    client._session.request.return_value = make_binary_response(
        html,
        content_type="text/html",
    )
    out = client.get_pay_stub_html(1)
    assert out == html
    assert isinstance(out, bytes)
    call = client._session.request.call_args
    assert call[0][0] == "GET"
    assert "/api/pdf-stub.php" in call[0][1]
    assert call[1]["params"] == {"id": 1}


def test_get_w2_html(client):
    html = b"<html><body>W-2 2026</body></html>"
    client._session.request.return_value = make_binary_response(
        html,
        content_type="text/html",
    )
    out = client.get_w2_html(2026)
    assert out == html
    assert isinstance(out, bytes)
    call = client._session.request.call_args
    assert call[0][0] == "GET"
    assert "/api/generate-w2.php" in call[0][1]
    assert call[1]["params"] == {"year": 2026}
