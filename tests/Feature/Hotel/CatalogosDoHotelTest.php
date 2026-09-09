<?php

namespace Tests\Feature\Hotel;

use App\Models\Client;
use App\Models\HR\Employee;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Staff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * OS QUATRO CATÁLOGOS DO HOTEL NO ECRÃ GENÉRICO.
 *
 * Tipos de quarto, quartos, hóspedes e pessoal — quatro listas com a forma de
 * sempre, que passam para o ecrã que já servia os fornecedores, os turnos e a
 * oficina.
 *
 * O QUE ESTES ENSAIOS GUARDAM, para além de «abre e responde»:
 *
 *  · O HÓSPEDE É UM CLIENTE DA FACTURAÇÃO. É a tabela que as reservas usam
 *    (`hotel_reservations.client_id`) e a que a factura precisa. E o NIF é
 *    único por empresa: o ecrã de sempre não o dizia e repetir um dava um 1062
 *    cru na cara de quem escrevia a ficha.
 *  · AS COMODIDADES E OS EXTRAS são chaves (`sea_view`), e a lista mostra o
 *    RÓTULO. Sem isso dizia «balcony sea_view bathtub».
 *  · O ESTADO DE LIMPEZA é editável. A coluna existia, o ecrã mostrava-a, e
 *    não havia por onde mudá-la.
 *  · OS EXTRAS DO QUARTO gravam-se. A coluna existia e o formulário nunca teve
 *    controlo nenhum: guardava-se sempre a lista vazia.
 *  · A GALERIA do tipo de quarto — é ela que o site de reservas mostra.
 */
class CatalogosDoHotelTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
    }

    /* ─── Tipos de quarto ─────────────────────────────────────────────── */

    public function test_criar_um_tipo_de_quarto_grava_as_comodidades(): void
    {
        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.create');

        $this->postJson(self::API . '/tipos-de-quarto', [
            'name' => 'Suite Presidencial',
            'code' => 'ste',
            'base_price' => 90000,
            'weekend_price' => 110000,
            'capacity' => 4,
            'extra_bed_capacity' => 1,
            'extra_bed_price' => 12000,
            'amenities' => ['wifi', 'sea_view', 'bathtub'],
        ])->assertCreated();

        $t = RoomType::first();

        $this->assertSame('STE', $t->code, 'o código sobe em maiúsculas');
        $this->assertSame(['wifi', 'sea_view', 'bathtub'], $t->amenities);
    }

    /** Uma comodidade inventada não entra na lista pela porta do pedido. */
    public function test_uma_comodidade_inventada_nao_entra(): void
    {
        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.create');

        $this->postJson(self::API . '/tipos-de-quarto', [
            'name' => 'Quarto', 'base_price' => 1000, 'capacity' => 2,
            'extra_bed_capacity' => 0, 'extra_bed_price' => 0,
            'amenities' => ['wifi', 'heliporto'],
        ])->assertCreated();

        $this->assertSame(['wifi'], RoomType::first()->amenities);
    }

    /**
     * O PREÇO DE FIM-DE-SEMANA EM BRANCO FICA A NULO — e não a zero.
     *
     * Um zero é um quarto de graça ao sábado, e é assim que a tarifa o lê.
     */
    public function test_o_preco_de_fim_de_semana_em_branco_fica_a_nulo(): void
    {
        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.create');

        $this->postJson(self::API . '/tipos-de-quarto', [
            'name' => 'Quarto', 'base_price' => 1000, 'weekend_price' => '',
            'capacity' => 2, 'extra_bed_capacity' => 0, 'extra_bed_price' => 0,
        ])->assertCreated();

        $this->assertNull(RoomType::first()->weekend_price);
    }

    public function test_um_tipo_com_quartos_nao_se_apaga(): void
    {
        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.delete');

        $tipo = $this->tipo();
        $quarto = Room::create(['tenant_id' => $this->tenant->id, 'room_type_id' => $tipo->id, 'number' => '101']);

        $this->deleteJson(self::API . "/tipos-de-quarto/{$tipo->id}")->assertStatus(422);

        $quarto->forceDelete();
        $this->deleteJson(self::API . "/tipos-de-quarto/{$tipo->id}")->assertOk();
    }

    /** As comodidades chegam à lista com o RÓTULO, e não com a chave. */
    public function test_a_coluna_das_comodidades_traz_os_rotulos(): void
    {
        $this->comPermissoes('hotel.room-types.view');

        $coluna = collect($this->getJson(self::API . '/tipos-de-quarto/opcoes')->assertOk()->json('colunas'))
            ->firstWhere('chave', 'amenities');

        $this->assertSame('multi', $coluna['formato']);
        $this->assertNotEmpty($coluna['opcoes'], 'sem opções, a lista mostrava «sea_view»');
        $this->assertSame(__('Vista para o mar'),
            collect($coluna['opcoes'])->firstWhere('valor', 'sea_view')['rotulo']);
    }

    /* ─── A galeria ───────────────────────────────────────────────────── */

    /**
     * A GALERIA DO TIPO DE QUARTO — é ela que faz alguém escolher um quarto em
     * vez de outro no site de reservas.
     */
    public function test_a_galeria_junta_e_tira_imagens(): void
    {
        Storage::fake('public');

        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.edit');

        $tipo = $this->tipo();

        $r = $this->post(self::API . "/tipos-de-quarto/{$tipo->id}/galeria", [
            'imagens' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertCreated();

        $galeria = $r->json('data.galeria');
        $this->assertCount(2, $galeria);
        Storage::disk('public')->assertExists($galeria[0]['caminho']);

        // TIRA-SE PELO CAMINHO: pela posição, apagar duas seguidas apagava a
        // errada, porque os índices mudam assim que a lista encolhe.
        $r = $this->deleteJson(self::API . "/tipos-de-quarto/{$tipo->id}/galeria", [
            'caminho' => $galeria[0]['caminho'],
        ])->assertOk();

        $this->assertCount(1, $r->json('data.galeria'));
        $this->assertSame($galeria[1]['caminho'], $r->json('data.galeria.0.caminho'));
        Storage::disk('public')->assertMissing($galeria[0]['caminho']);
    }

    /** Uma imagem que não é deste registo não se apaga por ele. */
    public function test_uma_imagem_de_outro_registo_nao_se_tira(): void
    {
        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.edit');

        $tipo = $this->tipo();

        $this->deleteJson(self::API . "/tipos-de-quarto/{$tipo->id}/galeria", [
            'caminho' => 'hotel/room-types/999/inventada.jpg',
        ])->assertNotFound();
    }

    /** E onde não há galeria, a porta nem existe. */
    public function test_onde_nao_ha_galeria_a_porta_e_um_404(): void
    {
        $this->comPermissoes('hotel.rooms.view', 'hotel.rooms.edit');

        $quarto = Room::create(['tenant_id' => $this->tenant->id, 'room_type_id' => $this->tipo()->id, 'number' => '101']);

        $this->post(self::API . "/quartos/{$quarto->id}/galeria", ['imagens' => []])->assertNotFound();
    }

    /**
     * A IMAGEM DE DESTAQUE VAI PARA A COLUNA DO ESQUEMA.
     *
     * A acção estava presa a `logo` e à pasta `suppliers/`: um tipo de quarto
     * guarda a sua em `featured_image`.
     */
    public function test_a_imagem_de_destaque_vai_para_a_coluna_certa(): void
    {
        Storage::fake('public');

        $this->comPermissoes('hotel.room-types.view', 'hotel.room-types.edit');

        $tipo = $this->tipo();

        $r = $this->post(self::API . "/tipos-de-quarto/{$tipo->id}/logotipo", [
            'logotipo' => UploadedFile::fake()->image('destaque.jpg'),
        ])->assertOk();

        $tipo->refresh();

        $this->assertNotNull($tipo->featured_image);
        $this->assertStringStartsWith('hotel/room-types/', $tipo->featured_image);
        $this->assertNotNull($r->json('data.logo'), 'a imagem sai sempre como `logo`, seja qual for a coluna');
    }

    /* ─── Quartos ─────────────────────────────────────────────────────── */

    /**
     * O ESTADO DE LIMPEZA É OUTRA COISA que o estado do quarto: um quarto pode
     * estar livre e sujo. A coluna existia, o ecrã mostrava-a, e não havia por
     * onde mudá-la.
     */
    public function test_o_quarto_grava_o_estado_de_limpeza_e_os_extras(): void
    {
        $this->comPermissoes('hotel.rooms.view', 'hotel.rooms.create');

        $tipo = $this->tipo();

        $this->postJson(self::API . '/quartos', [
            'number' => ' 101 ',
            'room_type_id' => $tipo->id,
            'floor' => '1',
            'status' => 'available',
            'housekeeping_status' => 'dirty',
            'features' => ['balcony', 'sea_view'],
        ])->assertCreated();

        $q = Room::first();

        $this->assertSame('101', $q->number, 'o número normaliza-se');
        $this->assertSame('dirty', $q->housekeeping_status);
        $this->assertSame(['balcony', 'sea_view'], $q->features);
    }

    /** Dois «101» é o começo de uma reserva no quarto errado. */
    public function test_o_numero_do_quarto_nao_se_repete(): void
    {
        $this->comPermissoes('hotel.rooms.view', 'hotel.rooms.create');

        $dados = ['number' => '101', 'room_type_id' => $this->tipo()->id, 'status' => 'available', 'housekeeping_status' => 'clean'];

        $this->postJson(self::API . '/quartos', $dados)->assertCreated();
        $this->postJson(self::API . '/quartos', $dados)->assertStatus(422)->assertJsonValidationErrors('number');
    }

    public function test_um_quarto_com_reserva_viva_nao_se_apaga(): void
    {
        $this->comPermissoes('hotel.rooms.view', 'hotel.rooms.delete');

        $quarto = Room::create(['tenant_id' => $this->tenant->id, 'room_type_id' => $this->tipo()->id, 'number' => '101']);

        $reserva = Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -5),
            'room_id' => $quarto->id,
            'room_type_id' => $quarto->room_type_id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'room_rate' => 28000,
            'status' => 'confirmed',
        ]);

        $this->deleteJson(self::API . "/quartos/{$quarto->id}")->assertStatus(422);

        $reserva->forceDelete();
        $this->deleteJson(self::API . "/quartos/{$quarto->id}")->assertOk();
    }

    /** O filtro por tipo de quarto traz as opções dos dados, e não do esquema. */
    public function test_o_filtro_por_tipo_traz_as_opcoes_dos_dados(): void
    {
        $this->comPermissoes('hotel.rooms.view');

        $tipo = $this->tipo(['name' => 'Suite do Ensaio']);

        $filtro = collect($this->getJson(self::API . '/quartos/opcoes')->assertOk()->json('filtros'))
            ->firstWhere('chave', 'room_type_id');

        $this->assertNotNull($filtro);
        $this->assertSame([['valor' => (string) $tipo->id, 'rotulo' => 'Suite do Ensaio']], $filtro['opcoes']);
    }

    /* ─── Hóspedes ────────────────────────────────────────────────────── */

    /**
     * O HÓSPEDE É UM CLIENTE — e nasce como PESSOA, porque é o `type` que
     * decide se há retenção de IRT na factura.
     */
    public function test_criar_um_hospede_cria_um_cliente_pessoa(): void
    {
        $this->comPermissoes('hotel.guests.view', 'hotel.guests.create');

        $this->postJson(self::API . '/hospedes', [
            'name' => 'Dona Ana Kiluanje',
            'phone' => '923111222',
            'document_type' => 'passaporte',
            'document_number' => 'N1234567',
            'nif' => '005512345LA041',
            'hotel_vip' => true,
        ])->assertCreated();

        $c = Client::firstWhere('name', 'Dona Ana Kiluanje');

        $this->assertSame('pessoa_fisica', $c->type);
        $this->assertTrue((bool) $c->hotel_vip);
        $this->assertTrue((bool) $c->is_active);
    }

    /**
     * O NIF É ÚNICO POR EMPRESA — e diz-se no campo.
     *
     * Há índice na base; o ecrã de sempre não o declarava e repetir um NIF
     * dava um 1062 cru, com o SQL inteiro na cara de quem escrevia a ficha.
     */
    public function test_o_nif_do_hospede_nao_se_repete(): void
    {
        $this->comPermissoes('hotel.guests.view', 'hotel.guests.create');

        $this->postJson(self::API . '/hospedes', ['name' => 'Um', 'nif' => '005512345LA041'])->assertCreated();
        $this->postJson(self::API . '/hospedes', ['name' => 'Dois', 'nif' => '005512345LA041'])
            ->assertStatus(422)->assertJsonValidationErrors('nif');
    }

    /** Sem NIF fica a nulo — e dois sem NIF não são um NIF repetido. */
    public function test_dois_hospedes_sem_nif_gravam_os_dois(): void
    {
        $this->comPermissoes('hotel.guests.view', 'hotel.guests.create');

        $this->postJson(self::API . '/hospedes', ['name' => 'Um', 'nif' => ''])->assertCreated();
        $this->postJson(self::API . '/hospedes', ['name' => 'Dois', 'nif' => ''])->assertCreated();

        $this->assertNull(Client::firstWhere('name', 'Um')->nif);
    }

    public function test_um_hospede_com_reservas_nao_se_apaga(): void
    {
        $this->comPermissoes('hotel.guests.view', 'hotel.guests.delete');

        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Com Reserva', 'type' => 'pessoa_fisica']);

        Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'RES-' . substr(uniqid(), -5),
            'client_id' => $cliente->id,
            'room_type_id' => $this->tipo()->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'room_rate' => 28000,
            'status' => 'confirmed',
        ]);

        $this->deleteJson(self::API . "/hospedes/{$cliente->id}")->assertStatus(422);
    }

    /* ─── Pessoal ─────────────────────────────────────────────────────── */

    public function test_criar_um_colaborador_grava_os_dias_e_as_horas(): void
    {
        $this->comPermissoes('hotel.staff.view', 'hotel.staff.create');

        $this->postJson(self::API . '/pessoal-do-hotel', [
            'name' => 'Marta Sebastião',
            'position' => 'receptionist',
            'department' => 'front_desk',
            'work_start' => '07:00',
            'work_end' => '15:00',
            'working_days' => [1, 2, 3, 4, 5],
        ])->assertCreated();

        $p = Staff::first();

        $this->assertSame([1, 2, 3, 4, 5], $p->working_days);
        $this->assertSame('07:00', $p->work_start->format('H:i'));
    }

    /** Uma função que não existe não entra. */
    public function test_uma_funcao_inventada_e_recusada(): void
    {
        $this->comPermissoes('hotel.staff.view', 'hotel.staff.create');

        $this->postJson(self::API . '/pessoal-do-hotel', [
            'name' => 'Quem', 'position' => 'astronauta', 'department' => 'front_desk',
        ])->assertStatus(422)->assertJsonValidationErrors('position');
    }

    /**
     * IMPORTAR DE RH LIGA A FICHA — e é o que impede a mesma pessoa de entrar
     * duas vezes.
     *
     * O ecrã de sempre nunca preenchia o `hr_employee_id`: importar duas vezes
     * criava duas fichas da mesma pessoa, sem maneira de as juntar.
     */
    public function test_importar_de_rh_liga_a_ficha_e_nao_duplica(): void
    {
        $this->comPermissoes('hotel.staff.view', 'hotel.staff.create');

        $f = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-' . substr(uniqid(), -5),
            'first_name' => 'Ana', 'last_name' => 'Baptista',
            'hire_date' => now()->subYear(), 'status' => 'active',
        ]);

        $this->postJson(self::API . '/pessoal-do-hotel/importar', ['ids' => [$f->id]])
            ->assertOk()->assertJsonPath('quantos', 1);

        $this->assertSame($f->id, Staff::first()->hr_employee_id);

        // Segunda vez: já cá está.
        $lista = $this->getJson(self::API . '/pessoal-do-hotel/importaveis')->assertOk()->json('data');
        $this->assertTrue($lista[0]['bloqueado']);

        $this->postJson(self::API . '/pessoal-do-hotel/importar', ['ids' => [$f->id]])
            ->assertOk()->assertJsonPath('quantos', 0);

        $this->assertSame(1, Staff::count());
    }

    /* ─── As permissões ───────────────────────────────────────────────── */

    public function test_cada_catalogo_pede_a_sua_permissao(): void
    {
        foreach (['tipos-de-quarto', 'quartos', 'hospedes', 'pessoal-do-hotel'] as $qual) {
            $this->getJson(self::API . "/{$qual}")->assertForbidden();
            $this->getJson(self::API . "/{$qual}/opcoes")->assertForbidden();
        }
    }

    /** E as quatro moradas abrem com o ecrã React montado. */
    public function test_as_quatro_moradas_abrem_em_react(): void
    {
        $this->comPermissoes(
            'hotel.room-types.view', 'hotel.rooms.view', 'hotel.guests.view', 'hotel.staff.view'
        );

        foreach (['room-types', 'rooms', 'guests', 'staff'] as $morada) {
            $this->get("/hotel/{$morada}")
                ->assertOk()
                ->assertSee('data-ecra="facturacao/catalogo"', false);
        }
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function tipo(array $campos = []): RoomType
    {
        return RoomType::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Duplo',
            'code' => 'DBL-' . substr(uniqid(), -4),
            'base_price' => 28000,
            'capacity' => 2,
            'extra_bed_capacity' => 1,
            'extra_bed_price' => 8000,
            'is_active' => true,
        ], $campos));
    }
}
