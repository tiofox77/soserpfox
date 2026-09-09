<?php

namespace Tests\Feature\Workshop;

use App\Models\Client;
use App\Models\HR\Employee;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Service;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * OS TRÊS CATÁLOGOS DA OFICINA NO ECRÃ GENÉRICO.
 *
 * Mecânicos, viaturas e serviços eram três componentes Livewire com a mesma
 * forma e três cópias das mesmas quinhentas linhas. Passaram para o esquema
 * (`Catalogos`) e para o ecrã que já servia os fornecedores e os turnos.
 *
 * Passar um Livewire para o ecrã genérico PERDE REGRAS EM SILÊNCIO: o esquema
 * é uma tradução à mão das `rules()`, e uma regra que fique por traduzir não
 * dá erro nenhum — dá um formulário que aceita o que não devia. Foi o que
 * aconteceu com sete guardas dos catálogos da tesouraria. Estes ensaios são a
 * lista de verificação escrita em código:
 *
 *   · as listas de escolha batem com os `enum` da base;
 *   · a matrícula é única POR EMPRESA, e diz-se no campo (não um 1062 cru);
 *   · o número da viatura e o código do serviço geram-se sozinhos;
 *   · um id de outra empresa (o cliente) não entra;
 *   · o que está em uso não se apaga — guarda que o Livewire NÃO tinha;
 *   · cada verbo pede a sua permissão;
 *   · e o «Importar de RH», que era um botão do ecrã de sempre.
 */
class CatalogosDaOficinaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    /* ─── As listas de escolha batem com a base ───────────────────────── */

    /**
     * UM VALOR GRAVADO QUE NÃO ESTEJA NAS OPÇÕES abre o registo com o campo em
     * branco e recusa-o ao guardar — 422 sobre o valor do próprio registo.
     * Por isso as opções lêem-se do `enum` da coluna, e não da memória.
     */
    public function test_as_escolhas_batem_com_os_enum_da_base(): void
    {
        $esperado = [
            ['mecanicos', 'level', 'workshop_mechanics', 'level'],
            ['viaturas', 'status', 'workshop_vehicles', 'status'],
            ['viaturas', 'fuel_type', 'workshop_vehicles', 'fuel_type'],
            ['servicos', 'category', 'workshop_services', 'category'],
        ];

        foreach ($esperado as [$catalogo, $campo, $tabela, $coluna]) {
            $def = \App\Services\Invoicing\Catalogos::um($catalogo);
            $doEsquema = collect($def['campos'])->firstWhere('chave', $campo)['opcoes'] ?? [];

            $this->assertSame(
                $this->valoresDoEnum($tabela, $coluna),
                array_column($doEsquema, 'valor'),
                "{$catalogo}.{$campo}: as opções do esquema não são as da coluna"
            );
        }
    }

    /** @return list<string> */
    private function valoresDoEnum(string $tabela, string $coluna): array
    {
        $tipo = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM {$tabela} LIKE '{$coluna}'")[0]->Type;

        preg_match_all("/'((?:[^']|'')*)'/", $tipo, $m);

        return array_map(fn ($v) => str_replace("''", "'", $v), $m[1]);
    }

    /* ─── Mecânicos ───────────────────────────────────────────────────── */

    public function test_criar_um_mecanico_grava_as_especialidades(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.create');

        $r = $this->postJson(self::API . '/mecanicos', [
            'name' => 'Zeca Mota',
            'phone' => '923000111',
            'level' => 'senior',
            'specialties' => ['Motor', 'Chapa'],
            'hourly_rate' => 2500,
        ])->assertCreated();

        $m = Mechanic::firstWhere('name', 'Zeca Mota');

        $this->assertSame(['Motor', 'Chapa'], $m->specialties);
        $this->assertSame('senior', $m->level);
        $this->assertSame($this->tenant->id, $m->tenant_id);
        // A linha volta pronta para a tabela, com a lista tal como se gravou.
        $this->assertSame(['Motor', 'Chapa'], $r->json('data.specialties'));
    }

    /**
     * SEM ESPECIALIDADE NENHUMA, a lista de mecânicos deixa de responder à
     * única pergunta que se lhe faz: «quem é que mexe em motores?»
     */
    public function test_um_mecanico_sem_especialidade_nao_se_grava(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.create');

        $this->postJson(self::API . '/mecanicos', [
            'name' => 'Sem Nada', 'phone' => '923000112', 'level' => 'pleno', 'specialties' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('specialties');
    }

    /** E uma especialidade inventada não entra na lista pela porta do pedido. */
    public function test_uma_especialidade_inventada_nao_entra(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.create');

        $this->postJson(self::API . '/mecanicos', [
            'name' => 'Inventor', 'phone' => '923000113', 'level' => 'pleno',
            'specialties' => ['Astrofísica'],
        ])->assertStatus(422)->assertJsonValidationErrors('specialties');

        $this->postJson(self::API . '/mecanicos', [
            'name' => 'Meio Inventor', 'phone' => '923000114', 'level' => 'pleno',
            'specialties' => ['Motor', 'Astrofísica'],
        ])->assertCreated();

        $this->assertSame(['Motor'], Mechanic::firstWhere('name', 'Meio Inventor')->specialties);
    }

    /**
     * UM MECÂNICO COM ORDENS NÃO SE APAGA — guarda que o ecrã em Livewire não
     * tinha. Apagá-lo deixava a ordem de serviço a apontar para ninguém.
     */
    public function test_um_mecanico_com_ordens_nao_se_apaga(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.delete');

        $m = Mechanic::create(['name' => 'Ocupado', 'phone' => '923000115', 'specialties' => ['Motor']]);
        $ordem = $this->ordem(['mechanic_id' => $m->id]);

        $this->deleteJson(self::API . "/mecanicos/{$m->id}")->assertStatus(422);
        $this->assertNotNull(Mechanic::find($m->id));

        // E a lista di-lo antes de se tentar, para o botão poder ficar apagado.
        $linha = collect($this->getJson(self::API . '/mecanicos')->json('data'))->firstWhere('id', $m->id);
        $this->assertFalse($linha['pode_apagar']);

        $ordem->forceDelete();
        $this->deleteJson(self::API . "/mecanicos/{$m->id}")->assertOk();
    }

    /* ─── Viaturas ────────────────────────────────────────────────────── */

    public function test_a_viatura_ganha_numero_e_a_matricula_sobe_em_maiusculas(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $this->postJson(self::API . '/viaturas', [
            'plate' => ' ld-42-11-aa ',
            'owner_name' => 'Dona Ana',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'status' => 'active',
        ])->assertCreated();

        $v = Vehicle::first();

        $this->assertSame('LD-42-11-AA', $v->plate, 'a matrícula normaliza-se');
        $this->assertNotNull($v->vehicle_number, 'o número interno gera-se sozinho');
        $this->assertStringStartsWith('VEH-', $v->vehicle_number);
    }

    /**
     * A MATRÍCULA É ÚNICA POR EMPRESA — e diz-se no campo.
     *
     * A tabela tem o índice `(tenant_id, plate)`; sem isto declarado, repetir
     * uma matrícula dava um 1062 cru, com o SQL inteiro na cara de quem estava
     * a escrever.
     */
    public function test_a_matricula_nao_se_repete_na_mesma_empresa(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $dados = ['plate' => 'LD-11-11-AA', 'owner_name' => 'A', 'brand' => 'B', 'model' => 'C', 'status' => 'active'];

        $this->postJson(self::API . '/viaturas', $dados)->assertCreated();
        $this->postJson(self::API . '/viaturas', $dados)
            ->assertStatus(422)->assertJsonValidationErrors('plate');
    }

    /** Um cliente de outra empresa não pode ser o dono de uma viatura desta. */
    public function test_o_cliente_tem_de_ser_desta_empresa(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $outra = \App\Models\Tenant::create([
            'name' => 'Oficina do Lado', 'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Client::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id,
            'name' => 'Cliente do Lado',
        ]);

        $this->postJson(self::API . '/viaturas', [
            'plate' => 'LD-22-22-AA', 'owner_name' => 'A', 'brand' => 'B', 'model' => 'C',
            'status' => 'active', 'client_id' => $alheio->id,
        ])->assertStatus(422)->assertJsonValidationErrors('client_id');
    }

    /**
     * AS TRÊS VALIDADES SAEM NA FORMA QUE O CAMPO DE DATA LÊ.
     *
     * O modelo converte-as para Carbon e em JSON iam com hora e fuso; o
     * `<input type="date">` não as lia, o campo abria vazio e guardar apagava
     * a data que lá estava.
     */
    public function test_as_validades_saem_como_data_simples(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $this->postJson(self::API . '/viaturas', [
            'plate' => 'LD-33-33-AA', 'owner_name' => 'A', 'brand' => 'B', 'model' => 'C', 'status' => 'active',
            'registration_expiry' => '2027-03-15',
            'insurance_expiry' => '2026-11-30',
            'inspection_expiry' => '2026-10-01',
        ])->assertCreated()
            ->assertJsonPath('data.registration_expiry', '2027-03-15')
            ->assertJsonPath('data.insurance_expiry', '2026-11-30')
            ->assertJsonPath('data.inspection_expiry', '2026-10-01');
    }

    /** E a coluna «Nº», que não é campo do formulário, chega mesmo assim. */
    public function test_a_coluna_do_numero_nao_vem_vazia(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $this->postJson(self::API . '/viaturas', [
            'plate' => 'LD-44-44-AA', 'owner_name' => 'A', 'brand' => 'B', 'model' => 'C', 'status' => 'active',
        ])->assertCreated();

        $linha = $this->getJson(self::API . '/viaturas')->assertOk()->json('data.0');

        $this->assertArrayHasKey('vehicle_number', $linha);
        $this->assertStringStartsWith('VEH-', (string) $linha['vehicle_number']);
    }

    public function test_uma_viatura_com_ordens_nao_se_apaga(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.delete');

        $ordem = $this->ordem();

        $this->deleteJson(self::API . "/viaturas/{$ordem->vehicle_id}")->assertStatus(422);
        $this->assertNotNull(Vehicle::find($ordem->vehicle_id));
    }

    /* ─── Serviços ────────────────────────────────────────────────────── */

    public function test_o_servico_ganha_codigo_sozinho(): void
    {
        $this->comPermissoes('workshop.services.view', 'workshop.services.create');

        $this->postJson(self::API . '/servicos', [
            'name' => 'Mudança de óleo', 'category' => 'Manutenção',
            'labor_cost' => 15000, 'estimated_hours' => 1.5,
        ])->assertCreated();

        $s = Service::first();

        $this->assertStringStartsWith('SRV-', $s->service_code);
        $this->assertSame('1.50', (string) $s->estimated_hours);
    }

    public function test_um_servico_ja_lancado_numa_ordem_nao_se_apaga(): void
    {
        $this->comPermissoes('workshop.services.view', 'workshop.services.delete');

        $s = Service::create(['service_code' => 'SRV-X', 'name' => 'Alinhamento', 'labor_cost' => 5000]);
        $ordem = $this->ordem();

        $item = WorkOrderItem::create([
            'work_order_id' => $ordem->id, 'service_id' => $s->id, 'type' => 'service',
            'name' => 'Alinhamento', 'quantity' => 1, 'unit_price' => 5000, 'subtotal' => 5000,
        ]);

        $this->deleteJson(self::API . "/servicos/{$s->id}")->assertStatus(422);

        $item->forceDelete();
        $this->deleteJson(self::API . "/servicos/{$s->id}")->assertOk();
    }

    /* ─── As permissões, verbo a verbo ────────────────────────────────── */

    public function test_cada_verbo_pede_a_sua_permissao(): void
    {
        // Só a de VER: a lista abre, e mais nada.
        $this->comPermissoes('workshop.mechanics.view');

        $this->getJson(self::API . '/mecanicos')->assertOk();
        $this->getJson(self::API . '/mecanicos/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_escrever', false);

        $this->postJson(self::API . '/mecanicos', [
            'name' => 'Novo', 'phone' => '923000116', 'level' => 'pleno', 'specialties' => ['Motor'],
        ])->assertForbidden();

        $m = Mechanic::create(['name' => 'Existente', 'phone' => '923000117', 'specialties' => ['Motor']]);

        $this->putJson(self::API . "/mecanicos/{$m->id}", [
            'name' => 'Outro', 'phone' => '923000117', 'level' => 'pleno', 'specialties' => ['Motor'],
        ])->assertForbidden();

        $this->deleteJson(self::API . "/mecanicos/{$m->id}")->assertForbidden();
    }

    /** E sem a de ver, nem a lista abre. */
    public function test_sem_permissao_de_ver_a_lista_nao_abre(): void
    {
        $this->getJson(self::API . '/mecanicos')->assertForbidden();
        $this->getJson(self::API . '/viaturas')->assertForbidden();
        $this->getJson(self::API . '/servicos')->assertForbidden();
    }

    /* ─── Importar de RH ──────────────────────────────────────────────── */

    /**
     * O «IMPORTAR DE RH» era um botão do ecrã de sempre, e é o que impede a
     * oficina de escrever a mesma pessoa duas vezes.
     */
    public function test_importar_de_rh_cria_os_mecanicos(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.create');

        $um = $this->funcionario('Ana', 'Baptista', 'ana@exemplo.ao', '923111111');
        $dois = $this->funcionario('Bento', 'Cruz', 'bento@exemplo.ao', '923222222');

        $lista = $this->getJson(self::API . '/mecanicos/importaveis')->assertOk()->json('data');
        $this->assertCount(2, $lista);
        $this->assertFalse($lista[0]['bloqueado']);

        $this->postJson(self::API . '/mecanicos/importar', ['ids' => [$um->id, $dois->id]])
            ->assertOk()->assertJsonPath('quantos', 2);

        $this->assertSame(2, Mechanic::count());
        $this->assertSame('923111111', Mechanic::firstWhere('name', 'Ana Baptista')->phone);
    }

    /**
     * QUEM JÁ CÁ ESTÁ VEM MARCADO E TRANCADO — e importar outra vez não o
     * duplica. Era a regra do ecrã de sempre («já existente»), e é o que faz
     * o botão poder carregar-se sem medo.
     */
    public function test_quem_ja_e_mecanico_nao_se_importa_duas_vezes(): void
    {
        $this->comPermissoes('workshop.mechanics.view', 'workshop.mechanics.create');

        $f = $this->funcionario('Ana', 'Baptista', 'ana@exemplo.ao', '923111111');

        $this->postJson(self::API . '/mecanicos/importar', ['ids' => [$f->id]])->assertOk();

        $lista = $this->getJson(self::API . '/mecanicos/importaveis')->assertOk()->json('data');

        $this->assertTrue($lista[0]['bloqueado'], 'quem já cá está diz que já cá está');
        $this->assertTrue($lista[0]['atribuido']);

        $this->postJson(self::API . '/mecanicos/importar', ['ids' => [$f->id]])
            ->assertOk()->assertJsonPath('quantos', 0);

        $this->assertSame(1, Mechanic::count());
    }

    /** Importar CRIA: pede a permissão de criar, não a de ver. */
    public function test_importar_pede_a_permissao_de_criar(): void
    {
        $this->comPermissoes('workshop.mechanics.view');

        $f = $this->funcionario('Ana', 'Baptista', 'ana@exemplo.ao', '923111111');

        $this->getJson(self::API . '/mecanicos/importaveis')->assertForbidden();
        $this->postJson(self::API . '/mecanicos/importar', ['ids' => [$f->id]])->assertForbidden();
    }

    /** E nos catálogos que não importam de lado nenhum, a porta nem existe. */
    public function test_onde_nao_se_importa_a_porta_e_um_404(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $this->getJson(self::API . '/viaturas/importaveis')->assertNotFound();
        $this->postJson(self::API . '/viaturas/importar', ['ids' => []])->assertNotFound();
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function funcionario(string $nome, string $apelido, string $email, string $telefone): Employee
    {
        return Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-' . substr(uniqid(), -5),
            'first_name' => $nome,
            'last_name' => $apelido,
            'email' => $email,
            'phone' => $telefone,
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    private function ordem(array $campos = []): WorkOrder
    {
        $viatura = Vehicle::create([
            'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-ZZ',
            'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Marca', 'model' => 'Modelo',
        ]);

        return WorkOrder::create(array_merge([
            'order_number' => 'OS-' . substr(uniqid(), -5),
            'vehicle_id' => $viatura->id,
            'received_at' => now(),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
        ], $campos));
    }
}
