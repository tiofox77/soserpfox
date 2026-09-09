<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Teste de fumo: todos os ecrãs dos módulos de negócio renderizam.
 *
 * Apanha a classe de defeito mais comum e mais cara deste projeto — o erro 500
 * ao abrir a página. Já apanhou, entre outros: uma classe de categoria que não
 * existia, um `@php(...)` de uma linha que rebentava a compilação do Blade, e
 * uma vista a usar variáveis que o componente nunca passava.
 */
class ModuleScreensRenderTest extends TenantTestCase
{
    /**
     * Componentes sem parâmetros: têm de montar e renderizar tal e qual.
     *
     * Sem app_path(): os data providers do PHPUnit correm ANTES de a aplicação
     * arrancar, e qualquer helper do Laravel rebenta com
     * "Call to undefined method Container::path()".
     */
    public static function ecrasSimples(): array
    {
        $raiz = dirname(__DIR__, 2);
        $classes = [];

        foreach (['Workshop', 'Salon', 'Hotel', 'Restaurant'] as $modulo) {
            foreach (glob("{$raiz}/app/Livewire/{$modulo}/*.php") as $ficheiro) {
                $classe = "App\\Livewire\\{$modulo}\\" . basename($ficheiro, '.php');

                if (!class_exists($classe)) {
                    continue;
                }

                // Os que exigem parâmetro de rota têm teste próprio abaixo.
                $mount = method_exists($classe, 'mount')
                    ? new \ReflectionMethod($classe, 'mount')
                    : null;

                $exigeParametro = $mount && collect($mount->getParameters())
                    ->contains(fn ($p) => !$p->isOptional());

                if ($exigeParametro) {
                    continue;
                }

                $classes[class_basename($classe)] = [$classe];
            }
        }

        return $classes;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("ecrasSimples")]
    public function test_ecra_renderiza(string $classe): void
    {
        $html = Livewire::test($classe)->html();

        $this->assertNotEmpty($html);
    }

    /**
     * OS ECRÃS COM PARÂMETRO DE ROTA — o folio e a página pública.
     *
     * Eram Livewire e passaram a React. O teste de fumo é o mesmo em espírito:
     * a morada abre. O que ele apanha não mudou — o erro 500 ao abrir a página
     * — só mudou de camada: agora é a rota que tem de responder, e não o
     * componente que tem de montar.
     */
    public function test_ecras_com_parametro_renderizam(): void
    {
        $this->comModulo('hotel');
        $this->comPermissoes('hotel.reservations.view');

        $tipo = \App\Models\Hotel\RoomType::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Duplo', 'code' => 'DUP',
            'base_price' => 30000, 'capacity' => 2, 'is_active' => true,
        ]);

        $reserva = \App\Models\Hotel\Reservation::create([
            'tenant_id' => $this->tenant->id,
            'reservation_number' => 'R' . strtoupper(substr(uniqid(), -9)),
            'client_id' => $this->cliente->id, 'room_type_id' => $tipo->id,
            'check_in_date' => now(), 'check_out_date' => now()->addDay(),
            'nights' => 1, 'room_rate' => 30000,
            'status' => 'checked_in', 'payment_status' => 'pending',
        ]);

        $this->get(route('hotel.reservations.folio', ['id' => $reserva->id]))->assertOk();

        $definicoes = \App\Models\Hotel\HotelSettings::create([
            'tenant_id'              => $this->tenant->id,
            'hotel_name'             => 'Hotel de Teste',
            'booking_slug'           => 'hotel-teste-' . uniqid(),
            'online_booking_enabled' => true,
        ]);

        // A PÁGINA PÚBLICA abre sem sessão nenhuma — é o que ela é.
        $this->get(route('hotel.booking.online', ['slug' => $definicoes->booking_slug]))
            ->assertOk()
            // O cabeçalho é do servidor de propósito: o que o WhatsApp mostra
            // tem de estar no HTML antes de o JavaScript correr.
            ->assertSee('og:title', false)
            ->assertSee('Hotel de Teste', false);
    }

    public function test_pagina_publica_de_reservas_recusa_hotel_desconhecido(): void
    {
        // Antes mostrava "o primeiro tenant que aparecesse" — o hotel de outra
        // empresa, numa página pública, e aceitava reservas para ela.
        $this->get('/hotel/booking/nao-existe-' . uniqid())->assertNotFound();
    }

    public function test_reservas_online_desligadas_sao_recusadas(): void
    {
        $definicoes = \App\Models\Hotel\HotelSettings::create([
            'tenant_id'              => $this->tenant->id,
            'hotel_name'             => 'Hotel Fechado',
            'booking_slug'           => 'fechado-' . uniqid(),
            'online_booking_enabled' => false,
        ]);

        $this->get('/hotel/booking/' . $definicoes->booking_slug)->assertForbidden();
    }
}
