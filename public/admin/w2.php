<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $year = (int)($_POST['year'] ?? 0);
    if ($year < 2000 || $year > 2100) {
        $error = 'Invalid year';
    } else {
        $db = getDbConnection();
        $company = $db->querySingle("SELECT employer_name, employer_ein, employer_address_line1, employer_address_line2, employer_city, employer_state, employer_zip FROM company_settings WHERE id = 1", true);
        if (!$company || empty($company['employer_name']) || empty($company['employer_ein'])) {
            $error = 'Set employer name and EIN in company settings first.';
        } else {
            $y = (string)$year;
            $stmt = $db->prepare("
                SELECT e.id, e.full_name, e.ssn, e.address_line1, e.address_line2, e.city, e.state, e.zip,
                       p.ytd_gross, p.ytd_federal_withheld, p.ytd_ss, p.ytd_medicare
                FROM payroll_history p
                JOIN employees e ON e.id = p.employee_id
                JOIN (SELECT employee_id, MAX(pay_date) as md FROM payroll_history WHERE strftime('%Y', pay_date) = :year GROUP BY employee_id) last ON last.employee_id = p.employee_id AND last.md = p.pay_date
                WHERE strftime('%Y', p.pay_date) = :year2
            ");
            $stmt->bindValue(':year', $y, SQLITE3_TEXT);
            $stmt->bindValue(':year2', $y, SQLITE3_TEXT);
            $r = $stmt->execute();
            $w2s = [];
            while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
                if (empty($row['address_line1']) || empty($row['city']) || empty($row['state']) || empty($row['zip'])) continue;
                $w2s[] = $row;
            }
            if (empty($w2s)) {
                $error = 'No employees with payroll and complete address for that year.';
            } else {
                header('Content-Type: text/html; charset=utf-8');
                header('Content-Disposition: attachment; filename="W2-' . $year . '.html"');
                $fmt = function ($n) { return number_format((float)$n, 2); };
                $ein = htmlspecialchars($company['employer_ein']);
                $ename = htmlspecialchars($company['employer_name']);
                $eaddr = trim(implode(', ', array_filter([$company['employer_address_line1'], $company['employer_address_line2'], $company['employer_city'], $company['employer_state'], $company['employer_zip']])));
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>W-2 ' . $year . '</title><style>body{font-family:system-ui}.w2{border:1px solid #000;width:600px;margin:1rem auto;padding:0.5rem;page-break-after:always}table{width:100%;border-collapse:collapse}.w2 td{padding:2px 4px;border:1px solid #ccc}</style></head><body>';
                foreach ($w2s as $w) {
                    $empAddr = trim(implode(', ', array_filter([$w['address_line1'], $w['address_line2'], $w['city'], $w['state'], $w['zip']])));
                    $w1 = $fmt($w['ytd_gross']);
                    $w2 = $fmt($w['ytd_federal_withheld']);
                    $w3 = $fmt($w['ytd_ss']);
                    $w4 = $fmt($w['ytd_medicare']);
                    echo '<div class="w2"><p><strong>Form W-2 - ' . $year . '</strong></p><table>';
                    echo '<tr><td>Employer EIN</td><td>' . $ein . '</td><td>Employer</td><td>' . $ename . '</td></tr>';
                    echo '<tr><td colspan="4">' . htmlspecialchars($eaddr) . '</td></tr>';
                    echo '<tr><td>Employee SSN</td><td>' . htmlspecialchars($w['ssn']) . '</td><td>Name</td><td>' . htmlspecialchars($w['full_name']) . '</td></tr>';
                    echo '<tr><td colspan="4">' . htmlspecialchars($empAddr) . '</td></tr>';
                    echo '<tr><td>1 Wages</td><td>$' . $w1 . '</td><td>2 Federal</td><td>$' . $w2 . '</td></tr>';
                    echo '<tr><td>3 SS wages</td><td>$' . $w1 . '</td><td>4 SS withheld</td><td>$' . $w3 . '</td></tr>';
                    echo '<tr><td>5 Med wages</td><td>$' . $w1 . '</td><td>6 Med withheld</td><td>$' . $w4 . '</td></tr></table></div>';
                }
                echo '</body></html>';
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>W-2 — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">W-2 generation</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Generate W-2s</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="info-box">
                <form method="POST">
                    <?= csrfField() ?>
                    <label for="year">Tax year</label>
                    <input type="number" id="year" name="year" min="2000" max="2100" value="<?= date('Y') ?>" style="width: 100px;">
                    <button type="submit" class="btn" style="margin-top: 0.5rem;">Generate W-2s (HTML download)</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
