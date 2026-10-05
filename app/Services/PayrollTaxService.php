<?php

namespace App\Services;

/**
 * Uganda payroll statutory deductions (resident employees, monthly). The single source
 * for these figures - payroll/create.blade.php mirrors calculate() in JS for the live
 * preview, but the server always recomputes on save.
 */
class PayrollTaxService
{
    public const NSSF_EMPLOYEE_RATE = 0.05;  // deducted from the employee's gross
    public const NSSF_EMPLOYER_RATE = 0.10;  // paid by the company on top of gross

    /**
     * Resident monthly PAYE on gross pay:
     *   0 – 335,000            nil
     *   335,001 – 410,000      20% of the excess over 335,000
     *   410,001 – 485,000      15,000 + 25% of the excess over 410,000
     *   485,001 – 10,000,000   33,750 + 30% of the excess over 485,000
     *   above 10,000,000       2,888,250 + 40% of the excess over 10,000,000 (30% + 10% surtax)
     */
    public function paye(float $gross): float
    {
        $bands = [
            // [from, to, rate] - each band taxes only the slice of gross inside it
            [335000,   410000,   0.20],
            [410000,   485000,   0.25],
            [485000,   10000000, 0.30],
            [10000000, INF,      0.40],
        ];

        $tax = 0.0;
        foreach ($bands as [$from, $to, $rate]) {
            if ($gross > $from) {
                $tax += (min($gross, $to) - $from) * $rate;
            }
        }

        return round($tax, 2);
    }

    /**
     * Full breakdown for one employee's month.
     *
     * @return array{gross: float, paye: float, nssf_employee: float, nssf_employer: float, other_deductions: float, net: float, employer_cost: float}
     */
    public function calculate(float $basic, float $allowances = 0, float $otherDeductions = 0): array
    {
        $gross        = round($basic + $allowances, 2);
        $paye         = $this->paye($gross);
        $nssfEmployee = round($gross * self::NSSF_EMPLOYEE_RATE, 2);
        $nssfEmployer = round($gross * self::NSSF_EMPLOYER_RATE, 2);

        return [
            'gross'            => $gross,
            'paye'             => $paye,
            'nssf_employee'    => $nssfEmployee,
            'nssf_employer'    => $nssfEmployer,
            'other_deductions' => round($otherDeductions, 2),
            'net'              => round($gross - $paye - $nssfEmployee - $otherDeductions, 2),
            'employer_cost'    => round($gross + $nssfEmployer, 2),
        ];
    }
}
