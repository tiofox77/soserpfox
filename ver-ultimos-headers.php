<?php

$logFile = __DIR__ . '/storage/logs/laravel.log';
$content = file_get_contents($logFile);

// Pegar todos os blocos DEBUG que contêm headers
preg_match_all('/local\.DEBUG: (From:.*?)(?=\[2025-|\z)/s', $content, $matches);

// Pegar os últimos 2
$last2 = array_slice($matches[1], -2);

echo "\n═══════════════════════════════════════════════════════\n";
echo "  ÚLTIMOS 2 EMAILS ENVIADOS\n";
echo "═══════════════════════════════════════════════════════\n\n";

foreach ($last2 as $i => $headers) {
    $num = $i + 1;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "EMAIL {$num}:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Extrair apenas as primeiras linhas importantes
    preg_match('/From: (.+)/m', $headers, $from);
    preg_match('/To: (.+)/m', $headers, $to);
    preg_match('/Subject: (.+)/m', $headers, $subject);
    preg_match('/Message-ID: <(.+?)>/m', $headers, $msgid);
    
    echo "From:       " . ($from[1] ?? 'N/A') . "\n";
    echo "To:         " . ($to[1] ?? 'N/A') . "\n";
    echo "Subject:    " . ($subject[1] ?? 'N/A') . "\n";
    echo "Message-ID: <" . ($msgid[1] ?? 'N/A') . ">\n";
    
    // Extrair domínio do Message-ID
    if (isset($msgid[1])) {
        $parts = explode('@', $msgid[1]);
        $domain = $parts[1] ?? 'N/A';
        echo "Domínio:    @{$domain}\n";
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "COMPARAÇÃO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if (count($last2) >= 2) {
    // Comparar From
    preg_match('/From: (.+)/m', $last2[0], $from1);
    preg_match('/From: (.+)/m', $last2[1], $from2);
    
    echo "FROM:\n";
    echo "  Email 1: " . ($from1[1] ?? 'N/A') . "\n";
    echo "  Email 2: " . ($from2[1] ?? 'N/A') . "\n";
    echo "  → " . (($from1[1] ?? '') === ($from2[1] ?? '') ? '✅ IDÊNTICO' : '❌ DIFERENTE') . "\n\n";
    
    // Comparar domínio do Message-ID
    preg_match('/Message-ID: <(.+?)>/m', $last2[0], $msgid1);
    preg_match('/Message-ID: <(.+?)>/m', $last2[1], $msgid2);
    
    $domain1 = explode('@', $msgid1[1] ?? '')[1] ?? 'N/A';
    $domain2 = explode('@', $msgid2[1] ?? '')[1] ?? 'N/A';
    
    echo "MESSAGE-ID (domínio):\n";
    echo "  Email 1: @{$domain1}\n";
    echo "  Email 2: @{$domain2}\n";
    echo "  → " . ($domain1 === $domain2 ? '✅ IDÊNTICO' : '❌ DIFERENTE - PODE CAUSAR SPAM!') . "\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "VERIFIQUE NO GMAIL:\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "Email 1 (TESTE MODAL):    'Bem-vindo... TESTE MODAL'\n";
echo "Email 2 (TESTE REGISTRO): 'Bem-vindo... TESTE REGISTRO'\n\n";

echo "Se ambos estiverem no MESMO lugar:\n";
echo "  → Código está IDÊNTICO ✅\n\n";

echo "Se estiverem em lugares DIFERENTES:\n";
echo "  → Há diferença no código ❌\n";
echo "  → Veja as diferenças acima\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
