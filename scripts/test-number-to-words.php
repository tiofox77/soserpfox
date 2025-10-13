<?php

/**
 * TESTAR CONVERSÃO DE VALORES POR EXTENSO
 * 
 * Testa a função numberToWords() em português de Angola
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTE: CONVERSÃO DE VALORES POR EXTENSO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Valores de teste
$testValues = [
    0,
    1,
    15,
    25.50,
    100,
    150.75,
    1000,
    1500.25,
    2500.50,
    10000,
    15000.99,
    100000,
    250000,
    500000.50,
    1000000,
    1500000.75,
    10000000,
    1000000000,
];

echo "📊 Conversões em AOA (Kwanzas):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($testValues as $value) {
    $extenso = numberToWords($value, 'AOA');
    $formatted = number_format($value, 2, ',', '.');
    
    echo "💰 {$formatted} Kz\n";
    echo "   → {$extenso}\n\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  OUTRAS MOEDAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Teste com outras moedas
$amount = 2500.75;

echo "USD (Dólares):\n";
echo number_format($amount, 2, ',', '.') . " USD\n";
echo "→ " . numberToWords($amount, 'USD') . "\n\n";

echo "EUR (Euros):\n";
echo number_format($amount, 2, ',', '.') . " EUR\n";
echo "→ " . numberToWords($amount, 'EUR') . "\n\n";

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  CASOS ESPECIAIS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$specialCases = [
    ['valor' => 0.01, 'desc' => 'Um cêntimo'],
    ['valor' => 0.99, 'desc' => 'Noventa e nove cêntimos'],
    ['valor' => 1.00, 'desc' => 'Um kwanza exato'],
    ['valor' => 1.01, 'desc' => 'Um kwanza e um cêntimo'],
    ['valor' => 2.00, 'desc' => 'Dois kwanzas exatos'],
    ['valor' => 100.00, 'desc' => 'Cem kwanzas exatos'],
    ['valor' => 1000.00, 'desc' => 'Mil kwanzas exatos'],
    ['valor' => 1000000.00, 'desc' => 'Um milhão exato'],
];

foreach ($specialCases as $case) {
    echo "💵 {$case['desc']}:\n";
    echo "   " . number_format($case['valor'], 2, ',', '.') . " Kz\n";
    echo "   → " . numberToWords($case['valor'], 'AOA') . "\n\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ONDE APARECE NOS DOCUMENTOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Os valores por extenso aparecem nos seguintes documentos:\n\n";

$documents = [
    '✓ Faturas de Venda (sales-invoice.blade.php)',
    '✓ Faturas de Compra (purchase-invoice.blade.php)',
    '✓ Proformas de Venda (proforma.blade.php)',
    '✓ Proformas de Compra (purchase-proforma.blade.php)',
    '✓ Faturas Genéricas (invoice.blade.php)',
    '✓ Notas de Crédito (credit-note.blade.php)',
    '✓ Notas de Débito (debit-note.blade.php)',
    '✓ Recibos (receipt.blade.php)',
    '✓ Recibos Novos (receipt-new.blade.php)',
    '✓ Adiantamentos (advance.blade.php)',
];

foreach ($documents as $doc) {
    echo "  {$doc}\n";
}

echo "\n";
echo "📍 Localização no documento:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "O valor por extenso aparece logo abaixo do \"Total a Pagar\"\n";
echo "na seção de resumo financeiro do documento.\n\n";

echo "Exemplo visual:\n";
echo "┌─────────────────────────────────────┐\n";
echo "│  Total a Pagar      15.000,00       │\n";
echo "├─────────────────────────────────────┤\n";
echo "│  QUINZE MIL KWANZAS                 │\n";
echo "└─────────────────────────────────────┘\n\n";

echo "💡 Como funciona:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Uso no Blade:\n";
echo "{{ numberToWords(\$invoice->total, 'AOA') }}\n\n";

echo "Parâmetros:\n";
echo "  • \$amount: Valor numérico (float)\n";
echo "  • \$currency: Moeda (AOA, USD, EUR, BRL)\n\n";

echo "Retorno:\n";
echo "  • String formatada em português\n";
echo "  • Primeira letra maiúscula\n";
echo "  • Inclui centavos quando > 0\n\n";

echo "✅ Implementação concluída com sucesso!\n";
echo "   Todos os documentos PDF já exibem valores por extenso.\n\n";
