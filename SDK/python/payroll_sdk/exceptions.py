# Payroll - Copyright (C) 2026 Decision Science Corp. - Licensed under GNU AGPL v3.0. See LICENSE.


class PayrollAPIError(Exception):
    """Raised when the API returns an error (4xx/5xx or success=false)."""

    def __init__(self, message, status_code=None, response_body=None):
        self.message = message
        self.status_code = status_code
        self.response_body = response_body
        super().__init__(message)

    def __str__(self):
        if self.status_code is not None:
            return f"[{self.status_code}] {self.message}"
        return self.message
