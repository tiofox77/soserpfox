<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$template = \App\Models\EmailTemplate::where('slug', 'welcome')->first();

if (!$template) {
    echo "Template welcome não encontrado!\n";
    exit;
}

echo "═══════════════════════════════════════════════════════\n";
echo "  TEMPLATE WELCOME\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "ID: {$template->id}\n";
echo "Slug: {$template->slug}\n";
echo "Subject: {$template->subject}\n\n";

echo "BODY HTML:\n";
echo "─────────────────────────────────────────────────────\n";
echo $template->body_html;
echo "\n─────────────────────────────────────────────────────\n\n";

echo "Tamanho: " . strlen($template->body_html) . " caracteres\n\n";

// Verificar palavras suspeitas
$suspiciousWords = ['free', 'click here', 'urgent', 'winner', 'prize', 'guarantee', 'money', 'cash', 'bonus'];
$found = [];

foreach ($suspiciousWords as $word) {
    if (stripos($template->body_html, $word) !== false) {
        $found[] = $word;
    }
}

if (!empty($found)) {
    echo "⚠️  Palavras suspeitas encontradas: " . implode(', ', $found) . "\n\n";
} else {
    echo "✅ Nenhuma palavra suspeita comum encontrada\n\n";
}
