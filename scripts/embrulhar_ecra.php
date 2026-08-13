<?php

/**
 * Embrulha em __() o texto visível de um ecrã Blade.
 *
 * A primeira versão disto olhava para o conteúdo de cada tag — <h1>Texto</h1>
 * — e apanhava metade. Falhava no caso mais comum de todos, que é haver um
 * ícone pelo meio:
 *
 *     <h1><i class="fas fa-box"></i> Produtos</h1>
 *
 * Aqui o conteúdo do <h1> já não é texto puro, e a regra de segurança (só
 * mexer em texto puro) mandava deixar a linha em paz — deixando "Produtos"
 * por traduzir sem dizer nada.
 *
 * Esta versão trabalha sobre NÓS DE TEXTO: o que estiver entre um `>` e um
 * `<`. Para isso ser seguro, tudo o que não é HTML é tapado primeiro —
 * comentários, <script>, <style>, expressões {{ }} e directivas @. Sem esse
 * passo, um `@if($n > 0 && $x < 5)` parece um nó de texto e o script
 * embrulhava uma comparação de PHP.
 *
 * O que fica sempre de fora, por decisão e não por esquecimento:
 *   · siglas fiscais (AGT, NIF, IVA, IRT, INSS, SAFT) e moedas (Kz, AOA)
 *   · números, códigos e pontuação solta
 *   · comentários — ficam em português, são para quem lê o código
 *
 * Uso:
 *   php scripts/embrulhar_ecra.php <ficheiro.blade.php> [...] [--escrever]
 */

$argumentos = array_slice($argv, 1);
$escrever = in_array('--escrever', $argumentos, true);
$ficheiros = array_values(array_filter($argumentos, fn ($a) => !str_starts_with($a, '--')));

if (!$ficheiros) {
    fwrite(STDERR, "Uso: php scripts/embrulhar_ecra.php <ficheiro> [...] [--escrever]\n");
    exit(1);
}

$raiz = dirname(__DIR__);

// Nunca se traduzem. Uma sigla fiscal traduzida deixa de identificar o que
// identifica, e "Kz" noutra língua continua a ser Kz.
$intocaveis = [
    'AGT', 'NIF', 'IVA', 'IRT', 'INSS', 'SAFT', 'SAFT-AO', 'POS', 'PWA', 'PDF',
    'Kz', 'AOA', 'EUR', 'USD', 'CSV', 'QR', 'ID', 'SKU', 'EAN', 'N/A', 'OK',
    'Excel', 'Livewire', 'Laravel', 'KDS', 'CRM', 'DRE', 'OPcache',
];

$emoji = '[\x{1F300}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]';

/** Vale a pena traduzir isto? */
$traduzivel = function (string $texto) use ($intocaveis): bool {
    $texto = trim($texto);

    if ($texto === '' || in_array($texto, $intocaveis, true)) {
        return false;
    }

    // Um cinto a mais, além do marcador sem letras: se sobrar aqui alguma
    // coisa tapada, é código, não texto.
    if (str_contains($texto, "\x01")) {
        return false;
    }

    // Precisa de pelo menos uma palavra a sério: descarta "· 12 —" e afins.
    if (!preg_match('/\p{L}{2,}/u', $texto)) {
        return false;
    }

    // Uma sigla sozinha, mesmo fora da lista, não se traduz: SIGLA, ABC-12.
    if (preg_match('/^[A-Z0-9][A-Z0-9\/.-]*$/u', $texto)) {
        return false;
    }

    return true;
};

$totalGeral = 0;

foreach ($ficheiros as $relativo) {
    $caminho = is_file($relativo) ? $relativo : "{$raiz}/{$relativo}";

    if (!is_file($caminho)) {
        fwrite(STDERR, "Não existe: {$relativo}\n");
        exit(1);
    }

    $fonte = file_get_contents($caminho);
    $antes = $fonte;

    // ---------- 1. tapar o que não é HTML ----------
    // O marcador NÃO pode ter letras. A primeira versão usava "COFRE1" e o
    // script tomava o próprio marcador por texto a traduzir: o comentário
    // HTML saía embrulhado num __(), com o comentário inteiro lá dentro.
    // Só dígitos entre dois caracteres de controlo — nada que uma expressão
    // \p{L} consiga confundir com uma palavra.
    $cofre = [];
    $guardar = function ($m) use (&$cofre) {
        $chave = "\x01" . count($cofre) . "\x02";
        $cofre[$chave] = $m[0];
        return $chave;
    };

    // A ordem importa: os comentários primeiro, senão o que está lá dentro
    // é tratado como código a sério.
    $padroesACobrir = [
        '/\{\{--.*?--\}\}/s',                       // comentário Blade
        '/<!--.*?-->/s',                            // comentário HTML
        '/@php\b.*?@endphp/s',                      // bloco PHP
        '/<script\b[^>]*>.*?<\/script>/is',         // JavaScript
        '/<style\b[^>]*>.*?<\/style>/is',           // CSS
        '/\{!!.*?!!\}/s',                           // eco sem escape
        '/\{\{.*?\}\}/s',                           // eco
    ];

    foreach ($padroesACobrir as $padrao) {
        $fonte = preg_replace_callback($padrao, $guardar, $fonte);
    }

    // As directivas com argumentos contam-se à mão, e não por expressão
    // regular. Uma regex de parênteses equilibrados só sabe descer um nível;
    //
    //     @foreach(Brand::where('t', auth()->user()->tenant_id)->get() as $b)
    //
    // tem três, e a regex parava a meio. O resto da linha — "get() as $b)" —
    // passava a parecer texto e saía embrulhado num __(), com a directiva
    // partida ao meio e a página a rebentar. Aconteceu; daí este contador.
    $resultado = '';
    $i = 0;
    $tamanho = strlen($fonte);

    while ($i < $tamanho) {
        if ($fonte[$i] === '@' && preg_match('/\G@\w+/', $fonte, $m, 0, $i)) {
            $directiva = $m[0];
            $j = $i + strlen($directiva);

            // Espaços entre o nome e o parêntese são válidos: @if (...)
            $k = $j;
            while ($k < $tamanho && ($fonte[$k] === ' ' || $fonte[$k] === "\t")) {
                $k++;
            }

            if ($k < $tamanho && $fonte[$k] === '(') {
                $nivel = 0;
                $fim = null;

                for ($p = $k; $p < $tamanho; $p++) {
                    if ($fonte[$p] === '(') {
                        $nivel++;
                    } elseif ($fonte[$p] === ')') {
                        $nivel--;
                        if ($nivel === 0) {
                            $fim = $p;
                            break;
                        }
                    }
                }

                if ($fim !== null) {
                    $chave = "\x01" . count($cofre) . "\x02";
                    $cofre[$chave] = substr($fonte, $i, $fim - $i + 1);
                    $resultado .= $chave;
                    $i = $fim + 1;
                    continue;
                }
            }

            // Directiva sem argumentos: @else, @endif, @endforeach.
            $chave = "\x01" . count($cofre) . "\x02";
            $cofre[$chave] = $directiva;
            $resultado .= $chave;
            $i = $j;
            continue;
        }

        $resultado .= $fonte[$i];
        $i++;
    }

    $fonte = $resultado;

    // ---------- 2. embrulhar os nós de texto ----------
    $embrulhadas = [];

    $fonte = preg_replace_callback(
        '/>([^<>]+)</u',
        function ($m) use (&$embrulhadas, $traduzivel, $emoji) {
            $bruto = $m[1];

            // O espaço à volta preserva-se: mexer nele altera o desenho da
            // página, e isso não é trabalho de tradução.
            preg_match('/^(\s*)(.*?)(\s*)$/su', $bruto, $partes);
            [, $espacoA, $miolo, $espacoB] = $partes;

            // Emojis colados ficam de fora da chave — lêem-se igual nas três
            // línguas e dentro dela eram mais um sítio para se perderem.
            preg_match('/^((?:' . $emoji . '|\s)*)(.*?)((?:' . $emoji . '|\s)*)$/su', $miolo, $lados);
            [, $iconeA, $texto, $iconeB] = $lados;

            if (!$traduzivel($texto)) {
                return $m[0];
            }

            $embrulhadas[] = $texto;

            return '>' . $espacoA . $iconeA
                . "{{ __('" . str_replace("'", "\\'", $texto) . "') }}"
                . $iconeB . $espacoB . '<';
        },
        $fonte
    );

    // ---------- 3. atributos que o utilizador lê ----------
    foreach (['placeholder', 'title', 'alt'] as $atributo) {
        $fonte = preg_replace_callback(
            '/(\s' . $atributo . '=")([^"\x01]+)(")/u',
            function ($m) use (&$embrulhadas, $traduzivel) {
                if (!$traduzivel($m[2])) {
                    return $m[0];
                }

                $limpo = trim($m[2]);
                $embrulhadas[] = $limpo;

                return $m[1] . "{{ __('" . str_replace("'", "\\'", $limpo) . "') }}" . $m[3];
            },
            $fonte
        );
    }

    // ---------- 4. destapar ----------
    $fonte = strtr($fonte, $cofre);

    $unicas = array_values(array_unique($embrulhadas));
    $totalGeral += count($unicas);

    printf("%-64s %3d\n", $relativo, count($unicas));

    // Um preg_replace_callback que falha devolve NULL, e escrever NULL
    // esvazia o ficheiro. Aconteceu — uma classe de caracteres mal fechada
    // numa expressão apagou um ecrã inteiro, em silêncio, com o script a
    // dizer "0 cadeias" como se não tivesse feito nada.
    //
    // Um ficheiro nunca encolhe ao ser embrulhado: só se lhe acrescenta
    // "{{ __(' ') }}". Se encolheu, alguma coisa correu mal e não se grava.
    if ($escrever && $fonte !== $antes) {
        if (!is_string($fonte) || strlen($fonte) < strlen($antes)) {
            fwrite(STDERR, "ABORTADO em {$relativo}: o resultado é menor do que o original.\n");
            exit(1);
        }

        file_put_contents($caminho, $fonte);
    }
}

printf("\n%s — %d cadeias\n", $escrever ? 'GRAVADO' : 'simulação (falta --escrever)', $totalGeral);
