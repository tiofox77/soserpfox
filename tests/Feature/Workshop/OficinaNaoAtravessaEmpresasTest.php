<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Service;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Tests\TenantTestCase;

/**
 * A OFICINA DE UMA EMPRESA NÃO SE ALCANÇA DE OUTRA.
 *
 * Os quatro modelos da oficina não tinham escopo de empresa. Os componentes
 * filtravam por `tenant_id` em quase todo o lado — e esqueciam-se num ou
 * noutro:
 *
 *   Mechanic::find($this->editingId)->update($data);   // ← sem filtro
 *   Mechanic::with(...)->findOrFail($id);              // ← sem filtro, no «ver»
 *
 * O primeiro é o pior dos dois: `editingId` é uma propriedade PÚBLICA do
 * Livewire, e o browser define-a. Bastava mandar o id de um mecânico de outra
 * empresa e chamar `save()` para lhe reescrever a ficha inteira — sem nunca
 * passar pelo `edit()` que filtra.
 *
 * Filtrar os sítios à mão deixa o próximo aberto. O que estes ensaios guardam
 * é o escopo global: a partir dele, o modelo já não sabe devolver linha de
 * outra casa, e um `find()` esquecido devolve nada em vez de devolver tudo.
 */
class OficinaNaoAtravessaEmpresasTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/catalogos';

    private Tenant $outra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');

        $this->outra = Tenant::create([
            'name' => 'Oficina do Lado',
            'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    /** Uma linha da outra empresa, criada por fora do escopo. */
    private function alheio(string $classe, array $campos)
    {
        return $classe::withoutGlobalScopes()->create($campos + ['tenant_id' => $this->outra->id]);
    }

    public function test_um_mecanico_de_outra_empresa_nao_se_le(): void
    {
        $alheio = $this->alheio(Mechanic::class, ['name' => 'Mecânico do Lado', 'phone' => '900000000']);

        $this->assertNull(Mechanic::find($alheio->id), 'o find não pode alcançar outra casa');
        $this->assertSame(0, Mechanic::count());

        // E continua lá — o escopo esconde, não apaga.
        $this->assertNotNull(Mechanic::withoutGlobalScopes()->find($alheio->id));
    }

    /**
     * O CASO CONCRETO, agora pela API.
     *
     * Era uma propriedade PÚBLICA do Livewire (`editingId`) que o browser
     * definia; passou a ser o id no URL, que o browser também define. O que
     * guarda continua a ser o mesmo: o escopo de empresa.
     */
    public function test_gravar_com_o_id_de_outra_empresa_nao_lhe_toca(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.edit');

        $alheio = $this->alheio(Mechanic::class, ['name' => 'Mecânico do Lado', 'phone' => '900000000']);

        $this->putJson(self::API . "/mecanicos/{$alheio->id}", [
            'name' => 'Roubado',
            'phone' => '911111111',
            'level' => 'pleno',
            'specialties' => ['Motor'],
        ])->assertNotFound();

        $this->assertSame(
            'Mecânico do Lado',
            $alheio->fresh()->name,
            'a ficha do mecânico de outra empresa tem de ficar intacta'
        );
    }

    public function test_ver_um_mecanico_de_outra_empresa_nao_o_mostra(): void
    {
        $this->comPermissoes('workshop.mechanics.view');

        $alheio = $this->alheio(Mechanic::class, ['name' => 'Mecânico do Lado', 'phone' => '900000000']);

        $nomes = collect($this->getJson(self::API . '/mecanicos')->assertOk()->json('data'))->pluck('name');

        $this->assertNotContains('Mecânico do Lado', $nomes->all(),
            'a lista não mostra mecânicos de outra empresa');
    }

    /** A guarda também está no APAGAR: um id alheio não se apaga. */
    public function test_apagar_um_mecanico_de_outra_empresa_nao_o_apaga(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.delete');

        $alheio = $this->alheio(Mechanic::class, ['name' => 'Mecânico do Lado', 'phone' => '900000000']);

        $this->deleteJson(self::API . "/mecanicos/{$alheio->id}")->assertNotFound();

        $this->assertNotNull(Mechanic::withoutGlobalScopes()->find($alheio->id));
    }

    public function test_uma_viatura_um_servico_e_uma_ordem_de_outra_empresa_tambem_nao(): void
    {
        $viatura = $this->alheio(Vehicle::class, [
            'vehicle_number' => 'VEH-LADO', 'plate' => 'LD-00-00', 'owner_name' => 'Dono do Lado',
            'brand' => 'Alheia', 'model' => 'X',
        ]);

        $servico = $this->alheio(Service::class, ['service_code' => 'SRV-LADO', 'name' => 'Serviço do Lado']);

        $ordem = $this->alheio(WorkOrder::class, [
            'order_number' => 'OS-LADO',
            'vehicle_id' => $viatura->id,
            'received_at' => now(),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
        ]);

        $this->assertNull(Vehicle::find($viatura->id));
        $this->assertNull(Service::find($servico->id));
        $this->assertNull(WorkOrder::find($ordem->id));

        $this->assertSame(0, Vehicle::count() + Service::count() + WorkOrder::count());
    }

    /**
     * O QUE É DESTA EMPRESA CONTINUA A VER-SE.
     *
     * Um escopo que esconda também o que é nosso não é uma guarda: é uma
     * avaria.
     */
    public function test_o_que_e_desta_empresa_continua_a_ver_se(): void
    {
        $meu = Mechanic::create(['name' => 'Mecânico da Casa', 'phone' => '922222222']);

        $this->assertSame($this->tenant->id, $meu->tenant_id, 'o escopo preenche a empresa ao criar');
        $this->assertNotNull(Mechanic::find($meu->id));
        $this->assertSame(1, Mechanic::count());
    }

    /**
     * E A IMPRESSÃO DA ORDEM DE TRABALHO, que é uma rota com o id no URL.
     *
     * O controlador já filtrava à mão; o escopo é a segunda tranca.
     */
    public function test_a_impressao_de_uma_ordem_de_outra_empresa_nao_abre(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $viatura = $this->alheio(Vehicle::class, [
            'vehicle_number' => 'VEH-LADO-2', 'plate' => 'LD-11-11', 'owner_name' => 'Dono do Lado',
            'brand' => 'Alheia', 'model' => 'Y',
        ]);

        $ordem = $this->alheio(WorkOrder::class, [
            'order_number' => 'OS-LADO-2',
            'vehicle_id' => $viatura->id,
            'received_at' => now(),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
        ]);

        $this->get(route('workshop.work-orders.print', $ordem->id))->assertNotFound();
    }

    /**
     * AS OITO ROTAS PEDEM PERMISSÃO.
     *
     * As dezanove permissões da oficina existiam, apareciam no ecrã de papéis,
     * e nenhuma rota as exigia: quem tivesse o módulo activo abria as ordens de
     * trabalho, os mecânicos e os relatórios.
     */
    public function test_cada_rota_da_oficina_pede_a_sua_permissao(): void
    {
        $rotas = [
            'workshop.dashboard' => 'workshop.dashboard.view',
            'workshop.vehicles' => 'workshop.vehicles.view',
            'workshop.mechanics' => 'workshop.mechanics.view',
            'workshop.services' => 'workshop.services.view',
            'workshop.parts' => 'workshop.parts.view',
            'workshop.work-orders' => 'workshop.work-orders.view',
            'workshop.reports' => 'workshop.reports.view',
        ];

        foreach ($rotas as $rota => $permissao) {
            $this->get(route($rota))->assertForbidden();
        }

        $this->comPermissoes(...array_values($rotas));

        foreach (array_keys($rotas) as $rota) {
            $this->get(route($rota))->assertOk();
        }
    }
}
