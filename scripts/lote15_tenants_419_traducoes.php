<?php

/**
 * Traduções dos crachás da lista de empresas e da página de erro 419.
 *
 * Uso:  php scripts/lote15_tenants_419_traducoes.php
 */

$raiz = dirname(__DIR__);

$novas = [
    'en' => [
        ':n utilizadores no plano' => ':n users on the plan',
        ':n MB de espaço'          => ':n MB of storage',
        ':n modulos'               => ':n modules',
        ':n criados'               => ':n created',
        'Voltar à página'          => 'Back to the page',
        'Entrar de novo'           => 'Sign in again',
    ],
    'fr' => [
        ':n utilizadores no plano' => ':n utilisateurs dans le forfait',
        ':n MB de espaço'          => ':n Mo d\'espace',
        ':n modulos'               => ':n modules',
        ':n criados'               => ':n créés',
        'Voltar à página'          => 'Retour à la page',
        'Entrar de novo'           => 'Se reconnecter',
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
