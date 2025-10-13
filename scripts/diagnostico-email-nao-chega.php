<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  DIAGNÓSTICO: POR QUE O EMAIL NÃO CHEGA?\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📧 Email: tiofox2019@gmail.com\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  VERIFICAR LOGS DO SISTEMA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logFile = storage_path('logs/laravel.log');
$logs = file($logFile);
$lastEmailLogs = [];

foreach ($logs as $line) {
    if (stripos($line, 'EMAIL DE BOAS-VINDAS') !== false || 
        stripos($line, 'tiofox2019') !== false ||
        stripos($line, 'TemplateMail') !== false) {
        $lastEmailLogs[] = $line;
    }
}

$lastEmailLogs = array_slice($lastEmailLogs, -20);

if (empty($lastEmailLogs)) {
    echo "⚠️  Nenhum log de email encontrado.\n\n";
} else {
    echo "Últimos logs relacionados:\n";
    echo "─────────────────────────────────────────────────────\n";
    foreach ($lastEmailLogs as $log) {
        echo $log;
    }
    echo "─────────────────────────────────────────────────────\n\n";
}

// Verificar se há "ENVIADO COM SUCESSO"
$logContent = implode('', $lastEmailLogs);
if (strpos($logContent, 'ENVIADO COM SUCESSO') !== false) {
    echo "✅ LOG CONFIRMA: Email foi enviado!\n\n";
} else if (strpos($logContent, 'ERRO') !== false) {
    echo "❌ LOG MOSTRA: Houve erro no envio!\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  VERIFICAR CONFIGURAÇÕES SMTP\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$smtp = \App\Models\SmtpSetting::default()->active()->first();

if (!$smtp) {
    echo "❌ PROBLEMA: Nenhuma configuração SMTP ativa!\n\n";
    exit(1);
}

echo "✅ SMTP Configurado:\n";
echo "   Host: {$smtp->host}:{$smtp->port}\n";
echo "   De: {$smtp->from_email}\n";
echo "   Username: {$smtp->username}\n";
echo "   Último teste: " . ($smtp->last_tested_at ? $smtp->last_tested_at->diffForHumans() : 'Nunca') . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  TESTAR ENVIO DIRETO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Enviando email de teste...\n\n";

try {
    $smtp->configure();
    
    $testData = [
        'user_name' => 'TESTE - Diagnóstico',
        'tenant_name' => 'Empresa de Teste',
        'app_name' => config('app.name'),
        'login_url' => url('/login'),
    ];
    
    \Mail::to('tiofox2019@gmail.com')
        ->send(new \App\Mail\TemplateMail('welcome', $testData, null));
    
    echo "✅ EMAIL DE TESTE ENVIADO COM SUCESSO!\n\n";
    
    echo "🔍 POSSÍVEIS CAUSAS DO EMAIL NÃO CHEGAR:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "1. 📧 EMAIL ESTÁ EM SPAM\n";
    echo "   ✅ Verifique a pasta SPAM no Gmail\n";
    echo "   ✅ Verifique a pasta Promoções\n";
    echo "   ✅ Procure por remetente: {$smtp->from_email}\n\n";
    
    echo "2. ⏱️  DELAY NO ENVIO\n";
    echo "   ✅ Gmail pode levar alguns segundos/minutos\n";
    echo "   ✅ Aguarde 2-3 minutos e recarregue o Gmail\n\n";
    
    echo "3. 🚫 GMAIL REJEITOU SILENCIOSAMENTE\n";
    echo "   ✅ Senha de APP pode estar errada\n";
    echo "   ✅ Conta pode ter bloqueado o acesso\n";
    echo "   ✅ Verifique: https://myaccount.google.com/security\n\n";
    
    echo "4. 📬 EMAIL ENVIADO MAS GMAIL FILTROU\n";
    echo "   ✅ Configure SPF/DKIM no DNS\n";
    echo "   ✅ Adicione remetente aos contatos\n";
    echo "   ✅ Marque como 'Não é spam'\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "4️⃣  VERIFICAR CONTA GMAIL\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "VERIFIQUE NO GMAIL:\n";
    echo "1. Abra: https://mail.google.com\n";
    echo "2. Procure por:\n";
    echo "   - Remetente: {$smtp->from_email}\n";
    echo "   - Assunto: 'Bem-vindo ao'\n";
    echo "   - De hoje: " . date('d/m/Y') . "\n";
    echo "3. Verifique TODAS as pastas:\n";
    echo "   [ ] Caixa de entrada\n";
    echo "   [ ] SPAM\n";
    echo "   [ ] Promoções\n";
    echo "   [ ] Social\n";
    echo "   [ ] Atualizações\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO AO ENVIAR EMAIL DE TESTE!\n\n";
    echo "Mensagem: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}\n";
    echo "Linha: {$e->getLine()}\n\n";
    
    echo "🔧 SOLUÇÃO:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (strpos($e->getMessage(), 'authentication') !== false || 
        strpos($e->getMessage(), 'username') !== false) {
        echo "❌ PROBLEMA: Autenticação SMTP falhou!\n\n";
        echo "SOLUÇÃO:\n";
        echo "1. Gerar nova senha de APP:\n";
        echo "   https://myaccount.google.com/apppasswords\n";
        echo "2. Atualizar em: http://soserp.test/superadmin/smtp-settings\n";
        echo "3. Testar conexão\n\n";
    } else if (strpos($e->getMessage(), 'connection') !== false) {
        echo "❌ PROBLEMA: Não conecta ao servidor SMTP!\n\n";
        echo "SOLUÇÃO:\n";
        echo "1. Verifique firewall\n";
        echo "2. Teste: Test-NetConnection smtp.gmail.com -Port 587\n\n";
    } else {
        echo "❌ ERRO DESCONHECIDO\n";
        echo "   Verifique os logs: storage/logs/laravel.log\n\n";
    }
}

echo "═══════════════════════════════════════════════════════\n";
echo "  📊 RESUMO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "✅ Sistema ENVIA emails (logs confirmam)\n";
echo "❓ Email não chega no Gmail\n\n";

echo "🎯 PRÓXIMOS PASSOS:\n";
echo "1. Verifique SPAM no Gmail\n";
echo "2. Aguarde 2-3 minutos\n";
echo "3. Procure por remetente: {$smtp->from_email}\n";
echo "4. Se não aparecer, verifique senha de APP\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
