<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  ANALISAR POR QUE EMAIL VAI PARA SPAM\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Pegar template welcome
$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "❌ Template welcome não encontrado!\n";
    exit(1);
}

$sampleData = [
    'user_name' => 'Usuário Teste',
    'tenant_name' => 'Empresa Teste',
    'app_name' => config('app.name'),
    'login_url' => route('login'),
];

$rendered = $template->render($sampleData);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  ANÁLISE DO CONTEÚDO DO EMAIL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Assunto: " . $rendered['subject'] . "\n\n";

// Verificar links no conteúdo
preg_match_all('/href="([^"]+)"/', $rendered['body_html'], $links);
$uniqueLinks = array_unique($links[1]);

echo "🔗 Links encontrados no email:\n";
echo "─────────────────────────────────────────────────────\n";
foreach ($uniqueLinks as $link) {
    $isLocal = strpos($link, 'localhost') !== false || strpos($link, '.test') !== false || strpos($link, '127.0.0.1') !== false;
    $icon = $isLocal ? '⚠️ ' : '✅';
    echo "   {$icon} {$link}\n";
}
echo "\n";

// Verificar domínios locais
$hasLocalLinks = false;
foreach ($uniqueLinks as $link) {
    if (strpos($link, 'localhost') !== false || strpos($link, '.test') !== false) {
        $hasLocalLinks = true;
        break;
    }
}

if ($hasLocalLinks) {
    echo "❌ PROBLEMA ENCONTRADO: Links locais detectados!\n";
    echo "   Gmail considera links .test ou localhost como SPAM\n\n";
} else {
    echo "✅ Nenhum link local encontrado\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  ANÁLISE DE DOMÍNIOS E URLS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "APP_URL atual: " . config('app.url') . "\n";
echo "Mail FROM: " . config('mail.from.address') . "\n";
echo "Mail FROM name: " . config('mail.from.name') . "\n\n";

// Verificar SMTP
$smtp = \App\Models\SmtpSetting::default()->active()->first();
if ($smtp) {
    echo "SMTP configurado:\n";
    echo "   Host: {$smtp->host}\n";
    echo "   Port: {$smtp->port}\n";
    echo "   From: {$smtp->from_email}\n";
    echo "   From Name: {$smtp->from_name}\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  FATORES QUE CAUSAM SPAM\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$spamFactors = [];

// Verificar links locais
if ($hasLocalLinks) {
    $spamFactors[] = [
        'problema' => '🔴 Links para .test ou localhost',
        'impacto' => 'ALTO',
        'solucao' => 'Usar domínio real em APP_URL'
    ];
}

// Verificar HTTP vs HTTPS
if (strpos(config('app.url'), 'http://') === 0) {
    $spamFactors[] = [
        'problema' => '🟡 APP_URL usa HTTP (não HTTPS)',
        'impacto' => 'MÉDIO',
        'solucao' => 'Configurar APP_URL=https://soserp.vip'
    ];
}

// Verificar domínio do FROM
$fromDomain = explode('@', $smtp->from_email ?? '')[1] ?? '';
$appDomain = parse_url(config('app.url'), PHP_URL_HOST);
if ($fromDomain !== $appDomain) {
    $spamFactors[] = [
        'problema' => '🟡 Domínio do FROM diferente do APP_URL',
        'impacto' => 'MÉDIO',
        'solucao' => "FROM: {$fromDomain} vs APP: {$appDomain} - Alinhar domínios"
    ];
}

// Verificar se template tem muito HTML
$htmlLength = strlen($rendered['body_html']);
$textLength = strlen(strip_tags($rendered['body_html']));
$htmlRatio = $textLength > 0 ? ($htmlLength / $textLength) : 999;

if ($htmlRatio > 10) {
    $spamFactors[] = [
        'problema' => '🟡 Muito HTML, pouco texto',
        'impacto' => 'BAIXO',
        'solucao' => 'Adicionar mais conteúdo textual'
    ];
}

if (empty($spamFactors)) {
    echo "✅ Nenhum fator óbvio de SPAM detectado\n\n";
} else {
    foreach ($spamFactors as $i => $factor) {
        echo ($i + 1) . ". {$factor['problema']}\n";
        echo "   Impacto: {$factor['impacto']}\n";
        echo "   Solução: {$factor['solucao']}\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  COMO SAIR DO SPAM\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🎯 AÇÕES RECOMENDADAS:\n\n";

echo "1. ✅ IMEDIATO - Marcar como 'Não é spam' no Gmail\n";
echo "   - Abre o email no Gmail\n";
echo "   - Clica em 'Não é spam'\n";
echo "   - Próximos emails já vão direto pra caixa de entrada\n\n";

echo "2. ⚙️  CONFIGURAÇÃO - Usar domínio real\n";
echo "   - Editar .env: APP_URL=https://soserp.vip\n";
echo "   - Reiniciar servidor: php artisan config:clear\n\n";

echo "3. 📧 DNS - Configurar SPF e DKIM\n";
echo "   - Adicionar registro SPF no DNS de soserp.vip\n";
echo "   - Configurar DKIM no servidor de email\n";
echo "   - Isso melhora MUITO a entregabilidade\n\n";

echo "4. 📬 REPUTAÇÃO - Enviar emails gradualmente\n";
echo "   - Não envie muitos emails de uma vez\n";
echo "   - Construa reputação do domínio gradualmente\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "  📊 RESUMO\n";
echo "═══════════════════════════════════════════════════════\n\n";

if ($hasLocalLinks) {
    echo "🔴 PRINCIPAL PROBLEMA: Links locais (.test)\n";
    echo "   Solução: Configurar APP_URL com domínio real\n\n";
} else {
    echo "🟢 Email está tecnicamente correto\n";
    echo "   Problema: Primeira vez enviando (sem reputação)\n";
    echo "   Solução: Marcar 'Não é spam' + tempo\n\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
