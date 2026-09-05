<?php

namespace Tests\Feature\Pwa;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * O cliente criado sem rede, dentro do documento criado sem rede.
 *
 * A queixa: «criando um cliente offline e colocando na fatura offline não
 * sincroniza cliente e a fatura sai como consumidor final». Provado no
 * browser: a API recusava o cliente sem NIF com 422 «O campo nif é
 * obrigatório», o aparelho marcava-o como recusado de vez, e a venda que o
 * referenciava ficava «a reagendar» até morrer na fila.
 *
 * O que fica garantido: o cliente sem NIF entra; um documento que aponte
 * para um cliente pelo identificador local sai em NOME DELE; e se esse
 * cliente ainda não subiu, o servidor diz «espera» (409) em vez de emitir em
 * nome do Consumidor Final.
 */
class ClienteOfflineNoDocumentoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.clients.create');
        $this->actingAs($this->user);
    }

    /** Um artigo do catálogo: as linhas da proforma exigem produto a sério. */
    private function artigo(): array
    {
        $p = new \App\Models\Product();
        $p->forceFill([
            'tenant_id' => $this->tenant->id, 'code' => 'BALC-' . Str::random(5), 'name' => 'Serviço de balcão',
            'type' => 'servico', 'price' => 1000, 'is_active' => true, 'manage_stock' => false,
        ])->save();

        return ['product_id' => $p->id, 'product_name' => $p->name, 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 14];
    }

    // ── O cliente ──────────────────────────────────────────────────────

    public function test_o_cliente_sem_nif_entra(): void
    {
        $uuid = 'c_' . Str::random(8);

        $r = $this->postJson('/api/v1/invoicing/clients', [
            'local_uuid' => $uuid, 'name' => 'Freguês sem contribuinte', 'type' => 'pessoa_fisica', 'nif' => null,
        ])->assertStatus(201);

        $c = Client::find($r->json('id'));
        $this->assertSame('Freguês sem contribuinte', $c->name);
        $this->assertNull($c->nif, 'sem contribuinte é NULL, não um marcador');
        $this->assertSame($uuid, $c->local_uuid);
    }

    public function test_o_cliente_do_pwa_grava_o_pais_em_codigo(): void
    {
        $r = $this->postJson('/api/v1/invoicing/clients', [
            'local_uuid' => 'c_' . Str::random(8), 'name' => 'Da rua', 'nif' => '5417654321', 'country' => 'Angola',
        ])->assertStatus(201);

        $this->assertSame('AO', Client::find($r->json('id'))->country, 'o PWA antigo manda «Angola» por extenso');
    }

    // ── O documento aponta para o cliente pelo identificador local ────

    private function clienteLocal(string $uuid): Client
    {
        $c = new Client();
        $c->tenant_id = $this->tenant->id;
        $c->type = 'pessoa_fisica';
        $c->name = 'Cliente do Balcão ' . $uuid;
        $c->nif = null;
        $c->local_uuid = $uuid;
        $c->country = 'AO';
        $c->is_active = true;
        $c->save();

        return $c;
    }

    public function test_a_factura_com_client_local_uuid_sai_em_nome_do_cliente(): void
    {
        $uuid = 'c_' . Str::random(8);
        $cliente = $this->clienteLocal($uuid);

        $r = $this->postJson('/api/v1/invoicing/drafts', [
            'local_uuid' => 'd_' . Str::random(8), 'doc_type' => 'FT',
            'client_id' => null, 'client_local_uuid' => $uuid,
            'invoice_date' => now()->toDateString(), 'items' => [$this->artigo()],
        ])->assertStatus(201);

        $this->assertSame($cliente->id, SalesInvoice::find($r->json('id'))->client_id,
            'a factura tem de ser do cliente, não do Consumidor Final');
    }

    public function test_a_proforma_com_client_local_uuid_sai_em_nome_do_cliente(): void
    {
        $uuid = 'c_' . Str::random(8);
        $cliente = $this->clienteLocal($uuid);

        $r = $this->postJson('/api/v1/invoicing/drafts', [
            'local_uuid' => 'd_' . Str::random(8), 'doc_type' => 'proforma',
            'client_id' => null, 'client_local_uuid' => $uuid,
            'invoice_date' => now()->toDateString(), 'items' => [$this->artigo()],
        ])->assertStatus(201);

        $this->assertSame($cliente->id, SalesProforma::find($r->json('id'))->client_id);
    }

    public function test_a_venda_pos_com_client_local_uuid_sai_em_nome_do_cliente(): void
    {
        $uuid = 'c_' . Str::random(8);
        $cliente = $this->clienteLocal($uuid);

        $r = $this->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid' => 'v_' . Str::random(8), 'client_id' => null, 'client_local_uuid' => $uuid,
            'payment_method' => 'cash', 'amount_received' => 1140, 'items' => [$this->artigo()],
        ])->assertStatus(201);

        $this->assertSame($cliente->id, SalesInvoice::find($r->json('id'))->client_id);
    }

    /**
     * O CLIENTE AINDA NÃO SUBIU: espera-se, não se emite em nome de outro.
     *
     * Um 4xx qualquer seria «recusado de vez» para o aparelho; o 409 é o
     * único que ele lê como «tenta na próxima».
     */
    public function test_cliente_local_desconhecido_faz_o_documento_esperar(): void
    {
        $antes = SalesInvoice::count();

        $this->postJson('/api/v1/invoicing/drafts', [
            'local_uuid' => 'd_' . Str::random(8), 'doc_type' => 'FT',
            'client_id' => null, 'client_local_uuid' => 'c_nunca_subiu',
            'invoice_date' => now()->toDateString(), 'items' => [$this->artigo()],
        ])->assertStatus(409)->assertJson(['aguardar' => true]);

        $this->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid' => 'v_' . Str::random(8), 'client_id' => null, 'client_local_uuid' => 'c_nunca_subiu',
            'payment_method' => 'cash', 'amount_received' => 1140, 'items' => [$this->artigo()],
        ])->assertStatus(409)->assertJson(['aguardar' => true]);

        $this->assertSame($antes, SalesInvoice::count(), 'nada pode ter sido emitido em nome do Consumidor Final');
    }

    /** Sem cliente nenhum, continua a ser o Consumidor Final — como sempre. */
    public function test_sem_cliente_e_o_consumidor_final(): void
    {
        $r = $this->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid' => 'v_' . Str::random(8), 'client_id' => null,
            'payment_method' => 'cash', 'amount_received' => 1140, 'items' => [$this->artigo()],
        ])->assertStatus(201);

        $this->assertSame('999999999', SalesInvoice::find($r->json('id'))->client->nif);
    }

    // ── O aparelho ────────────────────────────────────────────────────

    public function test_o_service_worker_guarda_os_formularios(): void
    {
        preg_match('/const PRECACHE_PAGINAS = \[(.*?)\];/s', file_get_contents(resource_path('pwa/sw.js')), $m);

        $this->assertStringContainsString('/invoicing/offline/clients/new', $m[1] ?? '');
        $this->assertStringContainsString('/invoicing/offline/drafts/new', $m[1] ?? '');
    }

    public function test_o_motor_trata_o_409_como_espera_e_nao_como_recusa(): void
    {
        $motor = file_get_contents(public_path('js/pwa-invoicing.js'));

        $this->assertStringContainsString('response.status !== 409', $motor);
        $this->assertStringContainsString('async function resolverClienteLocal', $motor);
        $this->assertStringContainsString("...(existente || {}), ...c, _synced: 1", $motor,
            'a descarga tem de juntar e não substituir — senão apaga o local_uuid');
    }
}
