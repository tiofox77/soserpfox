<?php

/**
 * Script de Teste - Registro de Usuário e Email de Boas-Vindas
 * 
 * Este script testa todo o processo de registro e envio de email
 * para identificar problemas no sistema de emails.
 * 
 * Uso: php test-register-email.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "====================================================\n";
echo "  TESTE DE REGISTRO E EMAIL DE BOAS-VINDAS\n";
echo "====================================================\n\n";

// Configurações do teste
$testEmail = 'tiofox2019@gmail.com';
$testName = 'Teste TioFox';
$testPassword = 'password123';
$testCompanyName = 'Empresa Teste TioFox';
$baseUrl = 'http://soserp.test';

echo "📧 Email de teste: {$testEmail}\n";
echo "👤 Nome: {$testName}\n";
echo "🏢 Empresa: {$testCompanyName}\n\n";

// 1. Verificar configurações de email no BANCO (SMTP Settings)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  VERIFICANDO CONFIGURAÇÕES SMTP NO BANCO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Verificar configurações SMTP no banco de dados
$smtpSettings = \App\Models\SmtpSetting::active()->get();

echo "Total de configurações SMTP no banco: " . $smtpSettings->count() . "\n\n";

if ($smtpSettings->isEmpty()) {
    echo "❌ ERRO: Nenhuma configuração SMTP encontrada no banco!\n\n";
    echo "SOLUÇÃO:\n";
    echo "1. Acesse: {$baseUrl}/superadmin/smtp-settings\n";
    echo "2. Clique em 'Nova Configuração SMTP'\n";
    echo "3. Preencha os dados do servidor SMTP\n";
    echo "4. Marque como 'Padrão' e 'Ativo'\n";
    echo "5. Teste a conexão\n\n";
    echo "OU execute via Tinker:\n";
    echo "php artisan tinker\n";
    echo "\\App\\Models\\SmtpSetting::create([\n";
    echo "    'host' => 'smtp.gmail.com',\n";
    echo "    'port' => 587,\n";
    echo "    'username' => 'tiofox2019@gmail.com',\n";
    echo "    'password' => 'xxxx-xxxx-xxxx-xxxx',\n";
    echo "    'encryption' => 'tls',\n";
    echo "    'from_email' => 'tiofox2019@gmail.com',\n";
    echo "    'from_name' => 'SOSERP',\n";
    echo "    'is_default' => true,\n";
    echo "    'is_active' => true,\n";
    echo "]);\n\n";
    exit(1);
}

// Mostrar configurações encontradas
foreach ($smtpSettings as $smtp) {
    echo "📧 Configuração SMTP encontrada:\n";
    echo "   ID: {$smtp->id}\n";
    echo "   Host: {$smtp->host}\n";
    echo "   Port: {$smtp->port}\n";
    echo "   Encryption: {$smtp->encryption}\n";
    echo "   Username: {$smtp->username}\n";
    echo "   From: {$smtp->from_email} ({$smtp->from_name})\n";
    echo "   Padrão: " . ($smtp->is_default ? 'Sim' : 'Não') . "\n";
    echo "   Ativo: " . ($smtp->is_active ? 'Sim' : 'Não') . "\n";
    echo "   Tenant: " . ($smtp->tenant_id ? "#{$smtp->tenant_id}" : 'Global') . "\n";
    echo "   Último teste: " . ($smtp->last_tested_at ? $smtp->last_tested_at->format('d/m/Y H:i') : 'Nunca') . "\n\n";
}

// Pegar configuração padrão
$defaultSmtp = \App\Models\SmtpSetting::default()->active()->first();

if (!$defaultSmtp) {
    echo "⚠️  AVISO: Nenhuma configuração SMTP marcada como PADRÃO!\n";
    echo "   Marque uma configuração como padrão em: {$baseUrl}/superadmin/smtp-settings\n\n";
    
    // Usar a primeira ativa
    $defaultSmtp = $smtpSettings->first();
    echo "   Usando a primeira configuração ativa: {$defaultSmtp->host}\n\n";
}

echo "✅ Configuração SMTP padrão: {$defaultSmtp->host}:{$defaultSmtp->port}\n\n";

// 2. Verificar template de email
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  VERIFICANDO TEMPLATE DE EMAIL 'welcome'\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "❌ ERRO: Template 'welcome' não encontrado no banco!\n";
    echo "   Execute: php artisan db:seed --class=EmailTemplateSeeder\n\n";
    exit(1);
}

echo "✅ Template encontrado: {$template->name}\n";
echo "   Assunto: {$template->subject}\n";
echo "   Ativo: " . ($template->is_active ? 'Sim' : 'Não') . "\n\n";

if (!$template->is_active) {
    echo "⚠️  AVISO: Template está INATIVO!\n";
    echo "   Ativando template...\n";
    $template->update(['is_active' => true]);
    echo "✅ Template ativado\n\n";
}

// 3. Limpar usuário de teste se existir
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  LIMPANDO DADOS ANTIGOS DE TESTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$existingUser = \App\Models\User::where('email', $testEmail)->first();
if ($existingUser) {
    echo "🗑️  Removendo usuário existente...\n";
    
    // Remover tenant associado
    if ($existingUser->tenant_id) {
        $tenant = \App\Models\Tenant::find($existingUser->tenant_id);
        if ($tenant) {
            echo "   Removendo tenant: {$tenant->name}\n";
            $tenant->delete();
        }
    }
    
    $existingUser->delete();
    echo "✅ Dados antigos removidos\n\n";
} else {
    echo "✅ Nenhum dado antigo encontrado\n\n";
}

// 4. Testar envio de email simples USANDO AS CONFIGURAÇÕES DO BANCO
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  TESTANDO ENVIO DE EMAIL SIMPLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Configurar SMTP usando as configurações do banco
    echo "Configurando SMTP do banco...\n";
    $defaultSmtp->configure();
    echo "✅ SMTP configurado: {$defaultSmtp->host}:{$defaultSmtp->port}\n\n";
    
    echo "Enviando email de teste...\n";
    \Illuminate\Support\Facades\Mail::raw('🧪 Este é um email de teste do sistema SOSERP.\n\nSe você recebeu este email, as configurações SMTP estão corretas!', function ($message) use ($testEmail) {
        $message->to($testEmail)
                ->subject('🧪 Teste de Email - SOSERP');
    });
    
    echo "✅ Email de teste enviado com sucesso!\n";
    echo "   Servidor: {$defaultSmtp->host}:{$defaultSmtp->port}\n";
    echo "   De: {$defaultSmtp->from_email}\n";
    echo "   Para: {$testEmail}\n";
    echo "   Verifique a caixa de entrada (e SPAM)\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO ao enviar email de teste:\n";
    echo "   {$e->getMessage()}\n\n";
    echo "SOLUÇÃO:\n";
    echo "1. Acesse: {$baseUrl}/superadmin/smtp-settings\n";
    echo "2. Edite a configuração SMTP\n";
    echo "3. Clique em 'Testar Conexão'\n";
    echo "4. Verifique as credenciais (senha de APP do Gmail!)\n";
    echo "5. Teste novamente\n\n";
    exit(1);
}

// 5. Criar usuário e tenant de teste
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5️⃣  CRIANDO USUÁRIO E TENANT DE TESTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

DB::beginTransaction();

try {
    // Criar tenant
    $tenant = \App\Models\Tenant::create([
        'name' => $testCompanyName,
        'slug' => \Illuminate\Support\Str::slug($testCompanyName . '-' . time()),
        'nif' => '999999999LA',
        'address' => 'Endereço de Teste',
        'phone' => '+244 939 779 902',
        'email' => $testEmail,
        'is_active' => true,
        'trial_ends_at' => now()->addDays(30),
    ]);
    
    echo "✅ Tenant criado: {$tenant->name} (ID: {$tenant->id})\n";
    
    // Criar usuário
    $user = \App\Models\User::create([
        'tenant_id' => $tenant->id,
        'name' => $testName,
        'email' => $testEmail,
        'password' => \Illuminate\Support\Facades\Hash::make($testPassword),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    
    echo "✅ Usuário criado: {$user->name} (ID: {$user->id})\n\n";
    
    DB::commit();
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERRO ao criar usuário/tenant:\n";
    echo "   {$e->getMessage()}\n\n";
    exit(1);
}

// 6. Enviar email de boas-vindas
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6️⃣  ENVIANDO EMAIL DE BOAS-VINDAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $emailData = [
        'user_name' => $user->name,
        'user_email' => $user->email,
        'tenant_name' => $tenant->name,
        'login_url' => $baseUrl . '/login',
        'trial_days' => 30,
        'support_email' => config('mail.from.address'),
    ];
    
    echo "Dados do email:\n";
    foreach ($emailData as $key => $value) {
        echo "  - {$key}: {$value}\n";
    }
    echo "\n";
    
    \Illuminate\Support\Facades\Mail::to($user->email)
        ->send(new \App\Mail\TemplateMail('welcome', $emailData, $tenant->id));
    
    echo "✅ EMAIL DE BOAS-VINDAS ENVIADO COM SUCESSO!\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO ao enviar email de boas-vindas:\n";
    echo "   {$e->getMessage()}\n";
    echo "   Arquivo: {$e->getFile()}\n";
    echo "   Linha: {$e->getLine()}\n\n";
    
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}

// 7. Verificar logs
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "7️⃣  VERIFICANDO LOGS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    
    echo "Últimas 20 linhas do log:\n";
    echo "─────────────────────────────────────────────────\n";
    foreach ($lastLines as $line) {
        echo $line;
    }
    echo "─────────────────────────────────────────────────\n\n";
} else {
    echo "⚠️  Arquivo de log não encontrado\n\n";
}

// 8. Resumo final
echo "====================================================\n";
echo "  ✅ TESTE CONCLUÍDO COM SUCESSO!\n";
echo "====================================================\n\n";

echo "📋 RESUMO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Configurações de email: OK\n";
echo "✅ Template 'welcome': OK\n";
echo "✅ Email de teste simples: ENVIADO\n";
echo "✅ Usuário criado: {$user->email}\n";
echo "✅ Tenant criado: {$tenant->name}\n";
echo "✅ Email de boas-vindas: ENVIADO\n\n";

echo "🔍 PRÓXIMOS PASSOS:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. Verifique a caixa de entrada de: {$testEmail}\n";
echo "2. Verifique a pasta de SPAM/Lixo eletrônico\n";
echo "3. Se não recebeu, verifique:\n";
echo "   - Configurações SMTP no .env\n";
echo "   - Logs do servidor de email\n";
echo "   - Firewall/Portas bloqueadas\n";
echo "4. Teste fazer login com:\n";
echo "   Email: {$testEmail}\n";
echo "   Senha: {$testPassword}\n\n";

echo "📁 Logs disponíveis em:\n";
echo "   {$logFile}\n\n";

echo "🧹 Para limpar dados de teste:\n";
echo "   php artisan tinker\n";
echo "   User::where('email', '{$testEmail}')->first()->delete();\n";
echo "   Tenant::where('email', '{$testEmail}')->first()->delete();\n\n";

echo "====================================================\n\n";
