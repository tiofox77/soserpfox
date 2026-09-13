<?php

namespace Tests\Feature\Seguranca;

use App\Models\Client;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use Tests\TenantTestCase;

/**
 * A PÁGINA PÚBLICA DE RESERVAS NÃO EXPÕE NEM MUDA OS CLIENTES DA EMPRESA.
 *
 * Auditoria de segurança de 2026-09-13: «------» como telefone devolvia o
 * primeiro cliente da empresa; `hospede_id` sem sessão ligava a reserva a
 * qualquer ficha e mudava-lhe o email; o telefone de alguém renomeava-o; e uma
 * estada de anos bloqueava os quartos.
 */
class ReservaPublicaDoHotelTest extends TenantTestCase
{
    private HotelSettings $d;

    private RoomType $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('hotel');
        $this->d = HotelSettings::getForTenant($this->tenant->id);
        $this->d->forceFill([
            'hotel_name' => 'Hotel Seguro', 'booking_slug' => 'hotel-seguro-' . substr(uniqid(), -6),
            'hotel_description' => 'Casa de ensaio', 'online_booking_enabled' => true,
            'min_advance_booking_hours' => 0, 'max_advance_booking_days' => 365,
        ])->save();

        $this->tipo = RoomType::create(['tenant_id' => $this->tenant->id, 'name' => 'Duplo', 'base_price' => 20000, 'capacity' => 2, 'is_active' => true]);
        Room::create(['tenant_id' => $this->tenant->id, 'room_type_id' => $this->tipo->id, 'number' => '101', 'floor' => 1, 'status' => 'available', 'is_active' => true]);

        auth()->logout();
        session()->forget('active_tenant_id');
    }

    private function api(string $cauda): string
    {
        return '/api/publico/hotel/' . $this->d->booking_slug . $cauda;
    }

    public function test_um_telefone_sem_digitos_ou_incompleto_nao_devolve_clientes(): void
    {
        Client::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'type' => 'pessoa_fisica', 'name' => 'Cliente Privado', 'phone' => '+244 923 456 789', 'email' => 'privado@empresa.ao', 'country' => 'AO', 'is_active' => true]);

        $this->postJson($this->api('/entrar'), ['telefone' => '------'])->assertStatus(422)->assertDontSee('Cliente Privado');
        $this->postJson($this->api('/entrar'), ['telefone' => '456789'])->assertStatus(422)->assertDontSee('Cliente Privado');
    }

    public function test_hospede_id_sem_sessao_nao_liga_nem_muda_o_email(): void
    {
        $vitima = Client::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'type' => 'pessoa_fisica', 'name' => 'Vitima', 'phone' => '923000111', 'email' => 'vitima@empresa.ao', 'country' => 'AO', 'is_active' => true]);

        $this->postJson($this->api('/reservar'), [
            'tipo' => $this->tipo->id, 'de' => now()->addDays(3)->toDateString(), 'ate' => now()->addDays(4)->toDateString(),
            'adultos' => 1, 'criancas' => 0, 'hospede_id' => $vitima->id, 'email' => 'atacante@mal.ao',
        ])->assertStatus(422);

        $this->assertSame('vitima@empresa.ao', $vitima->fresh()->email);
    }

    public function test_o_telefone_de_alguem_nao_lhe_muda_o_nome(): void
    {
        $cliente = Client::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'type' => 'pessoa_fisica', 'name' => 'Nome Verdadeiro', 'phone' => '923000222', 'email' => 'real@empresa.ao', 'country' => 'AO', 'is_active' => true]);

        $this->postJson($this->api('/reservar'), [
            'tipo' => $this->tipo->id, 'de' => now()->addDays(3)->toDateString(), 'ate' => now()->addDays(4)->toDateString(),
            'adultos' => 1, 'criancas' => 0, 'nome' => 'Nome Falso', 'telefone' => '923000222', 'email' => 'falso@mal.ao',
        ])->assertCreated();

        $this->assertSame('Nome Verdadeiro', $cliente->fresh()->name);
        $this->assertSame('real@empresa.ao', $cliente->fresh()->email);
    }

    public function test_uma_estada_de_anos_nao_se_reserva(): void
    {
        $this->postJson($this->api('/reservar'), [
            'tipo' => $this->tipo->id, 'de' => now()->addDays(3)->toDateString(), 'ate' => now()->addYears(3)->toDateString(),
            'adultos' => 1, 'criancas' => 0, 'nome' => 'Bloqueio', 'telefone' => '923000333',
        ])->assertStatus(422)->assertJsonValidationErrors('ate');
    }
}
