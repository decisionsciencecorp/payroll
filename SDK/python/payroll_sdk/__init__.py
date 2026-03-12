# Payroll - Copyright (C) 2026 Decision Science Corp. - Licensed under GNU AGPL v3.0. See LICENSE.
"""Python SDK for the Payroll REST API."""

from payroll_sdk.client import PayrollClient
from payroll_sdk.exceptions import PayrollAPIError

__all__ = ["PayrollClient", "PayrollAPIError"]
__version__ = "0.4.0"
