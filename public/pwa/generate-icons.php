<?php
/**
 * PWA Icon Generator - Gera ícones para o manifest.json
 * Execute: php public/pwa/generate-icons.php
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$bgColor = [30, 64, 175]; // #1e40af
$textColor = [255, 255, 255]; // white

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    
    // Anti-aliasing
    imagealphablending($img, true);
    imageantialias($img, true);
    
    // Background
    $bg = imagecolorallocate($img, $bgColor[0], $bgColor[1], $bgColor[2]);
    $white = imagecolorallocate($img, $textColor[0], $textColor[1], $textColor[2]);
    $lightBlue = imagecolorallocate($img, 96, 165, 250); // #60a5fa
    
    // Preencher fundo
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);
    
    // Bordas arredondadas (simular com círculos nos cantos)
    $radius = (int)($size * 0.15);
    
    // Texto "SOS"
    $fontSize = (int)($size * 0.28);
    $fontSizeSub = (int)($size * 0.14);
    
    // Usar fonte built-in (mais robusto)
    $centerX = $size / 2;
    $centerY = $size / 2;
    
    // Desenhar "S" estilizado com círculo
    $circleRadius = (int)($size * 0.32);
    imagefilledellipse($img, (int)$centerX, (int)$centerY, $circleRadius * 2, $circleRadius * 2, $lightBlue);
    imagefilledellipse($img, (int)$centerX, (int)$centerY, (int)($circleRadius * 1.7), (int)($circleRadius * 1.7), $bg);
    
    // Texto SOS usando imagestring (tamanho 1-5)
    $font = 5; // maior fonte built-in
    if ($size >= 192) {
        // Para ícones grandes, usar texto maior
        $text = "SOS";
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        
        // Escalar desenhando múltiplas vezes
        $scale = max(1, (int)($size / 96));
        
        // Criar imagem de texto escalada
        $txtImg = imagecreatetruecolor($textWidth, $textHeight);
        $txtBg = imagecolorallocate($txtImg, $bgColor[0], $bgColor[1], $bgColor[2]);
        $txtWhite = imagecolorallocate($txtImg, 255, 255, 255);
        imagefilledrectangle($txtImg, 0, 0, $textWidth, $textHeight, $txtBg);
        imagestring($txtImg, $font, 0, 0, $text, $txtWhite);
        
        $destW = $textWidth * $scale;
        $destH = $textHeight * $scale;
        $destX = (int)(($size - $destW) / 2);
        $destY = (int)(($size - $destH) / 2) - (int)($size * 0.05);
        
        imagecopyresized($img, $txtImg, $destX, $destY, 0, 0, $destW, $destH, $textWidth, $textHeight);
        imagedestroy($txtImg);
        
        // "ERP" abaixo
        $text2 = "ERP";
        $txtImg2 = imagecreatetruecolor(imagefontwidth($font) * strlen($text2), $textHeight);
        $txtBg2 = imagecolorallocate($txtImg2, $bgColor[0], $bgColor[1], $bgColor[2]);
        $txtWhite2 = imagecolorallocate($txtImg2, 96, 165, 250);
        imagefilledrectangle($txtImg2, 0, 0, imagefontwidth($font) * strlen($text2), $textHeight, $txtBg2);
        imagestring($txtImg2, $font, 0, 0, $text2, $txtWhite2);
        
        $destW2 = imagefontwidth($font) * strlen($text2) * max(1, (int)($scale * 0.7));
        $destH2 = $textHeight * max(1, (int)($scale * 0.7));
        $destX2 = (int)(($size - $destW2) / 2);
        $destY2 = $destY + $destH + (int)($size * 0.02);
        
        imagecopyresized($img, $txtImg2, $destX2, $destY2, 0, 0, $destW2, $destH2, imagefontwidth($font) * strlen($text2), $textHeight);
        imagedestroy($txtImg2);
    } else {
        // Para ícones pequenos, texto simples
        $text = "SOS";
        $textWidth = imagefontwidth($font) * strlen($text);
        $x = (int)(($size - $textWidth) / 2);
        $y = (int)(($size - imagefontheight($font)) / 2) - 2;
        imagestring($img, $font, $x, $y, $text, $white);
        
        // "ERP" menor
        $font2 = 3;
        $text2 = "ERP";
        $textWidth2 = imagefontwidth($font2) * strlen($text2);
        $x2 = (int)(($size - $textWidth2) / 2);
        $y2 = $y + imagefontheight($font) + 1;
        imagestring($img, $font2, $x2, $y2, $text2, $lightBlue);
    }
    
    // Salvar
    $filename = __DIR__ . "/icon-{$size}x{$size}.png";
    imagepng($img, $filename);
    imagedestroy($img);
    
    echo "Generated: icon-{$size}x{$size}.png\n";
}

echo "\nAll PWA icons generated successfully!\n";
