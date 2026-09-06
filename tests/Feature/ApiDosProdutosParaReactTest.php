<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Stock;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * A API dos artigos.
 *
 * O QUE ESTES ENSAIOS GUARDAM é a regra que este código já aprendeu à sua
 * custa: **o `stock_quantity` é um agregado derivado das linhas**, mantido
 * pelo `StockObserver`. Escrevê-lo numa edição devolvia o valor que estava no
 * ecrã quando ele abriu — revertendo as vendas que aconteceram entretanto — e
 * criava ajustes fantasma sem movimento nem rasto.
 *
 * E a outra: um artigo já vendido não se apaga, desactiva-se. A linha da
 * factura aponta para ele.
 */
class ApiDosProdutosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/products';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function categoria(): Category
    {
        return Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );
    }

    private function artigo(array $por = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo ' . uniqid(),
            'type' => 'produto',
            'price' => 1000,
            'unit' => 'un',
            'category_id' => $this->categoria()->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'manage_stock' => true,
            'stock_quantity' => 10,
            'is_active' => true,
        ], $por));
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'name' => 'Artigo de Ensaio',
            'type' => 'produto',
            'price' => 1500,
            'unit' => 'un',
            'category_id' => $this->categoria()->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
        ], $por);
    }

    /* ─── Permissões ──────────────────────────────────────────────────── */

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $artigo = $this->artigo();

        $this->getJson(self::RAIZ)->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
        $this->putJson(self::RAIZ . '/' . $artigo->id, $this->corpo())->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $artigo->id)->assertForbidden();

        $this->comPermissoes('invoicing.products.view');

        $this->getJson(self::RAIZ)->assertOk();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /* ─── A REGRA DO STOCK ────────────────────────────────────────────── */

    /**
     * EDITAR UM ARTIGO NÃO MEXE NO STOCK. Nunca.
     *
     * @test
     */
    public function a_edicao_nao_toca_no_stock(): void
    {
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo(['stock_quantity' => 42]);

        // O corpo até traz `stock_quantity` — e tem de ser ignorado.
        $this->putJson(self::RAIZ . '/' . $artigo->id, $this->corpo([
            'name' => 'Nome Novo',
            'stock_quantity' => 999,
        ]))->assertOk();

        $this->assertSame(42.0, (float) $artigo->fresh()->stock_quantity,
            'o agregado do stock não pode mudar por uma edição de ficha');
        $this->assertSame('Nome Novo', $artigo->fresh()->name);
    }

    /** Na CRIAÇÃO, a quantidade inicial entra — é a única altura em que entra. @test */
    public function a_criacao_aceita_a_quantidade_inicial(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $id = $this->postJson(self::RAIZ, $this->corpo(['stock_quantity' => 7]))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(7.0, (float) Product::find($id)->stock_quantity);
    }

    /**
     * O STOCK QUE SAI É A SOMA DAS LINHAS, não a coluna agregada.
     *
     * Quando as duas discordam — e discordam quando o agregado ficou para
     * trás — é a soma que diz a verdade.
     *
     * @test
     */
    public function o_stock_mostrado_vem_das_linhas(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $artigo = $this->artigo(['stock_quantity' => 999]);

        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $artigo->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => 3,
        ]);

        $linha = collect($this->getJson(self::RAIZ)->json('data'))->firstWhere('id', $artigo->id);

        $this->assertSame(3.0, (float) $linha['stock'],
            'o que se mostra é a soma das linhas, não o agregado desactualizado');
    }

    /** Um SERVIÇO não gere stock, mesmo que peçam. @test */
    public function um_servico_nao_gere_stock(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'type' => 'servico',
            'manage_stock' => true,
        ]))->assertCreated()->json('data.id');

        $this->assertFalse((bool) Product::find($id)->manage_stock,
            'um serviço com manage_stock ligado desaparecia do POS ao chegar a zero');
    }

    /**
     * OS CAMPOS DEIXADOS EM BRANCO NÃO REBENTAM A GRAVAÇÃO.
     *
     * `cost` e `stock_min` são NOT NULL *com omissão na base* — e uma omissão
     * só se aplica quando a coluna não vem no INSERT. Mandar `null`
     * explicitamente atropela-a: «Column 'cost' cannot be null», 500 no ecrã.
     * Um campo em branco no formulário chega cá como null.
     *
     * @test
     */
    public function os_campos_numericos_em_branco_gravam_como_zero(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'cost' => null,
            'stock_min' => null,
        ]))->assertCreated()->json('data.id');

        $artigo = Product::find($id);

        $this->assertSame(0.0, (float) $artigo->cost);
        $this->assertSame(0, (int) $artigo->stock_min);
    }

    /* ─── O imposto ───────────────────────────────────────────────────── */

    /** @test */
    public function o_imposto_e_uma_taxa_do_catalogo_ou_uma_isencao_com_motivo(): void
    {
        $this->comPermissoes('invoicing.products.create');

        // IVA sem taxa escolhida: recusa.
        $this->postJson(self::RAIZ, $this->corpo(['tax_type' => 'iva', 'tax_rate_id' => null]))
            ->assertJsonValidationErrors('tax_rate_id');

        // Isento sem motivo: recusa. A AGT exige o motivo da isenção.
        $this->postJson(self::RAIZ, $this->corpo(['tax_type' => 'isento', 'exemption_reason' => null]))
            ->assertJsonValidationErrors('exemption_reason');
    }

    /** Escolher isento limpa a taxa, e vice-versa: nunca os dois. @test */
    public function nao_ficam_os_dois_gravados(): void
    {
        $this->comPermissoes('invoicing.products.create', 'invoicing.products.edit');

        $taxa = \App\Models\Invoicing\Tax::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true]
        );

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
            'tax_rate_id' => $taxa->id,
        ]))->assertCreated()->json('data.id');

        $this->assertNull(Product::find($id)->tax_rate_id,
            'isento não guarda taxa nenhuma');
    }

    /* ─── Apagar ──────────────────────────────────────────────────────── */

    /**
     * UM ARTIGO JÁ VENDIDO NÃO SE APAGA — DESACTIVA-SE.
     *
     * A linha da factura aponta para ele; apagá-lo deixava documentos fiscais
     * a referir um artigo que já não existe.
     *
     * @test
     */
    public function um_artigo_ja_vendido_desactiva_se_em_vez_de_ser_apagado(): void
    {
        $this->comPermissoes('invoicing.products.delete');

        $artigo = $this->artigo();

        $factura = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $factura->id,
            'product_id' => $artigo->id,
            'product_name' => $artigo->name,
            'description' => $artigo->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this->deleteJson(self::RAIZ . '/' . $artigo->id)
            ->assertOk()
            ->assertJsonPath('desactivado', true);

        $fresco = $artigo->fresh();

        $this->assertNotNull($fresco, 'não pode ter sido apagado');
        $this->assertFalse((bool) $fresco->is_active, 'tinha de ficar desactivado');
    }

    /** Um artigo nunca vendido apaga-se. @test */
    public function um_artigo_nunca_vendido_apaga_se(): void
    {
        $this->comPermissoes('invoicing.products.delete');

        $artigo = $this->artigo();

        $this->deleteJson(self::RAIZ . '/' . $artigo->id)
            ->assertOk()
            ->assertJsonPath('desactivado', false);

        $this->assertSoftDeleted('invoicing_products', ['id' => $artigo->id]);
    }

    /* ─── Ler ─────────────────────────────────────────────────────────── */

    /** @test */
    public function nao_se_veem_os_artigos_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra',
            'slug' => 'outra-' . uniqid(),
            'email' => 'o' . uniqid() . '@ex.com',
        ]);

        $meu = $this->artigo();
        $alheio = Product::create([
            'tenant_id' => $outra->id,
            'name' => 'Artigo Alheio',
            'type' => 'produto',
            'price' => 1,
            'unit' => 'un',
            'tax_type' => 'isento',
        ]);

        $ids = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertFalse($ids->contains($alheio->id));
    }

    /** «Em falta» só conta em quem gere stock E tem mínimo definido. @test */
    public function em_falta_exige_minimo_definido(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $semMinimo = $this->artigo(['stock_quantity' => 0, 'stock_min' => 0]);
        $comMinimo = $this->artigo(['stock_quantity' => 1, 'stock_min' => 5]);
        $servico = $this->artigo(['type' => 'servico', 'manage_stock' => false]);

        $porId = collect($this->getJson(self::RAIZ)->json('data'))->keyBy('id');

        $this->assertFalse($porId[$semMinimo->id]['em_falta'],
            'sem mínimo definido, todo o artigo a zero aparecia sempre em falta');
        $this->assertTrue($porId[$comMinimo->id]['em_falta']);
        $this->assertFalse($porId[$servico->id]['em_falta'], 'um serviço nunca está em falta');
        $this->assertNull($porId[$servico->id]['stock'], 'e não tem stock nenhum para mostrar');
    }

    /* ─── AS IMAGENS ──────────────────────────────────────────────────── */

    /*
     * O SÍTIO E O FORMATO SÃO OS DE SEMPRE: caminho relativo no disco
     * `public`, coluna `featured_image` para a de destaque e a coluna JSON
     * `gallery` para as restantes. É o que o POS do restaurante, a carta e a
     * transferência entre empresas já lêem — um segundo esquema deixava
     * metade do sistema a ver imagens e a outra metade a ver caixas vazias.
     */

    private function disco(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::fake('public');
    }

    /**
     * Um ficheiro em multipart — com o `Accept: application/json` à mão.
     *
     * O `postJson` não serve (um ficheiro não viaja em JSON) e o `post` seco
     * não diz que quer JSON: uma validação falhada subia como excepção em vez
     * de voltar como 422, e o ensaio rebentava em vez de medir.
     */
    private function enviar(string $url, array $dados): \Illuminate\Testing\TestResponse
    {
        return $this->post($url, $dados, ['Accept' => 'application/json']);
    }

    /** @test */
    public function a_imagem_de_destaque_grava_no_disco_e_na_coluna_de_sempre(): void
    {
        $disco = $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo(['name' => 'Xarope da Tosse']);

        $resposta = $this->enviar(self::RAIZ . '/' . $artigo->id . '/imagem', [
            'imagem' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertOk();

        $caminho = $artigo->fresh()->featured_image;

        $this->assertSame('products/' . $artigo->id . '/featured_xarope-da-tosse.jpg', $caminho,
            'o caminho é o mesmo que o ecrã de sempre gravava');
        $disco->assertExists($caminho);

        // A ficha sai com as duas formas: o caminho (a chave) e a URL (o que se mostra).
        $resposta->assertJsonPath('data.imagem_caminho', $caminho);
        $this->assertStringContainsString($caminho, $resposta->json('data.imagem'));
    }

    /**
     * SUBSTITUIR NÃO PODE APAGAR A IMAGEM QUE ACABOU DE SUBIR.
     *
     * O nome sai do nome do artigo: um JPEG substituído por outro JPEG dá o
     * MESMO caminho. O ecrã de sempre escrevia a nova e a seguir apagava «a
     * anterior» — que era o mesmo ficheiro. O artigo ficava com um caminho
     * gravado a apontar para o vazio, e ninguém percebia porquê.
     *
     * @test
     */
    public function substituir_a_imagem_com_o_mesmo_nome_nao_a_apaga(): void
    {
        $disco = $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo(['name' => 'Xarope da Tosse']);
        $raiz = self::RAIZ . '/' . $artigo->id . '/imagem';

        $this->enviar($raiz, ['imagem' => UploadedFile::fake()->image('primeira.jpg')])->assertOk();
        $this->enviar($raiz, ['imagem' => UploadedFile::fake()->image('segunda.jpg')])->assertOk();

        $disco->assertExists($artigo->fresh()->featured_image);
    }

    /** Uma imagem de outro formato substitui a anterior E tira-a do disco. @test */
    public function substituir_por_outro_formato_leva_a_anterior_do_disco(): void
    {
        $disco = $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo(['name' => 'Creme de Rosto']);
        $raiz = self::RAIZ . '/' . $artigo->id . '/imagem';

        $this->enviar($raiz, ['imagem' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $antiga = $artigo->fresh()->featured_image;

        $this->enviar($raiz, ['imagem' => UploadedFile::fake()->image('b.png')])->assertOk();

        $disco->assertMissing($antiga);
        $disco->assertExists($artigo->fresh()->featured_image);
        $this->assertStringEndsWith('.png', $artigo->fresh()->featured_image);
    }

    /** Apagar a imagem tira-a do artigo E do disco. @test */
    public function apagar_a_imagem_de_destaque_limpa_as_duas_pontas(): void
    {
        $disco = $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo();
        $raiz = self::RAIZ . '/' . $artigo->id . '/imagem';

        $this->enviar($raiz, ['imagem' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $caminho = $artigo->fresh()->featured_image;

        $this->deleteJson($raiz)->assertOk()->assertJsonPath('data.imagem', null);

        $this->assertNull($artigo->fresh()->featured_image);
        $disco->assertMissing($caminho);
    }

    /**
     * A GALERIA ACRESCENTA — nunca substitui.
     *
     * E os nomes têm de ser diferentes: o ecrã de sempre juntava a posição na
     * lista ao segundo do relógio, e duas imagens acrescentadas no mesmo
     * segundo davam o mesmo `gallery_1_…` — a segunda apagava a primeira sem
     * uma palavra.
     *
     * @test
     */
    public function a_galeria_acrescenta_e_cada_imagem_fica_com_o_seu_ficheiro(): void
    {
        $disco = $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo();
        $raiz = self::RAIZ . '/' . $artigo->id . '/galeria';

        $this->enviar($raiz, [
            'imagens' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')],
        ])->assertOk();

        $this->enviar($raiz, ['imagens' => [UploadedFile::fake()->image('3.jpg')]])->assertOk();

        $galeria = $artigo->fresh()->gallery;

        $this->assertCount(3, $galeria, 'a segunda subida acrescenta, não substitui');
        $this->assertSame($galeria, array_unique($galeria), 'dois ficheiros nunca partilham o mesmo caminho');

        foreach ($galeria as $caminho) {
            $disco->assertExists($caminho);
        }
    }

    /** A galeria tem tecto: sem ele, uma tecla presa enchia o disco da empresa. @test */
    public function a_galeria_recusa_passar_do_tecto(): void
    {
        $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo();
        $raiz = self::RAIZ . '/' . $artigo->id . '/galeria';

        $dez = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 10));

        $this->enviar($raiz, ['imagens' => $dez])->assertOk();
        $this->enviar($raiz, ['imagens' => [UploadedFile::fake()->image('a-mais.jpg')]])
            ->assertJsonValidationErrors('imagens');

        $this->assertCount(10, $artigo->fresh()->gallery);
    }

    /**
     * O CAMINHO A APAGAR CONFIRMA-SE CONTRA A GALERIA DESTE ARTIGO.
     *
     * Vem do pedido, e um caminho do pedido é um caminho que alguém pode
     * escrever à mão. Sem esta confirmação apagava-se o que não era dele.
     *
     * @test
     */
    public function so_se_apaga_da_galeria_uma_imagem_deste_artigo(): void
    {
        $disco = $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $meu = $this->artigo();
        $outro = $this->artigo();

        $this->enviar(self::RAIZ . '/' . $meu->id . '/galeria', [
            'imagens' => [UploadedFile::fake()->image('minha.jpg')],
        ])->assertOk();

        $this->enviar(self::RAIZ . '/' . $outro->id . '/galeria', [
            'imagens' => [UploadedFile::fake()->image('alheia.jpg')],
        ])->assertOk();

        $alheia = $outro->fresh()->gallery[0];

        $this->deleteJson(self::RAIZ . '/' . $meu->id . '/galeria?caminho=' . urlencode($alheia))
            ->assertNotFound();

        $disco->assertExists($alheia);
        $this->assertCount(1, $outro->fresh()->gallery);

        // E a que é mesmo dele apaga-se.
        $minha = $meu->fresh()->gallery[0];

        $this->deleteJson(self::RAIZ . '/' . $meu->id . '/galeria?caminho=' . urlencode($minha))
            ->assertOk()
            ->assertJsonPath('data.galeria', []);

        $disco->assertMissing($minha);
    }

    /** Um ficheiro que não é imagem não entra. @test */
    public function o_que_nao_e_imagem_nao_sobe(): void
    {
        $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo();

        $this->enviar(self::RAIZ . '/' . $artigo->id . '/imagem', [
            'imagem' => UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'),
        ])->assertJsonValidationErrors('imagem');

        $this->assertNull($artigo->fresh()->featured_image);
    }

    /** Mexer nas imagens é editar o artigo — e pede a permissão de editar. @test */
    public function as_imagens_pedem_a_permissao_de_editar(): void
    {
        $this->disco();
        $this->comPermissoes('invoicing.products.view');

        $artigo = $this->artigo();

        $this->enviar(self::RAIZ . '/' . $artigo->id . '/imagem', [
            'imagem' => UploadedFile::fake()->image('a.jpg'),
        ])->assertForbidden();

        $this->enviar(self::RAIZ . '/' . $artigo->id . '/galeria', [
            'imagens' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertForbidden();

        $this->deleteJson(self::RAIZ . '/' . $artigo->id . '/imagem')->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $artigo->id . '/galeria?caminho=x')->assertForbidden();
    }

    /** Não se põe imagem num artigo de outra empresa. @test */
    public function nao_se_mexe_nas_imagens_de_outra_empresa(): void
    {
        $this->disco();
        $this->comPermissoes('invoicing.products.edit');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra',
            'slug' => 'outra-' . uniqid(),
            'email' => 'o' . uniqid() . '@ex.com',
        ]);

        $alheio = Product::create([
            'tenant_id' => $outra->id,
            'name' => 'Artigo Alheio',
            'type' => 'produto',
            'price' => 1,
            'unit' => 'un',
            'tax_type' => 'isento',
        ]);

        $this->enviar(self::RAIZ . '/' . $alheio->id . '/imagem', [
            'imagem' => UploadedFile::fake()->image('a.jpg'),
        ])->assertNotFound();

        $this->assertNull($alheio->fresh()->featured_image);
    }

    /** Um artigo sem imagens sai com as chaves à mesma — a null e vazia. @test */
    public function um_artigo_sem_imagens_traz_as_chaves_na_mesma(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $artigo = $this->artigo();
        $linha = collect($this->getJson(self::RAIZ)->json('data'))->firstWhere('id', $artigo->id);

        // Uma chave omitida deixava a imagem do artigo anterior no ecrã.
        $this->assertArrayHasKey('imagem', $linha);
        $this->assertNull($linha['imagem']);
        $this->assertSame([], $linha['galeria']);
    }
}
