<?php

/**
 * TESTAR CÁLCULOS DE RH - IRT E INSS (Angola)
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTES: CÁLCULOS DE IRT E INSS - ANGOLA\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Casos de teste com diferentes salários
$testCases = [
    ['salary' => 50000, 'name' => 'Salário Mínimo'],
    ['salary' => 100000, 'name' => 'Salário Baixo'],
    ['salary' => 250000, 'name' => 'Salário Médio'],
    ['salary' => 500000, 'name' => 'Salário Médio-Alto'],
    ['salary' => 1000000, 'name' => 'Salário Alto'],
    ['salary' => 2000000, 'name' => 'Salário Executivo'],
];

echo "📊 TESTES DE CÁLCULO POR ESCALÃO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($testCases as $test) {
    $salary = $test['salary'];
    $name = $test['name'];
    
    echo "💰 {$name}: " . number_format($salary, 2, ',', '.') . " Kz\n";
    echo str_repeat("─", 55) . "\n";
    
    // Calcular INSS
    $inss = calculateINSS($salary);
    
    // Calcular IRT
    $irt = calculateIRT($salary, ['inss_employee' => $inss['inss_employee']]);
    
    // Calcular salário líquido
    $netSalary = calculateNetSalary($salary);
    
    echo "INSS Empregado (3%):    " . number_format($inss['inss_employee'], 2, ',', '.') . " Kz\n";
    echo "INSS Empregador (8%):   " . number_format($inss['inss_employer'], 2, ',', '.') . " Kz\n";
    echo "Base IRT:               " . number_format($irt['irt_base'], 2, ',', '.') . " Kz\n";
    echo "IRT (Escalão {$irt['bracket']} - {$irt['irt_rate']}%): " . number_format($irt['irt_amount'], 2, ',', '.') . " Kz\n";
    echo "Salário Líquido:        " . number_format($netSalary['net_salary'], 2, ',', '.') . " Kz\n";
    echo "Custo Empresa:          " . number_format($netSalary['total_cost_to_company'], 2, ',', '.') . " Kz\n";
    echo "\n";
}

// Teste detalhado com subsídios
echo "\n";
echo "📋 TESTE COMPLETO COM SUBSÍDIOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$baseSalary = 1000000;
$allowances = [
    'food' => 50000,
    'transport' => 30000,
    'housing' => 100000,
];

$deductions = [
    'advance' => 100000,
    'loan' => 50000,
];

echo "VENCIMENTOS:\n";
echo "  Salário Base:          " . number_format($baseSalary, 2, ',', '.') . " Kz\n";
echo "  Subsídio Alimentação:  " . number_format($allowances['food'], 2, ',', '.') . " Kz\n";
echo "  Subsídio Transporte:   " . number_format($allowances['transport'], 2, ',', '.') . " Kz\n";
echo "  Subsídio Habitação:    " . number_format($allowances['housing'], 2, ',', '.') . " Kz\n";
echo "  " . str_repeat("─", 50) . "\n";

$calculation = calculateNetSalary($baseSalary, $allowances, $deductions);

echo "  TOTAL BRUTO:           " . number_format($calculation['total_gross'], 2, ',', '.') . " Kz\n\n";

echo "DEDUÇÕES:\n";
echo "  INSS (3%):             " . number_format($calculation['inss_employee'], 2, ',', '.') . " Kz\n";
echo "  IRT ({$calculation['irt_rate']}%):              " . number_format($calculation['irt_amount'], 2, ',', '.') . " Kz\n";
echo "  Adiantamento:          " . number_format($calculation['advance_payment'], 2, ',', '.') . " Kz\n";
echo "  Empréstimo:            " . number_format($calculation['loan_deduction'], 2, ',', '.') . " Kz\n";
echo "  " . str_repeat("─", 50) . "\n";
echo "  TOTAL DEDUÇÕES:        " . number_format($calculation['total_deductions'], 2, ',', '.') . " Kz\n\n";

echo "SALÁRIO LÍQUIDO:         " . number_format($calculation['net_salary'], 2, ',', '.') . " Kz\n";
echo "CUSTO PARA EMPRESA:      " . number_format($calculation['total_cost_to_company'], 2, ',', '.') . " Kz\n";

// Tabela IRT
echo "\n\n";
echo "📊 TABELA PROGRESSIVA IRT 2024\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "┌──────┬──────────────┬──────────────┬──────┬───────────────┐\n";
echo "│ Esc. │ De (Kz)      │ Até (Kz)     │ Taxa │ Parc. Abater  │\n";
echo "├──────┼──────────────┼──────────────┼──────┼───────────────┤\n";

for ($i = 1; $i <= 10; $i++) {
    $bracket = getIRTBracketInfo($i);
    $min = number_format($bracket['min'], 0, ',', '.');
    $max = $bracket['max'] ? number_format($bracket['max'], 0, ',', '.') : '∞';
    $rate = $bracket['rate'] . '%';
    $deduction = number_format($bracket['deduction'], 0, ',', '.');
    
    printf("│ %-4s │ %12s │ %12s │ %4s │ %13s │\n", 
           $i . 'º', $min, $max, $rate, $deduction);
}

echo "└──────┴──────────────┴──────────────┴──────┴───────────────┘\n";

// Subsídios
echo "\n\n";
echo "💰 SUBSÍDIOS OBRIGATÓRIOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$testSalary = 500000;
$monthsWorked = 12;

$subsidy13th = calculate13thMonth($testSalary, $monthsWorked);
$subsidy14th = calculate14thMonth($testSalary);

echo "Salário Base: " . number_format($testSalary, 2, ',', '.') . " Kz\n";
echo "Meses Trabalhados: {$monthsWorked}\n\n";

echo "13º Mês (Subsídio de Natal):\n";
echo "  Valor: " . number_format($subsidy13th, 2, ',', '.') . " Kz\n";
echo "  Pago em: Novembro/Dezembro\n\n";

echo "14º Mês (Subsídio de Férias):\n";
echo "  Valor: " . number_format($subsidy14th, 2, ',', '.') . " Kz\n";
echo "  Percentual: 50% do salário (mínimo legal)\n";
echo "  Pago em: Antes das férias\n\n";

// Resumo
echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  RESUMO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "✅ Testes de Cálculo Concluídos!\n\n";

echo "📚 Funções Disponíveis:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  • calculateIRT(\$salary, \$deductions)\n";
echo "  • calculateINSS(\$salary, \$options)\n";
echo "  • calculateNetSalary(\$salary, \$allowances, \$deductions)\n";
echo "  • calculate13thMonth(\$baseSalary, \$monthsWorked)\n";
echo "  • calculate14thMonth(\$baseSalary)\n";
echo "  • getIRTBracketInfo(\$bracket)\n\n";

echo "📖 Documentação:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  docs/HR-MODULE-MVP.md\n\n";

echo "🎯 Próximos Passos:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  1. Executar migrations: php artisan migrate\n";
echo "  2. Criar Livewire components\n";
echo "  3. Implementar processamento de folha\n";
echo "  4. Gerar recibos de pagamento\n\n";

echo "✨ Sistema de RH pronto para desenvolvimento!\n\n";
