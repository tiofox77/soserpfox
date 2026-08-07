<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Sales\InvoiceCreate;
use App\Models\Invoicing\InvoicingSeries;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A série da factura fica ESCOLHIDA no ecrã, não implícita.
 *
 * O selector abria em "Série AGT padrão" — um valor vazio. O documento saía na
 * série por omissão de qualquer maneira, mas o ecrã não dizia qual, e quem
 * emitia não tinha como saber em que série ia ficar sem ir procurar às
 * definições. Numa empresa com três séries de factura — e há — isso é uma
 * escolha fiscal feita às escuras.
 */
class FacturaSerieEscolhidaTest extends TenantTestCase
{
    private function serie(string $codigo, bool $porOmissao, string $tipo = 'invoice'): InvoicingSeries
    {
        return InvoicingSeries::updateOrCreate(
            [
                'tenant_id'     => $this->tenant->id,
                'document_type' => $tipo,
                'series_code'   => $codigo,
            ],
            [
                'name'           => 'Série ' . $codigo,
                'prefix'         => $tipo === 'pos' ? 'FR' : 'FT',
                'next_number'    => 1,
                'number_padding' => 6,
                'is_active'      => true,
                'is_default'     => $porOmissao,
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->comModulo('invoicing');

        // A empresa de teste já traz séries. Sem as neutralizar, a "por
        // omissão" do teste não é a única e a ordenação decide por código.
        InvoicingSeries::where('tenant_id', $this->tenant->id)->update(['is_default' => false]);
    }

    public function test_abre_com_a_serie_por_omissao_escolhida(): void
    {
        $this->serie('SOSFTB', false);
        $porOmissao = $this->serie('SOSFT', true);

        Livewire::actingAs($this->user)
            ->test(InvoiceCreate::class)
            ->assertSet('series_id', (string) $porOmissao->id);
    }

    public function test_nao_fica_vazia_quando_nenhuma_e_por_omissao(): void
    {
        // Sem série marcada por omissão, escolhe-se a primeira — o campo nunca
        // pode ficar em branco a decidir sozinho.
        $this->serie('SOSFTC', false);

        $componente = Livewire::actingAs($this->user)->test(InvoiceCreate::class);

        $this->assertNotSame('', $componente->get('series_id'));
    }

    public function test_a_escolha_do_utilizador_e_respeitada(): void
    {
        $this->serie('SOSFT', true);
        $outra = $this->serie('SOSFTB', false);

        Livewire::actingAs($this->user)
            ->test(InvoiceCreate::class)
            ->set('series_id', (string) $outra->id)
            ->assertSet('series_id', (string) $outra->id);
    }

    public function test_mudar_para_factura_recibo_troca_a_serie(): void
    {
        // FT e FR usam conjuntos de séries diferentes: a que estava escolhida
        // deixa de servir e tem de ser substituída, não esvaziada.
        $this->serie('SOSFT', true);
        $fr = $this->serie('SOSFR', true, 'pos');

        Livewire::actingAs($this->user)
            ->test(InvoiceCreate::class)
            ->set('invoice_type', 'FR')
            ->assertSet('series_id', (string) $fr->id);
    }
}
