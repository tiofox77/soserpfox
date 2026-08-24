<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Tests\TenantTestCase;

/**
 * A renovação emite UMA factura por período.
 *
 * A varredura corre de hora a hora, à boleia do tráfego. Se a verificação de
 * "já facturei este período" falhar, sai uma factura por hora — cada uma com
 * o seu email e SMS ao cliente. Foi o que aconteceu: 10 facturas em 10 horas.
 */
class RenovacaoNaoDuplicaTest extends TenantTestCase
{
    private function subscricaoQueTerminaAmanha(\DateTimeInterface $inicio): void
    {
        $this->tenant->activeSubscription->update([
            'current_period_start' => $inicio,
            'current_period_end'   => now()->addDay(),
            'status'               => 'active',
            'amount'               => 44900,
        ]);
    }

    private function facturasDaSubscricao(): int
    {
        return Invoice::where('subscription_id', $this->tenant->activeSubscription->id)->count();
    }

    public function test_periodo_comecado_hoje_nao_gera_factura_por_hora(): void
    {
        // O caso que partiu em produção: o período começou HOJE e acaba amanhã.
        // A factura de renovação sai hoje — e a verificação antiga procurava
        // uma factura de amanhã em diante, pelo que nunca encontrava a sua.
        $this->subscricaoQueTerminaAmanha(now());

        $servico = app(RenovacaoDeSubscricoes::class);

        $servico->emitirFacturasAVencer();
        $depoisDaPrimeira = $this->facturasDaSubscricao();
        $this->assertSame(1, $depoisDaPrimeira, 'a primeira passagem devia emitir uma factura');

        // As horas seguintes: não pode sair mais nenhuma.
        $servico->emitirFacturasAVencer();
        $servico->emitirFacturasAVencer();
        $servico->emitirFacturasAVencer();

        $this->assertSame(1, $this->facturasDaSubscricao(),
            'saiu factura repetida — é uma por hora, com email e SMS de cada vez');
    }

    public function test_periodo_normal_continua_a_facturar_uma_vez(): void
    {
        // Caso de sempre: período começou há um mês, acaba amanhã.
        $this->subscricaoQueTerminaAmanha(now()->subMonth());

        $servico = app(RenovacaoDeSubscricoes::class);
        $servico->emitirFacturasAVencer();
        $servico->emitirFacturasAVencer();

        $this->assertSame(1, $this->facturasDaSubscricao());
    }

    public function test_a_factura_emitida_vence_no_fim_do_periodo(): void
    {
        $this->subscricaoQueTerminaAmanha(now());
        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();

        $f = Invoice::where('subscription_id', $this->tenant->activeSubscription->id)->first();

        // É esta marca que torna a verificação exacta.
        $this->assertSame(
            now()->addDay()->toDateString(),
            $f->due_date->toDateString()
        );
    }
}
