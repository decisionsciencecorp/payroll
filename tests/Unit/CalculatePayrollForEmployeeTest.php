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
        // 10k gross: (19900-7500)*0.10 + (10000-19900)*0.12 is wrong - bracket is min/max, amount in bracket = min(taxable,max)-min(taxable,min)
        // For 10000: bracket 0-7500: 0; 7500-19900: min(10000,19900)-min(10000,7500)=10000-7500=2500*0.10=250; 19900-57900: 0. So federal = 250
        $this->assertSame(250.0, $result['federal_withholding']);
        $this->assertSame(620.0, $result['employee_ss']); // 10000 * 0.062
        $this->assertSame(145.0, $result['employee_medicare']); // 10000 * 0.0145
        $this->assertSame(8985.0, $result['net_pay']);
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
        // 20k: married 0-15k = 0, 15k-39800: 5000*0.10 = 500
        $this->assertSame(500.0, $result['federal_withholding']);
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
        // 0-11250: 0; 11250-27900: 3750*0.10 = 375
        $this->assertSame(375.0, $result['federal_withholding']);
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
        $this->assertGreaterThan(200.0, $result['ytd_federal_withheld']);
        $this->assertGreaterThan(620.0, $result['ytd_ss']);
        $this->assertGreaterThan(145.0, $result['ytd_medicare']);
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
