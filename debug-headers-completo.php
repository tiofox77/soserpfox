<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  DEBUG COMPLETO: TESTE vs REGISTRO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Simular contexto do TESTE (autenticado)
echo "1️⃣  SIMULANDO ENVIO DE TESTE (autenticado)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Login como admin
$admin = \App\Models\User::where('is_super_admin', true)->first();
if ($admin) {
    \Illuminate\Support\Facades\Auth::login($admin);
    echo "✅ Logado como: {$admin->name} (Super Admin)\n\n";
}

$smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
$smtpSetting->configure();

$sampleData = [
    'user_name' => 'Teste',
    'tenant_name' => 'Empresa',
    'app_name' => config('app.name'),
    'login_url' => route('login'),
];

// Capturar configurações do mailer ANTES do envio
echo "Config ANTES (TESTE):\n";
echo "  mail.default: " . config('mail.default') . "\n";
echo "  mail.from.address: " . config('mail.from.address') . "\n";
echo "  mail.from.name: " . config('mail.from.name') . "\n";
echo "  mail.mailers.smtp.host: " . config('mail.mailers.smtp.host') . "\n";
echo "  mail.mailers.smtp.port: " . config('mail.mailers.smtp.port') . "\n";
echo "  mail.mailers.smtp.username: " . config('mail.mailers.smtp.username') . "\n\n";

// Criar instância do TemplateMail
$mail = new \App\Mail\TemplateMail('welcome', $sampleData);

echo "TemplateMail criado:\n";
echo "  templateSlug: {$mail->templateSlug}\n";
echo "  tenantId: " . ($mail->tenantId ?? 'NULL') . "\n";
echo "  isSystemEmail: " . ($mail->isSystemEmail ? 'true' : 'false') . "\n\n";

// Fazer logout
\Illuminate\Support\Facades\Auth::logout();

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simular contexto do REGISTRO (NÃO autenticado)
echo "2️⃣  SIMULANDO ENVIO DE REGISTRO (não autenticado)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ Não autenticado (sessão pública)\n\n";

// Reconfigurar SMTP
$smtpSetting2 = \App\Models\SmtpSetting::getForTenant(null);
$smtpSetting2->configure();

// Capturar configurações do mailer ANTES do envio
echo "Config ANTES (REGISTRO):\n";
echo "  mail.default: " . config('mail.default') . "\n";
echo "  mail.from.address: " . config('mail.from.address') . "\n";
echo "  mail.from.name: " . config('mail.from.name') . "\n";
echo "  mail.mailers.smtp.host: " . config('mail.mailers.smtp.host') . "\n";
echo "  mail.mailers.smtp.port: " . config('mail.mailers.smtp.port') . "\n";
echo "  mail.mailers.smtp.username: " . config('mail.mailers.smtp.username') . "\n\n";

$mail2 = new \App\Mail\TemplateMail('welcome', $sampleData);

echo "TemplateMail criado:\n";
echo "  templateSlug: {$mail2->templateSlug}\n";
echo "  tenantId: " . ($mail2->tenantId ?? 'NULL') . "\n";
echo "  isSystemEmail: " . ($mail2->isSystemEmail ? 'true' : 'false') . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "3️⃣  COMPARAÇÃO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "DIFERENÇAS ENCONTRADAS:\n\n";

// Comparar configurações
$diffs = [];

if (config('mail.from.address') !== $smtpSetting->from_email) {
    $diffs[] = "mail.from.address diferente de SMTP configurado";
}

if (config('mail.from.name') !== $smtpSetting->from_name) {
    $diffs[] = "mail.from.name diferente de SMTP configurado";
}

if (empty($diffs)) {
    echo "✅ Nenhuma diferença óbvia encontrada\n\n";
} else {
    foreach ($diffs as $diff) {
        echo "⚠️  {$diff}\n";
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "4️⃣  VERIFICAR build() DO TemplateMail\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Chamar build() manualmente
try {
    $built = $mail2->build();
    echo "✅ build() executado com sucesso\n\n";
    
    // Verificar configuração DEPOIS do build
    echo "Config DEPOIS do build():\n";
    echo "  mail.from.address: " . config('mail.from.address') . "\n";
    echo "  mail.from.name: " . config('mail.from.name') . "\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro no build(): {$e->getMessage()}\n\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
