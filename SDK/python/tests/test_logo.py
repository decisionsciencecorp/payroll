"""Tests for logo category: upload_logo (path + file-like), get_logo."""
import io
import tempfile
from pathlib import Path

from tests.conftest import make_json_response, make_binary_response


def test_upload_logo_file_path(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Logo uploaded"}
    )
    with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as f:
        f.write(b"\x89PNG\r\n\x1a\n")
        path = f.name
    try:
        out = client.upload_logo(path)
        assert out["success"] is True
        call = client._session.request.call_args
        assert call[0][0] == "POST"
        assert "/api/upload-logo.php" in call[0][1]
        assert call[1]["files"] is not None
        assert "logo" in call[1]["files"]
    finally:
        Path(path).unlink(missing_ok=True)


def test_upload_logo_file_like(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Logo uploaded"}
    )
    bio = io.BytesIO(b"fake-png-content")
    bio.name = "mylogo.png"
    out = client.upload_logo(bio)
    assert out["success"] is True
    assert client._session.request.call_args[1]["files"]["logo"][0] == "mylogo.png"


def test_upload_logo_file_like_no_name(client):
    client._session.request.return_value = make_json_response(
        {"success": True, "message": "Logo uploaded"}
    )
    client.upload_logo(io.BytesIO(b"x"))
    # default name when file-like has no .name
    assert "logo" in client._session.request.call_args[1]["files"]


def test_get_logo(client):
    client._session.request.return_value = make_binary_response(
        b"\x89PNG\r\n\x1a\nfake image",
        content_type="image/png",
    )
    out = client.get_logo()
    assert isinstance(out, bytes)
    assert out.startswith(b"\x89PNG")
    call = client._session.request.call_args
    assert call[0][0] == "GET"
    assert "/api/logo-file.php" in call[0][1]
