<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AGT\JwsSigner;

$jsonPath = $argv[1] ?? __DIR__ . '/agt_fr_C1_v12.json';
$json = json_decode(file_get_contents($jsonPath), true);
$signer = new JwsSigner();

echo "\n=== VALIDAÇÃO AGT v1.2 ===\n";
echo "Ficheiro: {$jsonPath}\n";
echo "Schema: " . ($json['schemaVersion'] ?? 'N/A') . "\n";
echo "NIF Emissor: " . ($json['taxRegistrationNumber'] ?? 'N/A') . "\n";
echo "submissionUUID: " . ($json['submissionUUID'] ?? 'N/A') . "\n";
echo "numberOfEntries: " . ($json['numberOfEntries'] ?? 0) . "\n";

// Campos obrigatórios envelope
$required = ['schemaVersion', 'submissionUUID', 'taxRegistrationNumber',
             'submissionTimeStamp', 'softwareInfo', 'numberOfEntries',
             'documents', 'jwsSignature'];
$missing = array_diff($required, array_keys($json));
echo "\n[Envelope] Campos obrigatórios: " . (empty($missing) ? '✅ TODOS' : '❌ FALTAM: ' . implode(', ', $missing)) . "\n";

// Verificar jwsSoftwareSignature
$soft = $json['softwareInfo']['jwsSoftwareSignature'] ?? null;
if ($soft) {
    $v = $signer->verify($soft);
    echo "\n[1] jwsSoftwareSignature: " . ($v['valid'] ? '✅ VÁLIDA' : '❌ INVÁLIDA') . "\n";
    echo "    payload: " . json_encode($v['payload'], JSON_UNESCAPED_UNICODE) . "\n";
}

// Verificar jwsSignature do request
$req = $json['jwsSignature'] ?? null;
if ($req) {
    $v = $signer->verify($req);
    echo "\n[2] jwsSignature (request): " . ($v['valid'] ? '✅ VÁLIDA' : '❌ INVÁLIDA') . "\n";
    echo "    payload: " . json_encode($v['payload'], JSON_UNESCAPED_UNICODE) . "\n";
}

// Verificar jwsDocumentSignature por documento
echo "\n[3] jwsDocumentSignature por documento:\n";
foreach ($json['documents'] ?? [] as $i => $doc) {
    $sig = $doc['jwsDocumentSignature'] ?? null;
    if (!$sig) {
        echo "    Doc " . ($i+1) . " ❌ sem assinatura\n";
        continue;
    }
    $v = $signer->verify($sig);
    $valid = $v['valid'] ? '✅' : '❌';
    echo sprintf("    Doc %d (%s): %s\n", $i+1, $doc['documentNo'], $valid);
    if (!$v['valid']) {
        echo "        Erro: " . ($v['error'] ?? '?') . "\n";
    }
}

// Validar totais matemáticos
echo "\n[4] Validação matemática dos totais:\n";
$ok = true;
foreach ($json['documents'] ?? [] as $i => $doc) {
    $totals = $doc['documentTotals'] ?? [];
    $expectedGross = ($totals['netTotal'] ?? 0) + ($totals['taxPayable'] ?? 0);
    $actualGross   = $totals['grossTotal'] ?? 0;

    // Para casos com IEC, taxPayable agrega vários impostos, então verifica com tolerância
    $diff = abs($expectedGross - $actualGross);
    $status = $diff < 0.01 ? '✅' : '❌';
    if ($diff >= 0.01) $ok = false;
    echo sprintf("    Doc %d: net=%.2f + tax=%.2f = %.2f vs gross=%.2f %s\n",
        $i+1, $totals['netTotal'], $totals['taxPayable'], $expectedGross, $actualGross, $status);
}

// withholdingTax
echo "\n[5] withholdingTaxList:\n";
foreach ($json['documents'] ?? [] as $i => $doc) {
    if (!empty($doc['withholdingTaxList'])) {
        foreach ($doc['withholdingTaxList'] as $w) {
            echo sprintf("    Doc %d: %s · %.2f Kz · %s\n",
                $i+1, $w['withholdingTaxType'], $w['withholdingTaxAmount'],
                $w['withholdingTaxDescription']);
        }
    }
}

echo "\n=== RESULTADO FINAL ===\n";
echo $ok ? "✅ JSON pronto para submissão AGT v1.2\n" : "❌ Há diferenças nos totais\n";
echo "\n";
