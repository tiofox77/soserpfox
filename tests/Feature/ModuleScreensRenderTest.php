<?php

namespace Tests\Feature;

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
     * NÃO HÁ LIVEWIRE NENHUM — e é isso que aqui se guarda.
     *
     * Este ensaio montava, um a um, todos os componentes Livewire dos módulos de
     * negócio: era a rede que apanhava o erro 500 ao abrir a página. Apanhou uma
     * classe de categoria que não existia, um `@php(...)` de uma linha que
     * rebentava a compilação do Blade, e vistas a usar variáveis que o
     * componente nunca passava.
     *
     * A LISTA ENCOLHEU ATÉ ZERO: primeiro os módulos de negócio (a contabilidade
     * foi a última, a 2026-09-12), depois a barra do topo, o painel da
     * plataforma, o portal do cliente, o registo, a instalação, a carta do
     * restaurante e a marcação do salão (2026-09-13). Com o último componente
     * saiu também o pacote, e o Alpine passou a vir do disco.
     *
     * O QUE ISTO GUARDA É UM TRAVÃO: um componente, uma directiva ou o pacote
     * de volta são um passo para trás, e este ensaio fá-lo notar no minuto em
     * que nascerem. Cada ecrã em React tem o seu ensaio de fumo próprio.
     */
    public function test_nao_ha_livewire_nenhum(): void
    {
        $raiz = dirname(__DIR__, 2);

        $this->assertDirectoryDoesNotExist("{$raiz}/app/Livewire");
        $this->assertDirectoryDoesNotExist("{$raiz}/resources/views/livewire");
        $this->assertArrayNotHasKey('livewire/livewire', json_decode(file_get_contents("{$raiz}/composer.json"), true)['require']);

        $comLivewire = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$raiz}/resources/views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $ficheiro) {
            if (! str_ends_with($ficheiro->getFilename(), '.blade.php')) {
                continue;
            }
            $conteudo = file_get_contents($ficheiro->getPathname());
            if (preg_match('/@livewire|<livewire:|wire:(model|click|navigate|submit|loading|init)/', $conteudo)) {
                $comLivewire[] = str_replace($raiz.DIRECTORY_SEPARATOR, '', $ficheiro->getPathname());
            }
        }

        $this->assertSame([], $comLivewire, 'vistas que ainda falam com o Livewire');
    }

    /**
     * O LAYOUT DA APLICAÇÃO NÃO TEM JAVASCRIPT FORA DO REACT.
     *
     * Depois do Livewire, a barra do topo ainda vivia de Alpine (a língua, o
     * botão da barra, a raposa, o suporte) e o fim do layout de `<script>`
     * soltos com jQuery e toastr. Passou tudo para peças React; o que resta de
     * JavaScript no layout é o pacote. Um `x-data` ou um `<script>` em linha
     * de volta é um passo atrás.
     */
    public function test_o_layout_nao_traz_alpine_nem_jquery(): void
    {
        $html = $this->get('/home')->assertOk()->getContent();

        foreach (['/vendor/js/alpine.min.js', 'livewire.js', 'jquery', 'toastr', 'x-data', '@click', 'mascara-dinheiro', 'pdf-do-documento', 'painel-facturacao'] as $antigo) {
            $this->assertStringNotContainsString($antigo, $html, "o layout ainda traz {$antigo}");
        }

        foreach (['casca', 'casca/alternar', 'casca/lingua', 'casca/sistema', 'casca/suporte', 'casca/notificacoes'] as $peca) {
            $this->assertStringContainsString('data-peca="'.$peca.'"', $html, "falta a peça {$peca}");
        }

        // Os únicos <script> em linha são os do pacote (língua) e os do pixel/JSON.
        preg_match_all('#<script(?![^>]*\bsrc=)(?![^>]*type="application/(?:ld\+)?json")[^>]*>(.*?)</script>#s', $html, $m);
        foreach ($m[1] as $corpo) {
            $this->assertMatchesRegularExpression('/__reactLingua|fbq\(|gtag\(|dataLayer/', $corpo, "script em linha que devia ser React:\n".substr(trim($corpo), 0, 200));
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
        $this->comModulo('hotel');

        $definicoes = \App\Models\Hotel\HotelSettings::create([
            'tenant_id'              => $this->tenant->id,
            'hotel_name'             => 'Hotel Fechado',
            'booking_slug'           => 'fechado-' . uniqid(),
            'online_booking_enabled' => false,
        ]);

        $this->get('/hotel/booking/' . $definicoes->booking_slug)->assertForbidden();
    }
}
