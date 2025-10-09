<?php

/**
 * DIAGNÓSTICO COMPLETO - POR QUE O EMAIL NÃO CHEGA NO REGISTRO?
 * 
 * Este script simula exatamente o que acontece no RegisterWizard
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  DIAGNÓSTICO COMPLETO - EMAIL DE REGISTRO\n";
echo "═══════════════════════════════════════════════════════\n\n";

$testEmail = 'tiofox2019@gmail.com';

// PASSO 1: Verificar SMTP Settings no banco
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  VERIFICANDO CONFIGURAÇÕES SMTP NO BANCO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$smtpSettings = \App\Models\SmtpSetting::all();

if ($smtpSettings->isEmpty()) {
    echo "❌ PROBLEMA 1: NENHUMA CONFIGURAÇÃO SMTP NO BANCO!\n\n";
    echo "📍 SOLUÇÃO:\n";
    echo "   1. Acesse: http://soserp.test/superadmin/smtp-settings\n";
    echo "   2. Clique em 'Nova Configuração'\n";
    echo "   3. Preencha:\n";
    echo "      - Host: smtp.gmail.com\n";
    echo "      - Port: 587\n";
    echo "      - Username: tiofox2019@gmail.com\n";
    echo "      - Password: [Senha de APP do Gmail]\n";
    echo "      - Encryption: TLS\n";
    echo "      - From Email: tiofox2019@gmail.com\n";
    echo "      - From Name: SOSERP\n";
    echo "      ✓ Marcar como Padrão\n";
    echo "      ✓ Marcar como Ativo\n\n";
    echo "   OU execute via Tinker:\n\n";
    echo "   php artisan tinker\n\n";
    echo "   \\App\\Models\\SmtpSetting::create([\n";
    echo "       'host' => 'smtp.gmail.com',\n";
    echo "       'port' => 587,\n";
    echo "       'username' => 'tiofox2019@gmail.com',\n";
    echo "       'password' => 'sua-senha-de-app-aqui',\n";
    echo "       'encryption' => 'tls',\n";
    echo "       'from_email' => 'tiofox2019@gmail.com',\n";
    echo "       'from_name' => 'SOSERP',\n";
    echo "       'is_default' => true,\n";
    echo "       'is_active' => true,\n";
    echo "   ]);\n\n";
    
    echo "PARE AQUI! Configure o SMTP primeiro.\n\n";
    exit(1);
}

echo "✅ Configurações SMTP encontradas: " . $smtpSettings->count() . "\n\n";

// Mostrar cada configuração
foreach ($smtpSettings as $smtp) {
    $status = $smtp->is_active ? '🟢 ATIVA' : '🔴 INATIVA';
    $default = $smtp->is_default ? '⭐ PADRÃO' : '';
    
    echo "📧 Configuração #{$smtp->id} {$status} {$default}\n";
    echo "   Host: {$smtp->host}:{$smtp->port}\n";
    echo "   Encryption: " . strtoupper($smtp->encryption) . "\n";
    echo "   Username: {$smtp->username}\n";
    echo "   From: {$smtp->from_email} ({$smtp->from_name})\n";
    echo "   Tenant: " . ($smtp->tenant_id ? "#{$smtp->tenant_id}" : "Global") . "\n";
    
    // Tentar descriptografar senha
    try {
        $password = $smtp->password; // Usa o accessor que descriptografa
        echo "   Senha: " . (strlen($password) > 0 ? str_repeat('*', strlen($password)) . " (" . strlen($password) . " caracteres)" : "❌ VAZIA!") . "\n";
    } catch (\Exception $e) {
        echo "   Senha: ❌ ERRO AO DESCRIPTOGRAFAR!\n";
    }
    
    if ($smtp->last_tested_at) {
        echo "   Último teste: {$smtp->last_tested_at->format('d/m/Y H:i')}\n";
    } else {
        echo "   Último teste: ⚠️  Nunca testado\n";
    }
    echo "\n";
}

// Verificar se tem padrão ativa
$defaultSmtp = \App\Models\SmtpSetting::default()->active()->first();

if (!$defaultSmtp) {
    echo "❌ PROBLEMA 2: NENHUMA CONFIGURAÇÃO MARCADA COMO PADRÃO E ATIVA!\n\n";
    echo "📍 SOLUÇÃO:\n";
    echo "   1. Acesse: http://soserp.test/superadmin/smtp-settings\n";
    echo "   2. Edite uma configuração\n";
    echo "   3. Marque ✓ Padrão\n";
    echo "   4. Marque ✓ Ativo\n";
    echo "   5. Salve\n\n";
    
    // Tentar pegar a primeira ativa
    $defaultSmtp = \App\Models\SmtpSetting::active()->first();
    
    if (!$defaultSmtp) {
        echo "❌ PROBLEMA 3: NENHUMA CONFIGURAÇÃO ATIVA!\n";
        echo "   Ative pelo menos uma configuração.\n\n";
        exit(1);
    }
    
    echo "⚠️  Usando a primeira configuração ativa como fallback.\n\n";
}

echo "✅ Configuração SMTP padrão: {$defaultSmtp->host}:{$defaultSmtp->port}\n\n";

// PASSO 2: Verificar Template
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  VERIFICANDO TEMPLATE 'welcome'\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "❌ PROBLEMA 4: TEMPLATE 'welcome' NÃO EXISTE!\n\n";
    echo "📍 SOLUÇÃO:\n";
    echo "   php artisan db:seed --class=EmailTemplateSeeder\n\n";
    exit(1);
}

echo "✅ Template encontrado: {$template->name}\n";
echo "   Assunto: {$template->subject}\n";
echo "   Ativo: " . ($template->is_active ? 'Sim' : 'Não') . "\n\n";

if (!$template->is_active) {
    echo "❌ PROBLEMA 5: TEMPLATE ESTÁ INATIVO!\n\n";
    echo "📍 SOLUÇÃO:\n";
    echo "   Ativando template...\n";
    $template->update(['is_active' => true]);
    echo "   ✅ Template ativado!\n\n";
}

// PASSO 3: Simular exatamente o envio do RegisterWizard
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  SIMULANDO ENVIO EXATO DO REGISTER WIZARD\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Criar dados de teste exatamente como o RegisterWizard
$emailData = [
    'user_name' => 'Teste TioFox',
    'tenant_name' => 'Empresa Teste',
    'app_name' => config('app.name', 'SOS ERP'),
    'login_url' => route('login'),
];

echo "Dados do email (exatamente como RegisterWizard):\n";
foreach ($emailData as $key => $value) {
    echo "  - {$key}: {$value}\n";
}
echo "\n";

// Simular o envio EXATAMENTE como RegisterWizard faz
echo "Tentando enviar email exatamente como RegisterWizard...\n\n";

try {
    \Log::info('===== TESTE: INICIANDO ENVIO DE EMAIL =====', [
        'user_email' => $testEmail,
        'template' => 'welcome',
    ]);
    
    // EXATAMENTE como na linha 622-623 do RegisterWizard.php
    \Illuminate\Support\Facades\Mail::to($testEmail)
        ->send(new \App\Mail\TemplateMail('welcome', $emailData, null));
    
    \Log::info('===== TESTE: EMAIL ENVIADO COM SUCESSO! =====');
    
    echo "✅ EMAIL ENVIADO COM SUCESSO!\n\n";
    echo "📧 Servidor usado: {$defaultSmtp->host}:{$defaultSmtp->port}\n";
    echo "📧 De: {$defaultSmtp->from_email}\n";
    echo "📧 Para: {$testEmail}\n\n";
    echo "🔍 VERIFIQUE:\n";
    echo "   1. Caixa de entrada de {$testEmail}\n";
    echo "   2. Pasta de SPAM\n";
    echo "   3. Pasta de Promoções (Gmail)\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO AO ENVIAR EMAIL!\n\n";
    echo "Mensagem: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}\n";
    echo "Linha: {$e->getLine()}\n\n";
    
    echo "Stack Trace:\n";
    echo "─────────────────────────────────────────────────────\n";
    echo $e->getTraceAsString();
    echo "\n─────────────────────────────────────────────────────\n\n";
    
    \Log::error('===== TESTE: ERRO AO ENVIAR EMAIL =====', [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
    ]);
    
    // Diagnóstico específico do erro
    echo "🔍 DIAGNÓSTICO DO ERRO:\n\n";
    
    $errorMsg = strtolower($e->getMessage());
    
    if (strpos($errorMsg, 'authentication') !== false || strpos($errorMsg, 'username') !== false || strpos($errorMsg, 'password') !== false) {
        echo "❌ PROBLEMA: ERRO DE AUTENTICAÇÃO SMTP\n\n";
        echo "📍 POSSÍVEIS CAUSAS:\n";
        echo "   1. Senha incorreta (deve ser Senha de APP do Gmail, não a senha normal!)\n";
        echo "   2. Username incorreto\n";
        echo "   3. Conta Gmail sem autenticação de 2 fatores ativada\n\n";
        echo "📍 SOLUÇÃO:\n";
        echo "   1. Acesse: https://myaccount.google.com/apppasswords\n";
        echo "   2. Gere uma senha de app para 'SOSERP'\n";
        echo "   3. Copie a senha (remova espaços)\n";
        echo "   4. Acesse: http://soserp.test/superadmin/smtp-settings\n";
        echo "   5. Edite a configuração e cole a nova senha\n";
        echo "   6. Teste a conexão\n\n";
        
    } elseif (strpos($errorMsg, 'connection') !== false || strpos($errorMsg, 'refused') !== false) {
        echo "❌ PROBLEMA: NÃO CONSEGUE CONECTAR AO SERVIDOR SMTP\n\n";
        echo "📍 POSSÍVEIS CAUSAS:\n";
        echo "   1. Porta bloqueada pelo firewall\n";
        echo "   2. Host incorreto\n";
        echo "   3. Servidor SMTP fora do ar\n\n";
        echo "📍 SOLUÇÃO:\n";
        echo "   1. Testar conexão: Test-NetConnection smtp.gmail.com -Port 587\n";
        echo "   2. Verificar firewall do Windows\n";
        echo "   3. Tentar porta 465 com SSL ao invés de 587 TLS\n\n";
        
    } elseif (strpos($errorMsg, 'template') !== false) {
        echo "❌ PROBLEMA: ERRO NO TEMPLATE\n\n";
        echo "   Execute: php artisan db:seed --class=EmailTemplateSeeder\n\n";
        
    } else {
        echo "❌ PROBLEMA: ERRO DESCONHECIDO\n\n";
        echo "   Verifique os logs: storage/logs/laravel.log\n\n";
    }
    
    exit(1);
}

// PASSO 4: Verificar logs
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  VERIFICANDO LOGS DO SISTEMA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logFile = storage_path('logs/laravel.log');

if (file_exists($logFile)) {
    $lines = file($logFile);
    $emailLines = array_filter($lines, function($line) {
        return stripos($line, 'email') !== false || 
               stripos($line, 'mail') !== false || 
               stripos($line, 'smtp') !== false;
    });
    
    $lastEmailLines = array_slice($emailLines, -10);
    
    if (!empty($lastEmailLines)) {
        echo "Últimas 10 linhas relacionadas a email:\n";
        echo "─────────────────────────────────────────────────────\n";
        foreach ($lastEmailLines as $line) {
            echo $line;
        }
        echo "─────────────────────────────────────────────────────\n\n";
    } else {
        echo "⚠️  Nenhuma linha de log relacionada a email encontrada.\n\n";
    }
} else {
    echo "⚠️  Arquivo de log não encontrado.\n\n";
}

// RESUMO FINAL
echo "═══════════════════════════════════════════════════════\n";
echo "  ✅ DIAGNÓSTICO CONCLUÍDO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📋 CHECKLIST:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Configuração SMTP no banco: SIM\n";
echo "✅ Configuração marcada como padrão: SIM\n";
echo "✅ Configuração ativa: SIM\n";
echo "✅ Template 'welcome' existe: SIM\n";
echo "✅ Template 'welcome' ativo: SIM\n";
echo "✅ Email enviado com sucesso: SIM\n\n";

echo "🎯 PRÓXIMO PASSO:\n";
echo "   1. Verifique o email em: {$testEmail}\n";
echo "   2. Verifique a pasta de SPAM\n";
echo "   3. Se não receber, o problema é no Gmail/SMTP\n";
echo "   4. Teste fazer um registro real em: http://soserp.test/register\n\n";

echo "📁 Logs disponíveis em:\n";
echo "   {$logFile}\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
