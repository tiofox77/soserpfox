<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  COMPARAR: default() vs getForTenant(null)               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$smtp1 = \App\Models\SmtpSetting::default()->active()->first();
$smtp2 = \App\Models\SmtpSetting::getForTenant(null);

echo "Método 1: SmtpSetting::default()->active()->first()\n";
if ($smtp1) {
    echo "   ID: {$smtp1->id}\n";
    echo "   Host: {$smtp1->host}\n";
    echo "   From: {$smtp1->from_email}\n";
    echo "   Is Default: " . ($smtp1->is_default ? 'SIM' : 'NÃO') . "\n\n";
} else {
    echo "   NULL\n\n";
}

echo "Método 2: SmtpSetting::getForTenant(null)\n";
if ($smtp2) {
    echo "   ID: {$smtp2->id}\n";
    echo "   Host: {$smtp2->host}\n";
    echo "   From: {$smtp2->from_email}\n";
    echo "   Is Default: " . ($smtp2->is_default ? 'SIM' : 'NÃO') . "\n\n";
} else {
    echo "   NULL\n\n";
}

if ($smtp1 && $smtp2) {
    if ($smtp1->id === $smtp2->id) {
        echo "✅ SÃO O MESMO SMTP!\n\n";
    } else {
        echo "❌ SÃO SMTP DIFERENTES!\n";
        echo "   Isso pode causar problemas!\n\n";
    }
}

echo "╚════════════════════════════════════════════════════════════╝\n\n";

// AGORA A PARTE CRUCIAL: VERIFICAR QUANDO configure() É CHAMADO
echo "═══════════════════════════════════════════════════════\n";
echo "  CRUCIAL: TemplateMail chama configure() DENTRO!\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "RegisterWizard atual:\n";
echo "  1. Linha 653: \$smtpSetting->configure()  ← PRIMEIRA VEZ\n";
echo "  2. Linha 681: new TemplateMail()\n";
echo "  3. Linha 682: Mail::to()->send()\n";
echo "  4. Laravel chama build() interno\n";
echo "  5. Linha 131 TemplateMail: configure()  ← SEGUNDA VEZ\n\n";

echo "❌ PROBLEMA: Configurando DUAS VEZES!\n";
echo "❌ A segunda vez pode sobrescrever com algo errado!\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "  SOLUÇÃO: NÃO chamar configure() antes!\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "Deixe o TemplateMail configurar sozinho!\n";
echo "REMOVER linha 653 do RegisterWizard:\n";
echo "  // $smtpSetting->configure();  ← DELETAR ISSO\n\n";

echo "Por quê?\n";
echo "  • TemplateMail JÁ configura no build()\n";
echo "  • Configurar antes pode causar conflito\n";
echo "  • Teste funciona porque faz a mesma coisa\n\n";

echo "═══════════════════════════════════════════════════════\n\n";
