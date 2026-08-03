<?php

/**
 * HELPER PARA CÁLCULOS DE IMPOSTOS E CONTRIBUIÇÕES - ANGOLA
 * 
 * Legislação Angolana:
 * - IRT (Imposto sobre Rendimentos do Trabalho) - Tabela Progressiva
 * - INSS (Instituto Nacional de Segurança Social) - 3% empregado + 8% empregador
 * - Subsídio de Natal (13º mês)
 * - Subsídio de Férias (14º mês)
 */

if (!function_exists('calculateIRT')) {
    /**
     * Calcular IRT (Imposto sobre Rendimentos do Trabalho) - Angola, Grupo A
     *
     * Método oficial: IRT = parcela_fixa + taxa × (base − limite_inferior_do_escalão).
     * Isenção até 150.000 Kz. Tabela CONTÍNUA (sem saltos) — igual ao IRTTaxBracketSeeder.
     * Esta função é apenas o fallback quando um tenant não tem escalões na BD.
     *
     * | Escalão | De (Kz)     | Até (Kz)    | Taxa   | Parcela Fixa |
     * |---------|-------------|-------------|--------|--------------|
     * | 1º      | 0           | 150.000     | 0%     | 0            |
     * | 2º      | 150.000     | 200.000     | 16%    | 0            |
     * | 3º      | 200.000     | 300.000     | 18%    | 8.000        |
     * | 4º      | 300.000     | 500.000     | 19%    | 26.000       |
     * | 5º      | 500.000     | 1.000.000   | 20%    | 64.000       |
     * | 6º      | 1.000.000   | 1.500.000   | 21%    | 164.000      |
     * | 7º      | 1.500.000   | 2.000.000   | 22%    | 269.000      |
     * | 8º      | 2.000.000   | 2.500.000   | 23%    | 379.000      |
     * | 9º      | 2.500.000   | 5.000.000   | 24%    | 494.000      |
     * | 10º     | 5.000.000   | 10.000.000  | 24,5%  | 1.094.000    |
     * | 11º     | 10.000.000  | +           | 25%    | 2.319.000    |
     *
     * @param float $grossSalary Salário bruto mensal
     * @param array $deductions Deduções permitidas (INSS, seguros, etc)
     * @return array ['irt_amount', 'irt_base', 'irt_rate', 'bracket']
     */
    function calculateIRT(float $grossSalary, array $deductions = []): array
    {
        // Deduções permitidas
        $inssEmployee = $deductions['inss_employee'] ?? 0;
        $otherDeductions = $deductions['other'] ?? 0;

        // Base de cálculo IRT = Salário Bruto - INSS - Outras deduções
        $irtBase = $grossSalary - $inssEmployee - $otherDeductions;

        if ($irtBase <= 0) {
            return ['irt_amount' => 0, 'irt_base' => 0, 'irt_rate' => 0, 'bracket' => 1];
        }

        // Tabela contínua: [limite_inferior, limite_superior|null, taxa%, parcela_fixa]
        $irtTable = [
            ['min' => 0,        'max' => 150000,   'rate' => 0,    'fixed' => 0],
            ['min' => 150000,   'max' => 200000,   'rate' => 16,   'fixed' => 0],
            ['min' => 200000,   'max' => 300000,   'rate' => 18,   'fixed' => 8000],
            ['min' => 300000,   'max' => 500000,   'rate' => 19,   'fixed' => 26000],
            ['min' => 500000,   'max' => 1000000,  'rate' => 20,   'fixed' => 64000],
            ['min' => 1000000,  'max' => 1500000,  'rate' => 21,   'fixed' => 164000],
            ['min' => 1500000,  'max' => 2000000,  'rate' => 22,   'fixed' => 269000],
            ['min' => 2000000,  'max' => 2500000,  'rate' => 23,   'fixed' => 379000],
            ['min' => 2500000,  'max' => 5000000,  'rate' => 24,   'fixed' => 494000],
            ['min' => 5000000,  'max' => 10000000, 'rate' => 24.5, 'fixed' => 1094000],
            ['min' => 10000000, 'max' => null,     'rate' => 25,   'fixed' => 2319000],
        ];

        // Selecionar o escalão mais alto cujo limite inferior <= base (progressivo por excesso)
        $sel = $irtTable[0];
        $bracket = 1;
        foreach ($irtTable as $index => $escalao) {
            if ($irtBase >= $escalao['min']) {
                $sel = $escalao;
                $bracket = $index + 1;
            }
        }

        // IRT = parcela fixa + taxa × (base − limite inferior)
        $irtAmount = $sel['fixed'] + ($sel['rate'] / 100) * ($irtBase - $sel['min']);
        $irtAmount = max(0, $irtAmount);

        return [
            'irt_amount' => round($irtAmount, 2),
            'irt_base' => round($irtBase, 2),
            'irt_rate' => $sel['rate'],
            'bracket' => $bracket,
            'parcela_fixa' => $sel['fixed'],
        ];
    }
}

if (!function_exists('calculateINSS')) {
    /**
     * Calcular INSS (Instituto Nacional de Segurança Social) - Angola
     * 
     * Taxas INSS Angola:
     * - Empregado: 3% do salário bruto
     * - Empregador: 8% do salário bruto
     * - Total: 11%
     * 
     * Base de cálculo: Salário bruto + subsídios regulares
     * 
     * @param float $grossSalary Salário bruto
     * @param array $options Opções adicionais
     * @return array ['inss_employee', 'inss_employer', 'inss_total', 'inss_base']
     */
    function calculateINSS(float $grossSalary, array $options = []): array
    {
        $employeeRate = $options['employee_rate'] ?? 3; // 3%
        $employerRate = $options['employer_rate'] ?? 8; // 8%
        
        // Base de cálculo INSS
        $inssBase = $grossSalary;
        
        // Adicionar subsídios se especificado
        if (isset($options['include_allowances']) && $options['include_allowances']) {
            $inssBase += ($options['food_allowance'] ?? 0);
            $inssBase += ($options['transport_allowance'] ?? 0);
            $inssBase += ($options['housing_allowance'] ?? 0);
        }
        
        // Calcular INSS
        $inssEmployee = $inssBase * ($employeeRate / 100);
        $inssEmployer = $inssBase * ($employerRate / 100);
        $inssTotal = $inssEmployee + $inssEmployer;
        
        return [
            'inss_employee' => round($inssEmployee, 2),
            'inss_employer' => round($inssEmployer, 2),
            'inss_total' => round($inssTotal, 2),
            'inss_base' => round($inssBase, 2),
            'employee_rate' => $employeeRate,
            'employer_rate' => $employerRate,
        ];
    }
}

if (!function_exists('calculateNetSalary')) {
    /**
     * Calcular Salário Líquido completo
     * 
     * @param float $grossSalary Salário bruto
     * @param array $allowances Subsídios (alimentação, transporte, etc)
     * @param array $deductions Deduções adicionais
     * @return array Detalhes completos do cálculo
     */
    function calculateNetSalary(float $grossSalary, array $allowances = [], array $deductions = []): array
    {
        // Subsídios
        $foodAllowance = $allowances['food'] ?? 0;
        $transportAllowance = $allowances['transport'] ?? 0;
        $housingAllowance = $allowances['housing'] ?? 0;
        $otherAllowances = $allowances['other'] ?? 0;
        
        // Total bruto
        $totalGross = $grossSalary + $foodAllowance + $transportAllowance + $housingAllowance + $otherAllowances;
        
        // Calcular INSS (Decreto 227/18 — base = remuneração total)
        $inss = calculateINSS($grossSalary, [
            'include_allowances' => true,
            'food_allowance' => $foodAllowance,
            'transport_allowance' => $transportAllowance,
            'housing_allowance' => $housingAllowance,
        ]);
        
        // Calcular IRT — subsídios isentos até 30.000 Kz cada
        $taxableFoodAllowance = max(0, $foodAllowance - 30000);
        $taxableTransportAllowance = max(0, $transportAllowance - 30000);
        $irtBase = $grossSalary + $taxableFoodAllowance + $taxableTransportAllowance + $housingAllowance + $otherAllowances;
        
        $irt = calculateIRT($irtBase, [
            'inss_employee' => $inss['inss_employee'],
            'other' => $deductions['other'] ?? 0,
        ]);
        
        // Outras deduções
        $advancePayment = $deductions['advance'] ?? 0;
        $loanDeduction = $deductions['loan'] ?? 0;
        $absenceDeduction = $deductions['absence'] ?? 0;
        $otherDeductions = $deductions['other'] ?? 0;
        
        // Total deduções
        $totalDeductions = $inss['inss_employee'] + $irt['irt_amount'] + 
                          $advancePayment + $loanDeduction + $absenceDeduction + $otherDeductions;
        
        // Salário líquido
        $netSalary = $totalGross - $totalDeductions;
        
        return [
            // Vencimentos
            'base_salary' => $grossSalary,
            'food_allowance' => $foodAllowance,
            'transport_allowance' => $transportAllowance,
            'housing_allowance' => $housingAllowance,
            'other_allowances' => $otherAllowances,
            'total_gross' => $totalGross,
            
            // Impostos e Contribuições
            'inss_employee' => $inss['inss_employee'],
            'inss_employer' => $inss['inss_employer'],
            'inss_base' => $inss['inss_base'],
            'irt_amount' => $irt['irt_amount'],
            'irt_base' => $irt['irt_base'],
            'irt_rate' => $irt['irt_rate'],
            'irt_bracket' => $irt['bracket'],
            
            // Deduções
            'advance_payment' => $advancePayment,
            'loan_deduction' => $loanDeduction,
            'absence_deduction' => $absenceDeduction,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            
            // Líquido
            'net_salary' => $netSalary,
            
            // Custos para empresa
            'total_cost_to_company' => $totalGross + $inss['inss_employer'],
        ];
    }
}

if (!function_exists('calculate13thMonth')) {
    /**
     * Calcular 13º Mês (Subsídio de Natal) - Angola
     * 
     * Geralmente pago em Novembro ou Dezembro
     * Valor = Salário base mensal
     * 
     * @param float $baseSalary Salário base
     * @param int $monthsWorked Meses trabalhados no ano
     * @return float
     */
    function calculate13thMonth(float $baseSalary, int $monthsWorked = 12): float
    {
        // Proporcional aos meses trabalhados
        return ($baseSalary / 12) * $monthsWorked;
    }
}

if (!function_exists('calculate14thMonth')) {
    /**
     * Calcular 14º Mês (Subsídio de Férias) - Angola
     * 
     * Geralmente pago antes das férias
     * Valor = 50% do salário base (mínimo legal)
     * 
     * @param float $baseSalary Salário base
     * @return float
     */
    function calculate14thMonth(float $baseSalary): float
    {
        // Mínimo 50% do salário
        return $baseSalary * 0.5;
    }
}

if (!function_exists('getIRTBracketInfo')) {
    /**
     * Obter informações sobre escalão de IRT
     * 
     * @param int $bracket Número do escalão (1-10)
     * @return array
     */
    function getIRTBracketInfo(int $bracket): array
    {
        // Tabela IRT Angola Grupo A (contínua) — igual a calculateIRT() e ao seeder
        $brackets = [
            1  => ['min' => 0,        'max' => 150000,   'rate' => 0,    'fixed' => 0],
            2  => ['min' => 150000,   'max' => 200000,   'rate' => 16,   'fixed' => 0],
            3  => ['min' => 200000,   'max' => 300000,   'rate' => 18,   'fixed' => 8000],
            4  => ['min' => 300000,   'max' => 500000,   'rate' => 19,   'fixed' => 26000],
            5  => ['min' => 500000,   'max' => 1000000,  'rate' => 20,   'fixed' => 64000],
            6  => ['min' => 1000000,  'max' => 1500000,  'rate' => 21,   'fixed' => 164000],
            7  => ['min' => 1500000,  'max' => 2000000,  'rate' => 22,   'fixed' => 269000],
            8  => ['min' => 2000000,  'max' => 2500000,  'rate' => 23,   'fixed' => 379000],
            9  => ['min' => 2500000,  'max' => 5000000,  'rate' => 24,   'fixed' => 494000],
            10 => ['min' => 5000000,  'max' => 10000000, 'rate' => 24.5, 'fixed' => 1094000],
            11 => ['min' => 10000000, 'max' => null,     'rate' => 25,   'fixed' => 2319000],
        ];

        return $brackets[$bracket] ?? $brackets[1];
    }
}
