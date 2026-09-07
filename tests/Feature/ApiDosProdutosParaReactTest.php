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

    private function marca(): \App\Models\Brand
    {
        return \App\Models\Brand::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Marca de Ensaio'],
            ['is_active' => true]
        );
    }

    private function fornecedor(): \App\Models\Supplier
    {
        return \App\Models\Supplier::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor de Ensaio'],
            ['is_active' => true]
        );
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

    /*
     * A PROCURA — o que o selector de artigos dos editores usa.
     *
     * O modal «Adicionar artigo» dos emissores (`EscolhaDeArtigo.tsx`) manda
     * `procura` e `activo=1` a esta rota e mostra o que vier. Filtrar no
     * browser obrigava a descarregar o catálogo inteiro — cinco mil artigos
     * numa empresa grande — e era exactamente o que se veio evitar. Por isso
     * o que se prova aqui é o contrato de que esse ecrã depende: que se
     * procura pelas QUATRO referências do artigo, e que um artigo
     * desactivado não aparece a quem pediu só os activos.
     */

    /** @test */
    public function a_procura_encontra_pelo_nome_pelo_codigo_pelo_sku_e_pelo_codigo_de_barras(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $cimento = $this->artigo(['name' => 'Cimento Portland 50kg', 'code' => 'CIM-050']);
        $tinta = $this->artigo(['name' => 'Tinta Plástica Branca', 'sku' => 'TNT-BR-01']);
        $areia = $this->artigo(['name' => 'Areia Lavada', 'barcode' => '5601234567890']);

        $encontrados = fn (string $termo) => collect(
            $this->getJson(self::RAIZ . '?procura=' . urlencode($termo))->assertOk()->json('data')
        )->pluck('id');

        // Pelo nome, e por um pedaço dele: é assim que se escreve ao balcão.
        $this->assertTrue($encontrados('cim')->contains($cimento->id));
        $this->assertFalse($encontrados('cim')->contains($tinta->id));

        $this->assertTrue($encontrados('CIM-050')->contains($cimento->id), 'pelo código');
        $this->assertTrue($encontrados('TNT-BR-01')->contains($tinta->id), 'pelo SKU');
        $this->assertTrue($encontrados('5601234567890')->contains($areia->id), 'pelo código de barras');

        $this->assertCount(0, $encontrados('zzz-nao-existe-zzz'));
    }

    /**
     * O QUE O CARTÃO MOSTRA vem já decidido do servidor.
     *
     * Nome, referência, preço e — só em quem gere stock — as existências e a
     * decisão de estar em falta. Um ecrã que decidisse «em falta» por si
     * teria de repetir a regra do mínimo, e as duas divergiriam.
     *
     * @test
     */
    public function a_procura_traz_o_preco_e_o_stock_e_deixa_de_fora_os_desactivados(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $activo = $this->artigo(['name' => 'Bloco de Ensaio Activo', 'price' => 2500, 'stock_min' => 5]);
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $activo->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => 2,
        ]);

        $morto = $this->artigo(['name' => 'Bloco de Ensaio Morto', 'is_active' => false]);

        $data = collect($this->getJson(self::RAIZ . '?procura=Bloco+de+Ensaio&activo=1')->assertOk()->json('data'));

        $this->assertFalse($data->pluck('id')->contains($morto->id), 'um artigo desactivado não se factura');

        $cartao = $data->firstWhere('id', $activo->id);

        $this->assertNotNull($cartao, 'o activo tem de vir');
        $this->assertSame(2500.0, (float) $cartao['price']);
        $this->assertSame(2.0, (float) $cartao['stock'], 'o stock sai das linhas, não da coluna agregada');
        $this->assertTrue($cartao['em_falta'], 'dois abaixo do mínimo de cinco');
        $this->assertTrue($cartao['manage_stock']);
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

    /* ─── MARCA, FORNECEDOR E LOTES ───────────────────────────────────── */

    /**
     * OS SEIS CAMPOS QUE A MIGRAÇÃO PARA REACT TINHA DEIXADO PARA TRÁS.
     *
     * A marca, o fornecedor habitual e as quatro marcas de lote. Sem as
     * últimas o artigo nunca entra no controlo de lotes e validades — é o
     * `track_batches` que o `Product::controlaStock()` lê.
     *
     * @test
     */
    public function grava_e_le_a_marca_o_fornecedor_e_os_lotes(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $marca = $this->marca();
        $fornecedor = $this->fornecedor();

        $resposta = $this->postJson(self::RAIZ, $this->corpo([
            'brand_id' => $marca->id,
            'supplier_id' => $fornecedor->id,
            'track_batches' => true,
            'track_expiry' => true,
            'require_batch_on_purchase' => true,
            'require_batch_on_sale' => true,
        ]))->assertCreated();

        $artigo = Product::findOrFail($resposta->json('data.id'));

        $this->assertSame($marca->id, $artigo->brand_id);
        $this->assertSame($fornecedor->id, $artigo->supplier_id);
        $this->assertTrue($artigo->track_batches);
        $this->assertTrue($artigo->track_expiry);
        $this->assertTrue($artigo->require_batch_on_purchase);
        $this->assertTrue($artigo->require_batch_on_sale);

        // E voltam na resposta: o formulário carrega a ficha daqui.
        $resposta->assertJsonPath('data.brand_id', $marca->id)
            ->assertJsonPath('data.supplier_id', $fornecedor->id)
            ->assertJsonPath('data.track_batches', true)
            ->assertJsonPath('data.track_expiry', true)
            ->assertJsonPath('data.require_batch_on_purchase', true)
            ->assertJsonPath('data.require_batch_on_sale', true);
    }

    /**
     * AS SEIS CHAVES SAEM SEMPRE, mesmo num artigo que não tem nada disto.
     *
     * Uma chave omitida deixava o valor anterior no formulário: abrir um
     * artigo com lotes e a seguir um sem eles mostrava o segundo marcado.
     *
     * @test
     */
    public function um_artigo_comum_traz_as_seis_chaves_na_mesma(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $artigo = $this->artigo();
        $linha = collect($this->getJson(self::RAIZ)->json('data'))->firstWhere('id', $artigo->id);

        foreach (['brand_id', 'supplier_id'] as $chave) {
            $this->assertArrayHasKey($chave, $linha);
            $this->assertNull($linha[$chave]);
        }

        foreach (['track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale'] as $chave) {
            $this->assertArrayHasKey($chave, $linha);
            $this->assertFalse($linha[$chave], "«{$chave}» tem de sair sempre, e falso quando não está ligado");
        }
    }

    /**
     * A MARCA E O FORNECEDOR DE OUTRA EMPRESA NÃO ENTRAM.
     *
     * A regra de sempre era um `exists:invoicing_brands,id` seco — um número
     * escrito à mão punha a marca de outra empresa num artigo nosso.
     *
     * @test
     */
    public function a_marca_e_o_fornecedor_de_outra_empresa_sao_recusados(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra',
            'slug' => 'outra-' . uniqid(),
            'email' => 'o' . uniqid() . '@ex.com',
        ]);

        $marcaAlheia = \App\Models\Brand::create([
            'tenant_id' => $outra->id, 'name' => 'Marca Alheia', 'is_active' => true,
        ]);
        $fornecedorAlheio = \App\Models\Supplier::create([
            'tenant_id' => $outra->id, 'name' => 'Fornecedor Alheio',
        ]);

        $this->postJson(self::RAIZ, $this->corpo(['brand_id' => $marcaAlheia->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('brand_id');

        $this->postJson(self::RAIZ, $this->corpo(['supplier_id' => $fornecedorAlheio->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_id');

        // E as da própria empresa entram.
        $this->postJson(self::RAIZ, $this->corpo([
            'brand_id' => $this->marca()->id,
            'supplier_id' => $this->fornecedor()->id,
        ]))->assertCreated();
    }

    /**
     * UM SERVIÇO NÃO TEM LOTES.
     *
     * É a mesma regra do stock, continuada: um lote é uma remessa com número
     * e validade, e uma hora de trabalho não tem remessa nenhuma.
     *
     * @test
     */
    public function um_servico_nao_fica_com_controlo_de_lotes(): void
    {
        $this->comPermissoes('invoicing.products.create');
        $this->comPermissoes('invoicing.products.edit');

        $resposta = $this->postJson(self::RAIZ, $this->corpo([
            'type' => 'servico',
            'track_batches' => true,
            'track_expiry' => true,
            'require_batch_on_purchase' => true,
            'require_batch_on_sale' => true,
        ]))->assertCreated();

        $servico = Product::findOrFail($resposta->json('data.id'));

        $this->assertFalse($servico->track_batches, 'um serviço não se rastreia por lotes');
        $this->assertFalse($servico->track_expiry);
        $this->assertFalse($servico->require_batch_on_purchase);
        $this->assertFalse($servico->require_batch_on_sale);

        // E um produto com lotes que passe a serviço perde-os.
        $produto = $this->artigo(['track_batches' => true, 'require_batch_on_sale' => true]);

        $this->putJson(self::RAIZ . '/' . $produto->id, $this->corpo([
            'type' => 'servico',
            'track_batches' => true,
        ]))->assertOk();

        $this->assertFalse($produto->fresh()->track_batches);
        $this->assertFalse($produto->fresh()->require_batch_on_sale);
    }

    /** Desligar uma marca de lote desliga-a mesmo — não é um botão só de ligar. @test */
    public function as_marcas_de_lote_tambem_se_desligam(): void
    {
        $this->comPermissoes('invoicing.products.edit');

        $artigo = $this->artigo(['track_batches' => true, 'track_expiry' => true]);

        $this->putJson(self::RAIZ . '/' . $artigo->id, $this->corpo([
            'track_batches' => false,
            'track_expiry' => false,
        ]))->assertOk();

        $this->assertFalse($artigo->fresh()->track_batches);
        $this->assertFalse($artigo->fresh()->track_expiry);
    }

    /** As opções trazem as marcas e os fornecedores — só os desta empresa. @test */
    public function as_opcoes_trazem_as_marcas_e_os_fornecedores_da_empresa(): void
    {
        $this->comPermissoes('invoicing.products.view');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra',
            'slug' => 'outra-' . uniqid(),
            'email' => 'o' . uniqid() . '@ex.com',
        ]);

        $minha = $this->marca();
        $alheia = \App\Models\Brand::create([
            'tenant_id' => $outra->id, 'name' => 'Marca Alheia', 'is_active' => true,
        ]);
        // Uma marca desligada não se oferece — era o que a lista de sempre fazia.
        $desligada = \App\Models\Brand::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Marca Antiga', 'is_active' => false,
        ]);

        $meu = $this->fornecedor();
        $alheio = \App\Models\Supplier::create([
            'tenant_id' => $outra->id, 'name' => 'Fornecedor Alheio',
        ]);

        $opcoes = $this->getJson(self::RAIZ . '/opcoes')->assertOk()->json();

        $marcas = collect($opcoes['marcas'])->pluck('id');
        $this->assertTrue($marcas->contains($minha->id));
        $this->assertFalse($marcas->contains($alheia->id));
        $this->assertFalse($marcas->contains($desligada->id));

        $fornecedores = collect($opcoes['fornecedores'])->pluck('id');
        $this->assertTrue($fornecedores->contains($meu->id));
        $this->assertFalse($fornecedores->contains($alheio->id));
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

    /* ─── O código do artigo ──────────────────────────────────────────── */

    /**
     * O CÓDIGO É GERADO — mas quem quiser escreve o seu.
     *
     * É assim desde sempre: o ecrã em Blade mostrava o código com um «gerado
     * automaticamente — editável» ao lado, e quem já tem códigos no armazém
     * punha lá o seu. A API em React nem sequer o aceitava: o campo entrava
     * pela porta do modelo e não havia caminho nenhum para o mudar.
     */
    /** @test */
    public function o_codigo_gera_se_sozinho_e_aceita_se_escrito(): void
    {
        $this->comPermissoes('invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit');

        // SEM CÓDIGO, o servidor gera-o.
        $gerado = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data');

        $this->assertNotEmpty($gerado['code']);
        $this->assertStringStartsWith('PROD', $gerado['code']);

        // COM CÓDIGO, fica o que se escreveu.
        $meu = $this->postJson(self::RAIZ, $this->corpo([
            'name' => 'Artigo com código próprio',
            'code' => 'ARM-0042',
        ]))->assertCreated()->json('data');

        $this->assertSame('ARM-0042', $meu['code']);

        // E muda-se a editar.
        $this->putJson(self::RAIZ . '/' . $meu['id'], $this->corpo([
            'name' => 'Artigo com código próprio',
            'code' => 'ARM-0043',
        ]))->assertOk()->assertJsonPath('data.code', 'ARM-0043');
    }

    /**
     * O MESMO CÓDIGO NÃO SE REPETE NA EMPRESA.
     *
     * É o que a coluna promete e o que o gerador presume ao procurar o maior
     * número usado: dois artigos com `PROD000007` deixavam o gerador a repetir
     * códigos para sempre, e o artigo deixava de se encontrar por ele.
     */
    /** @test */
    public function o_codigo_nao_se_repete_na_mesma_empresa(): void
    {
        $this->comPermissoes('invoicing.products.create');

        $this->postJson(self::RAIZ, $this->corpo(['code' => 'REPETIDO']))->assertCreated();

        $this->postJson(self::RAIZ, $this->corpo(['name' => 'Outro artigo', 'code' => 'REPETIDO']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /**
     * UM CÓDIGO EM BRANCO A EDITAR NÃO APAGA O QUE LÁ ESTAVA.
     *
     * O campo limpo por engano deixaria um artigo sem código — e esse artigo
     * anda em facturas já emitidas, onde o código é o que o liga à linha.
     */
    /** @test */
    public function o_codigo_em_branco_nao_apaga_o_que_estava(): void
    {
        $this->comPermissoes('invoicing.products.create', 'invoicing.products.edit');

        $artigo = $this->postJson(self::RAIZ, $this->corpo(['code' => 'FICA-ASSIM']))
            ->assertCreated()->json('data');

        $this->putJson(self::RAIZ . '/' . $artigo['id'], $this->corpo(['code' => '']))
            ->assertOk()
            ->assertJsonPath('data.code', 'FICA-ASSIM');
    }
}
