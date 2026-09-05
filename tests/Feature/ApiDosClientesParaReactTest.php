<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * A API dos clientes — a PRIMEIRA que escreve.
 *
 * É aqui que a promessa fica difícil: uma API que aceite o que o ecrã recusava
 * não trocou de tecnologia, abriu uma porta. Estes ensaios comparam-na com as
 * regras do `App\Livewire\Invoicing\Clients`, não com o que seria cómodo.
 */
class ApiDosClientesParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/clients';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    /** Um NIF de empresa válido para o verificador angolano. */
    private function nifValido(): string
    {
        return $this->clienteEmpresa()->nif;
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'type' => 'pessoa_juridica',
            'name' => 'Empresa de Ensaio, Lda',
            'nif' => '5000000000',
            'email' => 'ensaio@exemplo.ao',
            'country' => 'AO',
        ], $por);
    }

    /* ─── Quem pode o quê ─────────────────────────────────────────────── */

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'pessoa_juridica',
            'name' => 'Alguém',
            'nif' => '5000000001',
            'country' => 'AO',
        ]);

        $this->getJson(self::RAIZ)->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
        $this->putJson(self::RAIZ . '/' . $cliente->id, $this->corpo())->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $cliente->id)->assertForbidden();

        // Ver não dá direito a escrever.
        $this->comPermissoes('invoicing.clients.view');

        $this->getJson(self::RAIZ)->assertOk();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
        $this->putJson(self::RAIZ . '/' . $cliente->id, $this->corpo())->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $cliente->id)->assertForbidden();
    }

    /* ─── O que não pode sair ─────────────────────────────────────────── */

    /**
     * A tabela dos clientes guarda a palavra-passe do PORTAL. Um modelo cru
     * numa resposta publicava-a.
     *
     * @test
     */
    public function a_palavra_passe_do_portal_nao_viaja(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        Client::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'pessoa_juridica',
            'name' => 'Com Portal',
            'nif' => '5000000002',
            'country' => 'AO',
            'password' => bcrypt('segredo-do-cliente'),
            'remember_token' => 'lembrar-me-secreto',
        ]);

        $corpo = $this->getJson(self::RAIZ)->assertOk()->content();

        $this->assertStringNotContainsString('password', $corpo);
        $this->assertStringNotContainsString('remember_token', $corpo);
        $this->assertStringNotContainsString('lembrar-me-secreto', $corpo);
    }

    /* ─── Escrever ────────────────────────────────────────────────────── */

    /** @test */
    public function cria_um_cliente_e_prende_o_a_esta_empresa(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');

        $resposta = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();

        $id = $resposta->json('data.id');

        $this->assertDatabaseHas('invoicing_clients', [
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa de Ensaio, Lda',
        ]);
    }

    /**
     * AS MESMAS VALIDAÇÕES DO ECRÃ, e não umas parecidas.
     *
     * @test
     */
    public function recusa_o_que_o_ecra_recusava(): void
    {
        $this->comPermissoes('invoicing.clients.create');

        // Sem nome, sem NIF, sem país.
        $this->postJson(self::RAIZ, ['type' => 'pessoa_juridica'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'nif', 'country']);

        // Nome demasiado curto.
        $this->postJson(self::RAIZ, $this->corpo(['name' => 'ab']))
            ->assertJsonValidationErrors('name');

        // País tem de ser código ISO de duas letras — é assim que viaja para a AGT.
        $this->postJson(self::RAIZ, $this->corpo(['country' => 'Angola']))
            ->assertJsonValidationErrors('country');

        // Email tem de ser email.
        $this->postJson(self::RAIZ, $this->corpo(['email' => 'não-é-email']))
            ->assertJsonValidationErrors('email');
    }

    /**
     * O NIF É ÚNICO POR EMPRESA, não no mundo.
     *
     * Duas empresas podem ter o mesmo cliente; um índice global fazia a
     * segunda falhar sem razão nenhuma.
     *
     * @test
     */
    public function o_nif_nao_se_repete_dentro_da_mesma_empresa(): void
    {
        $this->comPermissoes('invoicing.clients.create', 'invoicing.clients.edit');

        $this->postJson(self::RAIZ, $this->corpo(['nif' => '5000000003']))->assertCreated();

        $this->postJson(self::RAIZ, $this->corpo(['nif' => '5000000003', 'name' => 'Outro Nome']))
            ->assertJsonValidationErrors('nif');

        // Mas guardar o PRÓPRIO cliente sem lhe mexer no NIF tem de passar.
        $id = Client::where('nif', '5000000003')->where('tenant_id', $this->tenant->id)->value('id');

        $this->putJson(self::RAIZ . '/' . $id, $this->corpo(['nif' => '5000000003', 'name' => 'Nome Novo']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Novo');
    }

    /** @test */
    public function nao_se_mexe_no_cliente_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.clients.edit', 'invoicing.clients.delete');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra Empresa',
            'slug' => 'outra-' . uniqid(),
            'email' => 'outra' . uniqid() . '@ex.com',
        ]);

        $alheio = Client::create([
            'tenant_id' => $outra->id,
            'type' => 'pessoa_juridica',
            'name' => 'Cliente Alheio',
            'nif' => '5000000004',
            'country' => 'AO',
        ]);

        $this->putJson(self::RAIZ . '/' . $alheio->id, $this->corpo())->assertNotFound();
        $this->deleteJson(self::RAIZ . '/' . $alheio->id)->assertNotFound();

        $this->assertDatabaseHas('invoicing_clients', ['id' => $alheio->id, 'name' => 'Cliente Alheio']);
    }

    /* ─── Apagar ──────────────────────────────────────────────────────── */

    /**
     * UM CLIENTE COM DOCUMENTOS NÃO SE APAGA.
     *
     * Uma factura sem cliente é um documento fiscal órfão: o SAFT deixa de
     * fechar e a AGT não tem a quem imputar a venda.
     *
     * @test
     */
    public function um_cliente_com_facturas_nao_se_apaga(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.delete');

        $cliente = $this->clienteEmpresa();

        SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $cliente->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 100,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson(self::RAIZ . '/' . $cliente->id)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => __('Este cliente tem :n documento(s) e não pode ser apagado. Um documento fiscal não pode ficar sem cliente.', ['n' => 1])]);

        $this->assertDatabaseHas('invoicing_clients', ['id' => $cliente->id]);

        // E o ecrã sabe disso ANTES de tentar.
        $linha = collect($this->getJson(self::RAIZ)->json('data'))->firstWhere('id', $cliente->id);

        $this->assertSame(1, $linha['documentos']);
        $this->assertFalse($linha['pode_apagar']);
    }

    /** Um cliente sem documentos apaga-se. @test */
    public function um_cliente_sem_documentos_apaga_se(): void
    {
        $this->comPermissoes('invoicing.clients.delete');

        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'pessoa_fisica',
            'name' => 'Sem Documentos',
            'nif' => '005417289LA041',
            'country' => 'AO',
        ]);

        $this->deleteJson(self::RAIZ . '/' . $cliente->id)->assertOk();

        // A tabela usa SoftDeletes: a linha fica, marcada. É o que se quer num
        // ERP — um cliente apagado por engano recupera-se, e um documento
        // antigo que lhe aponte continua a saber a quem foi emitido.
        $this->assertSoftDeleted('invoicing_clients', ['id' => $cliente->id]);

        // E deixa de aparecer na lista.
        $this->comPermissoes('invoicing.clients.view');

        $ids = collect($this->getJson(self::RAIZ)->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($cliente->id));
    }

    /* ─── Ler ─────────────────────────────────────────────────────────── */

    /** @test */
    public function procura_por_nome_nif_email_e_telefone(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        Client::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'pessoa_juridica',
            'name' => 'Padaria Bengo',
            'nif' => '5000000005',
            'email' => 'geral@padaria.ao',
            'phone' => '923000111',
            'country' => 'AO',
        ]);

        foreach (['Bengo', '5000000005', 'padaria.ao', '923000111'] as $termo) {
            $this->getJson(self::RAIZ . '?procura=' . urlencode($termo))
                ->assertOk()
                ->assertJsonFragment(['name' => 'Padaria Bengo']);
        }
    }

    /** As opções trazem a geografia e o que este utilizador pode fazer. @test */
    public function as_opcoes_dizem_o_que_se_pode_fazer(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');

        $this->getJson(self::RAIZ . '/opcoes')
            ->assertOk()
            ->assertJsonPath('pais_padrao', 'AO')
            ->assertJsonPath('permissoes.pode_criar', true)
            ->assertJsonPath('permissoes.pode_editar', false)
            ->assertJsonPath('permissoes.pode_apagar', false)
            ->assertJsonStructure(['provincias', 'paises', 'tipos']);
    }
}
