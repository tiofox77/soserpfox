<?php

namespace Tests\Feature\Billing;

use App\Models\Order;
use App\Services\Billing\AvisoDePagamentoPendente;
use App\Models\SoftwareSetting;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * O aviso de pagamento pendente sai UMA VEZ por pedido.
 *
 * Isto é saldo da operadora: o travão era uma chave de cache com 10 minutos,
 * pelo que o mesmo aviso voltava a sair passado esse tempo — ou a qualquer
 * `cache:clear`, que corre em cada actualização. Com o comprovativo a ser
 * reanexado, eram vários SMS pelo mesmo pedido.
 */
class AvisoPagamentoUmaVezTest extends TenantTestCase
{
    private function pedido(): Order
    {
        return Order::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'plan_id'   => $this->tenant->activeSubscription?->plan_id,
            'status'    => 'pending',
            'amount'    => 1000,
        ]);
    }

    /**
     * Conta por OCASIÃO. O OrderObserver já dispara `pedidoCriado` ao criar o
     * pedido, pelo que contar tudo junto misturava dois avisos distintos.
     */
    private function avisosDe(Order $o, string $ocasiao): int
    {
        return DB::table('avisos_de_subscricao')
            ->where('referencia', 'order:' . $o->id)
            ->where('aviso', 'pag_pend_' . $ocasiao)
            ->where('canal', 'sms')->count();
    }

    public function test_o_mesmo_aviso_nao_repete_mesmo_com_a_cache_limpa(): void
    {
        SoftwareSetting::set('billing', 'aviso_sms_ativo', true);
        $servico = app(AvisoDePagamentoPendente::class);
        $o = $this->pedido();

        $servico->pedidoCriado($o);
        $this->assertSame(1, $this->avisosDe($o, 'novo'), 'o primeiro aviso devia ficar registado');

        // O que partia antes: a memória vivia na cache e desaparecia.
        \Cache::flush();

        $servico->pedidoCriado($o);
        $servico->pedidoCriado($o);
        $this->assertSame(1, $this->avisosDe($o, 'novo'), 'o aviso repetiu-se — volta a gastar saldo');
    }

    public function test_comprovativo_reanexado_nao_manda_outro_sms(): void
    {
        SoftwareSetting::set('billing', 'aviso_sms_ativo', true);
        $servico = app(AvisoDePagamentoPendente::class);
        $o = $this->pedido();

        $servico->comprovativoAnexado($o);
        \Cache::flush();
        $servico->comprovativoAnexado($o);   // cliente anexou outra vez

        $this->assertSame(1, $this->avisosDe($o, 'comprovativo'));
    }

    public function test_ocasioes_diferentes_contam_separado(): void
    {
        SoftwareSetting::set('billing', 'aviso_sms_ativo', true);
        $servico = app(AvisoDePagamentoPendente::class);
        $o = $this->pedido();

        // Pedido novo e comprovativo são dois factos distintos: um de cada.
        $servico->pedidoCriado($o);
        $servico->comprovativoAnexado($o);

        $this->assertSame(1, $this->avisosDe($o, 'novo'));
        $this->assertSame(1, $this->avisosDe($o, 'comprovativo'));
    }
}
