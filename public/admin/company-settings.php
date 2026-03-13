<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

function validateEin($ein) {
    $digits = preg_replace('/\D/', '', $ein);
    return strlen($digits) === 9;
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['employer_name'] ?? '');
    $ein = trim($_POST['employer_ein'] ?? '');
    if (empty($name)) {
        $error = 'Employer name required';
    } elseif (!validateEin($ein)) {
        $error = 'EIN must be 9 digits (XX-XXXXXXX)';
    } else {
        $db = getDbConnection();
        $a1 = substr(trim($_POST['employer_address_line1'] ?? ''), 0, 255);
        $a2 = substr(trim($_POST['employer_address_line2'] ?? ''), 0, 255);
        $city = substr(trim($_POST['employer_city'] ?? ''), 0, 100);
        $state = substr(trim($_POST['employer_state'] ?? ''), 0, 50);
        $zip = substr(trim($_POST['employer_zip'] ?? ''), 0, 20);
        $stmt = $db->prepare("UPDATE company_settings SET employer_name = :n, employer_ein = :e, employer_address_line1 = :a1, employer_address_line2 = :a2, employer_city = :city, employer_state = :state, employer_zip = :zip, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
        $stmt->bindValue(':n', substr($name, 0, 255), SQLITE3_TEXT);
        $stmt->bindValue(':e', preg_replace('/\D/', '', $ein), SQLITE3_TEXT);
        $stmt->bindValue(':a1', $a1, SQLITE3_TEXT);
        $stmt->bindValue(':a2', $a2, SQLITE3_TEXT);
        $stmt->bindValue(':city', $city, SQLITE3_TEXT);
        $stmt->bindValue(':state', $state, SQLITE3_TEXT);
        $stmt->bindValue(':zip', $zip, SQLITE3_TEXT);
        $stmt->execute();
        $message = 'Company settings saved.';
    }
}

$db = getDbConnection();
$row = $db->querySingle("SELECT employer_name, employer_ein, employer_address_line1, employer_address_line2, employer_city, employer_state, employer_zip FROM company_settings WHERE id = 1", true);
$row = $row ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company settings — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Company (employer) settings</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Company settings</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($message): ?><div class="info-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" class="info-box">
                <?= csrfField() ?>
                <div class="mb-2">
                    <label for="employer_name">Employer name</label>
                    <input type="text" id="employer_name" name="employer_name" value="<?= htmlspecialchars($row['employer_name'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label for="employer_ein">EIN (9 digits, XX-XXXXXXX)</label>
                    <input type="text" id="employer_ein" name="employer_ein" value="<?= htmlspecialchars($row['employer_ein'] ?? '') ?>" placeholder="12-3456789">
                </div>
                <div class="mb-2">
                    <label for="employer_address_line1">Address line 1</label>
                    <input type="text" id="employer_address_line1" name="employer_address_line1" value="<?= htmlspecialchars($row['employer_address_line1'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label for="employer_address_line2">Address line 2</label>
                    <input type="text" id="employer_address_line2" name="employer_address_line2" value="<?= htmlspecialchars($row['employer_address_line2'] ?? '') ?>">
                </div>
                <div class="flex" style="gap: 1rem;">
                    <div class="mb-2" style="flex:1">
                        <label for="employer_city">City</label>
                        <input type="text" id="employer_city" name="employer_city" value="<?= htmlspecialchars($row['employer_city'] ?? '') ?>">
                    </div>
                    <div class="mb-2" style="width: 80px;">
                        <label for="employer_state">State</label>
                        <input type="text" id="employer_state" name="employer_state" value="<?= htmlspecialchars($row['employer_state'] ?? '') ?>" maxlength="2">
                    </div>
                    <div class="mb-2" style="width: 100px;">
                        <label for="employer_zip">ZIP</label>
                        <input type="text" id="employer_zip" name="employer_zip" value="<?= htmlspecialchars($row['employer_zip'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn">Save</button>
            </form>
        </main>
    </div>
</body>
</html>
