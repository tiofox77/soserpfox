<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  COMPARAR: EMAIL TESTE vs EMAIL REGISTRO\n";
echo "═══════════════════════════════════════════════════════\n\n";

$emailTo = 'tiofox2019@gmail.com';

// Limpar logs anteriores para capturar apenas os novos
$logFile = storage_path('logs/laravel.log');
$currentLogSize = filesize($logFile);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  ENVIANDO EMAIL PELO MÉTODO DE TESTE (Modal)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simular envio da modal de teste
$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();
$smtpSetting = \App\Models\SmtpSetting::getForTenant(null);

echo "Configurando SMTP (método teste)...\n";
$smtpSetting->configure();

$sampleData = [
    'user_name' => 'TESTE MODAL',
    'tenant_name' => 'Empresa Teste Modal',
    'app_name' => config('app.name', 'SOS ERP'),
    'login_url' => route('login'),
];

echo "Enviando email de TESTE...\n";
$mail1 = new \App\Mail\TemplateMail('welcome', $sampleData);
\Illuminate\Support\Facades\Mail::to($emailTo)->send($mail1);

echo "✅ Email de TESTE enviado!\n\n";

// Aguardar 2 segundos
sleep(2);

// Capturar headers do email de teste
$logContent1 = file_get_contents($logFile, false, null, $currentLogSize);
preg_match('/From:.*?\n.*?To:.*?\n.*?Subject:.*?\n.*?Message-ID:.*?\n/s', $logContent1, $headers1);
$headers1Text = $headers1[0] ?? 'Não capturado';

echo "Headers EMAIL TESTE:\n";
echo "─────────────────────────────────────────────────────\n";
echo $headers1Text;
echo "─────────────────────────────────────────────────────\n\n";

// Atualizar posição do log
$currentLogSize = filesize($logFile);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  ENVIANDO EMAIL PELO MÉTODO DE REGISTRO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simular envio do registro
$smtpSetting2 = \App\Models\SmtpSetting::getForTenant(null);

echo "Configurando SMTP (método registro)...\n";
$smtpSetting2->configure();

$emailData = [
    'user_name' => 'TESTE REGISTRO',
    'tenant_name' => 'Empresa Teste Registro',
    'app_name' => config('app.name', 'SOS ERP'),
    'login_url' => route('login'),
];

echo "Enviando email de REGISTRO...\n";
$mail2 = new \App\Mail\TemplateMail('welcome', $emailData);
\Illuminate\Support\Facades\Mail::to($emailTo)->send($mail2);

echo "✅ Email de REGISTRO enviado!\n\n";

// Capturar headers do email de registro
$logContent2 = file_get_contents($logFile, false, null, $currentLogSize);
preg_match('/From:.*?\n.*?To:.*?\n.*?Subject:.*?\n.*?Message-ID:.*?\n/s', $logContent2, $headers2);
$headers2Text = $headers2[0] ?? 'Não capturado';

echo "Headers EMAIL REGISTRO:\n";
echo "─────────────────────────────────────────────────────\n";
echo $headers2Text;
echo "─────────────────────────────────────────────────────\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  COMPARAÇÃO DETALHADA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Extrair campos individuais
preg_match('/From: (.+)/', $headers1Text, $from1);
preg_match('/From: (.+)/', $headers2Text, $from2);

preg_match('/Message-ID: <(.+?)>/', $headers1Text, $msgid1);
preg_match('/Message-ID: <(.+?)>/', $headers2Text, $msgid2);

preg_match('/Subject: (.+)/', $headers1Text, $subj1);
preg_match('/Subject: (.+)/', $headers2Text, $subj2);

echo "Campo: FROM\n";
echo "  Teste:    " . ($from1[1] ?? 'N/A') . "\n";
echo "  Registro: " . ($from2[1] ?? 'N/A') . "\n";
if (($from1[1] ?? '') === ($from2[1] ?? '')) {
    echo "  Status: ✅ IDÊNTICO\n\n";
} else {
    echo "  Status: ❌ DIFERENTE\n\n";
}

echo "Campo: MESSAGE-ID (domínio)\n";
$domain1 = explode('@', $msgid1[1] ?? '')[1] ?? 'N/A';
$domain2 = explode('@', $msgid2[1] ?? '')[1] ?? 'N/A';
echo "  Teste:    @{$domain1}\n";
echo "  Registro: @{$domain2}\n";
if ($domain1 === $domain2) {
    echo "  Status: ✅ IDÊNTICO\n\n";
} else {
    echo "  Status: ❌ DIFERENTE (PODE CAUSAR SPAM!)\n\n";
}

echo "Campo: SUBJECT\n";
echo "  Teste:    " . ($subj1[1] ?? 'N/A') . "\n";
echo "  Registro: " . ($subj2[1] ?? 'N/A') . "\n";
if (($subj1[1] ?? '') === ($subj2[1] ?? '')) {
    echo "  Status: ✅ IDÊNTICO\n\n";
} else {
    echo "  Status: ❌ DIFERENTE\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  VERIFICAR NO GMAIL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📧 2 emails foram enviados para: {$emailTo}\n\n";

echo "Verifique:\n";
echo "1. Email 1 (TESTE MODAL): 'Bem-vindo... TESTE MODAL'\n";
echo "   → Deve estar na CAIXA DE ENTRADA\n\n";

echo "2. Email 2 (TESTE REGISTRO): 'Bem-vindo... TESTE REGISTRO'\n";
echo "   → Verifique se está na CAIXA DE ENTRADA ou SPAM\n\n";

echo "Se ambos chegarem no mesmo lugar:\n";
echo "  ✅ Código está IDÊNTICO e funcionando!\n";
echo "  ✅ Problema é reputação/histórico do Gmail\n\n";

echo "Se chegarem em lugares diferentes:\n";
echo "  ❌ Ainda há diferença no código\n";
echo "  ❌ Verifique as diferenças nos headers acima\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
