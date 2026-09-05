<?php

namespace Tests\Feature;

use App\Models\Salon\Appointment;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * Os painéis dos módulos: tesouraria, RH, salão, oficina e hotel.
 *
 * A pergunta foi «estão a funcionar sem bugs». Não estavam. O que este
 * ficheiro guarda é o que se corrigiu, com o motivo à frente de cada caso.
 */
class PaineisDosModulosTest extends TenantTestCase
{
    /**
     * OS GRÁFICOS DESENHAM-SE DEPOIS DE O LIVEWIRE TROCAR O CANVAS.
     *
     * Medido no browser: chegando ao painel do salão ou da tesouraria pela
     * barra lateral, o gráfico ficava em branco. O `<script>` volta a correr e
     * até era chamado — mas o Livewire monta o componente A SEGUIR e troca o
     * `<canvas>`, deitando fora aquele onde se acabara de desenhar.
     *
     * @test
     */
    public function o_desenho_dos_graficos_espera_pelo_livewire(): void
    {
        $base = file_get_contents(resource_path('views/partials/graficos.blade.php'));

        $this->assertStringContainsString('window.sosDesenhar', $base,
            'um sítio único para dizer quando desenhar');

        $this->assertStringContainsString("addEventListener('livewire:navigated'", $base,
            'pela barra lateral não há recarregamento: é aqui que se desenha');

        $this->assertStringContainsString("morph.updated", $base,
            'trocar de dia ou de período troca o HTML: desenhar outra vez');

        $this->assertStringContainsString('window.SOS_ESCADA', $base,
            'nao ha um instante certo: desenha-se varias vezes enquanto a pagina assenta');

        // A chamada, nao a palavra: o comentario explica porque nao se usa.
        $this->assertStringNotContainsString('requestAnimationFrame(', $base,
            'o rAF nao corre num separador em segundo plano');

        $this->assertStringContainsString('anterior.destroy()', $base,
            'desenhar duas vezes no mesmo canvas da "Canvas is already in use"');

        $this->assertStringContainsString('window.sosGrafico', $base,
            'os new Chart a mao tambem tem de destruir o anterior');

        // E nenhum painel escreve `new Chart` a mao: um deles sem destruir o
        // anterior rebentava a funcao a meio e deixava os seguintes em branco.
        foreach (glob(resource_path('views/livewire/*/dashboard*.blade.php')) as $v) {
            if (! str_contains(file_get_contents($v), 'partials.graficos')) { continue; }

            $this->assertStringNotContainsString('new Chart(', file_get_contents($v),
                basename(dirname($v)) . ': usar sosGrafico(), que destroi o anterior');
        }

        // E NENHUM painel com gráficos volta ao DOMContentLoaded, que dispara
        // uma só vez. São nove: os cinco perguntados mais a contabilidade, o
        // CRM, o inventário e o restaurante, que tinham o mesmo defeito.
        $comGraficos = array_filter(
            array_merge(
                glob(resource_path('views/livewire/*/dashboard*.blade.php')),
                glob(resource_path('views/livewire/*/dashboard/dashboard.blade.php'))
            ),
            fn ($v) => str_contains(file_get_contents($v), 'partials.graficos')
        );

        $this->assertGreaterThanOrEqual(9, count($comGraficos),
            'o varrimento tem de apanhar os painéis todos');

        foreach ($comGraficos as $vista) {
            $this->assertStringNotContainsString('DOMContentLoaded', file_get_contents($vista),
                basename(dirname($vista)) . ': o DOMContentLoaded já passou quando se chega por wire:navigate');
            $this->assertStringContainsString('sosDesenhar', file_get_contents($vista),
                basename(dirname($vista)) . ': usa o sítio único do quando desenhar');
        }
    }

    /**
     * O CHART.JS É DA CASA E NÃO DE UM CDN.
     *
     * A tesouraria puxava-o de `cdn.jsdelivr.net`. A versão on-premise corre
     * sem internet, e quando o CDN falha o painel fica com um quadrado branco
     * sem aviso nenhum.
     *
     * @test
     */
    public function nenhum_painel_vai_buscar_o_chart_js_a_um_cdn(): void
    {
        foreach (['hr', 'hotel', 'salon', 'treasury'] as $painel) {
            $this->assertStringNotContainsString(
                'cdn.jsdelivr.net',
                file_get_contents(resource_path("views/livewire/{$painel}/dashboard.blade.php")),
                "{$painel}: o Chart.js tem de ser o local"
            );
        }
    }

    /**
     * UMA MARCAÇÃO DE OUTRA EMPRESA NÃO SE MEXE DAQUI.
     *
     * As acções rápidas do painel do salão faziam `Appointment::find($id)` com
     * o id vindo do browser — e os modelos do salão não têm escopo global de
     * empresa. Bastava chamar `quickComplete` com o id de outra empresa para
     * dar por concluída e paga a marcação de um concorrente.
     *
     * @test
     */
    public function as_accoes_rapidas_do_salao_nao_tocam_noutra_empresa(): void
    {
        $outra = Tenant::create([
            'name'      => 'Salão do Lado',
            'email'     => uniqid() . '@exemplo.ao',
            'nif'       => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);

        $clienteAlheio = \App\Models\Client::create([
            'tenant_id' => $outra->id,
            'name'      => 'Cliente do Lado',
        ]);

        $profAlheio = \App\Models\Salon\Professional::create([
            'tenant_id' => $outra->id,
            'name'      => 'Profissional do Lado',
        ]);

        $alheia = Appointment::create([
            'tenant_id'  => $outra->id,
            'client_id'       => $clienteAlheio->id,
            'professional_id' => $profAlheio->id,
            'date'       => today(),
            'start_time' => '10:00',
            'end_time'   => '11:00',
            'status'     => 'scheduled',
            'subtotal'   => 5000,
            'total'      => 5000,
        ]);

        $painel = new \App\Livewire\Salon\Dashboard();
        $painel->quickComplete($alheia->id);

        $this->assertSame('scheduled', $alheia->fresh()->status,
            'a marcação é de outra empresa: não se toca');
    }

    /**
     * O PAINEL DA OFICINA INCLUI O DIA DE HOJE.
     *
     * `received_at` é DATETIME e o intervalo vinha em datas secas — o limite
     * de cima ficava na meia-noite do último dia. Resultado: uma oficina não
     * via no painel nada do que entrou hoje.
     *
     * @test
     */
    public function o_painel_da_oficina_conta_o_dia_inteiro(): void
    {
        $fonte = file_get_contents(app_path('Livewire/Workshop/Dashboard.php'));

        $this->assertStringContainsString('endOfDay()', $fonte,
            'o intervalo tem de ir até ao fim do último dia');

        $this->assertStringNotContainsString('[$this->dateFrom, $this->dateTo]', $fonte,
            'as datas secas cortavam o dia de hoje às 00:00');
    }

    /**
     * AS COMPRAS EM RASCUNHO NÃO SÃO DÍVIDA.
     *
     * As vendas já só contavam as definitivas; as compras entravam todas, e um
     * rascunho de factura de fornecedor inflava o «Comprado» e o «A Pagar».
     *
     * @test
     */
    public function a_tesouraria_ignora_compras_em_rascunho(): void
    {
        $fonte = file_get_contents(app_path('Livewire/Treasury/Dashboard.php'));

        $this->assertStringContainsString("whereNotIn('status', ['draft', 'cancelled'])", $fonte,
            'um rascunho de compra não é dinheiro a pagar');

        // E a linha dos 7 dias deixa de ser 14 consultas dentro de um ciclo.
        $this->assertStringContainsString('groupBy(\'dia\', \'type\')', $fonte,
            'uma consulta agrupada, não duas por cada dia');
    }

    /** O nome do dia da semana segue a língua de quem vê, não 'pt_BR'. @test */
    public function o_rh_nao_fala_brasileiro(): void
    {
        $fonte = file_get_contents(app_path('Livewire/HR/HRDashboard.php'));

        $this->assertStringNotContainsString("locale('pt_BR')", $fonte);
        $this->assertStringContainsString("locale(app()->getLocale())", $fonte);
    }
}