<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  VERIFICAR LAYOUT DOS TEMPLATES\n";
echo "═══════════════════════════════════════════════════════\n\n";

$templates = \App\Models\EmailTemplate::all();

foreach ($templates as $template) {
    echo "📧 [{$template->id}] {$template->slug}\n";
    
    // Verificar elementos do novo layout
    $hasNewGradient = strpos($template->body_html, '#667eea') !== false;
    $hasGradient = strpos($template->body_html, 'linear-gradient') !== false;
    $hasEmojis = strpos($template->body_html, '🎉') !== false || strpos($template->body_html, '👋') !== false;
    $hasTables = strpos($template->body_html, '<table') !== false;
    $hasLogo = strpos($template->body_html, 'logo.png') !== false;
    
    echo "   Gradiente novo (#667eea): " . ($hasNewGradient ? '✅' : '❌') . "\n";
    echo "   Gradiente qualquer: " . ($hasGradient ? '✅' : '❌') . "\n";
    echo "   Emojis: " . ($hasEmojis ? '✅' : '❌') . "\n";
    echo "   Tabelas HTML: " . ($hasTables ? '✅' : '❌') . "\n";
    echo "   Logo: " . ($hasLogo ? '✅' : '❌') . "\n";
    
    if ($hasNewGradient && $hasEmojis && $hasTables && $hasLogo) {
        echo "   ✅ USANDO LAYOUT NOVO QUE FUNCIONOU!\n";
    } else {
        echo "   ⚠️  NÃO ESTÁ USANDO LAYOUT NOVO\n";
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
