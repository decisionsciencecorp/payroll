<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CalculatePayrollForEmployeeTest extends TestCase
{
    private function defaultConfig(): array
    {
        return [
            'ss_wage_base' => 184500,
            'fica_ss_rate' => 0.062,
            'fica_medicare_rate' => 0.0145,
            'additional_medicare_rate' => 0.009,
            'additional_medicare_thresholds' => [
                'single' => 200000,
                'married_filing_jointly' => 250000,
                'married_filing_separately' => 125000,
            ],
            'brackets' => [
                'single' => [
                    ['min' => 0, 'max' => 7500, 'rate' => 0.00],
                    ['min' => 7500, 'max' => 19900, 'rate' => 0.10],
                    ['min' => 19900, 'max' => 57900, 'rate' => 0.12],
                ],
                'married' => [
                    ['min' => 0, 'max' => 15000, 'rate' => 0.00],
                    ['min' => 15000, 'max' => 39800, 'rate' => 0.10],
                ],
                'head_of_household' => [
                    ['min' => 0, 'max' => 11250, 'rate' => 0.00],
                    ['min' => 11250, 'max' => 27900, 'rate' => 0.10],
                ],
            ],
        ];
    }

    public function testSingleFilerBracketCalculation(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 10000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        $result = calculatePayrollForEmployee($employee, $config, 0, 0, 0, 0);

        $this->assertArrayHasKey('federal_withholding', $result);
        $this->assertArrayHasKey('employee_ss', $result);
        $this->assertArrayHasKey('employee_medicare', $result);
        $this->assertArrayHasKey('net_pay', $result);
        $this->assertArrayHasKey('ytd_gross', $result);
        // Brackets are annual. 10k/month = 120k annual. single (3 brackets only): 0-7500@0, 7500-19900@0.1, 19900-57900@0.12.
        // Amount above 57900 has no bracket in test config, so not taxed. Annual tax: 12400*0.1 + 38000*0.12 = 5800. Per month = 483.33
        $this->assertSame(483.33, $result['federal_withholding']);
        $this->assertSame(620.0, $result['employee_ss']); // 10000 * 0.062
        $this->assertSame(145.0, $result['employee_medicare']); // 10000 * 0.0145
        $this->assertSame(8751.67, $result['net_pay']);
        $this->assertSame(10000.0, $result['ytd_gross']);
    }

    public function testStep4cExtraWithholding(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 5000,
            'step4c_extra_withholding' => 100,
        ];
        $config = $this->defaultConfig();
        $result = calculatePayrollForEmployee($employee, $config, 0, 0, 0, 0);
        $this->assertGreaterThanOrEqual(100.0, $result['federal_withholding']);
    }

    public function testMarriedFilingJointlyUsesMarriedBrackets(): void
    {
        $employee = [
            'filing_status' => 'Married filing jointly',
            'monthly_gross_salary' => 20000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        $result = calculatePayrollForEmployee($employee, $config, 0, 0, 0, 0);
        // Brackets annual. 20k/month = 240k. married: 0-15k@0, 15k-39800@0.1 → (24800)*0.1 = 2480 annual, /12 = 206.67
        $this->assertSame(206.67, $result['federal_withholding']);
    }

    public function testHeadOfHouseholdUsesHeadOfHouseholdBrackets(): void
    {
        $employee = [
            'filing_status' => 'Head of Household',
            'monthly_gross_salary' => 15000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        $result = calculatePayrollForEmployee($employee, $config, 0, 0, 0, 0);
        // Brackets annual. 15k/month = 180k. head_of_household: 0-11250@0, 11250-27900@0.1 → 16650*0.1 = 1665 annual, /12 = 138.75
        $this->assertSame(138.75, $result['federal_withholding']);
    }

    public function testYtdAccumulates(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 5000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        $result = calculatePayrollForEmployee($employee, $config, 10000, 200, 620, 145);
        $this->assertSame(15000.0, $result['ytd_gross']);
        $this->assertGreaterThanOrEqual(200.0, $result['ytd_federal_withheld']);
        $this->assertGreaterThanOrEqual(620.0, $result['ytd_ss']);
        $this->assertGreaterThanOrEqual(145.0, $result['ytd_medicare']);
    }

    public function testSsWageBaseCap(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 20000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        $config['ss_wage_base'] = 100000;
        // YTD already at 95000, so only 5000 subject to SS
        $result = calculatePayrollForEmployee($employee, $config, 95000, 0, 0, 0);
        $this->assertSame(310.0, $result['employee_ss']); // 5000 * 0.062
    }

    public function testAdditionalMedicareOverThreshold(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 10000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        // YTD 195000, so 195k + 10k = 205k > 200k threshold. Over = 5k, additional = 5000 * 0.009 = 45
        $result = calculatePayrollForEmployee($employee, $config, 195000, 0, 0, 0);
        $this->assertSame(145.0 + 45.0, $result['employee_medicare']);
    }

    public function testMarriedFilingSeparatelyThreshold(): void
    {
        $employee = [
            'filing_status' => 'Married filing separately',
            'monthly_gross_salary' => 10000,
            'step4c_extra_withholding' => 0,
        ];
        $config = $this->defaultConfig();
        $result = calculatePayrollForEmployee($employee, $config, 0, 0, 0, 0);
        $this->assertArrayHasKey('employee_medicare', $result);
        $this->assertSame(145.0, $result['employee_medicare']); // under 125k threshold
    }
}
