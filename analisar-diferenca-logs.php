<?php

$logFile = __DIR__ . '/storage/logs/laravel.log';
$content = file_get_contents($logFile);

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  ANÁLISE DE DIFERENÇAS NOS LOGS\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Separar logs da modal e do registro
$lines = explode("\n", $content);

$modalLogs = [];
$registroLogs = [];
$currentSection = null;

foreach ($lines as $line) {
    if (strpos($line, '🔷 MODAL:') !== false) {
        $currentSection = 'modal';
    } elseif (strpos($line, '🔷 REGISTRO:') !== false) {
        $currentSection = 'registro';
    }
    
    if ($currentSection === 'modal' && !empty($line)) {
        $modalLogs[] = $line;
    } elseif ($currentSection === 'registro' && !empty($line)) {
        $registroLogs[] = $line;
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "LOGS DA MODAL (" . count($modalLogs) . " linhas)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($modalLogs as $log) {
    if (strpos($log, 'local.INFO') !== false || strpos($log, 'local.DEBUG') !== false) {
        // Extrair apenas a parte importante
        preg_match('/local\.(INFO|DEBUG): (.+)/', $log, $matches);
        if (isset($matches[2])) {
            echo "  " . $matches[2] . "\n";
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "LOGS DO REGISTRO (" . count($registroLogs) . " linhas)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($registroLogs as $log) {
    if (strpos($log, 'local.INFO') !== false || strpos($log, 'local.DEBUG') !== false) {
        preg_match('/local\.(INFO|DEBUG): (.+)/', $log, $matches);
        if (isset($matches[2])) {
            echo "  " . $matches[2] . "\n";
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "COMPARAÇÃO DE PASSOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$passosModal = [];
$passosRegistro = [];

foreach ($modalLogs as $log) {
    if (strpos($log, '📧') !== false || strpos($log, '✅') !== false || 
        strpos($log, '📝') !== false || strpos($log, '🚀') !== false) {
        preg_match('/local\.INFO: (.+?) ({"|\[)/', $log, $matches);
        if (isset($matches[1])) {
            $passosModal[] = trim($matches[1]);
        }
    }
}

foreach ($registroLogs as $log) {
    if (strpos($log, '📧') !== false || strpos($log, '✅') !== false || 
        strpos($log, '📝') !== false || strpos($log, '🚀') !== false) {
        preg_match('/local\.INFO: (.+?) ({"|\[)/', $log, $matches);
        if (isset($matches[1])) {
            $passosRegistro[] = trim($matches[1]);
        }
    }
}

$maxPassos = max(count($passosModal), count($passosRegistro));

printf("%-50s | %-50s\n", "MODAL", "REGISTRO");
echo str_repeat("─", 103) . "\n";

for ($i = 0; $i < $maxPassos; $i++) {
    $modal = $passosModal[$i] ?? '(vazio)';
    $registro = $passosRegistro[$i] ?? '(vazio)';
    
    $simbolo = ($modal === $registro) ? '✅' : '❌';
    
    printf("%s %-48s | %-48s\n", $simbolo, 
        substr($modal, 0, 48), 
        substr($registro, 0, 48)
    );
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "HEADERS DOS EMAILS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

preg_match_all('/local\.DEBUG: (From:.*?)(?=\[2025-|\z)/s', $content, $matches);
$headers = $matches[1] ?? [];

if (count($headers) >= 2) {
    echo "EMAIL 1 (MODAL):\n";
    echo "─────────────────────────────────────────────────────\n";
    preg_match('/From: (.+)/m', $headers[0], $from1);
    preg_match('/Message-ID: <(.+?)>/m', $headers[0], $msgid1);
    echo "From:       " . ($from1[1] ?? 'N/A') . "\n";
    echo "Message-ID: <" . ($msgid1[1] ?? 'N/A') . ">\n\n";
    
    echo "EMAIL 2 (REGISTRO):\n";
    echo "─────────────────────────────────────────────────────\n";
    preg_match('/From: (.+)/m', $headers[1], $from2);
    preg_match('/Message-ID: <(.+?)>/m', $headers[1], $msgid2);
    echo "From:       " . ($from2[1] ?? 'N/A') . "\n";
    echo "Message-ID: <" . ($msgid2[1] ?? 'N/A') . ">\n\n";
    
    echo "COMPARAÇÃO:\n";
    echo "  From: " . (($from1[1] ?? '') === ($from2[1] ?? '') ? '✅ IDÊNTICO' : '❌ DIFERENTE') . "\n";
    
    $domain1 = explode('@', $msgid1[1] ?? '')[1] ?? '';
    $domain2 = explode('@', $msgid2[1] ?? '')[1] ?? '';
    echo "  Domain: " . ($domain1 === $domain2 ? '✅ IDÊNTICO' : '❌ DIFERENTE') . "\n";
} else {
    echo "⚠️  Não foi possível capturar os headers\n";
}

echo "\n═══════════════════════════════════════════════════════\n\n";
