<?php

/**
 * Traduções do NIF no cartão da empresa.
 *
 * Uso:  php scripts/lote17_nif_no_cartao_traducoes.php
 */

$raiz = dirname(__DIR__);

$novas = [
    'en' => [
        'NIF'                     => 'NIF',
        'Não é NIF de empresa'    => 'Not a company NIF',
        'por preencher'           => 'not filled in',
    ],
    'fr' => [
        'NIF'                     => 'NIF',
        'Não é NIF de empresa'    => 'Pas un NIF d\'entreprise',
        'por preencher'           => 'non renseigné',
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
