<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Artigos de um módulo de negócio (salão) não se vendem no POS de faturação.
 *
 * Um "Corte de Cabelo" existe em invoicing_products só para a linha da factura
 * ter artigo de catálogo. Quem o marca e cobra é o POS do salão, que sabe do
 * profissional, da duração e da marcação. Ao balcão da faturação era um artigo
 * solto, sem nada disso.
 *
 * O BALCÃO É HOJE REACT, e a regra tinha-se perdido: nem a grelha o escondia nem
 * a venda o recusava. O ensaio que a guardava apontava para um componente
 * Livewire que já nenhuma rota serve, e por isso nunca deu por nada.
 */
class PosNaoVendeArtigosDeModuloTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/pos';

    private function servicoDeSalao(): Product
    {
        $p = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Corte de Cabelo',
            'code' => 'SVC'.uniqid(),
            'type' => 'servico',
            'price' => 3000,
            'manage_stock' => false,
            'is_active' => true,
            'tax_type' => 'isento',
        ]);

        // `module` NÃO é fillable (só os modelos do módulo lhe tocam).
        $p->module = 'salon';
        $p->save();

        return $p;
    }

    private function artigoNormal(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pão',
            'code' => 'P'.uniqid(),
            'type' => 'produto',
            'price' => 200,
            'manage_stock' => false,
            'is_active' => true,
            'tax_type' => 'isento',
        ]);
    }

    private function turnoAberto(): void
    {
        \App\Models\Invoicing\PosShift::createSafely([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => 0,
        ], $this->tenant->id);
    }

    public function test_o_pos_de_faturacao_nao_lista_artigos_do_salao(): void
    {
        $salao = $this->servicoDeSalao();
        $normal = $this->artigoNormal();

        $this->comPermissoes('invoicing.pos.access')->comModulo('invoicing');
        $this->turnoAberto();

        $ids = collect(
            $this->actingAs($this->user)->getJson(self::RAIZ.'/artigos')->assertOk()->json('data')
        )->pluck('id');

        $this->assertFalse($ids->contains($salao->id), 'o serviço do salão não pode aparecer no POS de faturação');
        $this->assertTrue($ids->contains($normal->id), 'um artigo normal tem de continuar a aparecer');
    }

    /** E nem pela procura: o filtro é da consulta, não da grelha. */
    public function test_nem_a_procura_pelo_nome_traz_o_artigo_do_salao(): void
    {
        $salao = $this->servicoDeSalao();

        $this->comPermissoes('invoicing.pos.access')->comModulo('invoicing');
        $this->turnoAberto();

        $ids = collect(
            $this->actingAs($this->user)
                ->getJson(self::RAIZ.'/artigos?procura=Corte')->assertOk()->json('data')
        )->pluck('id');

        $this->assertFalse($ids->contains($salao->id));
    }

    /**
     * A GRELHA FILTRA, NÃO PROTEGE.
     *
     * Os ids das linhas vêm do browser: a venda tem de recusar por id, e não
     * contar com o ecrã para não mostrar.
     */
    public function test_a_venda_recusa_por_id_mesmo_que_a_grelha_seja_contornada(): void
    {
        $salao = $this->servicoDeSalao();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.sales.invoices.create')
            ->comModulo('invoicing');
        $this->turnoAberto();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/vender', [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $salao->id,
                'product_name' => $salao->name,
                'quantity' => 1,
                'unit_price' => 3000,
                'is_service' => true,
            ]],
        ])->assertStatus(422);

        // Nada foi facturado.
        $this->assertSame(0, \App\Models\Invoicing\SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    /** Um artigo normal continua a vender-se. */
    public function test_um_artigo_normal_vende_se(): void
    {
        $normal = $this->artigoNormal();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.sales.invoices.create')
            ->comModulo('invoicing');
        $this->turnoAberto();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/vender', [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'cash',
            'amount_received' => 200,
            'items' => [[
                'product_id' => $normal->id,
                'product_name' => $normal->name,
                'quantity' => 1,
                'unit_price' => 200,
            ]],
        ])->assertCreated();
    }

    public function test_o_sync_offline_tambem_nao_leva_artigos_do_salao(): void
    {
        $salao = $this->servicoDeSalao();
        $normal = $this->artigoNormal();

        $json = $this->actingAs($this->user)->getJson('/api/v1/invoicing/sync')->assertOk()->json();
        $ids = collect($json['data']['products'])->pluck('id');

        $this->assertFalse($ids->contains($salao->id));
        $this->assertTrue($ids->contains($normal->id));
    }
}
