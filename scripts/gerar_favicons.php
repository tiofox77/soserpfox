<?php

/**
 * Gera o conjunto de ícones do site a partir da marca.
 *
 * O ícone era o emblema inteiro — a raposa MAIS a grelha de seis mosaicos
 * (gráfico, ficha, calculadora, engrenagem, visto, Rx). A 48px já se percebia
 * mal; a 32 e a 16, que é o tamanho a que um separador do browser o mostra,
 * virava um borrão colorido sem forma reconhecível. Um ícone tem de se ler à
 * distância de um separador, e o que se lê da SOSERP é a raposa.
 *
 * Aqui recorta-se a raposa do emblema, deixam-se os mosaicos de fora, e
 * redesenha-se o anel azul à volta com a espessura certa para cada tamanho —
 * a 16px um anel fino desaparece, por isso engrossa proporcionalmente.
 *
 * Uso:  php scripts/gerar_favicons.php
 */

$raiz   = dirname(__DIR__);
$origem = $raiz . '/public/brand/soserp-logo-square-512.png';

if (!is_file($origem)) {
    fwrite(STDERR, "Não encontro a marca em {$origem}\n");
    exit(1);
}

// Medidas apuradas do ficheiro de origem (512x512):
// a raposa ocupa x 43–334, y 63–478; os mosaicos ficam à direita de x≈340.
const RAPOSA_X = 43;
const RAPOSA_Y = 63;
const RAPOSA_W = 292;
const RAPOSA_H = 416;

const AZUL   = [0x12, 0x70, 0xA7];
const LARANJA = [0xEF, 0x78, 0x11];

/**
 * A raposa sozinha, sem anel nem mosaicos, em fundo transparente.
 *
 * Guarda-se o que é laranja e deita-se fora todo o resto.
 *
 * À primeira tentei ao contrário — apagar o que fosse azul — e ficou um
 * quadrado fantasma dentro do anel: os píxeis esbatidos da borda dos mosaicos
 * são azul-esbranquiçado, não passam no teste "isto é azul", e sobreviveram
 * todos. Guardar o que se quer é mais seguro do que apagar o que não se quer.
 *
 * A transparência sai de quão laranja é o píxel, para a silhueta não ficar
 * aos degraus: o que é laranja vivo fica opaco, o que é quase branco
 * desaparece, e pelo meio faz-se a passagem.
 */
function raposaSozinha(string $origem): \GdImage
{
    $src = imagecreatefrompng($origem);

    $fox = imagecreatetruecolor(RAPOSA_W, RAPOSA_H);
    imagealphablending($fox, false);
    imagesavealpha($fox, true);
    imagefill($fox, 0, 0, imagecolorallocatealpha($fox, 0, 0, 0, 127));

    for ($y = 0; $y < RAPOSA_H; $y++) {
        for ($x = 0; $x < RAPOSA_W; $x++) {
            $c = imagecolorsforindex($src, imagecolorat($src, RAPOSA_X + $x, RAPOSA_Y + $y));

            if ($c['alpha'] > 100) {
                continue;
            }

            // Distância ao branco na direcção do laranja: 0 = branco, 1 = a cor da marca.
            $peso = ($c['blue'] < 255) ? (255 - $c['blue']) / (255 - LARANJA[2]) : 0.0;
            $peso = min(1.0, $peso);

            if ($peso < 0.12 || $c['red'] <= $c['blue'] + 10) {
                continue;                       // branco, azul, ou lixo pelo meio
            }

            imagesetpixel($fox, $x, $y, imagecolorallocatealpha(
                $fox, LARANJA[0], LARANJA[1], LARANJA[2], (int) round(127 * (1 - $peso))
            ));
        }
    }

    imagedestroy($src);

    return $fox;
}

/** Recorta o que sobra à volta da forma, para ela encher o ícone. */
function aparar(\GdImage $im): \GdImage
{
    $w = imagesx($im);
    $h = imagesy($im);
    $x0 = $w; $y0 = $h; $x1 = 0; $y1 = 0;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (imagecolorsforindex($im, imagecolorat($im, $x, $y))['alpha'] > 100) {
                continue;
            }
            $x0 = min($x0, $x); $y0 = min($y0, $y);
            $x1 = max($x1, $x); $y1 = max($y1, $y);
        }
    }

    $out = imagecreatetruecolor($x1 - $x0 + 1, $y1 - $y0 + 1);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopy($out, $im, 0, 0, $x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1);

    return $out;
}

/**
 * O ícone num tamanho: anel azul, raposa dentro.
 *
 * `$fundo` é para os ícones que não podem ser transparentes — o do iOS, que
 * assenta num quadrado, e os do PWA.
 */
function icone(\GdImage $fox, int $lado, bool $fundoBranco = false): \GdImage
{
    // Abaixo dos 20px o anel não cabe: ocupa metade do ícone e a raposa fica
    // reduzida a oito píxeis de laranja sem forma. A essa medida vale mais só
    // a raposa, a encher o quadrado — é assim que se lê num separador.
    if ($lado <= 20) {
        return soARaposa($fox, $lado);
    }

    // Desenha-se a 4x e reduz-se no fim. As elipses do GD não têm espessura e
    // o anel feito de disco-sobre-disco saía com falhas nos quatro pontos
    // cardeais — meio píxel de diferença entre o que a imagefilledellipse
    // pinta e o que um ciclo de píxeis apaga. A quatro vezes o tamanho, a
    // redução trata das bordas e o anel fecha.
    $escalaExtra = 4;
    $g = $lado * $escalaExtra;

    $tela = imagecreatetruecolor($g, $g);
    imagealphablending($tela, false);
    imagesavealpha($tela, true);
    imagefill($tela, 0, 0, imagecolorallocatealpha($tela, 0, 0, 0, 127));

    $azul   = imagecolorallocate($tela, ...AZUL);
    $branco = imagecolorallocate($tela, 255, 255, 255);
    $vazio  = imagecolorallocatealpha($tela, 0, 0, 0, 127);

    // A 16px um traço fino desaparece: a espessura acompanha o tamanho.
    $centro       = $g / 2;
    $raioExterior = $g / 2 - $escalaExtra * 0.5;
    $raioInterior = $raioExterior - max($escalaExtra, $lado * 0.085 * $escalaExtra);

    for ($y = 0; $y < $g; $y++) {
        for ($x = 0; $x < $g; $x++) {
            $dx = $x - $centro + 0.5;
            $dy = $y - $centro + 0.5;
            $d  = sqrt($dx * $dx + $dy * $dy);

            if ($d > $raioExterior) {
                continue;                       // fora do ícone
            }

            imagesetpixel($tela, $x, $y, $d >= $raioInterior
                ? $azul
                : ($fundoBranco ? $branco : $vazio));
        }
    }

    // A raposa, a encher o círculo interior sem lhe tocar nas bordas.
    imagealphablending($tela, true);

    $espaco = (int) round($raioInterior * 2 * 0.88);
    $fw = imagesx($fox);
    $fh = imagesy($fox);
    $escala = min($espaco / $fw, $espaco / $fh);
    $novoW = max(1, (int) round($fw * $escala));
    $novoH = max(1, (int) round($fh * $escala));

    imagecopyresampled(
        $tela, $fox,
        (int) round(($g - $novoW) / 2), (int) round(($g - $novoH) / 2),
        0, 0, $novoW, $novoH, $fw, $fh
    );

    $out = imagecreatetruecolor($lado, $lado);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $tela, 0, 0, 0, 0, $lado, $lado, $g, $g);
    imagedestroy($tela);

    return $out;
}

/**
 * A raposa sozinha a encher o quadrado, para os tamanhos mínimos.
 *
 * E só a cabeça: a marca inteira é alta e estreita (292x416), e num quadrado
 * de 16 sobrava-lhe metade da largura por preencher. Cortada à altura do
 * pescoço fica quadrada e enche o separador.
 */
function soARaposa(\GdImage $fox, int $lado): \GdImage
{
    $out = imagecreatetruecolor($lado, $lado);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagealphablending($out, true);

    $fw = imagesx($fox);
    $fh = min(imagesy($fox), imagesx($fox));    // só a cabeça
    $escala = min($lado / $fw, $lado / $fh);
    $novoW = max(1, (int) round($fw * $escala));
    $novoH = max(1, (int) round($fh * $escala));

    imagecopyresampled(
        $out, $fox,
        (int) round(($lado - $novoW) / 2), (int) round(($lado - $novoH) / 2),
        0, 0, $novoW, $novoH, $fw, $fh
    );

    return $out;
}

/** Escreve um .ico com vários tamanhos lá dentro (formato PNG embutido). */
function escreverIco(array $pngs, string $destino): void
{
    $n = count($pngs);
    $cab = pack('vvv', 0, 1, $n);
    $entradas = '';
    $dados = '';
    $offset = 6 + 16 * $n;

    foreach ($pngs as $lado => $png) {
        $entradas .= pack('CCCCvvVV',
            $lado >= 256 ? 0 : $lado,
            $lado >= 256 ? 0 : $lado,
            0, 0, 1, 32, strlen($png), $offset
        );
        $dados  .= $png;
        $offset += strlen($png);
    }

    file_put_contents($destino, $cab . $entradas . $dados);
}

function guardar(\GdImage $im, string $destino): void
{
    imagepng($im, $destino, 9);
    echo '  ' . str_pad(basename($destino), 32) . filesize($destino) . " bytes\n";
}

// ---------------------------------------------------------------------------

$fox = aparar(raposaSozinha($origem));
echo "raposa recortada: " . imagesx($fox) . 'x' . imagesy($fox) . "\n\n";

@mkdir($raiz . '/public/brand', 0755, true);
@mkdir($raiz . '/public/pwa', 0755, true);

// Favicons transparentes — assentam bem em separadores claros e escuros.
$paraIco = [];
foreach ([16, 32, 48, 64] as $lado) {
    $im = icone($fox, $lado);

    if (in_array($lado, [16, 32, 48], true)) {
        guardar($im, $raiz . "/public/brand/favicon-{$lado}x{$lado}.png");
    }

    ob_start();
    imagepng($im, null, 9);
    $paraIco[$lado] = ob_get_clean();

    imagedestroy($im);
}

escreverIco($paraIco, $raiz . '/public/favicon.ico');
echo '  ' . str_pad('favicon.ico', 32) . filesize($raiz . '/public/favicon.ico') . " bytes\n";

// Ícones com fundo — iOS e PWA recortam o alfa e mostrariam um quadrado preto.
guardar(icone($fox, 180, true), $raiz . '/public/apple-touch-icon.png');

@mkdir($raiz . '/public/pwa/default', 0755, true);

foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $lado) {
    $im = icone($fox, $lado, true);
    guardar($im, $raiz . "/public/pwa/icon-{$lado}x{$lado}.png");

    // O PwaController serve daqui quando não há logótipo utilizável. Estavam
    // cá uns marcadores de lugar com "SOS ERP" escrito num círculo — nada a
    // ver com a marca.
    imagepng($im, $raiz . "/public/pwa/default/icon-{$lado}x{$lado}.png", 9);
    imagedestroy($im);
}

guardar(icone($fox, 192, true), $raiz . '/public/brand/soserp-icone-192.png');
guardar(icone($fox, 512, true), $raiz . '/public/brand/soserp-icone-512.png');

echo "\nfeito\n";
