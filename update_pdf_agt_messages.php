<?php

/**
 * Script para adicionar mensagem AGT com hash em todos os PDFs
 * 
 * Execução: php update_pdf_agt_messages.php
 */

$templates = [
    'proforma.blade.php',
    'credit-note.blade.php',
    'debit-note.blade.php',
    'receipt.blade.php',
    'purchase-invoice.blade.php',
    'advance.blade.php',
];

$basePath = __DIR__ . '/resources/views/pdf/invoicing/';

foreach ($templates as $template) {
    $filePath = $basePath . $template;
    
    if (!file_exists($filePath)) {
        echo "❌ Arquivo não encontrado: $template\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Padrão antigo
    $oldPatterns = [
        '/<div class="agt-description">\s*Esta .*? foi processada.*?<\/div>/s',
        '/<div class="agt-description">\s*Documento processado.*?<\/div>/s',
    ];
    
    // Novo padrão com AGTHelper
    $newPattern = <<<'BLADE'
{{-- Mensagem AGT com Hash --}}
            @php
                $agtMessage = \App\Helpers\AGTHelper::getFooterMessage($invoice ?? $proforma ?? $receipt ?? $note);
            @endphp
            
            @if($agtMessage)
            <div class="agt-description">
                {{ $agtMessage }}
            </div>
            @endif
BLADE;
    
    // Substituir
    $updated = false;
    foreach ($oldPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newPattern, $content);
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        file_put_contents($filePath, $content);
        echo "✅ Atualizado: $template\n";
    } else {
        echo "⚠️  Padrão não encontrado em: $template\n";
    }
}

echo "\n🎉 Script concluído!\n";
echo "📝 Verifique os arquivos manualmente se necessário.\n";
