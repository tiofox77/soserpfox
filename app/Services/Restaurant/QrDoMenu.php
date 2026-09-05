<?php

namespace App\Services\Restaurant;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;

/**
 * O QR que vai colado à mesa.
 *
 * Um cliente aponta o telemóvel e a carta abre já com a mesa identificada. É
 * a peça que faz o menu online valer alguma coisa numa sala: sem ela, alguém
 * tem de escrever um endereço e depois dizer em que mesa está — e é aí que se
 * perde metade das pessoas.
 *
 * O LOGÓTIPO AO MEIO não é enfeite: um QR sem marca, impresso e colado numa
 * mesa, não diz a ninguém o que vai acontecer se o apontarem. Com a marca lá,
 * lê-se como uma carta e não como um código de rastreio.
 *
 * E é por isso que a correcção de erro é a mais alta (H, 30%). Tapar o centro
 * do QR destrói módulos; com correcção alta, o código continua a ler-se com
 * quase um terço dele ilegível. Com a correcção normal, um logótipo deste
 * tamanho tornava o QR inútil — e ninguém dava por isso até imprimir cem
 * autocolantes.
 */
class QrDoMenu
{
    /** Quanto do lado do QR o logótipo ocupa. Acima de ~0,22 nem o nível H salva. */
    private const FRACCAO_DO_LOGOTIPO = 0.20;

    /**
     * Devolve os bytes de um PNG com o QR e o logótipo ao centro.
     *
     * @param string $url     o endereço que o QR abre
     * @param int    $lado    lado do PNG, em pixels
     * @param string|null $logotipo caminho para o PNG do logótipo; null = o ícone do SOS ERP
     */
    public function png(string $url, int $lado = 600, ?string $logotipo = null): string
    {
        $renderer = new GDLibRenderer($lado, 2, 'png', 9);
        $writer = new Writer($renderer);

        $bytes = $writer->writeString($url, 'UTF-8', ErrorCorrectionLevel::H());

        $logotipo = $logotipo ?: public_path('pwa/icon-192x192.png');

        // Um QR sem logótipo continua a ser um QR que funciona. Se a imagem
        // não estiver lá, ou o GD tropeçar, devolve-se o código limpo em vez
        // de rebentar — o autocolante sai sem marca, e não sai vazio.
        try {
            return $this->comLogotipoAoCentro($bytes, $logotipo);
        } catch (\Throwable $e) {
            Log::warning('QrDoMenu: logótipo não aplicado', ['erro' => $e->getMessage()]);

            return $bytes;
        }
    }

    /** O mesmo, já pronto a pôr num `<img src="...">`. */
    public function dataUri(string $url, int $lado = 600, ?string $logotipo = null): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($url, $lado, $logotipo));
    }

    private function comLogotipoAoCentro(string $pngDoQr, string $caminhoDoLogotipo): string
    {
        if (!is_file($caminhoDoLogotipo)) {
            throw new \RuntimeException("Logótipo não encontrado: {$caminhoDoLogotipo}");
        }

        $qr = imagecreatefromstring($pngDoQr);
        $marca = imagecreatefromstring(file_get_contents($caminhoDoLogotipo));

        if ($qr === false || $marca === false) {
            throw new \RuntimeException('Imagem ilegível ao compor o QR.');
        }

        try {
            $ladoDoQr = imagesx($qr);
            $ladoDaMarca = (int) round($ladoDoQr * self::FRACCAO_DO_LOGOTIPO);

            // A ALMOFADA BRANCA POR BAIXO.
            //
            // Sem ela, o logótipo assenta em cima dos módulos pretos e os
            // leitores confundem-se nas bordas — funcionava num telemóvel e
            // falhava no seguinte. Um quadrado branco com cantos limpos dá ao
            // descodificador uma zona sem ruído, que é o que a correcção de
            // erro espera reconstruir.
            $almofada = (int) round($ladoDaMarca * 1.18);
            $x = (int) round(($ladoDoQr - $almofada) / 2);
            $y = $x;

            $branco = imagecolorallocate($qr, 255, 255, 255);
            imagefilledrectangle($qr, $x, $y, $x + $almofada, $y + $almofada, $branco);

            imagealphablending($qr, true);
            imagecopyresampled(
                $qr,
                $marca,
                (int) round(($ladoDoQr - $ladoDaMarca) / 2),
                (int) round(($ladoDoQr - $ladoDaMarca) / 2),
                0,
                0,
                $ladoDaMarca,
                $ladoDaMarca,
                imagesx($marca),
                imagesy($marca)
            );

            ob_start();
            imagepng($qr);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($qr);
            imagedestroy($marca);
        }
    }
}
