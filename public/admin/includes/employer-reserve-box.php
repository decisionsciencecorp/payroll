<?php
/** @var array $reserveProjections from buildEmployerReserveProjections() */
if (!isset($reserveProjections)) {
    return;
}
$boxTitle = $boxTitle ?? 'Monthly employer reserve';
?>
<div class="info-box employer-reserve-box">
    <h2 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($boxTitle) ?></h2>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
        Cash you pay <em>on top of</em> net paychecks — employer Social Security and Medicare match (not withheld from the employee).
        Employee federal/SS/Medicare amounts come out of gross pay on the stub; you still remit those separately when paying the IRS.
    </p>
    <?php if (!empty($reserveProjections['tax_config_missing'])): ?>
        <p style="color: var(--danger);">Upload tax config for <?= (int)$reserveProjections['tax_year'] ?> to see projections.</p>
    <?php elseif (empty($reserveProjections['employees'])): ?>
        <p style="color: var(--text-muted);">Add employees to see monthly reserve amounts.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Gross / mo</th>
                    <th>Employer SS</th>
                    <th>Employer Medicare</th>
                    <th>Your reserve / mo</th>
                    <th>From check (ref.)</th>
                    <th>Net pay (ref.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reserveProjections['employees'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td>$<?= number_format((float)$row['gross_pay'], 2) ?></td>
                        <td>$<?= number_format((float)$row['employer_ss'], 2) ?></td>
                        <td>$<?= number_format((float)$row['employer_medicare'], 2) ?></td>
                        <td><strong>$<?= number_format((float)$row['employer_fica_total'], 2) ?></strong></td>
                        <td style="color: var(--text-muted);">$<?= number_format((float)$row['employee_withholdings_total'], 2) ?></td>
                        <td style="color: var(--text-muted);">$<?= number_format((float)$row['net_pay'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (count($reserveProjections['employees']) > 1): ?>
                <tfoot>
                    <tr>
                        <th colspan="4">Company total (employer reserve)</th>
                        <th>$<?= number_format((float)$reserveProjections['company_total'], 2) ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.75rem;">
            Projections use <?= (int)$reserveProjections['tax_year'] ?> tax config and current salaries.
            YTD wage base affects Social Security when an employee nears the annual cap.
        </p>
    <?php endif; ?>
</div>
