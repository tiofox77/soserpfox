# Preparar sistema para teste de registro
Write-Host "`n═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  PREPARAR SISTEMA PARA TESTE" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════`n" -ForegroundColor Cyan

# 1. Limpar logs antigos (opcional)
Write-Host "1️⃣  Limpar logs antigos? (S/N): " -NoNewline -ForegroundColor Yellow
$limpar = Read-Host

if ($limpar -eq "S" -or $limpar -eq "s") {
    Write-Host "   Limpando logs..." -ForegroundColor Yellow
    "" | Out-File "storage\logs\laravel.log" -Encoding UTF8
    Write-Host "   ✅ Logs limpos!`n" -ForegroundColor Green
} else {
    Write-Host "   ⏭️  Pulando limpeza de logs`n" -ForegroundColor Gray
}

# 2. Deletar usuário de teste
Write-Host "2️⃣  Limpar dados do usuário tiofox2019@gmail.com..." -ForegroundColor Yellow
php delete-user-tiofox.php

# 3. Verificar SMTP
Write-Host "`n3️⃣  Verificando configuração SMTP..." -ForegroundColor Yellow
php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$smtp = \App\Models\SmtpSetting::default()->active()->first();
if (\$smtp) {
    echo '   ✅ SMTP padrão configurado: ' . \$smtp->host . ':' . \$smtp->port . PHP_EOL;
} else {
    echo '   ❌ ERRO: Nenhum SMTP padrão configurado!' . PHP_EOL;
    exit(1);
}

\$template = \App\Models\EmailTemplate::where('slug', 'welcome')->active()->first();
if (\$template) {
    echo '   ✅ Template welcome existe e está ativo' . PHP_EOL;
} else {
    echo '   ❌ ERRO: Template welcome não encontrado!' . PHP_EOL;
    exit(1);
}
"

if ($LASTEXITCODE -ne 0) {
    Write-Host "`n❌ Configuração incompleta! Corrija os erros acima.`n" -ForegroundColor Red
    exit 1
}

Write-Host "`n═══════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "  ✅ SISTEMA PRONTO PARA TESTE!" -ForegroundColor Green
Write-Host "═══════════════════════════════════════════════════════`n" -ForegroundColor Green

Write-Host "📋 PRÓXIMOS PASSOS:" -ForegroundColor Cyan
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`n" -ForegroundColor Cyan

Write-Host "1. Abrir monitor de logs:" -ForegroundColor Yellow
Write-Host "   PowerShell> .\monitor-registro.ps1`n" -ForegroundColor White

Write-Host "2. Em outra janela, acessar:" -ForegroundColor Yellow
Write-Host "   http://soserp.test/register`n" -ForegroundColor White

Write-Host "3. Preencher o formulário com:" -ForegroundColor Yellow
Write-Host "   Email: tiofox2019@gmail.com" -ForegroundColor White
Write-Host "   Nome: [seu nome]" -ForegroundColor White
Write-Host "   Empresa: [nome empresa]`n" -ForegroundColor White

Write-Host "4. Clicar em 'Finalizar Cadastro'" -ForegroundColor Yellow
Write-Host "   Acompanhe os logs em tempo real!`n" -ForegroundColor White

Write-Host "5. Verificar email em:" -ForegroundColor Yellow
Write-Host "   https://mail.google.com`n" -ForegroundColor White

Write-Host "═══════════════════════════════════════════════════════`n" -ForegroundColor Green
