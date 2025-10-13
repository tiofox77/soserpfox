<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TEMPLATES DE EMAIL DO SISTEMA\n";
echo "═══════════════════════════════════════════════════════\n\n";

$templates = \App\Models\EmailTemplate::orderBy('id')->get();

foreach ($templates as $template) {
    $status = $template->is_active ? '✅' : '❌';
    echo "{$status} [{$template->id}] {$template->slug}\n";
    echo "   Nome: {$template->name}\n";
    echo "   Subject: {$template->subject}\n";
    echo "   Descrição: {$template->description}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "Total de templates: {$templates->count()}\n";
echo "═══════════════════════════════════════════════════════\n\n";
