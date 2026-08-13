<?php

/**
 * As chaves do dicionário têm de ser o que a linguagem vê EM EXECUÇÃO.
 *
 * Um `__('Linha 1\nLinha 2')` em JavaScript procura uma chave com uma mudança
 * de linha a sério — o `\n` do código-fonte é uma instrução para o
 * interpretador, não dois caracteres. Guardado no JSON como barra-mais-ene, o
 * dicionário nunca corresponde e a cadeia sai em português sem que nada
 * acuse: não é um erro, é uma tradução que simplesmente não é encontrada.
 *
 * Uso:  php scripts/corrigir_escapes_traducoes.php
 */

$raiz = dirname(__DIR__);
$escapes = ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t"];

foreach (['en', 'fr'] as $lingua) {
    $caminho = "{$raiz}/lang/{$lingua}.json";
    $dicionario = json_decode(file_get_contents($caminho), true);

    if (!is_array($dicionario)) {
        fwrite(STDERR, "lang/{$lingua}.json não é JSON válido.\n");
        exit(1);
    }

    $novo = [];
    $corrigidas = [];

    foreach ($dicionario as $chave => $valor) {
        $chaveReal = strtr($chave, $escapes);
        // O valor traduzido leva o mesmo tratamento: se a chave tem mudanças
        // de linha, a tradução também as deve ter.
        $valorReal = strtr($valor, $escapes);

        if ($chaveReal !== $chave) {
            $corrigidas[] = mb_substr($chaveReal, 0, 45);
        }

        $novo[$chaveReal] = $valorReal;
    }

    ksort($novo, SORT_NATURAL | SORT_FLAG_CASE);

    file_put_contents(
        $caminho,
        json_encode($novo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    printf("lang/%s.json: %d chave(s) corrigida(s)\n", $lingua, count($corrigidas));

    foreach ($corrigidas as $c) {
        echo '    ' . str_replace("\n", '⏎', $c) . "\n";
    }
}
