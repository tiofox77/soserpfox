<?php

/**
 * Traduções do ecrã de definições sem empresa activa.
 *
 * Uso:  php scripts/lote11_sem_empresa_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    'Nenhuma empresa activa' => 'No active company',
    'Estas configurações pertencem a uma empresa — moeda, séries, impostos e o comportamento do ponto de venda. Escolha primeiro a empresa com que quer trabalhar, no selector no topo da página.'
        => 'These settings belong to a company — currency, series, taxes and how the point of sale behaves. Pick the company you want to work with first, using the selector at the top of the page.',
    'Se a sua conta ainda não está ligada a nenhuma empresa, peça a quem administra o sistema que o associe a uma.'
        => 'If your account is not linked to any company yet, ask whoever administers the system to add you to one.',
    'Voltar ao início' => 'Back to home',
];

$fr = [
    'Nenhuma empresa activa' => 'Aucune entreprise active',
    'Estas configurações pertencem a uma empresa — moeda, séries, impostos e o comportamento do ponto de venda. Escolha primeiro a empresa com que quer trabalhar, no selector no topo da página.'
        => 'Ces paramètres appartiennent à une entreprise — devise, séries, taxes et comportement du point de vente. Choisissez d\'abord l\'entreprise avec laquelle vous voulez travailler, dans le sélecteur en haut de la page.',
    'Se a sua conta ainda não está ligada a nenhuma empresa, peça a quem administra o sistema que o associe a uma.'
        => 'Si votre compte n\'est encore rattaché à aucune entreprise, demandez à l\'administrateur du système de vous en associer une.',
    'Voltar ao início' => 'Retour à l\'accueil',
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
