<?php

/**
 * Embrulha em __() as mensagens de um componente Livewire, e corrige a
 * codificação pelo caminho.
 *
 * Duas coisas de uma vez, porque são a mesma linha:
 *
 * 1. AS MENSAGENS. dispatch('success', message: 'Guardado!') é o que o
 *    utilizador lê depois de carregar num botão. Ficam por traduzir com uma
 *    facilidade especial porque não estão no Blade — quem revê o ecrã não as
 *    vê lá.
 *
 * 2. O TEXTO CORROMPIDO. Há mensagens gravadas com os bytes UTF-8 lidos como
 *    Latin-1: "permissÃ£o" em vez de "permissão". Sai assim ao utilizador
 *    hoje. Se fossem embrulhadas como estão, a chave do dicionário ficava
 *    com o lixo lá dentro e a corrupção passava a ser permanente — traduzida
 *    e tudo.
 *
 * Uso:
 *   php scripts/embrulhar_componente.php <ficheiro.php> [...] [--escrever]
 */

$argumentos = array_slice($argv, 1);
$escrever = in_array('--escrever', $argumentos, true);
$ficheiros = array_values(array_filter($argumentos, fn ($a) => !str_starts_with($a, '--')));

if (!$ficheiros) {
    fwrite(STDERR, "Uso: php scripts/embrulhar_componente.php <ficheiro> [...] [--escrever]\n");
    exit(1);
}

$raiz = dirname(__DIR__);

// Só as sequências que aparecem mesmo, e não uma reconversão automática:
// mb_convert_encoding às cegas estraga o que já está bem.
$corrompidos = [
    'Ã£' => 'ã', 'Ã¡' => 'á', 'Ã¢' => 'â', 'Ã ' => 'à',
    'Ã©' => 'é', 'Ãª' => 'ê', 'Ã­' => 'í', 'Ã³' => 'ó',
    'Ãµ' => 'õ', 'Ã´' => 'ô', 'Ãº' => 'ú', 'Ã§' => 'ç',
    'Ã‡' => 'Ç', 'Ãƒ' => 'Ã', 'Ã‰' => 'É', 'Ã•' => 'Õ',
    'Â§' => '§', 'Âº' => 'º', 'Âª' => 'ª', 'Â´' => '´',
    'â€”' => '—', 'â€“' => '–', 'â€œ' => '"', 'â€' => '"', 'â€™' => "'",
];

$totalMensagens = 0;
$totalCorrigidas = 0;
$todasAsCadeias = [];

foreach ($ficheiros as $relativo) {
    $caminho = is_file($relativo) ? $relativo : "{$raiz}/{$relativo}";

    if (!is_file($caminho)) {
        fwrite(STDERR, "Não existe: {$relativo}\n");
        exit(1);
    }

    $fonte = file_get_contents($caminho);
    $antes = $fonte;

    // ---------- 1. desfazer a corrupção ----------
    $corrigidas = 0;

    foreach ($corrompidos as $mau => $bom) {
        $n = 0;
        $fonte = str_replace($mau, $bom, $fonte, $n);
        $corrigidas += $n;
    }

    // ---------- 2. embrulhar as mensagens ----------
    $mensagens = [];

    // Três formas de avisar o utilizador, e os componentes usam as três —
    // conforme quem os escreveu e quando. Um script que só conhecesse a
    // primeira dava zero em metade dos ficheiros e passava por "nada a
    // fazer aqui", que é a pior das leituras.
    //
    //   dispatch('success', message: 'Guardado!')          — argumento nomeado
    //   session()->flash('message', 'Guardado!')           — flash de sessão
    //   dispatch('notify', ['message' => 'Guardado!'])     — array
    $prefixos = [
        '/(message:\s*)',                                  // message: '...'
        "/(session\(\)->flash\(\s*'(?:message|error|success|warning)'\s*,\s*)",
        "/('message'\s*=>\s*)",                            // dentro do array
    ];

    foreach ($prefixos as $prefixo) {
        foreach (["'((?:[^'\\\\]|\\\\.)+)'", '"((?:[^"\\\\$]|\\\\.)+)"'] as $aspas) {
            $fonte = preg_replace_callback(
                $prefixo . $aspas . '/',
                function ($m) use (&$mensagens) {
                    $texto = end($m);

                    // Já embrulhada, ou tem uma variável lá dentro — nesse
                    // caso é preciso um placeholder e isso não é trabalho
                    // para um script.
                    if (str_contains($texto, '__(') || str_contains($texto, '$')) {
                        return $m[0];
                    }

                    $mensagens[] = str_replace("\\'", "'", $texto);

                    return $m[1] . "__('" . $texto . "')";
                },
                $fonte
            );
        }
    }

    $unicas = array_values(array_unique($mensagens));
    $totalMensagens += count($unicas);
    $totalCorrigidas += $corrigidas;
    $todasAsCadeias = array_merge($todasAsCadeias, $unicas);

    printf(
        "%-52s %3d mensagens%s\n",
        basename($relativo),
        count($unicas),
        $corrigidas ? "   ({$corrigidas} caracteres corrompidos corrigidos)" : ''
    );

    if ($escrever && $fonte !== $antes) {
        file_put_contents($caminho, $fonte);
    }
}

printf(
    "\n%s — %d mensagens, %d caracteres corrigidos\n",
    $escrever ? 'GRAVADO' : 'simulação (falta --escrever)',
    $totalMensagens,
    $totalCorrigidas
);
