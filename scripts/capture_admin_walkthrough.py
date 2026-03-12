#!/usr/bin/env python3
"""
Capture screenshots of the Payroll admin UI for the visual walkthrough.
Uses Playwright (run from repo root with .venv-playwright).

Usage:
  PAYROLL_BASE_URL=https://payroll.decisionsciencecorp.com \\
  PAYROLL_PASSWORD=StableGen1us \\
  .venv-playwright/bin/python scripts/capture_admin_walkthrough.py

Screenshots are saved to docs/images/walkthrough/.
"""
import os
import sys
from pathlib import Path

# Repo root
REPO_ROOT = Path(__file__).resolve().parent.parent
OUT_DIR = REPO_ROOT / "docs" / "images" / "walkthrough"

# Pages to capture: (path, short name for filename)
ADMIN_PAGES = [
    ("/admin/login.php", "01-login"),
    ("/admin/index.php", "02-dashboard"),
    ("/admin/employees.php", "03-employees"),
    ("/admin/payroll.php", "04-payroll"),
    ("/admin/tax-config.php", "05-tax-config"),
    ("/admin/api-keys.php", "06-api-keys"),
    ("/admin/logo.php", "07-logo"),
    ("/admin/company-settings.php", "08-company"),
    ("/admin/w2.php", "09-w2"),
    ("/admin/users.php", "10-users"),
    ("/admin/change-password.php", "11-change-password"),
]


def main():
    base_url = (os.environ.get("PAYROLL_BASE_URL") or "").strip().rstrip("/")
    password = os.environ.get("PAYROLL_PASSWORD", "")
    if not base_url:
        print("Set PAYROLL_BASE_URL (e.g. https://payroll.decisionsciencecorp.com)", file=sys.stderr)
        sys.exit(1)
    if not password:
        print("Set PAYROLL_PASSWORD for admin login", file=sys.stderr)
        sys.exit(1)

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    from playwright.sync_api import sync_playwright

    viewport = {"width": 1280, "height": 720}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport=viewport)
        context.set_default_timeout(15000)
        page = context.new_page()

        # 1) Login page (before login)
        login_url = base_url + "/admin/login.php"
        page.goto(login_url, wait_until="networkidle")
        page.screenshot(path=OUT_DIR / "01-login.png", full_page=False)
        print("Saved 01-login.png")

        # 2) Log in
        page.fill('input[name="username"]', "admin")
        page.fill('input[name="password"]', password)
        page.click('button[type="submit"]')
        page.wait_for_url(lambda url: "index.php" in url or "login" not in url, timeout=10000)
        page.wait_for_load_state("networkidle")
        page.screenshot(path=OUT_DIR / "02-dashboard.png", full_page=False)
        print("Saved 02-dashboard.png")

        # 3) Rest of admin pages (already logged in)
        for path, name in ADMIN_PAGES[2:]:
            url = base_url + path
            page.goto(url, wait_until="networkidle")
            page.screenshot(path=OUT_DIR / f"{name}.png", full_page=False)
            print(f"Saved {name}.png")

        browser.close()

    print(f"Done. Screenshots in {OUT_DIR}")


if __name__ == "__main__":
    main()
