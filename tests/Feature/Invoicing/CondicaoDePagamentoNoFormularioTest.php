<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoicing\PaymentTerm;
use Tests\TenantTestCase;

/**
 * O SELECTOR DA CONDIÇÃO DE PAGAMENTO NO FORMULÁRIO DO CLIENTE.
 *
 * Produção, 15/09/2026: numa empresa que nunca tinha aberto o catálogo das
 * condições, o «Novo Cliente» mostrava «— Sem condição —» e mais nada. Os
 * padrões nasciam no `mount()` do Livewire, que já não existe.
 */
class CondicaoDePagamentoNoFormularioTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');
    }

    public function test_uma_empresa_sem_catalogo_recebe_os_padroes_ao_abrir_o_formulario(): void
    {
        $this->assertFalse(PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->exists());

        $r = $this->getJson('/api/v1/invoicing/react/clients/opcoes')->assertOk();

        $condicoes = collect($r->json('condicoes_pagamento'));
        $this->assertCount(4, $condicoes);
        $this->assertSame('Pronto Pagamento', $condicoes->firstWhere('padrao', true)['nome'], 'o formulário escolhe a padrão num cliente novo');

        // Abrir outra vez não duplica.
        $this->getJson('/api/v1/invoicing/react/clients/opcoes')->assertOk();
        $this->assertSame(4, PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_uma_empresa_com_catalogo_proprio_nao_ganha_os_padroes(): void
    {
        PaymentTerm::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Pagamento a 60 dias', 'days' => 60, 'is_default' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $r = $this->getJson('/api/v1/invoicing/react/clients/opcoes')->assertOk();

        $this->assertSame(['Pagamento a 60 dias'], array_column($r->json('condicoes_pagamento'), 'nome'), 'quem apagou os padrões não os vê voltar');
    }
}
