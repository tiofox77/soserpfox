<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * O CLIENTE QUE FICAVA DE FORA DOS 500.
 *
 * As opções dos emissores (factura, proforma, notas, recibos…) trazem os 500
 * primeiros clientes por ordem alfabética, e a caixa de procura filtrava só
 * essa lista no browser. Na JG Inox, com 598 clientes, a «T.P.A.- TELEVISAO
 * PUBLICA DE ANGOLA» era a 545.ª: não aparecia na factura nem na proforma
 * (29/09/2026). A procura passou a ir ao servidor, por `/partes/{tipo}`.
 */
class ClienteForaDosQuinhentosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private Client $tpa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        // 510 clientes que vêm antes na ordem alfabética: empurram a T.P.A.
        // para lá do 500.º, como na JG Inox. Em bruto, por ser muitos.
        $agora = now();
        DB::table('invoicing_clients')->insert(array_map(fn ($i) => [
            'tenant_id' => $this->tenant->id,
            'name' => sprintf('Cliente A%03d', $i),
            'nif' => (string) (300000000 + $i),
            'type' => 'pessoa_juridica',
            'is_active' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], range(1, 510)));

        $this->tpa = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'T.P.A.- TELEVISAO PUBLICA DE ANGOLA',
            'nif' => '5410003055',
            'type' => 'pessoa_juridica',
            'phone' => '222 330 000',
            'province' => 'Cabinda',
            'payment_term_days' => 15,
            'is_active' => true,
        ]);
    }

    /** O retrato do defeito: a lista das opções pára antes dela. */
    public function test_a_lista_das_opcoes_nao_a_traz(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $ids = collect($this->getJson(self::RAIZ . '/factura/opcoes')->assertOk()->json('clientes'))->pluck('id');

        $this->assertCount(500, $ids);
        $this->assertNotContains($this->tpa->id, $ids);
    }

    public function test_a_procura_no_servidor_encontra_a_por_nome_sem_acento_e_sem_pontos(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        foreach (['televisão', 'TELEVISAO', 'tpa', 'T.P.A', 'publica de angola'] as $termo) {
            $ids = collect($this->getJson(self::RAIZ . '/partes/clientes?procura=' . urlencode($termo))->assertOk()->json('data'))->pluck('id');
            $this->assertContains($this->tpa->id, $ids, "«{$termo}» tinha de encontrar a T.P.A.");
        }
    }

    public function test_a_procura_no_servidor_encontra_a_pelo_nif_e_pelo_telefone(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        foreach (['5410003055', '330 000'] as $termo) {
            $ids = collect($this->getJson(self::RAIZ . '/partes/clientes?procura=' . urlencode($termo))->assertOk()->json('data'))->pluck('id');
            $this->assertContains($this->tpa->id, $ids, "«{$termo}» tinha de encontrar a T.P.A.");
        }
    }

    /**
     * Vem com a mesma forma das opções da factura: o vencimento e a região
     * fiscal (Cabinda) saem dela quando se escolhe.
     */
    public function test_pelo_id_vem_com_o_prazo_e_a_regiao(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $parte = $this->getJson(self::RAIZ . '/partes/clientes?id=' . $this->tpa->id)->assertOk()->json('data.0');

        $this->assertSame($this->tpa->id, $parte['id']);
        $this->assertSame('5410003055', $parte['nif']);
        $this->assertSame(15, $parte['payment_term_days']);
        $this->assertSame('AO-CAB', $parte['regiao']);
    }

    public function test_menos_de_duas_letras_nao_procura(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->getJson(self::RAIZ . '/partes/clientes?procura=t')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_nao_mostra_os_clientes_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $vizinha = Tenant::create([
            'name' => 'Vizinha', 'slug' => 'vizinha-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => 'v' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);
        $alheio = Client::create([
            'tenant_id' => $vizinha->id, 'name' => 'TELEVISAO DA VIZINHA', 'nif' => '5410009999',
            'type' => 'pessoa_juridica', 'is_active' => true,
        ]);

        $ids = collect($this->getJson(self::RAIZ . '/partes/clientes?procura=televisao')->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($alheio->id, $ids);

        $this->getJson(self::RAIZ . '/partes/clientes?id=' . $alheio->id)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_sem_permissao_de_nenhum_ecra_que_escolhe_clientes_e_recusado(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $this->getJson(self::RAIZ . '/partes/clientes?procura=televisao')->assertForbidden();
    }

    public function test_os_fornecedores_procuram_se_so_entre_os_activos_mas_pelo_id_aparecem_todos(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $activo = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Inox Luanda', 'nif' => '5000000001', 'is_active' => true]);
        $parado = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Inox Parado', 'nif' => '5000000002', 'is_active' => false]);

        $ids = collect($this->getJson(self::RAIZ . '/partes/fornecedores?procura=inox')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($activo->id, $ids);
        $this->assertNotContains($parado->id, $ids);

        // Um documento antigo com um fornecedor desactivado mostra-o na mesma.
        $this->getJson(self::RAIZ . '/partes/fornecedores?id=' . $parado->id)->assertOk()->assertJsonPath('data.0.id', $parado->id);
    }

    /** O `%` escrito na caixa é um carácter, não um curinga que traz tudo. */
    public function test_o_percento_nao_traz_a_empresa_inteira(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->getJson(self::RAIZ . '/partes/clientes?procura=' . urlencode('%%'))->assertOk()->assertExactJson(['data' => []]);
    }
}
