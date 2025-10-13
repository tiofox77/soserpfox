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
     * Calcular IRT (Imposto sobre Rendimentos do Trabalho) - Angola
     * 
     * Tabela Progressiva IRT 2025 (valores em Kwanzas):
     * ATUALIZAÇÃO: Isenção até 100.000 Kz (antes era 70.000 Kz)
     * 
     * | Escalão | De (Kz)    | Até (Kz)   | Taxa  | Parcela Abater |
     * |---------|------------|------------|-------|----------------|
     * | 1º      | 0          | 100.000    | 0%    | 0              |
     * | 2º      | 100.001    | 150.000    | 10%   | 10.000         |
     * | 3º      | 150.001    | 200.000    | 13%   | 14.500         |
     * | 4º      | 200.001    | 300.000    | 16%   | 20.000         |
     * | 5º      | 300.001    | 500.000    | 18%   | 26.000         |
     * | 6º      | 500.001    | 1.000.000  | 19%   | 31.000         |
     * | 7º      | 1.000.001  | 1.500.000  | 20%   | 41.000         |
     * | 8º      | 1.500.001  | 2.000.000  | 21%   | 56.000         |
     * | 9º      | 2.000.001  | 2.500.000  | 22%   | 76.000         |
     * | 10º     | 2.500.001  | +          | 23%   | 101.000        |
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
        
        // Se base for negativa ou zero, IRT = 0
        if ($irtBase <= 0) {
            return [
                'irt_amount' => 0,
                'irt_base' => 0,
                'irt_rate' => 0,
                'bracket' => 1,
            ];
        }
        
        // Tabela progressiva IRT Angola 2025
        // ATUALIZAÇÃO 2025: Isenção aumentada de 70.000 para 100.000 Kz
        $irtTable = [
            ['min' => 0,       'max' => 100000,   'rate' => 0,    'deduction' => 0],
            ['min' => 100001,  'max' => 150000,   'rate' => 10,   'deduction' => 10000],
            ['min' => 150001,  'max' => 200000,   'rate' => 13,   'deduction' => 14500],
            ['min' => 200001,  'max' => 300000,   'rate' => 16,   'deduction' => 20000],
            ['min' => 300001,  'max' => 500000,   'rate' => 18,   'deduction' => 26000],
            ['min' => 500001,  'max' => 1000000,  'rate' => 19,   'deduction' => 31000],
            ['min' => 1000001, 'max' => 1500000,  'rate' => 20,   'deduction' => 41000],
            ['min' => 1500001, 'max' => 2000000,  'rate' => 21,   'deduction' => 56000],
            ['min' => 2000001, 'max' => 2500000,  'rate' => 22,   'deduction' => 76000],
            ['min' => 2500001, 'max' => PHP_FLOAT_MAX, 'rate' => 23, 'deduction' => 101000],
        ];
        
        // Encontrar escalão
        $bracket = 1;
        $irtRate = 0;
        $parcelaAbater = 0;
        
        foreach ($irtTable as $index => $escalao) {
            if ($irtBase >= $escalao['min'] && $irtBase <= $escalao['max']) {
                $bracket = $index + 1;
                $irtRate = $escalao['rate'];
                $parcelaAbater = $escalao['deduction'];
                break;
            }
        }
        
        // Calcular IRT: (Base × Taxa%) - Parcela a Abater
        $irtAmount = ($irtBase * $irtRate / 100) - $parcelaAbater;
        
        // IRT não pode ser negativo
        $irtAmount = max(0, $irtAmount);
        
        return [
            'irt_amount' => round($irtAmount, 2),
            'irt_base' => round($irtBase, 2),
            'irt_rate' => $irtRate,
            'bracket' => $bracket,
            'parcela_abater' => $parcelaAbater,
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
        
        // Calcular INSS
        $inss = calculateINSS($grossSalary, [
            'include_allowances' => false, // Normalmente INSS só sobre salário base
        ]);
        
        // Calcular IRT
        $irt = calculateIRT($totalGross, [
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
        // Tabela IRT 2025 - Atualizada
        $brackets = [
            1 => ['min' => 0,       'max' => 100000,   'rate' => 0,  'deduction' => 0],
            2 => ['min' => 100001,  'max' => 150000,   'rate' => 10, 'deduction' => 10000],
            3 => ['min' => 150001,  'max' => 200000,   'rate' => 13, 'deduction' => 14500],
            4 => ['min' => 200001,  'max' => 300000,   'rate' => 16, 'deduction' => 20000],
            5 => ['min' => 300001,  'max' => 500000,   'rate' => 18, 'deduction' => 26000],
            6 => ['min' => 500001,  'max' => 1000000,  'rate' => 19, 'deduction' => 31000],
            7 => ['min' => 1000001, 'max' => 1500000,  'rate' => 20, 'deduction' => 41000],
            8 => ['min' => 1500001, 'max' => 2000000,  'rate' => 21, 'deduction' => 56000],
            9 => ['min' => 2000001, 'max' => 2500000,  'rate' => 22, 'deduction' => 76000],
            10 => ['min' => 2500001, 'max' => null,    'rate' => 23, 'deduction' => 101000],
        ];
        
        return $brackets[$bracket] ?? $brackets[1];
    }
}
