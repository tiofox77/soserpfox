<?php

/**
 * Traduções do cabeçalho e do painel de turno no PWA.
 *
 * "Kz" não entra aqui: o kwanza escreve-se igual nas três línguas, e é o
 * Intl.NumberFormat no browser que trata do formato dos milhares.
 *
 * Uso:  php scripts/lote14_pwa_turno_traducoes.php
 */

$raiz = dirname(__DIR__);

$novas = [
    'en' => [
        'Olá'              => 'Hello',
        'Turno aberto'     => 'Shift open',
        'Sem turno aberto' => 'No shift open',
        'Abra um turno no POS antes de começar a vender.' => 'Open a shift in the POS before you start selling.',
        'Total vendido'    => 'Total sold',
        'Dinheiro'         => 'Cash',
        'Ir para o POS'    => 'Go to the POS',
        'desde'            => 'since',
    ],
    'fr' => [
        'Olá'              => 'Bonjour',
        'Turno aberto'     => 'Poste ouvert',
        'Sem turno aberto' => 'Aucun poste ouvert',
        'Abra um turno no POS antes de começar a vender.' => 'Ouvrez un poste dans le POS avant de commencer à vendre.',
        'Total vendido'    => 'Total vendu',
        'Dinheiro'         => 'Espèces',
        'Ir para o POS'    => 'Aller au POS',
        'desde'            => 'depuis',
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
