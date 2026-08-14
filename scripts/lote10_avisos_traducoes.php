<?php

/**
 * Traduções do painel de avisos da plataforma.
 *
 * Notas que não são óbvias:
 *
 *   · "SOS ERP" é o nome do produto e não se traduz;
 *
 *   · "Lido" é a etiqueta de um aviso já dispensado na barra. Em inglês Read
 *     é ambíguo entre o passado e o infinitivo, mas é o que toda a gente usa
 *     numa etiqueta de estado e o contexto resolve-o;
 *
 *   · "até" aparece antes de uma data ("até 21/08/2026 10:21"), não é a
 *     preposição de lugar.
 *
 * Uso:  php scripts/lote10_avisos_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    'Avisos da plataforma'            => 'Platform notices',
    'Comunicações da equipa SOS ERP'  => 'Messages from the SOS ERP team',
    'Lido'                            => 'Read',
    'Saber mais'                      => 'Find out more',
    'até'                             => 'until',
];

$fr = [
    'Avisos da plataforma'            => 'Avis de la plateforme',
    'Comunicações da equipa SOS ERP'  => 'Messages de l\'équipe SOS ERP',
    'Lido'                            => 'Lu',
    'Saber mais'                      => 'En savoir plus',
    'até'                             => 'jusqu\'au',
];

foreach (['en' => $en, 'fr' => $fr] as $lingua => $novas) {
    $caminho = "{$raiz}/lang/{$lingua}.json";
    $dicionario = json_decode(file_get_contents($caminho), true) ?: [];

    $acrescentadas = 0;

    foreach ($novas as $pt => $traduzida) {
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
