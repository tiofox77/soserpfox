<?php

/**
 * Traduções das etiquetas de produto no changelog.
 *
 * "Offline" fica Offline nas três línguas: é a palavra que os clientes já usam
 * para o modo sem internet, e traduzi-la obrigava a reaprender o nome.
 *
 * Uso:  php scripts/lote13_changelog_traducoes.php
 */

$raiz = dirname(__DIR__);

$novas = [
    'en' => ['Web' => 'Web', 'Offline' => 'Offline', 'Web + Offline' => 'Web + Offline'],
    'fr' => ['Web' => 'Web', 'Offline' => 'Hors ligne', 'Web + Offline' => 'Web + hors ligne'],
];

foreach ($novas as $lingua => $itens) {
    $caminho = "{$raiz}/lang/{$lingua}.json";
    $dicionario = json_decode(file_get_contents($caminho), true) ?: [];
    $acrescentadas = 0;

    foreach ($itens as $pt => $traduzida) {
        if (isset($dicionario[$pt])) {
            continue;
        }

        $dicionario[$pt] = $traduzida;
        $acrescentadas++;
    }

    ksort($dicionario, SORT_NATURAL | SORT_FLAG_CASE);
    file_put_contents(
        $caminho,
        json_encode($dicionario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    printf("lang/%s.json: +%d (total %d)\n", $lingua, $acrescentadas, count($dicionario));
}
