"""Pytest fixtures for smcp_plugin tests."""
import json
import sys
from pathlib import Path

import pytest


def pytest_configure(config):
    config.addinivalue_line(
        "filterwarnings",
        "ignore:.*smcp_plugin.cli.*:RuntimeWarning",
    )

# Ensure plugin and SDK are importable
_plugin_root = Path(__file__).resolve().parent.parent
_repo_root = _plugin_root.parent
_sdk_path = _repo_root / "SDK" / "python"
if _sdk_path.is_dir() and str(_sdk_path) not in sys.path:
    sys.path.insert(0, str(_sdk_path))


@pytest.fixture(autouse=True)
def clean_env(monkeypatch):
    """Clear payroll env vars so tests set them explicitly."""
    monkeypatch.delenv("PAYROLL_BASE_URL", raising=False)
    monkeypatch.delenv("PAYROLL_API_KEY", raising=False)
