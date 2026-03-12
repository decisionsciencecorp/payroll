<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$FILING_STATUSES = ['Single', 'Married filing jointly', 'Married filing separately', 'Head of Household'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$employee = null;
$isEdit = false;
if ($id) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM employees WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $r = $stmt->execute();
    $employee = $r->fetchArray(SQLITE3_ASSOC);
    if (!$employee) {
        header('Location: employees.php?error=' . urlencode('Employee not found'));
        exit;
    }
    $isEdit = true;
}

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $apiKey = getApiKeyForAdmin();
    if (!$apiKey) {
        $error = 'No API key. Create one in Admin → API Keys first.';
    } else {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $postId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'ssn' => preg_replace('/\D/', '', $_POST['ssn'] ?? ''),
            'filing_status' => $_POST['filing_status'] ?? 'Single',
            'hire_date' => $_POST['hire_date'] ?? '',
            'monthly_gross_salary' => (float)($_POST['monthly_gross_salary'] ?? 0),
            'address_line1' => trim($_POST['address_line1'] ?? '') ?: null,
            'address_line2' => trim($_POST['address_line2'] ?? '') ?: null,
            'city' => trim($_POST['city'] ?? '') ?: null,
            'state' => trim($_POST['state'] ?? '') ?: null,
            'zip' => trim($_POST['zip'] ?? '') ?: null,
        ];
        if (!empty($_POST['step4a_other_income'])) $data['step4a_other_income'] = (float)$_POST['step4a_other_income'];
        if (!empty($_POST['step4b_deductions'])) $data['step4b_deductions'] = (float)$_POST['step4b_deductions'];
        if (!empty($_POST['step4c_extra_withholding'])) $data['step4c_extra_withholding'] = (float)$_POST['step4c_extra_withholding'];
        if (!empty($_POST['i9_completed_at'])) $data['i9_completed_at'] = $_POST['i9_completed_at'];

        $ch = curl_init();
        if ($postId) {
            $data['id'] = $postId;
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/api/update-employee.php',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
            ]);
        } else {
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/api/create-employee.php',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
            ]);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($resp, true);

        if ($code >= 200 && $code < 300 && !empty($result['success'])) {
            header('Location: employees.php?msg=' . urlencode($postId ? 'Employee updated.' : 'Employee added.'));
            exit;
        }
        $error = $result['error'] ?? 'Request failed (HTTP ' . $code . ')';
        $employee = array_merge($employee ?? [], $_POST);
    }
}

$e = $employee ?? [];
$title = $isEdit ? 'Edit employee' : 'Add employee';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle"><?= htmlspecialchars($title) ?></p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1><?= htmlspecialchars($title) ?></h1>
                <a href="employees.php" class="btn btn-secondary">Back to list</a>
            </div>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="info-box">
                <form method="POST">
                    <?= csrfField() ?>
                    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><?php endif; ?>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.5rem;">
                        <div class="mb-2">
                            <label for="full_name">Full name *</label>
                            <input type="text" id="full_name" name="full_name" required value="<?= htmlspecialchars($e['full_name'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="ssn">SSN (digits only or XXX-XX-XXXX) *</label>
                            <input type="text" id="ssn" name="ssn" required placeholder="123456789" value="<?= htmlspecialchars($e['ssn'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="filing_status">Filing status *</label>
                            <select id="filing_status" name="filing_status" required>
                                <?php foreach ($FILING_STATUSES as $fs): ?>
                                    <option value="<?= htmlspecialchars($fs) ?>" <?= ($e['filing_status'] ?? '') === $fs ? 'selected' : '' ?>><?= htmlspecialchars($fs) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="hire_date">Hire date *</label>
                            <input type="date" id="hire_date" name="hire_date" required value="<?= htmlspecialchars($e['hire_date'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="monthly_gross_salary">Monthly gross salary *</label>
                            <input type="number" id="monthly_gross_salary" name="monthly_gross_salary" step="0.01" min="0" required value="<?= htmlspecialchars($e['monthly_gross_salary'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="i9_completed_at">I-9 completed (date, optional)</label>
                            <input type="date" id="i9_completed_at" name="i9_completed_at" value="<?= htmlspecialchars($e['i9_completed_at'] ?? '') ?>">
                        </div>
                        <div class="mb-2" style="grid-column: 1 / -1;">
                            <label for="address_line1">Address line 1</label>
                            <input type="text" id="address_line1" name="address_line1" value="<?= htmlspecialchars($e['address_line1'] ?? '') ?>">
                        </div>
                        <div class="mb-2" style="grid-column: 1 / -1;">
                            <label for="address_line2">Address line 2</label>
                            <input type="text" id="address_line2" name="address_line2" value="<?= htmlspecialchars($e['address_line2'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?= htmlspecialchars($e['city'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" value="<?= htmlspecialchars($e['state'] ?? '') ?>" maxlength="2" placeholder="e.g. CA">
                        </div>
                        <div class="mb-2">
                            <label for="zip">ZIP</label>
                            <input type="text" id="zip" name="zip" value="<?= htmlspecialchars($e['zip'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="step4a_other_income">W-4 step 4(a) other income</label>
                            <input type="number" id="step4a_other_income" name="step4a_other_income" step="0.01" min="0" value="<?= htmlspecialchars($e['step4a_other_income'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="step4b_deductions">W-4 step 4(b) deductions</label>
                            <input type="number" id="step4b_deductions" name="step4b_deductions" step="0.01" min="0" value="<?= htmlspecialchars($e['step4b_deductions'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label for="step4c_extra_withholding">W-4 step 4(c) extra withholding</label>
                            <input type="number" id="step4c_extra_withholding" name="step4c_extra_withholding" step="0.01" min="0" value="<?= htmlspecialchars($e['step4c_extra_withholding'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn" style="margin-top: 1rem;"><?= $isEdit ? 'Save changes' : 'Add employee' ?></button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
