<?php

namespace Tests\Unit;

use App\Services\AGT\QRCodeService;
use ReflectionMethod;
use Tests\TestCase;

class QRCodeLogoAgtTest extends TestCase
{
    public function test_simbolo_agt_fica_visivel_no_centro_do_qr_png(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD não disponível.');
        }

        $base = imagecreatetruecolor(100, 100);
        imagefill($base, 0, 0, imagecolorallocate($base, 255, 255, 255));
        ob_start(); imagepng($base); $png = ob_get_clean(); imagedestroy($base);

        $method = new ReflectionMethod(QRCodeService::class, 'embedLogoInPng');
        $result = $method->invoke(new QRCodeService(), $png, 100);
        $image = imagecreatefromstring($result);

        $bluePixels = 0;
        for ($y = 35; $y < 65; $y++) {
            for ($x = 35; $x < 65; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xff; $g = ($rgb >> 8) & 0xff; $b = $rgb & 0xff;
                if ($b > 70 && $b > $r * 1.18 && $b > $g * 1.05) $bluePixels++;
            }
        }
        imagedestroy($image);

        $this->assertGreaterThan(80, $bluePixels, 'O símbolo azul AGT deve ser claramente visível no centro.');
    }
}
