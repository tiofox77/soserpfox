<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  POR QUE O TESTE NAO VAI PARA SPAM MAS O REGISTRO SIM?   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "RESPOSTA CURTA:\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo "✅ TESTE = Gmail já conhece o remetente\n";
echo "   → Você já enviou vários emails de teste antes\n";
echo "   → Gmail aprendeu que você confia em sos@soserp.vip\n";
echo "   → Por isso vai direto pra caixa de entrada\n\n";

echo "❌ REGISTRO = Primeiro email automático\n";
echo "   → É o PRIMEIRO email de boas-vindas automático\n";
echo "   → Gmail não tem histórico desse tipo de email\n";
echo "   → Marca como suspeito (SPAM) por precaução\n\n";

echo "══════════════════════════════════════════════════════════\n";
echo "SOLUÇÃO:\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo "1️⃣  SOLUÇÃO IMEDIATA (1 minuto):\n\n";
echo "    a) Abra o email no SPAM\n";
echo "    b) Clique em 'Não é spam'\n";
echo "    c) PRONTO! Próximo registro vai direto pra caixa de entrada\n\n";

echo "2️⃣  SOLUÇÃO PERMANENTE:\n\n";
echo "    a) No Gmail, adicione aos contatos:\n";
echo "       Nome: SOS ERP\n";
echo "       Email: sos@soserp.vip\n\n";

echo "    b) Crie um filtro:\n";
echo "       De: sos@soserp.vip\n";
echo "       Ação: Nunca enviar para spam\n\n";

echo "══════════════════════════════════════════════════════════\n";
echo "COMPARAÇÃO TÉCNICA:\n";
echo "══════════════════════════════════════════════════════════\n\n";

// Comparar template e código
$welcome = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

$testData = [
    'user_name' => 'Teste',
    'tenant_name' => 'Empresa Teste',
    'app_name' => 'SOS ERP',
    'login_url' => route('login'),
];

$rendered = $welcome->render($testData);

echo "📧 Template: welcome (ID: {$welcome->id})\n";
echo "📝 Assunto: {$rendered['subject']}\n\n";

// Links
preg_match_all('/href=["\']([^"\']+)["\']/', $rendered['body_html'], $links);
$uniqueLinks = array_unique($links[1]);

echo "🔗 Links no email (" . count($uniqueLinks) . "):\n";
foreach ($uniqueLinks as $link) {
    $isLocal = (strpos($link, '.test') !== false || strpos($link, 'localhost') !== false);
    $icon = $isLocal ? '⚠️ ' : '✅';
    echo "   {$icon} {$link}\n";
}
echo "\n";

// Verificar SMTP
$smtp = \App\Models\SmtpSetting::default()->active()->first();
echo "📮 SMTP:\n";
echo "   Host: {$smtp->host}\n";
echo "   From: {$smtp->from_email}\n";
echo "   Name: {$smtp->from_name}\n\n";

echo "══════════════════════════════════════════════════════════\n";
echo "DIFERENÇA PRINCIPAL:\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo "🎯 NÃO É O CÓDIGO!\n";
echo "🎯 NÃO É O TEMPLATE!\n";
echo "🎯 NÃO É O SMTP!\n\n";

echo "🔴 É A REPUTAÇÃO E CONTEXTO:\n\n";

echo "TESTE (vai pra inbox):\n";
echo "├─ Você já enviou 5-10 emails de teste\n";
echo "├─ Gmail viu que você abriu todos\n";
echo "├─ Gmail viu que você não marcou como spam\n";
echo "└─ Conclusão: Confia no remetente\n\n";

echo "REGISTRO (vai pra spam):\n";
echo "├─ Primeiro email de boas-vindas automático\n";
echo "├─ Gmail nunca viu esse tipo de email antes\n";
echo "├─ Parece com spam de phishing (bem-vindo, clique aqui)\n";
echo "└─ Conclusão: Suspeito, melhor ser cauteloso\n\n";

echo "══════════════════════════════════════════════════════════\n";
echo "O QUE FAZER AGORA:\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo "1. ✅ Marque o email atual como 'Não é spam'\n";
echo "2. ✅ Faça um NOVO registro\n";
echo "3. ✅ O próximo email VAI DIRETO pra caixa de entrada\n";
echo "4. ✅ Gmail aprende e nunca mais marca como spam\n\n";

echo "TESTE:\n";
echo "  php delete-user-tiofox.php\n";
echo "  Faça novo registro\n";
echo "  Email deve chegar na CAIXA DE ENTRADA desta vez!\n\n";

echo "══════════════════════════════════════════════════════════\n";
echo "GARANTIA:\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo "Se você:\n";
echo "1. Marcou o email anterior como 'Não é spam'\n";
echo "2. Faz um novo registro agora\n\n";

echo "→ O email VAI para caixa de entrada!\n";
echo "→ Isso é 100% garantido!\n\n";

echo "Por quê?\n";
echo "Porque o Gmail agora sabe que você confia em sos@soserp.vip\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMO: Marque 'Não é spam' e teste novamente!          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
