<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    requireCsrfToken();
    $json = trim($_POST['config_json'] ?? '');
    $data = $json ? json_decode($json, true) : null;
    if (!$data || !isset($data['year'])) {
        $error = 'Invalid JSON or missing year';
    } else {
        $apiKey = getApiKeyForAdmin();
        if (!$apiKey) {
            $error = 'Create an API key first (API Keys page).';
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => SITE_URL . '/api/upload-tax-brackets.php',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($curlErr) {
                $error = 'Request failed: ' . $curlErr . (strpos(SITE_URL, 'localhost') !== false ? ' (check SITE_URL in config — use the URL you use to open the admin)' : '');
            } elseif ($resp === false || $resp === '') {
                $error = 'Empty response from API';
            } else {
                $dec = json_decode($resp, true);
                if ($code >= 200 && $code < 300 && !empty($dec['success'])) {
                    $message = 'Tax config saved for year ' . ($data['year'] ?? '');
                } else {
                    $error = $dec['error'] ?? ($dec['message'] ?? 'Upload failed (HTTP ' . $code . ')');
                    if ($dec === null && $resp !== '') {
                        $error .= ' — response was not JSON. ' . substr(preg_replace('/\s+/', ' ', strip_tags($resp)), 0, 200);
                    }
                }
            }
        }
    }
}

$db = getDbConnection();
$r = $db->query("SELECT tax_year FROM tax_config ORDER BY tax_year DESC");
$years = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) $years[] = $row['tax_year'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax config — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Tax config</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Tax config</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($message): ?><div class="info-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <p class="mb-2">Configured years: <?= empty($years) ? 'None' : implode(', ', $years) ?></p>
            <div class="info-box">
                <h2 style="margin-bottom: 1rem;">Upload JSON config</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload">
                    <label for="config_json">Tax config JSON (see PRD §6)</label>
                    <textarea id="config_json" name="config_json" rows="12" style="width:100%; font-family: monospace; font-size: 12px;">{"year": 2026, "ss_wage_base": 184500, "fica_ss_rate": 0.062, "fica_medicare_rate": 0.0145, "additional_medicare_rate": 0.009, "additional_medicare_thresholds": {"single": 200000, "married_filing_jointly": 250000, "married_filing_separately": 125000}, "brackets": {"single": [], "married": [], "head_of_household": []}}</textarea>
                    <button type="submit" class="btn" style="margin-top: 0.5rem;">Upload</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
