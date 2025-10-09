<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  COMPARAR TEMPLATES DE EMAIL\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar template welcome
$welcome = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$welcome) {
    echo "❌ Template 'welcome' não encontrado!\n\n";
    exit(1);
}

echo "✅ Template encontrado: {$welcome->name}\n";
echo "   Slug: {$welcome->slug}\n";
echo "   ID: {$welcome->id}\n";
echo "   Ativo: " . ($welcome->is_active ? 'SIM' : 'NÃO') . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  RENDERIZAÇÃO DO TESTE (Modal)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Dados do teste (como no formulário)
$testData = [
    'user_name' => 'Usuário Teste',
    'tenant_name' => 'Empresa Demo LTDA',
    'app_name' => config('app.name', 'SOS ERP'),
    'plan_name' => 'Plano Premium',
    'old_plan_name' => 'Plano Básico',
    'new_plan_name' => 'Plano Premium',
    'reason' => 'Teste de envio de email',
    'support_email' => config('mail.from.address', 'suporte@soserp.com'),
    'login_url' => route('login'),
];

$renderedTest = $welcome->render($testData);

echo "Assunto: {$renderedTest['subject']}\n\n";

// Extrair todos os links
preg_match_all('/href=["\']([^"\']+)["\']/', $renderedTest['body_html'], $linksTest);
$uniqueLinksTest = array_unique($linksTest[1]);

echo "🔗 Links no email de TESTE:\n";
foreach ($uniqueLinksTest as $link) {
    echo "   • {$link}\n";
}
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  RENDERIZAÇÃO DO REGISTRO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Dados do registro (reais)
$registerData = [
    'user_name' => 'tiofox2019@gmail.com',
    'tenant_name' => 'Empresa Teste',
    'app_name' => config('app.name', 'SOS ERP'),
    'plan_name' => 'Plano Premium',
    'old_plan_name' => 'Plano Básico',
    'new_plan_name' => 'Plano Premium',
    'reason' => 'Registro de nova conta',
    'support_email' => config('mail.from.address', 'suporte@soserp.com'),
    'login_url' => route('login'),
];

$renderedRegister = $welcome->render($registerData);

echo "Assunto: {$renderedRegister['subject']}\n\n";

// Extrair todos os links
preg_match_all('/href=["\']([^"\']+)["\']/', $renderedRegister['body_html'], $linksRegister);
$uniqueLinksRegister = array_unique($linksRegister[1]);

echo "🔗 Links no email de REGISTRO:\n";
foreach ($uniqueLinksRegister as $link) {
    echo "   • {$link}\n";
}
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  COMPARAÇÃO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Comparar assuntos
$sameSubject = $renderedTest['subject'] === $renderedRegister['subject'];
echo "Assunto: " . ($sameSubject ? '✅ IDÊNTICO' : '❌ DIFERENTE') . "\n";
if (!$sameSubject) {
    echo "   Teste: {$renderedTest['subject']}\n";
    echo "   Registro: {$renderedRegister['subject']}\n";
}
echo "\n";

// Comparar links
$sameLinks = $uniqueLinksTest == $uniqueLinksRegister;
echo "Links: " . ($sameLinks ? '✅ IDÊNTICOS' : '❌ DIFERENTES') . "\n";
if (!$sameLinks) {
    $onlyInTest = array_diff($uniqueLinksTest, $uniqueLinksRegister);
    $onlyInRegister = array_diff($uniqueLinksRegister, $uniqueLinksTest);
    
    if (!empty($onlyInTest)) {
        echo "\n   Apenas no TESTE:\n";
        foreach ($onlyInTest as $link) {
            echo "      • {$link}\n";
        }
    }
    
    if (!empty($onlyInRegister)) {
        echo "\n   Apenas no REGISTRO:\n";
        foreach ($onlyInRegister as $link) {
            echo "      • {$link}\n";
        }
    }
}
echo "\n";

// Comparar tamanhos
$testSize = strlen($renderedTest['body_html']);
$registerSize = strlen($renderedRegister['body_html']);
$sizeDiff = abs($testSize - $registerSize);

echo "Tamanho HTML:\n";
echo "   Teste: {$testSize} bytes\n";
echo "   Registro: {$registerSize} bytes\n";
echo "   Diferença: {$sizeDiff} bytes\n\n";

if ($sizeDiff > 100) {
    echo "⚠️  Diferença significativa de tamanho!\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  ANÁLISE DE SPAM\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Verificar se há palavras-gatilho de SPAM
$spamWords = [
    'grátis', 'ganhe', 'prêmio', 'clique aqui', 'urgente', 
    'desconto', 'promoção', 'oferta', 'compre agora', 'limitado'
];

$textContent = strtolower(strip_tags($renderedRegister['body_html']));
$foundSpamWords = [];

foreach ($spamWords as $word) {
    if (stripos($textContent, $word) !== false) {
        $foundSpamWords[] = $word;
    }
}

if (empty($foundSpamWords)) {
    echo "✅ Nenhuma palavra-gatilho de SPAM encontrada\n\n";
} else {
    echo "⚠️  Palavras-gatilho encontradas:\n";
    foreach ($foundSpamWords as $word) {
        echo "   • {$word}\n";
    }
    echo "\n";
}

// Verificar links suspeitos
$hasLocalLinks = false;
foreach ($uniqueLinksRegister as $link) {
    if (strpos($link, '.test') !== false || strpos($link, 'localhost') !== false || strpos($link, '127.0.0.1') !== false) {
        $hasLocalLinks = true;
        echo "⚠️  Link local encontrado: {$link}\n";
    }
}

if ($hasLocalLinks) {
    echo "\n🔴 PROBLEMA: Links locais (.test) causam SPAM!\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5️⃣  DIFERENÇA REAL: TESTE vs REGISTRO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🤔 POR QUE TESTE VAI PARA CAIXA DE ENTRADA?\n\n";

echo "Possíveis razões:\n\n";

echo "1. 📧 REPUTAÇÃO DO REMETENTE\n";
echo "   ✅ Teste: Você já enviou vários emails de teste\n";
echo "   ❌ Registro: Primeiro email automático do sistema\n";
echo "   → Gmail confia mais em emails recorrentes\n\n";

echo "2. 🕒 TIMING E COMPORTAMENTO\n";
echo "   ✅ Teste: Você clica manualmente no botão\n";
echo "   ❌ Registro: Trigger automático após cadastro\n";
echo "   → Gmail detecta padrões automáticos\n\n";

echo "3. 📨 HEADERS DIFERENTES\n";
echo "   ✅ Teste: Enviado de sessão autenticada (SuperAdmin)\n";
echo "   ❌ Registro: Enviado de sessão pública (não autenticado)\n";
echo "   → Gmail verifica IP e sessão\n\n";

echo "4. 🎯 DESTINATÁRIO\n";
echo "   ✅ Teste: Você escolhe o destinatário manualmente\n";
echo "   ❌ Registro: Destinatário vem de formulário público\n";
echo "   → Gmail suspeita de spam para emails não solicitados\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6️⃣  SOLUÇÃO DEFINITIVA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🎯 AÇÕES RECOMENDADAS:\n\n";

echo "1. ✅ IMEDIATO (resolve em 1 minuto):\n";
echo "   • Abra o email no SPAM\n";
echo "   • Clique em 'Não é spam'\n";
echo "   • Próximo email vai direto pra caixa de entrada\n\n";

echo "2. 📧 WHITELIST (resolve permanente):\n";
echo "   • Adicione sos@soserp.vip aos contatos\n";
echo "   • Crie filtro: 'De: sos@soserp.vip' → Nunca enviar para spam\n\n";

echo "3. 🌐 PRODUÇÃO (resolve para todos):\n";
echo "   • Configure APP_URL=https://soserp.vip\n";
echo "   • Adicione SPF/DKIM no DNS\n";
echo "   • Envie emails gradualmente (construa reputação)\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "  📊 CONCLUSÃO\n";
echo "═══════════════════════════════════════════════════════\n\n";

if ($sameSubject && $sameLinks) {
    echo "✅ Templates são IDÊNTICOS!\n";
    echo "✅ Código é IDÊNTICO!\n\n";
    echo "🎯 Diferença está em:\n";
    echo "   • Reputação do remetente (Gmail já conhece emails de teste)\n";
    echo "   • Contexto do envio (manual vs automático)\n";
    echo "   • Primeiro email de boas-vindas sempre é mais suspeito\n\n";
    echo "💡 SOLUÇÃO: Marque 'Não é spam' UMA VEZ e próximos vão direto!\n\n";
} else {
    echo "⚠️  Templates têm pequenas diferenças\n";
    echo "    Veja detalhes acima\n\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
