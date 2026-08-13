<?php

/**
 * Embrulha em __() os rótulos que os modelos devolvem ao ecrã.
 *
 * Os acessores `getStatusLabelAttribute` e companhia devolvem português puro.
 * Não estão no Blade, portanto escaparam a todos os lotes de tradução — e o
 * resultado é um crachá a dizer "Expirado" no meio de uma página inglesa,
 * numa área que os testes davam por traduzida.
 *
 * REGRA DE SEGURANÇA, e não é opcional: só se mexe DENTRO de um acessor cujo
 * nome acabe em LabelAttribute. A primeira versão disto varria o ficheiro
 * inteiro à procura de `'chave' => 'Valor'` e apanhava o que não devia:
 *
 *   · os códigos de documento da AGT no InvoicingSeries ('FT', 'NC', 'GT')
 *   · os códigos fiscais no Tax ('IVA14', 'NOR', 'M04') — que são GRAVADOS na
 *     base de dados, portanto traduzi-los punha lá dentro "VAT14" e o
 *     documento saía com um código que a AGT não conhece
 *   · nomes de classes ('SalesInvoice') em mapas de tipo de documento
 *   · dados de arranque (o armazém 'Sede' que se cria por omissão)
 *
 * Dentro do acessor embrulha-se só o LADO DIREITO do braço: a chave da
 * esquerda é o valor da coluna e traduzi-la partia a comparação.
 *
 * O `default =>` fica de fora: devolve a coluna crua, não um rótulo.
 *
 * Uso:  php scripts/embrulhar_rotulos_modelo.php <ficheiro.php> [...] [--escrever]
 */

$argumentos = array_slice($argv, 1);
$escrever = in_array('--escrever', $argumentos, true);
$ficheiros = array_values(array_filter($argumentos, fn ($a) => !str_starts_with($a, '--')));

if (!$ficheiros) {
    fwrite(STDERR, "Uso: php scripts/embrulhar_rotulos_modelo.php <ficheiro> [...] [--escrever]\n");
    exit(1);
}

$raiz = dirname(__DIR__);

// Cores e classes CSS aparecem em matches com a mesma forma. Não são texto.
$naoSaoRotulos = [
    'Green', 'Red', 'Gray', 'Blue', 'Yellow', 'Orange', 'Purple', 'Emerald', 'Indigo',
];

$total = 0;
$todas = [];

foreach ($ficheiros as $relativo) {
    $caminho = is_file($relativo) ? $relativo : "{$raiz}/{$relativo}";

    if (!is_file($caminho)) {
        fwrite(STDERR, "Não existe: {$relativo}\n");
        exit(1);
    }

    $antes = file_get_contents($caminho);
    $daqui = [];

    $embrulhar = function (string $corpo) use (&$daqui, $naoSaoRotulos): string {
        return preg_replace_callback(
            // 'chave_bd' => 'Rótulo',
            "/('[a-z][a-z0-9_]*'\s*=>\s*)'([\p{Lu}][^']*)'/u",
            function ($m) use (&$daqui, $naoSaoRotulos) {
                $texto = $m[2];

                if (in_array($texto, $naoSaoRotulos, true) || str_contains($texto, '__(')) {
                    return $m[0];
                }

                $daqui[] = $texto;

                return $m[1] . "__('" . str_replace("'", "\\'", $texto) . "')";
            },
            $corpo
        );
    };

    // Percorrer só os acessores de rótulo, um a um, contando chavetas para
    // saber onde cada um acaba.
    $depois = $antes;
    $procurarDe = 0;

    while (preg_match(
        '/public function get\w*LabelAttribute\s*\([^)]*\)\s*(?::\s*\??\w+\s*)?\{/',
        $depois,
        $m,
        PREG_OFFSET_CAPTURE,
        $procurarDe
    )) {
        $abre = $m[0][1] + strlen($m[0][0]) - 1;   // posição da chaveta

        $nivel = 0;
        $fim = null;

        for ($p = $abre; $p < strlen($depois); $p++) {
            if ($depois[$p] === '{') {
                $nivel++;
            } elseif ($depois[$p] === '}') {
                $nivel--;
                if ($nivel === 0) {
                    $fim = $p;
                    break;
                }
            }
        }

        if ($fim === null) {
            break;
        }

        $corpo = substr($depois, $abre, $fim - $abre + 1);
        $novo = $embrulhar($corpo);

        $depois = substr($depois, 0, $abre) . $novo . substr($depois, $fim + 1);
        $procurarDe = $abre + strlen($novo);
    }

    if (!is_string($depois)) {
        fwrite(STDERR, "ABORTADO em {$relativo}: preg falhou (" . preg_last_error() . ").\n");
        exit(1);
    }

    if (strlen($depois) < strlen($antes)) {
        fwrite(STDERR, "ABORTADO em {$relativo}: o resultado encolheu.\n");
        exit(1);
    }

    $unicas = array_values(array_unique($daqui));
    $total += count($unicas);
    $todas = array_merge($todas, $unicas);

    printf("%-34s %2d  %s\n", basename($relativo), count($unicas), implode(' · ', $unicas));

    if ($escrever && $depois !== $antes) {
        file_put_contents($caminho, $depois);
    }
}

printf("\n%s — %d rótulos (%d distintos)\n",
    $escrever ? 'GRAVADO' : 'simulação (falta --escrever)',
    $total,
    count(array_unique($todas))
);
