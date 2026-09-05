<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Os ícones MASCARÁVEIS do PWA — os que o Android não corta.
 *
 * O QUE ESTAVA MAL. O manifesto declarava todos os ícones como
 * `purpose: "any maskable"`. Isso é uma PROMESSA ao sistema operativo: "este
 * desenho tem margem de segurança, podes recortá-lo à vontade". Não tinha: o
 * crachá do SOS toca as quatro bordas, margem zero.
 *
 * O Android acredita na promessa e aplica a máscara adaptativa do lançador —
 * um círculo, um quadrado redondo, uma gota, consoante o telemóvel. O
 * resultado é o anel azul do logótipo cortado dos quatro lados, e o ícone da
 * aplicação a parecer partido no ecrã principal de quem a instalou.
 *
 * A ZONA SEGURA é um círculo com 80% do lado da tela. Tudo o que ficar fora
 * pode desaparecer. Aqui o crachá é desenhado a 72% e centrado: cabe folgado
 * nesse círculo, e o que a máscara corta é fundo.
 *
 * Estes ficheiros são gerados e vão para o repositório — não se geram a cada
 * pedido. Correr outra vez só quando o logótipo mudar.
 */
class GerarIconesMascaraveis extends Command
{
    protected $signature = 'pwa:icones-mascaraveis {--fundo=#ffffff : Cor de fundo do ícone}';

    protected $description = 'Gera os ícones mascaráveis do PWA a partir do ícone principal (zona segura respeitada)';

    /** Quanto do lado o crachá ocupa. Acima de ~0,76 a máscara começa a morder. */
    private const FRACCAO = 0.72;

    private const TAMANHOS = [72, 96, 128, 144, 152, 192, 384, 512];

    public function handle(): int
    {
        $origem = public_path('pwa/icon-512x512.png');

        if (!is_file($origem)) {
            $this->error("Ícone de origem não encontrado: {$origem}");

            return self::FAILURE;
        }

        [$r, $g, $b] = $this->corDeFundo((string) $this->option('fundo'));

        $logotipo = imagecreatefrompng($origem);

        if ($logotipo === false) {
            $this->error('Não foi possível ler o ícone de origem.');

            return self::FAILURE;
        }

        foreach (self::TAMANHOS as $lado) {
            $tela = imagecreatetruecolor($lado, $lado);

            // O FUNDO É OPACO, de propósito. Um ícone mascarável com fundo
            // transparente aparece como uma silhueta recortada no lançador —
            // a máscara não tem nada para preencher e o sistema põe preto por
            // baixo em alguns telemóveis.
            $fundo = imagecolorallocate($tela, $r, $g, $b);
            imagefilledrectangle($tela, 0, 0, $lado, $lado, $fundo);

            imagealphablending($tela, true);

            $ladoDoCracha = (int) round($lado * self::FRACCAO);
            $margem = (int) round(($lado - $ladoDoCracha) / 2);

            imagecopyresampled(
                $tela,
                $logotipo,
                $margem,
                $margem,
                0,
                0,
                $ladoDoCracha,
                $ladoDoCracha,
                imagesx($logotipo),
                imagesy($logotipo)
            );

            $destino = public_path("pwa/icon-maskable-{$lado}x{$lado}.png");
            imagepng($tela, $destino, 9);
            imagedestroy($tela);

            $this->line("  <fg=green>✓</> " . basename($destino));
        }

        imagedestroy($logotipo);

        $this->newLine();
        $this->info('Ícones mascaráveis gerados. O manifesto serve-os com purpose="maskable".');

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int,2:int} */
    private function corDeFundo(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [255, 255, 255];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
