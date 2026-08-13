<?php

/**
 * Que cadeias é que estão embrulhadas mas ainda não têm tradução?
 *
 * Percorre os ficheiros indicados, tira de lá tudo o que passou por __() ou
 * trans_choice(), e compara com os dicionários. O que falta sai em JSON,
 * pronto a dar a traduzir.
 *
 * Escrito porque a alternativa — abrir o lang/en.json e ir vendo à vista — é
 * onde se perdem as cadeias que depois aparecem em português a meio de uma
 * página inglesa, sem nada que o denuncie.
 *
 * Uso:  php scripts/cadeias_por_traduzir.php <ficheiro> [ficheiro...]
 */

$raiz = dirname(__DIR__);
$ficheiros = array_slice($argv, 1);

if (!$ficheiros) {
    fwrite(STDERR, "Uso: php scripts/cadeias_por_traduzir.php <ficheiro> [...]\n");
    exit(1);
}

$encontradas = [];

foreach ($ficheiros as $relativo) {
    $caminho = str_starts_with($relativo, '/') || preg_match('/^[A-Za-z]:/', $relativo)
        ? $relativo
        : "{$raiz}/{$relativo}";

    if (!is_file($caminho)) {
        fwrite(STDERR, "Não existe: {$caminho}\n");
        exit(1);
    }

    $conteudo = file_get_contents($caminho);

    // As duas formas de aspas desescapam-se de MANEIRAS DIFERENTES, e é aí que
    // se perdem traduções sem ninguém dar por isso.
    //
    // Entre plicas o PHP só conhece dois escapes: \' e \\. Um \" dentro de
    // plicas guarda a barra — 'Item \"x\"' vale, em execução, Item \"x\" com
    // barras e tudo. Quem desescapa às cegas grava no dicionário uma chave que
    // o __() nunca há-de procurar, e a linha sai em português para sempre.
    $formas = [
        "/\b(?:__|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)*)'/" => ["\\'" => "'", '\\\\' => '\\'],
        '/\b(?:__|trans_choice)\(\s*"((?:[^"\\\\]|\\\\.)*)"/' => ['\\"' => '"', '\\\\' => '\\', '\\n' => "\n", '\\t' => "\t"],
    ];

    foreach ($formas as $padrao => $escapes) {
        preg_match_all($padrao, $conteudo, $m);

        foreach ($m[1] as $bruta) {
            $encontradas[] = strtr($bruta, $escapes);
        }
    }
}

$encontradas = array_values(array_unique($encontradas));
sort($encontradas, SORT_NATURAL | SORT_FLAG_CASE);

$faltam = [];

foreach (['en', 'fr'] as $lingua) {
    $dicionario = json_decode(file_get_contents("{$raiz}/lang/{$lingua}.json"), true) ?: [];
    $emFalta = array_values(array_filter($encontradas, fn ($c) => !isset($dicionario[$c])));

    printf("%s: %d de %d por traduzir\n", $lingua, count($emFalta), count($encontradas));

    $faltam[$lingua] = $emFalta;
}

// A união das duas: normalmente é a mesma lista, mas se um lote tiver ficado
// a meio numa das línguas não se quer traduzir a outra às cegas.
$uniao = array_values(array_unique(array_merge($faltam['en'], $faltam['fr'])));
sort($uniao, SORT_NATURAL | SORT_FLAG_CASE);

file_put_contents(
    "{$raiz}/storage/app/por-traduzir.json",
    json_encode($uniao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);

printf("\n%d cadeias distintas em storage/app/por-traduzir.json\n", count($uniao));
