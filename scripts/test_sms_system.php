<?php
/**
 * Teste completo do sistema de SMS
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTE DO SISTEMA DE SMS ===\n\n";

use App\Services\SmsService;
use App\Models\SmsSetting;
use App\Models\User;
use App\Models\Tenant;

// 1. Verificar configuração SMS
echo "📝 1. Verificando configuração SMS...\n";
$setting = SmsSetting::getForTenant(null);

if ($setting) {
    echo "✅ Configuração encontrada\n";
    echo "   Provider: {$setting->provider}\n";
    echo "   Sender ID: {$setting->sender_id}\n";
    echo "   API URL: {$setting->api_url}\n";
    echo "   Token: " . substr($setting->api_token, 0, 20) . "...\n\n";
} else {
    echo "❌ Configuração não encontrada!\n";
    echo "   Execute: php artisan db:seed --class=SmsSettingSeeder\n\n";
    exit(1);
}

// 2. Teste de envio simples
echo "📝 2. Teste de envio de SMS simples...\n";
$smsService = new SmsService();

$testPhone = "+244939729902";
$testMessage = "Teste do sistema SMS - " . date('H:i:s');

echo "   Enviando para: {$testPhone}\n";
echo "   Mensagem: {$testMessage}\n";

$result = $smsService->send($testPhone, $testMessage, 'test', null, null);

if ($result['success']) {
    echo "✅ SMS enviado com sucesso!\n";
    echo "   Request ID: " . ($result['request_id'] ?? 'N/A') . "\n";
    echo "   Log ID: " . ($result['log_id'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ Erro ao enviar SMS\n";
    echo "   Erro: " . ($result['error'] ?? 'Desconhecido') . "\n\n";
}

// 3. Teste de envio de nova conta (simulado)
echo "📝 3. Teste de SMS de nova conta (simulado)...\n";

// Criar usuário temporário para teste
$testUser = new User();
$testUser->id = 999;
$testUser->name = "Teste User";
$testUser->email = "teste@teste.com";
$testUser->phone = $testPhone;
$testUser->exists = false; // Não salvar no BD

$testTenant = new Tenant();
$testTenant->id = 1;
$testTenant->name = "Empresa Teste";
$testTenant->exists = false;

$result = $smsService->sendNewAccountSms($testUser, "Senha123!", $testTenant);

if ($result['success']) {
    echo "✅ SMS de nova conta enviado com sucesso!\n";
    echo "   Request ID: " . ($result['request_id'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ Erro ao enviar SMS de nova conta\n";
    echo "   Erro: " . ($result['error'] ?? 'Desconhecido') . "\n\n";
}

// 4. Verificar logs
echo "📝 4. Últimos logs de SMS...\n";
$logs = \App\Models\SmsLog::orderBy('id', 'desc')->limit(5)->get();

if ($logs->count() > 0) {
    echo "   Total de logs: " . $logs->count() . "\n";
    foreach ($logs as $log) {
        $status = $log->status === 'sent' ? '✅' : '❌';
        echo "   {$status} {$log->recipient} - {$log->type} - " . $log->sent_at->format('H:i:s') . "\n";
    }
} else {
    echo "   Nenhum log encontrado\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Teste completo finalizado!\n";
echo "📅 " . date('d/m/Y H:i:s') . "\n\n";
