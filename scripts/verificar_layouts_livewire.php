<?php

/**
 * Componentes Livewire usados como PÁGINA que não declaram layout.
 *
 * Um componente ligado directamente a uma rota é uma página inteira, e o
 * Livewire vai então procurar o layout `components.layouts.app`. Este projecto
 * não tem esse ficheiro: quem não declarar o seu rebenta com 500 assim que
 * alguém abrir o endereço.
 *
 * O que torna isto traiçoeiro é que os testes de componente — Livewire::test —
 * NÃO passam pelo layout. Um ecrã pode ter uma dúzia de testes verdes e estar
 * partido para quem o abre no browser. Foi o que aconteceu com o ecrã de SMS
 * às empresas: catorze testes verdes, e 500 ao abrir.
 *
 * Uso:  php scripts/verificar_layouts_livewire.php
 */

$raiz  = dirname(__DIR__);
$rotas = file_get_contents("{$raiz}/routes/web.php");

preg_match_all(
    '/Route::get\([^,]+,\s*\\\\?(App\\\\Livewire\\\\[A-Za-z0-9_\\\\]+)::class/',
    $rotas,
    $encontrados
);

$classes = array_unique($encontrados[1] ?? []);
$semLayout = [];

// Reconhece as três formas de o declarar: o atributo curto, o atributo
// totalmente qualificado, e o ->layout() encadeado no render. Procurar só pela
// primeira dava por partido um ecrã já arranjado com a segunda — e um
// verificador que mente é pior do que verificador nenhum.
$declaraLayout = '/#\[\\\\?(?:Livewire\\\\Attributes\\\\)?Layout\s*\(|->layout\s*\(/';

foreach ($classes as $classe) {
    $ficheiro = $raiz . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $classe) . '.php';

    if (!is_file($ficheiro)) {
        continue;
    }

    if (!preg_match($declaraLayout, file_get_contents($ficheiro))) {
        $semLayout[] = $classe;
    }
}

printf("Componentes-página ligados a rotas: %d%s", count($classes), PHP_EOL);
printf("Sem layout declarado: %d%s", count($semLayout), PHP_EOL);

foreach ($semLayout as $classe) {
    echo "  ⚠  {$classe}" . PHP_EOL;
}

if (!$semLayout) {
    echo "  ✓ Todos declaram o seu." . PHP_EOL;
}

exit($semLayout ? 1 : 0);
