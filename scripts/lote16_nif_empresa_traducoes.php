<?php

/**
 * Traduções do NIF de empresa.
 *
 * "NIF" fica NIF: é a sigla angolana e é assim que aparece em todos os
 * documentos oficiais, incluindo os que a AGT devolve.
 *
 * Uso:  php scripts/lote16_nif_empresa_traducoes.php
 */

$raiz = dirname(__DIR__);

$novas = [
    'en' => [
        'NIF da empresa' => 'Company NIF',
        'Dez dígitos, começados por 5. Não é o número do bilhete de identidade.'
            => 'Ten digits, starting with 5. It is not the ID card number.',
        'Indique o NIF da empresa.' => 'Enter the company NIF.',
        'Esse é o número do bilhete de identidade. O NIF da empresa tem dez dígitos e começa por 5.'
            => 'That is the ID card number. A company NIF has ten digits and starts with 5.',
        'O NIF da empresa tem de ter dez dígitos.' => 'The company NIF must have ten digits.',
        'O NIF da empresa começa por 5. Um número começado por :inicio é de pessoa singular.'
            => 'A company NIF starts with 5. A number starting with :inicio belongs to an individual.',
    ],
    'fr' => [
        'NIF da empresa' => 'NIF de l\'entreprise',
        'Dez dígitos, começados por 5. Não é o número do bilhete de identidade.'
            => 'Dix chiffres, commençant par 5. Ce n\'est pas le numéro de la carte d\'identité.',
        'Indique o NIF da empresa.' => 'Indiquez le NIF de l\'entreprise.',
        'Esse é o número do bilhete de identidade. O NIF da empresa tem dez dígitos e começa por 5.'
            => 'Il s\'agit du numéro de la carte d\'identité. Le NIF d\'une entreprise a dix chiffres et commence par 5.',
        'O NIF da empresa tem de ter dez dígitos.' => 'Le NIF de l\'entreprise doit comporter dix chiffres.',
        'O NIF da empresa começa por 5. Um número começado por :inicio é de pessoa singular.'
            => 'Le NIF d\'une entreprise commence par 5. Un numéro commençant par :inicio appartient à un particulier.',
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
