<?php
// Database connection and schema. Loaded by config.php so DB_PATH, PASSWORD_COST, etc. are defined.

function getDbConnection() {
    try {
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        $db->busyTimeout(DB_TIMEOUT * 1000);
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    } catch (Exception $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Service unavailable.');
    }
}

function initializeDatabase() {
    $db = getDbConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_name TEXT NOT NULL,
            api_key TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_rate_limits (
            rate_key TEXT PRIMARY KEY,
            window_start INTEGER NOT NULL,
            count INTEGER NOT NULL
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            ssn TEXT NOT NULL,
            filing_status TEXT NOT NULL CHECK(filing_status IN ('Single','Married filing jointly','Married filing separately','Head of Household')),
            step4a_other_income REAL,
            step4b_deductions REAL,
            step4c_extra_withholding REAL,
            hire_date TEXT NOT NULL,
            monthly_gross_salary REAL NOT NULL,
            i9_completed_at TEXT,
            address_line1 TEXT,
            address_line2 TEXT,
            city TEXT,
            state TEXT,
            zip TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS payroll_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL REFERENCES employees(id),
            pay_period_start TEXT NOT NULL,
            pay_period_end TEXT NOT NULL,
            pay_date TEXT NOT NULL,
            gross_pay REAL NOT NULL,
            federal_withholding REAL NOT NULL,
            employee_ss REAL NOT NULL,
            employee_medicare REAL NOT NULL,
            employer_ss REAL NOT NULL,
            employer_medicare REAL NOT NULL,
            net_pay REAL NOT NULL,
            ytd_gross REAL NOT NULL,
            ytd_federal_withheld REAL NOT NULL,
            ytd_ss REAL NOT NULL,
            ytd_medicare REAL NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_payroll_employee_id ON payroll_history(employee_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_payroll_pay_date ON payroll_history(pay_date)");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_payroll_employee_date ON payroll_history(employee_id, pay_date)");

    $db->exec("
        CREATE TABLE IF NOT EXISTS tax_config (
            tax_year INTEGER PRIMARY KEY,
            config_json TEXT NOT NULL
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS company_settings (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            logo_path TEXT,
            site_url TEXT,
            employer_name TEXT,
            employer_ein TEXT,
            employer_address_line1 TEXT,
            employer_address_line2 TEXT,
            employer_city TEXT,
            employer_state TEXT,
            employer_zip TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("INSERT OR IGNORE INTO company_settings (id) VALUES (1)");
    try {
        $db->exec('ALTER TABLE company_settings ADD COLUMN site_url TEXT');
    } catch (Exception $e) {
        // Column already exists (existing install)
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            first_login_done INTEGER NOT NULL DEFAULT 0
        )
    ");
    try {
        $db->exec('ALTER TABLE admin_users ADD COLUMN first_login_done INTEGER NOT NULL DEFAULT 0');
    } catch (Exception $e) {
        // Column already exists (existing install)
    }

    $r = $db->query("SELECT COUNT(*) as c FROM admin_users");
    $row = $r->fetchArray(SQLITE3_ASSOC);
    if ($row && $row['c'] == 0) {
        $hash = password_hash('admin', PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash) VALUES ('admin', :h)");
        $stmt->bindValue(':h', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
}
