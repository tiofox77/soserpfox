<?php

namespace Tests\Feature;

use App\Livewire\Salon\ClientManagement;
use App\Livewire\Salon\ServiceCategoryManagement;
use App\Models\Salon\Appointment;
use App\Models\Salon\AppointmentService;
use App\Models\Salon\Client as ClienteDeSalao;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O módulo do salão de beleza, área a área.
 *
 * A queixa foi «parece que tem erros», com um 500 à frente. Eram cinco — e
 * quatro deles impediam o módulo de fazer aquilo para que existe: marcar.
 */
class ModuloDoSalaoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('salon');
    }

    private function categoria(): ServiceCategory
    {
        return ServiceCategory::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cabelo',
            'slug'      => 'cabelo-' . uniqid(),
        ]);
    }

    private function servico(ServiceCategory $categoria, string $nome = 'Corte'): Service
    {
        $s = Service::create([
            'tenant_id' => $this->tenant->id,
            'name'      => $nome,
            'price'     => 5000,
        ]);

        $s->updateSalonData(['category_id' => $categoria->id, 'duration' => 30]);

        return $s->fresh();
    }

    private function profissional(): Professional
    {
        return Professional::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Ana',
            'is_active' => true,
        ]);
    }

    /** Um cliente desta empresa, pessoa singular como manda o salão. */
    private function clienteDoSalao(): ClienteDeSalao
    {
        return ClienteDeSalao::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente do Salão',
            'type'      => 'pessoa_fisica',
            'is_active' => true,
        ]);
    }

    private function marcacao(array $extra = []): Appointment
    {
        return Appointment::create(array_merge([
            'tenant_id'       => $this->tenant->id,
            'client_id'       => $this->clienteDoSalao()->id,
            'professional_id' => $this->profissional()->id,
            'date'            => today(),
            'start_time'      => '10:00',
            'end_time'        => '10:30',
            'status'          => 'scheduled',
            'subtotal'        => 5000,
            'total'           => 5000,
        ], $extra));
    }

    /**
     * UM ARTIGO MAL CLASSIFICADO NÃO PODE ESCONDER TODAS AS MARCAÇÕES.
     *
     * `$svc->service->name`, na listagem: a relação traz o escopo do salão
     * (`type = servico`, `module = salon`) e vem a nulo assim que o artigo
     * deixa de o cumprir. Dava «Attempt to read property "name" on null» e a
     * página inteira ia abaixo — na base de bancada eram 56 linhas em 56.
     *
     * @test
     */
    public function o_nome_do_servico_aguenta_um_artigo_fora_do_catalogo_do_salao(): void
    {
        $servico = $this->servico($this->categoria());

        $linha = AppointmentService::create([
            'appointment_id' => $this->marcacao()->id,
            'service_id'     => $servico->id,
            'duration'       => 30,
            'price'          => 5000,
            'total'          => 5000,
        ]);

        $this->assertSame('Corte', $linha->nome_do_servico);

        // O artigo deixa de ser um serviço do salão — como se tivesse sido
        // reclassificado no catálogo da facturação.
        \App\Models\Product::where('id', $servico->id)->update(['module' => null, 'type' => 'produto']);

        $linha = $linha->fresh();

        $this->assertNull($linha->service, 'a relação deixa mesmo de resolver');

        $this->assertSame('Corte', $linha->nome_do_servico,
            'o nome vem do artigo; sem isto a listagem inteira ia abaixo com erro 500');
    }

    /**
     * MARCAR TEM DE FUNCIONAR.
     *
     * `Appointment::SOURCES` anuncia 'system' — que é o valor por omissão do
     * ecrã de nova marcação — e a coluna era um enum que não o conhecia.
     * Gravar dava «Data truncated for column 'source'».
     *
     * @test
     */
    public function todas_as_origens_que_o_modelo_anuncia_cabem_na_coluna(): void
    {
        $m = $this->marcacao(['source' => 'system']);

        $this->assertSame('system', $m->fresh()->source);

        foreach (array_keys(Appointment::SOURCES) as $origem) {
            $m->update(['source' => $origem]);

            $this->assertSame($origem, $m->fresh()->source,
                "a origem '{$origem}' aparece no ecrã: tem de caber na coluna");
        }
    }

    /**
     * CRIAR UM CLIENTE PELO ECRÃ DO SALÃO.
     *
     * Duas coisas ao mesmo tempo: `type` levava 'particular' numa coluna
     * enum('pessoa_fisica','pessoa_juridica'), e faltava o `tenant_id`, que não
     * aceita nulo. Nunca funcionou.
     *
     * @test
     */
    public function criar_um_cliente_pelo_ecra_do_salao(): void
    {
        Livewire::test(ClientManagement::class)
            ->call('openModal')
            ->set('name', 'Dona Maria')
            ->set('phone', '923000000')
            ->call('save')
            ->assertHasNoErrors();

        $c = ClienteDeSalao::where('tenant_id', $this->tenant->id)
            ->where('name', 'Dona Maria')
            ->first();

        $this->assertNotNull($c, 'o cliente tem de ficar criado');
        $this->assertSame($this->tenant->id, $c->tenant_id);
        $this->assertSame('pessoa_fisica', $c->type);
    }

    /**
     * A CATEGORIA CONTA-SE PELO SÍTIO ONDE ELA VIVE.
     *
     * A coluna `category_id` tem chave estrangeira para as categorias da
     * FACTURAÇÃO e fica sempre a nulo num serviço do salão — a categoria dele
     * está no JSON do `description`. Tudo o que contava pela coluna dava zero:
     * o ecrã das categorias, as pastilhas do POS e, pior, o travão que impede
     * apagar uma categoria que ainda tem serviços.
     *
     * @test
     */
    public function a_contagem_de_servicos_por_categoria_nao_e_zero(): void
    {
        $categoria = $this->categoria();

        $this->servico($categoria, 'Corte');
        $this->servico($categoria, 'Coloração');

        $this->assertSame(2, ServiceCategory::contagens($this->tenant->id)[$categoria->id] ?? 0,
            'uma categoria com dois serviços não pode contar zero');

        $this->assertSame(2, Service::where('tenant_id', $this->tenant->id)->daCategoria($categoria->id)->count(),
            'filtrar por categoria tem de devolver os serviços dela');

        // E o travão de apagar tem de morder.
        Livewire::test(ServiceCategoryManagement::class)
            ->call('openDeleteModal', $categoria->id)
            ->call('confirmDelete');

        $this->assertNotNull(ServiceCategory::find($categoria->id),
            'uma categoria com serviços não se apaga — senão ficam órfãos');
    }

    /**
     * O QUE ESTE MÊS CONTA É DESTE ANO.
     *
     * `whereMonth` sem `whereYear` na listagem das marcações: em Setembro
     * somava Setembro de todos os anos anteriores.
     *
     * @test
     */
    public function os_concluidos_do_mes_nao_somam_os_anos_anteriores(): void
    {
        $this->marcacao(['status' => 'completed', 'date' => today()]);
        $this->marcacao(['status' => 'completed', 'date' => today()->subYear()]);

        $painel = Livewire::test(\App\Livewire\Salon\AppointmentManagement::class);

        $this->assertSame(1, $painel->get('totalCompletedMonth'),
            'a marcação do ano passado não é deste mês');
    }

    /** As áreas todas do salão abrem. @test */
    public function todos_os_ecras_do_salao_abrem(): void
    {
        // AS DEZ ROTAS PASSARAM A PEDIR PERMISSÃO. As vinte e nove do salão
        // existiam e nenhuma rota as exigia; o que este ensaio mede é que os
        // ecrãs abrem a quem PODE, e a permissão que falta está no
        // `SalaoNaoAtravessaEmpresasTest`.
        $this->comPermissoes(
            'salon.dashboard.view', 'salon.appointments.view', 'salon.clients.view',
            'salon.professionals.view', 'salon.services.view', 'salon.categories.view',
            'salon.products.view', 'salon.pos.access', 'salon.reports.view', 'salon.settings.view',
        );

        foreach ([
            '/salon/dashboard', '/salon/appointments', '/salon/clients',
            '/salon/professionals', '/salon/services', '/salon/services/categories',
            '/salon/products', '/salon/pos', '/salon/reports/time', '/salon/settings',
        ] as $rota) {
            $r = $this->get($rota);

            // O POS pede um turno aberto e manda para lá — é assim que deve ser.
            if ($rota === '/salon/pos' && $r->isRedirect()) {
                $this->assertStringContainsString('/pos/shifts', $r->headers->get('Location'));
                continue;
            }

            $r->assertOk();
        }
    }
}
