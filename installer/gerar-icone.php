<?php
/**
 * Gera o icone .ico multi-resolucao do soserp a partir do logo da marca.
 *
 * Um .ico e um cabecalho + uma entrada por tamanho. Do Vista para a frente
 * cada entrada pode ser um PNG inteiro embutido — e assim mantem-se o alfa sem
 * ter de escrever bitmaps BMP com mascara AND.
 *
 * Uso: php installer/gerar-icone.php [origem.png] [destino.ico]
 */

$origem  = $argv[1] ?? __DIR__ . '/../public/brand/soserp-logo-square-512.png';
$destino = $argv[2] ?? __DIR__ . '/soserp.ico';

if (!is_file($origem)) {
    fwrite(STDERR, "Origem nao encontrada: $origem\n");
    exit(1);
}

$src = @imagecreatefrompng($origem);
if (!$src) {
    fwrite(STDERR, "Nao consegui ler o PNG: $origem\n");
    exit(1);
}
$sw = imagesx($src);
$sh = imagesy($src);

$tamanhos = [16, 32, 48, 64, 128, 256];
$imagens = [];

foreach ($tamanhos as $t) {
    $dst = imagecreatetruecolor($t, $t);
    // Preservar transparencia
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $t, $t, $sw, $sh);

    ob_start();
    imagepng($dst, null, 9);
    $imagens[$t] = ob_get_clean();
    imagedestroy($dst);
}
imagedestroy($src);

// ICONDIR: reservado(2) + tipo 1 = icone (2) + nº de imagens (2)
$ico = pack('vvv', 0, 1, count($imagens));

// Cada ICONDIRENTRY tem 16 bytes; os dados vêm todos a seguir ao directorio.
$offset = 6 + (16 * count($imagens));
$dados = '';

foreach ($imagens as $t => $png) {
    $ico .= pack(
        'CCCCvvVV',
        $t >= 256 ? 0 : $t,   // largura (0 significa 256)
        $t >= 256 ? 0 : $t,   // altura
        0,                    // cores da paleta (0 = truecolor)
        0,                    // reservado
        1,                    // planos
        32,                   // bits por pixel
        strlen($png),         // tamanho dos dados
        $offset               // onde comecam
    );
    $offset += strlen($png);
    $dados .= $png;
}

file_put_contents($destino, $ico . $dados);

printf("Icone gerado: %s (%d tamanhos, %.1f KB)\n", $destino, count($imagens), filesize($destino) / 1024);
