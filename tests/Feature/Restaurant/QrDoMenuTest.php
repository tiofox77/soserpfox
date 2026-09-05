<?php

namespace Tests\Feature\Restaurant;

use App\Services\Restaurant\QrDoMenu;
use Tests\TestCase;

/**
 * O QR que vai colado à mesa tem de LER-SE.
 *
 * É o ensaio mais importante desta funcionalidade, e o mais fácil de não
 * fazer: um QR com um logótipo ao meio parece sempre bem no ecrã. O que se
 * partiu foi o conteúdo — tapar o centro destrói módulos, e se a correcção de
 * erro não for a mais alta o código deixa de descodificar.
 *
 * O modo de falhar é cruel: funciona no telemóvel de quem testou, falha no do
 * cliente, e só se descobre depois de cem autocolantes impressos e colados.
 *
 * Por isso aqui não se olha para a imagem — descodifica-se, e compara-se com
 * o endereço que lá devia estar. Nos tamanhos pequenos e com endereços longos,
 * que é onde a margem acaba.
 */
class QrDoMenuTest extends TestCase
{
    private function descodificar(string $png): ?string
    {
        $temporario = tempnam(sys_get_temp_dir(), 'qr').'.png';
        file_put_contents($temporario, $png);

        $guiao = base_path('scripts/ler-qr.mjs');

        $saida = [];
        $codigo = 0;
        exec('node '.escapeshellarg($guiao).' '.escapeshellarg($temporario).' 2>&1', $saida, $codigo);

        @unlink($temporario);

        $linha = trim(implode("\n", $saida));

        if ($codigo !== 0) {
            $this->markTestSkipped('Leitor de QR indisponível (npm i -D jsqr pngjs): '.$linha);
        }

        return str_starts_with($linha, 'LIDO: ') ? substr($linha, 6) : null;
    }

    public static function casos(): array
    {
        return [
            // O caso normal.
            'endereço curto, tamanho de impressão' => ['https://soserp.vip/menu/piteu', 600],
            // Um QR pequeno num cartão de mesa.
            'endereço curto, pequeno' => ['https://soserp.vip/menu/piteu', 200],
            // O pior caso real: nome de restaurante comprido + código de mesa.
            // Mais dados = mais módulos = módulos mais pequenos = menos margem
            // para o logótipo tapar.
            'endereço longo, tamanho de impressão' => [
                'https://soserp.vip/menu/restaurante-sabores-ao-rubro-o-piteu/MESA-TERRACO-12', 600,
            ],
            'endereço longo, pequeno' => [
                'https://soserp.vip/menu/restaurante-sabores-ao-rubro-o-piteu/MESA-TERRACO-12', 300,
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider casos
     */
    public function o_qr_com_logotipo_continua_a_ler_se(string $url, int $lado): void
    {
        $png = (new QrDoMenu)->png($url, $lado);

        $this->assertSame($url, $this->descodificar($png));
    }

    /** @test */
    public function um_logotipo_que_nao_existe_nao_estraga_o_qr(): void
    {
        // O autocolante sai sem marca, e não sai vazio. Um QR ilegível colado
        // numa mesa é pior do que um QR sem logótipo.
        $url = 'https://soserp.vip/menu/piteu';

        $png = (new QrDoMenu)->png($url, 400, '/caminho/que/nao/existe.png');

        $this->assertSame($url, $this->descodificar($png));
    }

    /** @test */
    public function o_data_uri_serve_para_por_num_img(): void
    {
        $uri = (new QrDoMenu)->dataUri('https://soserp.vip/menu/piteu', 300);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertSame(
            'https://soserp.vip/menu/piteu',
            $this->descodificar(base64_decode(substr($uri, strlen('data:image/png;base64,'))))
        );
    }
}
