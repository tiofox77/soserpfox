<?php

namespace Tests\Feature\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * A condição de pagamento com que um cliente novo nasce.
 *
 * O catálogo de condições existia, o formulário de clientes já pré-seleccionava
 * a padrão, e mesmo assim **todos** os clientes desta base estavam sem condição
 * nenhuma: o formulário era o único caminho que a aplicava, e os clientes
 * entram também pelo PWA, pela API e pelas importações.
 *
 * Por isso a regra passou para o MODELO. Um sítio que se pode esquecer acaba
 * esquecido; num modelo não há como criar um cliente por fora.
 */
class CondicaoDePagamentoPorOmissaoTest extends TenantTestCase
{
    private function condicao(string $nome, int $dias, bool $padrao = false, int $ordem = 1): PaymentTerm
    {
        return PaymentTerm::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'name'       => $nome,
            'days'       => $dias,
            'is_default' => $padrao,
            'is_active'  => true,
            'sort_order' => $ordem,
        ]);
    }

    private function nif(): string
    {
        return (string) random_int(100000000, 199999999);
    }

    // ── O cliente nasce com ela ───────────────────────────────────────

    public function test_um_cliente_novo_nasce_com_a_condicao_padrao(): void
    {
        $trinta = $this->condicao('Pagamento a 30 dias', 30, padrao: true, ordem: 2);
        $this->condicao('Pronto Pagamento', 0, ordem: 1);

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente Novo',
            'nif'       => $this->nif(),
            'type'      => 'pessoa_juridica',
            'is_active' => true,
        ]);

        $this->assertSame($trinta->id, $cliente->payment_term_id);
        // O valor legado fica em sincronia: é dele que sai o vencimento.
        $this->assertSame(30, (int) $cliente->payment_term_days);
    }

    /** Quem escolhe uma condição fica com a que escolheu. */
    public function test_uma_condicao_escolhida_nao_e_substituida(): void
    {
        $this->condicao('Pagamento a 30 dias', 30, padrao: true, ordem: 2);
        $pronto = $this->condicao('Pronto Pagamento', 0, ordem: 1);

        $cliente = Client::create([
            'tenant_id'       => $this->tenant->id,
            'name'            => 'Escolhido à Mão',
            'nif'             => $this->nif(),
            'type'            => 'pessoa_juridica',
            'payment_term_id' => $pronto->id,
            'is_active'       => true,
        ]);

        $this->assertSame($pronto->id, $cliente->payment_term_id);
    }

    /**
     * O ENSAIO QUE FECHA O BURACO. O cliente criado pelo PWA passava ao lado
     * do formulário e nascia sem condição — e é por aí que entram os clientes
     * feitos ao balcão.
     */
    public function test_o_cliente_criado_pelo_pwa_tambem_nasce_com_ela(): void
    {
        $this->comModulo('invoicing');
        $termo = $this->condicao('Pagamento a 15 dias', 15, padrao: true);

        $resposta = $this->actingAs($this->user)->postJson('/api/v1/invoicing/clients', [
            'name'       => 'Cliente do Balcão',
            'nif'        => $this->nif(),
            'local_uuid' => 'pwa-' . uniqid(),
        ]);

        $resposta->assertStatus(201);

        $cliente = Client::withoutGlobalScopes()->find($resposta->json('id'));

        $this->assertSame($termo->id, $cliente->payment_term_id);
        $this->assertSame(15, (int) $cliente->payment_term_days);
    }

    /** Sem catálogo, o cliente cria-se na mesma — só fica sem condição. */
    public function test_sem_condicoes_no_catalogo_o_cliente_cria_se_na_mesma(): void
    {
        PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Sem Catálogo',
            'nif'       => $this->nif(),
            'type'      => 'pessoa_juridica',
            'is_active' => true,
        ]);

        $this->assertNotNull($cliente->id, 'a criação nunca pode falhar por causa disto');
        $this->assertNull($cliente->payment_term_id);
    }

    /** A condição de outra empresa nunca serve. */
    public function test_a_condicao_de_outra_empresa_nao_e_usada(): void
    {
        PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        PaymentTerm::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheia', 'days' => 90,
            'is_default' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente',
            'nif'       => $this->nif(),
            'type'      => 'pessoa_juridica',
            'is_active' => true,
        ]);

        $this->assertNull($cliente->payment_term_id);
    }

    // ── Qual é a padrão ───────────────────────────────────────────────

    /** A marcada ganha, mesmo estando por baixo na lista. */
    public function test_a_marcada_ganha_a_primeira_da_lista(): void
    {
        PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $this->condicao('Primeira', 0, ordem: 1);
        $marcada = $this->condicao('Marcada', 45, padrao: true, ordem: 9);

        $this->assertSame($marcada->id, PaymentTerm::padraoDe($this->tenant->id)?->id);
    }

    /** Sem nenhuma marcada, vale a primeira activa — melhor um prazo que nenhum. */
    public function test_sem_marcada_vale_a_primeira_activa(): void
    {
        PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $primeira = $this->condicao('Primeira', 0, ordem: 1);
        $this->condicao('Segunda', 30, ordem: 2);

        $this->assertSame($primeira->id, PaymentTerm::padraoDe($this->tenant->id)?->id);
    }

    /** Uma condição desactivada não pode ser a dos clientes novos. */
    public function test_uma_condicao_desactivada_nao_e_escolhida(): void
    {
        PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $desligada = $this->condicao('Desligada', 60, padrao: true, ordem: 1);
        $desligada->update(['is_active' => false]);
        $viva = $this->condicao('Viva', 15, ordem: 2);

        $this->assertSame($viva->id, PaymentTerm::padraoDe($this->tenant->id)?->id);
    }

    // ── O ecrã de Configurações ───────────────────────────────────────

    /**
     * UMA SÓ AUTORIDADE. As Configurações escrevem o `is_default` da condição
     * escolhida, e não uma cópia noutra tabela: com duas definições, este ecrã
     * dizia uma coisa e o das Condições dizia outra.
     */
    public function test_as_configuracoes_marcam_a_condicao_escolhida(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();
        $antiga = $this->condicao('Antiga', 0, padrao: true, ordem: 1);
        $nova = $this->condicao('Nova', 30, ordem: 2);

        \Livewire\Livewire::test(\App\Livewire\Invoicing\Settings::class)
            ->set('default_payment_term_id', $nova->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($nova->fresh()->is_default);
        $this->assertFalse($antiga->fresh()->is_default, 'duas padrão é o mesmo que nenhuma');
        $this->assertSame($nova->id, PaymentTerm::padraoDe($this->tenant->id)?->id);
    }

    /** Não se pode apontar os clientes novos para a condição de outra empresa. */
    public function test_as_configuracoes_recusam_condicao_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheia = PaymentTerm::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Alheia', 'days' => 90,
            'is_default' => false, 'is_active' => true, 'sort_order' => 1,
        ]);

        \Livewire\Livewire::test(\App\Livewire\Invoicing\Settings::class)
            ->set('default_payment_term_id', $alheia->id)
            ->call('save')
            ->assertHasErrors('default_payment_term_id');

        $this->assertFalse($alheia->fresh()->is_default);
    }

    // ── Os clientes antigos ───────────────────────────────────────────

    /** O comando só conta enquanto não lhe pedirem para gravar. */
    public function test_o_comando_nao_grava_sem_aplicar(): void
    {
        $this->condicao('Padrão', 30, padrao: true);

        $orfao = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Antigo', 'nif' => $this->nif(),
            'type' => 'pessoa_juridica', 'is_active' => true,
        ]);
        $orfao->forceFill(['payment_term_id' => null, 'payment_term_days' => 0])->saveQuietly();

        $this->artisan('clientes:condicao-em-falta', ['--tenant' => $this->tenant->id])
            ->assertSuccessful();

        $this->assertNull($orfao->fresh()->payment_term_id);
    }

    public function test_o_comando_preenche_os_antigos_com_aplicar(): void
    {
        $padrao = $this->condicao('Padrão', 30, padrao: true);

        $orfao = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Antigo', 'nif' => $this->nif(),
            'type' => 'pessoa_juridica', 'is_active' => true,
        ]);
        $orfao->forceFill(['payment_term_id' => null, 'payment_term_days' => 0])->saveQuietly();

        $escolhido = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Escolhido', 'nif' => $this->nif(),
            'type' => 'pessoa_juridica', 'is_active' => true,
        ]);
        $outra = $this->condicao('Outra', 60, ordem: 5);
        $escolhido->forceFill(['payment_term_id' => $outra->id])->saveQuietly();

        $this->artisan('clientes:condicao-em-falta', [
            '--tenant' => $this->tenant->id,
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertSame($padrao->id, $orfao->fresh()->payment_term_id);
        $this->assertSame(30, (int) $orfao->fresh()->payment_term_days);
        // Uma escolha feita à mão é deliberada — não se corrige.
        $this->assertSame($outra->id, $escolhido->fresh()->payment_term_id);
    }
}
