<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Models\Product;
use App\Services\POS\ImportacaoDeCopiaOffline;
use Tests\TenantTestCase;

/**
 * Recuperar uma cópia de segurança do PWA.
 *
 * A fila de sincronização vive em IndexedDB, no dispositivo. Se o telemóvel se
 * perde, se o navegador limpa os dados do site, ou se alguém carrega em
 * "Reiniciar tudo" antes de sincronizar, as vendas desaparecem — e essas
 * vendas já aconteceram, com dinheiro trocado e talão entregue.
 *
 * O que estes testes protegem, por ordem de gravidade: que a cadeia AGT não
 * parta, que uma cópia de outra empresa não entre, e que importar duas vezes
 * não duplique nada.
 */
class CopiaOfflineTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.sell');

        $this->artigo = Product::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Refrigerante 33cl',
            'code'       => 'REF-' . uniqid(),
            'price'      => 500,
            'cost_price' => 200,
            'type'       => 'produto',
            'is_active'  => true,
            'tax_id'     => $this->imposto->id,
        ]);
    }

    private function linha(): array
    {
        return [
            'product_id'   => $this->artigo->id,
            'product_name' => $this->artigo->name,
            'quantity'     => 2,
            'unit_price'   => 500,
        ];
    }

    private function copia(array $fila, array $extra = []): array
    {
        return array_merge([
            'formato'    => ImportacaoDeCopiaOffline::FORMATO,
            'versao'     => 1,
            'gerado_em'  => now()->toIso8601String(),
            'tenant_id'  => $this->tenant->id,
            'utilizador' => ['id' => $this->user->id, 'nome' => $this->user->name],
            'dados'      => ['sync_queue' => $fila],
        ], $extra);
    }

    private function importar(array $copia): array
    {
        return app(ImportacaoDeCopiaOffline::class)
            ->importar($copia, $this->tenant->id, $this->user->id);
    }

    // ==================== o essencial ====================

    /** Uma venda POS da cópia vira Fatura-Recibo real. */
    public function test_uma_venda_da_copia_entra_como_fatura_recibo(): void
    {
        $copia = $this->copia([[
            'op' => 'create_pos_sale',
            'payload' => [
                'local_uuid'     => 'copia-venda-1',
                'payment_method' => 'cash',
                'items'          => [$this->linha()],
            ],
        ]]);

        $r = $this->importar($copia);

        $this->assertSame(1, $r['importadas']);

        $factura = SalesInvoice::where('tenant_id', $this->tenant->id)
            ->where('local_uuid', 'copia-venda-1')
            ->first();

        $this->assertNotNull($factura);
        $this->assertNotEmpty($factura->invoice_number);
    }

    /**
     * A cadeia AGT não pode partir.
     *
     * É a razão de a importação chamar o MESMO serviço da sincronização normal
     * em vez de criar as facturas por sua conta. Uma Fatura-Recibo importada
     * tem de sair com tudo o que uma emitida no balcão tem: número da série,
     * hash SAFT e encadeamento ao documento anterior. Sem isso o SAFT-AO fica
     * inválido — e um documento fiscal emitido não se apaga para corrigir.
     */
    public function test_a_venda_importada_leva_numero_e_hash_agt(): void
    {
        $r = $this->importar($this->copia([[
            'op' => 'create_pos_sale',
            'payload' => [
                'local_uuid'     => 'copia-agt-1',
                'payment_method' => 'cash',
                'items'          => [$this->linha()],
            ],
        ]]));

        $this->assertSame(1, $r['importadas']);

        $factura = SalesInvoice::where('local_uuid', 'copia-agt-1')->first();

        $this->assertNotEmpty($factura->invoice_number, 'Sem número de série.');
        $this->assertNotEmpty($factura->saft_hash, 'Sem hash SAFT — a cadeia parte aqui.');
        $this->assertSame('1', (string) $factura->hash_control);
    }

    /**
     * Faturas, proformas e notas de crédito entram como RASCUNHO.
     *
     * E é isso que faz a importação ser segura do lado da AGT: um rascunho não
     * tem número nem hash, portanto não toca na cadeia. O número AGT é
     * atribuído quando alguém o finaliza na Faturação — exactamente como
     * acontece quando o dispositivo sincroniza pelo caminho normal.
     */
    public function test_uma_fatura_da_copia_entra_como_rascunho_sem_tocar_na_agt(): void
    {
        $r = $this->importar($this->copia([[
            'op' => 'create_draft',
            'payload' => [
                'doc_type'   => 'FT',
                'local_uuid' => 'copia-ft-1',
                'items'      => [$this->linha()],
            ],
        ]]));

        $this->assertSame(1, $r['rascunhos']);

        $ft = SalesInvoice::where('local_uuid', 'copia-ft-1')->first();

        $this->assertNotNull($ft);
        // Os documentos do PWA passaram a ser DEFINITIVOS: uma venda feita
        // offline e uma venda real, e ao chegar ao servidor tem de ficar
        // numerada e selada, nao em rascunho. O que continua a nao poder
        // acontecer e a MESMA venda entrar duas vezes — e isso que se guarda
        // aqui, pelo local_uuid.
        $this->assertSame('pending', $ft->status);
        $this->assertNotEmpty($ft->invoice_number, 'uma venda real tem de ficar numerada');
        $this->assertSame('F', $ft->invoice_status);

        // Reimportar a mesma copia nao pode criar um segundo documento.
        $this->importar($this->copia([[
            'op' => 'create_draft',
            'payload' => [
                'doc_type'   => 'FT',
                'local_uuid' => 'copia-ft-1',
                'items'      => [$this->linha()],
            ],
        ]]));

        $this->assertSame(1, SalesInvoice::where('local_uuid', 'copia-ft-1')->count(),
            'a mesma venda offline entrou duas vezes');
    }

    /** E a proforma também. */
    public function test_uma_proforma_da_copia_entra(): void
    {
        $antes = SalesProforma::where('tenant_id', $this->tenant->id)->count();

        $r = $this->importar($this->copia([[
            'op' => 'create_draft',
            'payload' => [
                'doc_type'   => 'proforma',
                'local_uuid' => 'copia-prf-1',
                'items'      => [$this->linha()],
            ],
        ]]));

        $this->assertSame(1, $r['rascunhos']);
        $this->assertSame($antes + 1, SalesProforma::where('tenant_id', $this->tenant->id)->count());
    }

    /** Os clientes entram, e entram ANTES das vendas que os usam. */
    public function test_os_clientes_da_copia_entram(): void
    {
        $r = $this->importar($this->copia([[
            'op' => 'create_client',
            'payload' => [
                'local_uuid' => 'copia-cli-1',
                'name'       => 'Padaria Central',
                'nif'        => '5000123456',
                'type'       => 'pessoa_juridica',
            ],
        ]]));

        $this->assertSame(1, $r['clientes']);

        $this->assertSame(
            1,
            Client::where('tenant_id', $this->tenant->id)->where('nif', '5000123456')->count()
        );
    }

    // ==================== repetir é seguro ====================

    /**
     * Importar duas vezes o mesmo ficheiro não duplica nada.
     *
     * É o caso NORMAL, não a excepção: quem recupera dados repete a operação
     * por não ter a certeza se resultou à primeira. E parte das vendas do
     * ficheiro pode entretanto ter sincronizado sozinha.
     */
    public function test_importar_duas_vezes_nao_duplica(): void
    {
        $copia = $this->copia([
            ['op' => 'create_client', 'payload' => [
                'local_uuid' => 'rep-cli', 'name' => 'Loja Azul', 'nif' => '5000999888',
            ]],
            ['op' => 'create_pos_sale', 'payload' => [
                'local_uuid' => 'rep-venda', 'payment_method' => 'cash', 'items' => [$this->linha()],
            ]],
            ['op' => 'create_draft', 'payload' => [
                'doc_type' => 'FT', 'local_uuid' => 'rep-ft', 'items' => [$this->linha()],
            ]],
        ]);

        $primeira = $this->importar($copia);
        $segunda  = $this->importar($copia);

        $this->assertSame(1, $primeira['importadas']);
        $this->assertSame(0, $segunda['importadas']);
        $this->assertSame(0, $segunda['clientes']);
        $this->assertSame(0, $segunda['rascunhos']);

        $this->assertSame(1, SalesInvoice::where('local_uuid', 'rep-venda')->count());
        $this->assertSame(1, SalesInvoice::where('local_uuid', 'rep-ft')->count());
        $this->assertSame(1, Client::where('nif', '5000999888')->count());
    }

    /** Uma venda que já tinha sincronizado conta como "já existia". */
    public function test_uma_venda_ja_sincronizada_nao_entra_outra_vez(): void
    {
        $carga = [
            'local_uuid'     => 'ja-sincronizada',
            'payment_method' => 'cash',
            'items'          => [$this->linha()],
        ];

        app(\App\Services\POS\PosSaleService::class)
            ->createFromPayload($carga, $this->tenant->id, $this->user->id);

        $r = $this->importar($this->copia([['op' => 'create_pos_sale', 'payload' => $carga]]));

        $this->assertSame(0, $r['importadas']);
        $this->assertSame(1, $r['ja_existiam']);
        $this->assertSame(1, SalesInvoice::where('local_uuid', 'ja-sincronizada')->count());
    }

    // ==================== o que se recusa ====================

    /**
     * Uma cópia de OUTRA empresa não entra.
     *
     * Sem esta verificação, as vendas de uma empresa iam parar à contabilidade
     * de outra — e uma factura emitida não se apaga para corrigir o engano.
     */
    public function test_uma_copia_de_outra_empresa_e_recusada(): void
    {
        $copia = $this->copia([['op' => 'create_pos_sale', 'payload' => [
            'local_uuid' => 'alheia-1', 'items' => [$this->linha()],
        ]]], ['tenant_id' => $this->tenant->id + 9999]);

        $problema = app(ImportacaoDeCopiaOffline::class)->validar($copia, $this->tenant->id);

        $this->assertNotNull($problema);
        $this->assertStringContainsString('outra empresa', $problema);
    }

    /**
     * Uma cópia SEM empresa também não.
     *
     * Não se pode verificar, logo não se importa. Deixá-la passar "porque é
     * antiga" abria a porta que a condição acima existe para fechar.
     */
    public function test_uma_copia_sem_empresa_e_recusada(): void
    {
        $copia = $this->copia([['op' => 'create_pos_sale', 'payload' => [
            'local_uuid' => 'sem-empresa', 'items' => [$this->linha()],
        ]]], ['tenant_id' => null]);

        $this->assertNotNull(
            app(ImportacaoDeCopiaOffline::class)->validar($copia, $this->tenant->id)
        );
    }

    /** Um ficheiro que não é uma cópia do sistema é recusado. */
    public function test_um_ficheiro_estranho_e_recusado(): void
    {
        $problema = app(ImportacaoDeCopiaOffline::class)
            ->validar(['qualquer' => 'coisa'], $this->tenant->id);

        $this->assertNotNull($problema);
        $this->assertStringContainsString('não é uma cópia', $problema);
    }

    /** Uma cópia de uma versão futura é recusada em vez de mal interpretada. */
    public function test_uma_copia_de_versao_futura_e_recusada(): void
    {
        $copia = $this->copia([['op' => 'create_pos_sale', 'payload' => ['local_uuid' => 'x']]], ['versao' => 99]);

        $this->assertNotNull(
            app(ImportacaoDeCopiaOffline::class)->validar($copia, $this->tenant->id)
        );
    }

    // ==================== resistência ====================

    /**
     * Uma venda estragada não leva as outras à frente.
     *
     * Numa recuperação, cada venda que entra é dinheiro que volta à
     * contabilidade. Parar na primeira que falha desperdiça as boas que vinham
     * a seguir.
     */
    public function test_uma_venda_estragada_nao_impede_as_outras(): void
    {
        $r = $this->importar($this->copia([
            ['op' => 'create_pos_sale', 'payload' => ['local_uuid' => 'sem-linhas']],
            ['op' => 'create_pos_sale', 'payload' => [
                'local_uuid' => 'boa-1', 'payment_method' => 'cash', 'items' => [$this->linha()],
            ]],
        ]));

        $this->assertSame(1, $r['importadas']);
        $this->assertNotNull(SalesInvoice::where('local_uuid', 'boa-1')->first());
    }

    /** O inventário mostra o que lá está sem importar nada. */
    public function test_o_inventario_nao_grava_nada(): void
    {
        $antes = SalesInvoice::where('tenant_id', $this->tenant->id)->count();

        $inventario = app(ImportacaoDeCopiaOffline::class)->inventario($this->copia([
            ['op' => 'create_pos_sale', 'payload' => ['local_uuid' => 'a', 'items' => [$this->linha()]]],
            ['op' => 'create_draft', 'payload' => ['doc_type' => 'FT', 'local_uuid' => 'b']],
            ['op' => 'open_pos_shift', 'payload' => []],
        ]));

        $this->assertSame(1, $inventario['vendas']);
        $this->assertSame(1, $inventario['rascunhos']);
        $this->assertSame(1, $inventario['turnos']);

        $this->assertSame($antes, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    // ==================== o ecrã ====================

    public function test_o_ecra_de_importacao_abre(): void
    {
        $this->get('/invoicing/importar-copia-offline')
            ->assertOk()
            ->assertSee('Importar Cópia Offline');
    }

    /** O PWA tem o botão que gera o ficheiro. */
    public function test_o_pwa_exporta_a_copia(): void
    {
        $js = file_get_contents(public_path('js/pwa-invoicing.js'));

        $this->assertStringContainsString('async exportarCopia()', $js);
        $this->assertStringContainsString("formato: 'soserp.pwa.copia'", $js);

        // O carimbo da empresa é o que permite recusar uma cópia alheia.
        $this->assertStringContainsString('window.SOS_TENANT_ID', $js);

        // Só o que está por enviar: o catálogo vem do servidor e não se perde.
        $this->assertStringContainsString("where('_synced').equals(0)", $js);
    }
}
