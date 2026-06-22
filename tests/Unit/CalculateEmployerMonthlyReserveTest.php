<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CalculateEmployerMonthlyReserveTest extends TestCase
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
                ],
                'married' => [],
                'head_of_household' => [],
            ],
        ];
    }

    public function testEmployerReserveMatchesEmployerFicaOnGross(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 1800,
            'step4c_extra_withholding' => 0,
        ];
        $result = calculateEmployerMonthlyReserve($employee, $this->defaultConfig(), 0, 0, 0, 0);

        $this->assertSame(111.6, $result['employer_ss']);
        $this->assertSame(26.1, $result['employer_medicare']);
        $this->assertSame(137.7, $result['employer_fica_total']);
        $this->assertSame(1800.0, $result['gross_pay']);
        $this->assertGreaterThan(0, $result['employee_withholdings_total']);
        $this->assertLessThan(1800.0, $result['net_pay']);
    }

    public function testEmployerReserveExcludesEmployeeWithholdingsFromTotal(): void
    {
        $employee = [
            'filing_status' => 'Single',
            'monthly_gross_salary' => 5000,
            'step4c_extra_withholding' => 0,
        ];
        $result = calculateEmployerMonthlyReserve($employee, $this->defaultConfig(), 0, 0, 0, 0);
        $employerOnly = $result['employer_ss'] + $result['employer_medicare'];

        $this->assertSame($result['employer_fica_total'], round($employerOnly, 2));
        $this->assertNotEquals(
            $result['employer_fica_total'],
            $result['employee_withholdings_total']
        );
    }
}
