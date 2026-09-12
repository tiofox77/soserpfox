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
     * NENHUM MÓDULO DE NEGÓCIO TEM LIVEWIRE — e é isso que aqui se guarda.
     *
     * Este ensaio montava, um a um, todos os componentes Livewire dos módulos de
     * negócio: era a rede que apanhava o erro 500 ao abrir a página, a classe de
     * defeito mais comum e mais cara deste projeto. Apanhou uma classe de
     * categoria que não existia, um `@php(...)` de uma linha que rebentava a
     * compilação do Blade, e vistas a usar variáveis que o componente nunca
     * passava.
     *
     * A LISTA ENCOLHEU ATÉ ZERO, e o seu próprio comentário já o antecipava. A
     * oficina, o salão, o hotel e o restaurante passaram a React; depois os
     * eventos, o CRM, os projetos, as compras e o inventário; e a CONTABILIDADE
     * foi a última, em 2026-09-12. Cada ecrã que passou levou o seu ensaio de
     * fumo próprio, morada a morada (`EcrasD…EmReactTest`), e um ensaio que
     * passa por não ter dados nenhuns não diz nada — o PHPUnit recusa-o à cara,
     * e bem.
     *
     * O QUE ISTO GUARDA MUDOU DE SINAL: era uma rede, é um TRAVÃO. Um componente
     * novo num módulo de negócio é um passo para trás na migração, e este ensaio
     * fá-lo notar no minuto em que nascer — se voltar a haver, volta a haver
     * lista, e a rede monta-se outra vez.
     *
     * Fica de fora o que NÃO é ecrã de módulo de negócio e continua em Livewire
     * de propósito: a barra do topo (avisos, mensagens, notificações, relógio da
     * subscrição, troca de empresa), o portal do cliente (que corre no guarda
     * `client`), o assistente de registo, o de instalação, e o painel da
     * plataforma.
     */
    public function test_nenhum_modulo_de_negocio_tem_livewire(): void
    {
        $raiz = dirname(__DIR__, 2);

        $sobras = [];

        foreach ([
            'Accounting', 'CRM', 'Compras', 'Events', 'Hotel', 'Inventario',
            'Invoicing', 'POS', 'Projetos', 'Rh', 'Treasury', 'Users', 'Workshop',
        ] as $modulo) {
            foreach (glob("{$raiz}/app/Livewire/{$modulo}/*.php") as $ficheiro) {
                $sobras[] = $modulo.'/'.basename($ficheiro);
            }
        }

        $this->assertSame([], $sobras,
            'um ecrã de módulo de negócio em Livewire é um passo para trás: os ecrãs são React');

        /*
         * E SE ALGUM VOLTAR, monta-se — que é o que esta rede fazia. O laço fica
         * porque é ele que apanha o 500 ao abrir, e não a contagem.
         */
        foreach ($sobras as $sobra) {
            [$modulo, $ficheiro] = explode('/', $sobra);
            $classe = "App\\Livewire\\{$modulo}\\".basename($ficheiro, '.php');

            if (! class_exists($classe)) {
                continue;
            }

            $mount = method_exists($classe, 'mount') ? new \ReflectionMethod($classe, 'mount') : null;

            // Os que exigem parâmetro de rota têm teste próprio abaixo.
            if ($mount && collect($mount->getParameters())->contains(fn ($p) => ! $p->isOptional())) {
                continue;
            }

            $this->assertNotEmpty(Livewire::test($classe)->html(), $sobra.': não renderiza');
        }
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
