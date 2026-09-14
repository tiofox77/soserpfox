<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * A API dos clientes — a PRIMEIRA que escreve.
 *
 * É aqui que a promessa fica difícil: uma API que aceite o que o ecrã recusava
 * não trocou de tecnologia, abriu uma porta. Estes ensaios guardam as regras
 * que o ecrã de clientes sempre teve — o NIF único por empresa, o país em
 * código ISO, um cliente com documentos que não se apaga —, e não o que seria
 * cómodo para quem chama a API.
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

    /**
     * A CASCATA DA MORADA VEM RESOLVIDA DO SERVIDOR.
     *
     * O ecrã precisa dos municípios da província escolhida e das sugestões de
     * bairro do município. Se não viessem daqui, viriam de uma lista escrita
     * à mão em TypeScript — que é como as províncias acabaram escritas cinco
     * vezes e diferentes umas das outras.
     *
     * @test
     */
    public function as_opcoes_entregam_os_municipios_de_cada_provincia(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        $opcoes = $this->getJson(self::RAIZ . '/opcoes')->assertOk()->json();

        $this->assertCount(21, $opcoes['provincias']);

        foreach ($opcoes['provincias'] as $provincia) {
            $this->assertNotEmpty(
                $opcoes['municipios'][$provincia] ?? [],
                "a província «{$provincia}» chegou ao ecrã sem municípios"
            );
        }

        $this->assertContains('Lobito', $opcoes['municipios']['Benguela']);
        $this->assertContains('Talatona', $opcoes['municipios']['Luanda']);

        // As da reforma de 2024 vêm assinaladas, para ninguém achar que são engano.
        $this->assertContains('Icolo e Bengo', $opcoes['provincias_novas']);

        // Os bairros SUGEREM — só os municípios que têm sugestões aparecem, e
        // o campo aceita o que se escrever.
        $this->assertContains('Ingombota', $opcoes['bairros']['Luanda']);
        $this->assertArrayNotHasKey('Lobito', $opcoes['municipios'],
            'o mapa é província => municípios, e «Lobito» é município');

        // E o ecrã sabe dizer onde é que o cliente entra no portal.
        $this->assertStringContainsString('/client/login', $opcoes['portal_url']);
    }

    /**
     * O ECRÃ MOSTRA A MORADA INTEIRA.
     *
     * A API grava `municipality` e `neighbourhood` desde sempre; o formulário
     * em React só tinha província e cidade, e o que o ecrã não mostra não se
     * grava — na prática o município e o bairro estavam inalcançáveis.
     *
     * @test
     */
    public function o_formulario_tem_municipio_e_bairro(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/Clientes.tsx'));

        // O rótulo pode estar em texto simples ou pelo tradutor (`t('…')`),
        // que é como os ecrãs falam as três línguas — o que se guarda é que o
        // CAMPO existe, não a forma como o rótulo foi escrito.
        foreach (['Município', 'Bairro'] as $campo) {
            $this->assertMatchesRegularExpression(
                '/etiqueta=("' . $campo . '"|\{t\(' . "'" . $campo . "'" . '\)\})/u',
                $ecra,
                "falta o campo {$campo} no formulário"
            );
        }

        // A cascata é a do servidor, não uma segunda lista escrita aqui.
        $this->assertStringContainsString('opcoes?.municipios', $ecra);
        $this->assertStringNotContainsString("'Luanda',", $ecra,
            'o ecrã voltou a ter a sua própria lista de geografia');
    }

    /**
     * O «CLIENTE RÁPIDO» DO EMISSOR ENTRA POR ESTA MESMA PORTA.
     *
     * Está-se a emitir uma factura, o cliente não existe, e cria-se ali mesmo
     * sem largar o documento a meio. O que **não** existe é um segundo caminho
     * de criação: o formulário rápido manda o que o formulário completo manda,
     * para esta rota, e recebe as mesmas recusas. Uma criação «rápida» mais
     * permissiva que a normal não seria rápida — seria a porta das traseiras
     * para meter na base o que a casa recusa à frente.
     *
     * @test
     */
    public function o_cliente_rapido_do_emissor_grava_com_as_mesmas_regras(): void
    {
        $this->comPermissoes(
            'invoicing.clients.view',
            'invoicing.clients.create',
            'invoicing.sales.invoices.create'
        );

        // Exactamente o que o formulário rápido envia: os cinco campos, mais o
        // tipo e o país que ele assume por omissão.
        $rapido = fn (array $por = []) => array_merge([
            'type' => 'pessoa_juridica',
            'name' => 'Cliente do Balcão, Lda',
            'nif' => '5000000123',
            'email' => null,
            'phone' => null,
            'address' => null,
            'country' => 'AO',
        ], $por);

        // O NIF passa pelo verificador angolano: curto demais, ou a começar
        // por um dígito que não existe, não entra.
        $this->postJson(self::RAIZ, $rapido(['nif' => '123']))
            ->assertStatus(422)->assertJsonValidationErrors('nif');
        $this->postJson(self::RAIZ, $rapido(['nif' => '900000000']))
            ->assertStatus(422)->assertJsonValidationErrors('nif');

        // E o nome curto continua a ser nome curto.
        $this->postJson(self::RAIZ, $rapido(['name' => 'ab']))
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $id = $this->postJson(self::RAIZ, $rapido())->assertCreated()->json('data.id');

        $this->assertDatabaseHas('invoicing_clients', [
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'nif' => '5000000123',
        ]);

        // O MESMO NIF NÃO ENTRA DUAS VEZES nesta empresa — foi o que aconteceu
        // ao ecrã antigo, que gravava todos os clientes rápidos sem NIF com o
        // mesmo «999999999» e assim os tornava indistinguíveis na AGT.
        $this->postJson(self::RAIZ, $rapido(['name' => 'Outro Balcão, Lda']))
            ->assertStatus(422)->assertJsonValidationErrors('nif');

        // E, criado, tem de estar escolhível no emissor: é lá que o ecrã o vai
        // buscar para o pôr no documento que está a meio.
        $clientes = collect(
            $this->getJson('/api/v1/invoicing/react/factura/opcoes')->assertOk()->json('clientes')
        );

        $this->assertTrue(
            $clientes->contains('id', $id),
            'o cliente criado do emissor tem de aparecer nas opções da factura'
        );
    }

    /* ─── O que a migração para React tinha deixado cair ──────────────── */

    /**
     * A CONDIÇÃO DE PAGAMENTO.
     *
     * É dela que sai o VENCIMENTO das facturas deste cliente. Sem o campo, todo
     * o cliente novo ficava com a condição padrão da empresa (o modelo põe-na à
     * nascença) sem ninguém poder trocá-la, e o prazo só se descobria na
     * primeira factura emitida.
     */
    /** @test */
    public function a_condicao_de_pagamento_grava_se_e_arrasta_os_dias(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit');

        $trinta = PaymentTerm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pagamento a 30 dias',
            'days' => 30,
            'is_active' => true,
        ]);

        $id = $this->postJson(self::RAIZ, $this->corpo(['payment_term_id' => $trinta->id]))
            ->assertCreated()
            ->json('data.id');

        /*
         * `payment_term_days` FICA EM SINCRONIA — são duas colunas para a
         * mesma coisa: a condição é o catálogo, os dias são o valor legado que
         * o cálculo do vencimento ainda lê. Sem esta sincronia, escolher «30
         * dias» não mexia no vencimento de factura nenhuma.
         */
        $this->assertDatabaseHas('invoicing_clients', [
            'id' => $id,
            'payment_term_id' => $trinta->id,
            'payment_term_days' => 30,
        ]);

        // E sai na lista com o NOME já feito, para o ecrã não cruzar listas.
        $this->getJson(self::RAIZ . '?procura=Empresa de Ensaio')->assertOk()
            ->assertJsonPath('data.0.payment_term_id', $trinta->id)
            ->assertJsonPath('data.0.condicao_pagamento', 'Pagamento a 30 dias');

        // Tirada a condição, os dias voltam a zero: senão ficava um prazo de 30
        // dias a dar vencimentos a um cliente que já não tem condição nenhuma.
        $this->putJson(self::RAIZ . '/' . $id, $this->corpo(['payment_term_id' => null]))->assertOk();

        $this->assertDatabaseHas('invoicing_clients', [
            'id' => $id,
            'payment_term_id' => null,
            'payment_term_days' => 0,
        ]);
    }

    /**
     * A CONDIÇÃO DE OUTRA EMPRESA NÃO ENTRA.
     *
     * O catálogo é por empresa. Um `exists` seco deixava passar um número
     * escrito à mão no pedido, e era o prazo de outra empresa que passava a
     * dar o vencimento às facturas deste cliente.
     */
    /** @test */
    public function a_condicao_de_outra_empresa_e_recusada(): void
    {
        $this->comPermissoes('invoicing.clients.create');

        $vizinha = Tenant::create([
            'name' => 'Empresa Vizinha',
            'slug' => 'vizinha-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'vizinha' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $outra = PaymentTerm::create([
            'tenant_id' => $vizinha->id,
            'name' => 'Condição alheia',
            'days' => 90,
            'is_active' => true,
        ]);

        $this->postJson(self::RAIZ, $this->corpo(['payment_term_id' => $outra->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_term_id');
    }

    /** As opções trazem o catálogo desta empresa, para o formulário o mostrar. */
    /** @test */
    public function as_opcoes_trazem_as_condicoes_de_pagamento(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        PaymentTerm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Condição de bancada',
            'days' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Uma DESLIGADA não se oferece: não se atribui de novo o que a empresa
        // já retirou do catálogo.
        PaymentTerm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Condição desligada',
            'days' => 45,
            'is_default' => false,
            'is_active' => false,
        ]);

        $opcoes = $this->getJson(self::RAIZ . '/opcoes')->assertOk()->json();

        $nomes = collect($opcoes['condicoes_pagamento'])->pluck('nome');

        $this->assertTrue($nomes->contains('Condição de bancada'));
        $this->assertFalse($nomes->contains('Condição desligada'));

        // O padrão vem marcado: é com ele que o formulário abre um cliente novo.
        $this->assertTrue(
            collect($opcoes['condicoes_pagamento'])->firstWhere('nome', 'Condição de bancada')['padrao']
        );
    }

    /**
     * O LOGÓTIPO DO CLIENTE.
     *
     * Vai à parte da ficha, em multipart, porque um ficheiro não viaja em
     * JSON. E EXIGE A PERMISSÃO DE EDITAR: quem só pode ver não troca a imagem
     * de uma ficha.
     */
    /** @test */
    public function o_logotipo_grava_se_e_apaga_se(): void
    {
        Storage::fake('public');

        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');

        // SEM A PERMISSÃO DE EDITAR, não passa.
        $this->postJson(self::RAIZ . "/{$id}/logotipo", [
            'logotipo' => UploadedFile::fake()->image('marca.png'),
        ])->assertForbidden();

        $this->comPermissoes('invoicing.clients.edit');

        $resposta = $this->postJson(self::RAIZ . "/{$id}/logotipo", [
            'logotipo' => UploadedFile::fake()->image('marca.png'),
        ])->assertOk();

        $caminho = $resposta->json('data.logo_caminho');

        $this->assertNotNull($caminho, 'o logótipo tinha de ficar gravado na ficha');
        Storage::disk('public')->assertExists($caminho);

        // A URL sai pronta a mostrar — o ecrã não tem de a montar.
        $this->assertNotNull($resposta->json('data.logo'));

        // E tira-se: o ficheiro sai do disco e a coluna fica limpa.
        $this->deleteJson(self::RAIZ . "/{$id}/logotipo")->assertOk()
            ->assertJsonPath('data.logo', null);

        Storage::disk('public')->assertMissing($caminho);
    }

    /** O que não é imagem, ou é grande de mais, não entra. */
    /** @test */
    public function o_logotipo_tem_de_ser_uma_imagem(): void
    {
        Storage::fake('public');

        $this->comPermissoes('invoicing.clients.create', 'invoicing.clients.edit');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');

        $this->postJson(self::RAIZ . "/{$id}/logotipo", [
            'logotipo' => UploadedFile::fake()->create('conta.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('logotipo');

        $this->postJson(self::RAIZ . "/{$id}/logotipo", [
            'logotipo' => UploadedFile::fake()->image('enorme.png')->size(3000),
        ])->assertStatus(422)->assertJsonValidationErrors('logotipo');
    }

    /**
     * O CELULAR.
     *
     * Estava no tipo e no carregamento da edição, e a lista até o mostrava
     * quando não havia telefone — mas o formulário não tinha campo nenhum por
     * onde o escrever. Em Angola é o número que a maioria dos clientes atende.
     */
    /** @test */
    public function o_celular_grava_se(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');

        $id = $this->postJson(self::RAIZ, $this->corpo(['mobile' => '923111222']))
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('invoicing_clients', ['id' => $id, 'mobile' => '923111222']);

        // A procura é pelos mesmos quatro campos de sempre (nome, NIF, email e
        // telefone) — o celular grava-se e mostra-se, não se procura por ele.
        $this->getJson(self::RAIZ . '?procura=Empresa de Ensaio')
            ->assertOk()
            ->assertJsonPath('data.0.mobile', '923111222');
    }

    /* ─── Os filtros da lista ─────────────────────────────────────────── */

    /**
     * A CIDADE PROCURA POR DENTRO.
     *
     * Era assim o `cityFilter` do ecrã de sempre: escrito à mão, e «Luanda»
     * tinha de apanhar «Luanda Sul». Um `=` obrigava a acertar a cidade toda,
     * e ninguém sabe de cor como está escrita em cada ficha.
     *
     * @test
     */
    public function a_cidade_filtra_por_dentro(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');

        $sul = $this->postJson(self::RAIZ, $this->corpo([
            'name' => 'Cliente do Sul, Lda',
            'nif' => '5000000900',
            'city' => 'Luanda Sul',
        ]))->assertCreated()->json('data.id');

        $benguela = $this->postJson(self::RAIZ, $this->corpo([
            'name' => 'Cliente de Benguela, Lda',
            'nif' => '5000000901',
            'city' => 'Benguela',
        ]))->assertCreated()->json('data.id');

        $ids = collect($this->getJson(self::RAIZ . '?cidade=Luanda')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($sul), '«Luanda» tem de apanhar «Luanda Sul»');
        $this->assertFalse($ids->contains($benguela));
    }

    /** «Quem entrou este mês» — pela data de criação da ficha. @test */
    public function o_intervalo_de_datas_filtra_pela_criacao(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');

        $antigo = $this->postJson(self::RAIZ, $this->corpo(['name' => 'Cliente antigo, Lda', 'nif' => '5000000902']))
            ->assertCreated()->json('data.id');

        Client::where('id', $antigo)->update(['created_at' => now()->subMonths(3)]);

        $hoje = $this->postJson(self::RAIZ, $this->corpo(['name' => 'Cliente de hoje, Lda', 'nif' => '5000000903']))
            ->assertCreated()->json('data.id');

        $desdeOntem = collect(
            $this->getJson(self::RAIZ . '?de=' . now()->subDay()->toDateString())->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($desdeOntem->contains($hoje));
        $this->assertFalse($desdeOntem->contains($antigo));
    }

    /* ─── O extrato: a ficha de ver ───────────────────────────────────── */

    /**
     * O EXTRATO DO CLIENTE — o modal de ver que a migração não trouxe.
     *
     * São as duas perguntas que se fazem antes de dar crédito ou negociar um
     * preço: quanto já comprou, e de quanto em quanto tempo volta.
     *
     * @test
     */
    public function o_extrato_conta_o_que_o_cliente_comprou(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        $cliente = $this->clienteEmpresa();
        $artigo = $this->produtoComStock();

        foreach ([['2026-01-10', 1000.0, 400.0], ['2026-02-10', 3000.0, 3000.0]] as [$data, $total, $pago]) {
            $factura = SalesInvoice::create([
                'tenant_id' => $this->tenant->id,
                'client_id' => $cliente->id,
                'invoice_number' => 'FT EXTRATO/' . random_int(1000, 9999),
                'invoice_date' => $data,
                'due_date' => $data,
                'status' => 'sent',
                'total' => $total,
                'paid_amount' => $pago,
                'created_by' => $this->user->id,
            ]);

            \App\Models\Invoicing\SalesInvoiceItem::create([
                'sales_invoice_id' => $factura->id,
                'product_id' => $artigo->id,
                'product_name' => 'Artigo do extrato',
                'quantity' => 2,
                'unit_price' => $total / 2,
                'total' => $total,
            ]);
        }

        $e = $this->getJson(self::RAIZ . "/{$cliente->id}/extrato")->assertOk()->json();

        $this->assertSame(2, $e['resumo']['documentos']);
        $this->assertEqualsWithDelta(4000, $e['resumo']['facturado'], 0.01);
        $this->assertEqualsWithDelta(3400, $e['resumo']['pago'], 0.01);
        $this->assertEqualsWithDelta(600, $e['resumo']['pendente'], 0.01);
        $this->assertEqualsWithDelta(2000, $e['resumo']['ticket_medio'], 0.01);

        // A PRIMEIRA E A ÚLTIMA saem por ordem, e não trocadas.
        $this->assertSame('2026-01-10', $e['resumo']['primeira']);
        $this->assertSame('2026-02-10', $e['resumo']['ultima']);

        /*
         * A MÉDIA DE DIAS NUNCA É NEGATIVA.
         *
         * O `diffInDays` do Carbon 3 devolve valor com sinal, e o cálculo
         * herdado do Livewire comparava cada data com a anterior — a ficha de
         * um cliente com 52 facturas mostrava «−0,1 dias».
         */
        $this->assertGreaterThan(0, $e['resumo']['dias_entre']);
        $this->assertEqualsWithDelta(31, $e['resumo']['dias_entre'], 1);

        // Os artigos que ele mais leva.
        $this->assertSame('Artigo do extrato', $e['artigos'][0]['nome']);
        $this->assertEqualsWithDelta(4, $e['artigos'][0]['quantidade'], 0.001);
        $this->assertSame(2, $e['artigos'][0]['documentos']);

        // E as últimas facturas, com o saldo já feito.
        $this->assertCount(2, $e['documentos']);
        $this->assertEqualsWithDelta(600, collect($e['documentos'])->sum('saldo'), 0.01);

        // A frequência, mês a mês.
        $this->assertCount(2, $e['frequencia']);
        $this->assertSame('2026-01', $e['frequencia'][0]['periodo']);
    }

    /**
     * UM CLIENTE SEM COMPRAS NENHUMAS não rebenta nem inventa números.
     *
     * O ticket médio de zero facturas seria uma divisão por zero — que sai no
     * JSON como `null` e no ecrã como um espaço em branco.
     *
     * @test
     */
    public function o_extrato_de_um_cliente_sem_compras_responde_a_zeros(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        $cliente = $this->clienteEmpresa();

        $e = $this->getJson(self::RAIZ . "/{$cliente->id}/extrato")->assertOk()->json();

        $this->assertSame(0, $e['resumo']['documentos']);
        // `assertEquals` e não `assertSame`: o JSON escreve `0.0` como `0`, e
        // o que interessa é o valor, não o tipo com que atravessou a rede.
        $this->assertEquals(0, $e['resumo']['ticket_medio']);
        $this->assertEquals(0, $e['resumo']['dias_entre']);
        $this->assertNull($e['resumo']['primeira']);
        $this->assertSame([], $e['documentos']);
        $this->assertSame([], $e['artigos']);
    }

    /**
     * A FICHA OFERECE O EXTRACTO DE CONTA EM PAPEL — a quem o pode abrir.
     *
     * O papel é o do relatório do extracto e pede a permissão dos relatórios:
     * a quem só vê clientes não se dá uma ligação que abriria um 403.
     *
     * @test
     */
    public function o_extrato_traz_o_pdf_do_extracto_de_conta_a_quem_o_abre(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        $cliente = $this->clienteEmpresa();

        $this->assertNull($this->getJson(self::RAIZ . "/{$cliente->id}/extrato")->assertOk()->json('pdf'));

        $this->comPermissoes('invoicing.reports.view');

        $pdf = (string) $this->getJson(self::RAIZ . "/{$cliente->id}/extrato")->assertOk()->json('pdf');

        $this->assertSame('/invoicing/reports/account-statement/pdf', parse_url($pdf, PHP_URL_PATH));
        parse_str((string) parse_url($pdf, PHP_URL_QUERY), $q);
        $this->assertSame(['entidade' => 'cliente', 'id' => (string) $cliente->id], $q);

        $this->assertStringStartsWith('%PDF-', $this->get($pdf)->assertOk()->getContent());
    }

    /** O extrato exige a permissão de ver clientes, e o cliente é desta empresa. @test */
    public function o_extrato_segue_a_permissao_e_a_empresa(): void
    {
        $cliente = $this->clienteEmpresa();

        $this->getJson(self::RAIZ . "/{$cliente->id}/extrato")->assertForbidden();

        $this->comPermissoes('invoicing.clients.view');

        $vizinha = Tenant::create([
            'name' => 'Vizinha do extrato',
            'slug' => 'vizinha-ext-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'vext' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $alheio = Client::create([
            'tenant_id' => $vizinha->id,
            'type' => 'pessoa_juridica',
            'name' => 'Cliente alheio',
            'nif' => '5000000777',
            'country' => 'AO',
        ]);

        $this->getJson(self::RAIZ . "/{$alheio->id}/extrato")->assertNotFound();
    }
}
