<?php

/** Traduções do ecrã de carregamento do PWA.  Uso: php scripts/lote18_pwa_carregamento_traducoes.php */

$raiz = dirname(__DIR__);

$novas = [
    'en' => [
        'A preparar o ponto de venda' => 'Getting the point of sale ready',
        'Um momento…'                 => 'One moment…',
        'A carregar pela primeira vez. Com internet é mais rápido.'
            => 'Loading for the first time. It is faster with an internet connection.',
    ],
    'fr' => [
        'A preparar o ponto de venda' => 'Préparation du point de vente',
        'Um momento…'                 => 'Un instant…',
        'A carregar pela primeira vez. Com internet é mais rápido.'
            => 'Premier chargement. C\'est plus rapide avec une connexion internet.',
    ],
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
