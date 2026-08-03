{{--
    Logótipo do cabeçalho dos documentos.

    Cascata: logótipo da empresa → logótipo do SOS ERP → caixa com "LOGO".
    Antes uma empresa sem logótipo carregado imprimia todos os documentos com a
    caixa cinzenta a dizer "LOGO", o que num documento fiscal entregue ao cliente
    parece um erro de sistema.

    Cada nível tenta primeiro embutir a imagem (data URI) e só depois o URL: os
    geradores de PDF (dompdf) não resolvem URLs de storage de forma fiável e a
    imagem saía em branco; no preview HTML o URL serve na mesma.

    Espera: $tenant
--}}
@php
    // Ficheiro no disco → data URI; ficheiro registado mas ausente → URL.
    $__comoImagem = function (?string $relativo) {
        if (blank($relativo)) {
            return null;
        }

        $caminho = storage_path('app/public/' . ltrim($relativo, '/'));
        if (is_file($caminho)) {
            return 'data:' . (mime_content_type($caminho) ?: 'image/png')
                . ';base64,' . base64_encode(file_get_contents($caminho));
        }

        return asset('storage/' . ltrim($relativo, '/'));
    };

    $__logo = $__comoImagem($tenant->logo ?? null);
    $__alt  = $tenant->name ?? 'Empresa';

    if (!$__logo) {
        // Logótipo do sistema (SuperAdmin › Definições)
        $__logo = app_logo_data_uri() ?: app_logo();
        $__alt  = 'SOS ERP';
    }
@endphp

@if($__logo)
    <img src="{{ $__logo }}" alt="{{ $__alt }}" class="logo-image" />
@else
    <div class="logo-fallback">LOGO</div>
@endif
