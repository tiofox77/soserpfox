<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  DELETAR TEMPLATE DUPLICADO\n";
echo "═══════════════════════════════════════════════════════\n\n";

$template = \App\Models\EmailTemplate::find(6);

if ($template) {
    echo "📧 Template encontrado:\n";
    echo "   ID: {$template->id}\n";
    echo "   Slug: {$template->slug}\n";
    echo "   Nome: {$template->name}\n\n";
    
    $template->delete();
    echo "✅ Template [6] user_invitation deletado com sucesso!\n\n";
} else {
    echo "⚠️ Template não encontrado\n\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
