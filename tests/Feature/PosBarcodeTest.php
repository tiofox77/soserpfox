<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Leitura de código de barras no POS.
 *
 * O leitor escreve o código no campo de procura. Até aqui isso só filtrava a
 * grelha — e a grelha esconde o que está sem stock (1415 de 5729 artigos numa
 * das farmácias), por isso ler um artigo esgotado devolvia um ecrã vazio,
 * indistinguível de «este código não existe». O operador concluía que a leitura
 * não funcionava, com o produto na mão.
 *
 * O BALCÃO É HOJE REACT e a regra tinha-se perdido outra vez: a procura filtrava
 * a grelha e mais nada. O ensaio que a guardava apontava para um componente
 * Livewire que já nenhuma rota serve.
 *
 * Agora há uma porta que pergunta ao CATÁLOGO INTEIRO e diz qual dos casos é.
 */
class PosBarcodeTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/pos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.view')
            ->comModulo('invoicing');

        \App\Models\Invoicing\PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'T'.strtoupper(substr(uniqid(), -8)),
            'opened_at' => now(),
            'status' => 'open',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
    }

    private function comCodigo(string $codigo, float $stock = 10): Product
    {
        $p = $this->produtoComStock($stock);
        $p->update(['barcode' => $codigo]);

        return $p->fresh();
    }

    private function ler(string $codigo)
    {
        return $this->actingAs($this->user)
            ->getJson(self::RAIZ.'/por-codigo?codigo='.urlencode($codigo))->assertOk();
    }

    public function test_ler_um_codigo_encontra_o_artigo(): void
    {
        $p = $this->comCodigo('5601234567890', 10);

        $this->ler('5601234567890')
            ->assertJsonPath('estado', 'encontrado')
            ->assertJsonPath('artigo.id', $p->id);
    }

    /**
     * ESTE É O CASO QUE FAZIA PARECER QUE A LEITURA NÃO FUNCIONAVA.
     *
     * A grelha esconde o que não tem stock, e o operador via um ecrã vazio.
     */
    public function test_um_artigo_esgotado_diz_porque_nao_entra(): void
    {
        $this->comCodigo('5609999999999', 0);

        $r = $this->ler('5609999999999')->assertJsonPath('estado', 'sem_stock');

        $this->assertStringContainsString('sem stock', (string) $r->json('message'));
    }

    public function test_um_artigo_inactivo_nao_se_vende(): void
    {
        $p = $this->comCodigo('5608888888888', 10);
        $p->update(['is_active' => false]);

        $r = $this->ler('5608888888888')->assertJsonPath('estado', 'inactivo');

        $this->assertStringContainsString('inactivo', (string) $r->json('message'));
    }

    /** E um artigo de módulo também se diz pelo nome, em vez de ficar mudo. */
    public function test_um_artigo_de_modulo_diz_onde_se_vende(): void
    {
        $p = $this->comCodigo('5604444444444', 10);
        $p->module = 'salon';
        $p->save();

        $r = $this->ler('5604444444444')->assertJsonPath('estado', 'de_modulo');

        $this->assertStringContainsString('salon', (string) $r->json('message'));
    }

    /** Um código curto nem chega a ser uma leitura: é alguém a escrever. */
    public function test_um_codigo_parcial_nao_dispara(): void
    {
        $this->comCodigo('5606666666666', 10);

        $this->ler('560')->assertJsonPath('estado', 'curto');
    }

    public function test_um_codigo_desconhecido_diz_se_desconhecido(): void
    {
        $this->ler('1234567890123')->assertJsonPath('estado', 'desconhecido');
    }

    /** O código de outra empresa não existe nesta. */
    public function test_o_codigo_de_outra_empresa_nao_entra(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Alheia', 'slug' => 'alheia-'.uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'a'.uniqid().'@x.ao', 'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio '.uniqid(),
            'code' => 'AL'.strtoupper(substr(uniqid(), -6)), 'barcode' => '5605555555555',
            'type' => 'produto', 'price' => 100, 'cost' => 50, 'unit' => 'UN',
            'manage_stock' => true, 'is_active' => true, 'stock_quantity' => 99,
        ]);

        $this->ler('5605555555555')->assertJsonPath('estado', 'desconhecido');
    }

    /**
     * O ENVELOPE GS1 E O EAN-13 SÃO O MESMO ARTIGO.
     *
     * O catálogo pode ter vindo de um sistema que guardava a linha completa do
     * leitor; o leitor da loja manda só o EAN-13 de dentro — e ao contrário.
     */
    public function test_le_o_ean13_e_encontra_o_artigo_guardado_com_o_envelope_gs1(): void
    {
        $p = $this->comCodigo('0108902292003269');

        $this->ler('8902292003269')
            ->assertJsonPath('estado', 'encontrado')
            ->assertJsonPath('artigo.id', $p->id);
    }

    public function test_le_o_envelope_gs1_e_encontra_o_artigo_guardado_como_ean13(): void
    {
        $p = $this->comCodigo('8902292003269');

        $this->ler('0108902292003269')
            ->assertJsonPath('estado', 'encontrado')
            ->assertJsonPath('artigo.id', $p->id);
    }

    public function test_um_codigo_interno_com_zeros_a_frente_continua_a_encontrar_se(): void
    {
        // Os zeros à frente são do código interno da farmácia, não são envelope
        // nenhum: têm de continuar a dar correspondência exacta.
        $p = $this->comCodigo('0000548');

        $this->ler('0000548')
            ->assertJsonPath('estado', 'encontrado')
            ->assertJsonPath('artigo.id', $p->id);
    }

    /**
     * E O ECRÃ PERGUNTA ANTES DE DIZER QUE NÃO EXISTE.
     *
     * Um ecrã que se limitasse a dizer «nada encontrado» punha o defeito de
     * volta, com a porta do servidor já feita.
     */
    public function test_o_ecra_pergunta_ao_catalogo_antes_de_dizer_que_nao_existe(): void
    {
        $fonte = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        $this->assertStringContainsString('pos.porCodigo(procura)', $fonte);
        $this->assertMatchesRegularExpression(
            "/porCodigo\(procura\)[\s\S]{0,800}Nada encontrado para/",
            $fonte,
            'o aviso genérico tem de vir DEPOIS de se perguntar ao catálogo',
        );
    }
}
