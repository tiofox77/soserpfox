<?php

/**
 * Embrulha os rótulos do menu lateral em __().
 *
 * São 191 rótulos no mesmo ficheiro, todos com a mesma forma. À mão seriam
 * 191 edições e uma delas sairia errada; o que interessa verificar é o
 * PADRÃO, e esse verifica-se uma vez.
 *
 * O que este script NÃO toca, de propósito:
 *   · rótulos que já tenham {{ }} lá dentro — já foram feitos
 *   · o emoji, que fica fora do __(): não se traduz e só sujava a chave
 *   · tudo o que não seja um <span> de menu
 *
 * Uso:  php scripts/embrulhar_menu_traducoes.php [--escrever]
 */

$raiz = dirname(__DIR__);
$caminho = "{$raiz}/resources/views/layouts/app.blade.php";
$escrever = in_array('--escrever', $argv, true);

$fonte = file_get_contents($caminho);
$antes = $fonte;

$embrulhadas = [];
$saltadas = [];

// Os emojis vêm colados ao rótulo ("📊 Dashboard"). Ficam fora do __():
// são iguais nas três línguas e dentro da chave obrigavam o tradutor a
// copiá-los à mão — mais um sítio para se perder um.
// O \x{2300}-\x{23FF} apanha o ⏰ dos Turnos de Caixa, que não cai na
// zona dos emojis nem na dos símbolos.
$emoji = '(?:[\x{1F300}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]+\s*)';

$fonte = preg_replace_callback(
    '/(<span\s+x-show="sidebarOpen"[^>]*>)(\s*)(' . $emoji . ')?([^<>{}]+?)(\s*)(<\/span>)/u',
    function ($m) use (&$embrulhadas, &$saltadas) {
        [$tudo, $abre, $espacoA, $icone, $texto, $espacoB, $fecha] = $m;

        $texto = trim($texto);

        // Nada para traduzir: vazio, só números, ou uma variável Blade.
        if ($texto === '' || is_numeric($texto) || str_contains($texto, '@')) {
            $saltadas[] = $texto;
            return $tudo;
        }

        $embrulhadas[] = $texto;

        return $abre . $espacoA . ($icone ?? '')
            . "{{ __('" . str_replace("'", "\\'", $texto) . "') }}"
            . $espacoB . $fecha;
    },
    $fonte
);

// Os @yield com valor por omissão: é o que aparece no cabeçalho de qualquer
// ecrã que não defina o seu próprio título.
$fonte = preg_replace_callback(
    "/@yield\('(page-title|page-subtitle)',\s*'([^']+)'\)/",
    function ($m) use (&$embrulhadas) {
        $embrulhadas[] = $m[2];
        return "@yield('{$m[1]}', __('{$m[2]}'))";
    },
    $fonte
);

// Os relatórios vêm de arrays PHP: ['rota', 'Rótulo', 'ícone'].
$fonte = preg_replace_callback(
    "/(\['(?:invoicing|reports)\.[a-z0-9._-]+',\s*)'([^']+)'(,\s*'fa-)/",
    function ($m) use (&$embrulhadas) {
        $embrulhadas[] = $m[2];
        return $m[1] . "__('" . str_replace("'", "\\'", $m[2]) . "')" . $m[3];
    },
    $fonte
);

$unicas = array_values(array_unique($embrulhadas));
sort($unicas, SORT_NATURAL | SORT_FLAG_CASE);

printf("%d rótulos embrulhados (%d distintos)\n", count($embrulhadas), count($unicas));

if ($saltadas) {
    printf("%d saltados\n", count($saltadas));
}

if (!$escrever) {
    echo "\n(simulação — corra com --escrever para gravar)\n\n";
    foreach ($unicas as $u) {
        echo "  {$u}\n";
    }
    exit(0);
}

if ($fonte === $antes) {
    echo "Nada mudou.\n";
    exit(0);
}

file_put_contents($caminho, $fonte);
file_put_contents("{$raiz}/storage/app/menu-cadeias.json", json_encode($unicas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo "Gravado. Cadeias em storage/app/menu-cadeias.json\n";
