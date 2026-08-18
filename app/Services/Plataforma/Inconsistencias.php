<?php

namespace App\Services\Plataforma;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Inconsistências da plataforma.
 *
 * TODAS as verificações são de leitura. O agente detecta e descreve; a
 * correcção é sempre de um humano. A regra não é teórica: uma rota de
 * manutenção sem argumentos já fez um reconcile reescrever centenas de
 * produtos em produção sem ninguém pedir.
 *
 * A accao_sugerida é TEXTO. Nunca um comando que alguém possa encadear.
 */
class Inconsistencias
{
    /** Catálogo do que se sabe verificar, para o agente não adivinhar nomes. */
    public function catalogo(): array
    {
        return [
            'subscricao_expirada_activa' => [
                'descricao'  => 'Subscrição com data terminada mas ainda marcada como activa',
                'severidade' => 'alta',
            ],
            'teste_expirado_em_uso' => [
                'descricao'  => 'Período de teste terminado e a empresa continua em teste',
                'severidade' => 'alta',
            ],
            'subscricao_sem_prazo' => [
                'descricao'  => 'Subscrição activa sem data de fim',
                'severidade' => 'media',
            ],
            'pedidos_pendentes_antigos' => [
                'descricao'  => 'Pedidos por decidir há mais de 3 dias',
                'severidade' => 'media',
            ],
            'pedidos_duplicados' => [
                'descricao'  => 'A mesma empresa com vários pedidos pendentes do mesmo plano',
                'severidade' => 'alta',
            ],
            'tenant_sem_subscricao' => [
                'descricao'  => 'Empresa registada sem subscrição nenhuma',
                'severidade' => 'media',
            ],
        ];
    }

    /**
     * Corre as verificações pedidas (ou todas as permitidas).
     * Um id fora da allowlist é ignorado, nunca executado.
     */
    public function correr(?array $pedidas = null, ?int $tenantId = null): array
    {
        $permitidas = config('agent.verificacoes', []);

        $alvo = $pedidas
            ? array_values(array_intersect($pedidas, $permitidas))
            : $permitidas;

        $achados = [];
        foreach ($alvo as $id) {
            $metodo = 'verificar' . str_replace('_', '', ucwords($id, '_'));

            if (method_exists($this, $metodo)) {
                $achados = array_merge($achados, $this->$metodo($tenantId));
            }
        }

        return $achados;
    }

    private function achado(string $check, $tenantId, string $descricao, array $evidencia, string $accao): array
    {
        $catalogo = $this->catalogo();

        return [
            'check'          => $check,
            'severidade'     => $catalogo[$check]['severidade'] ?? 'media',
            'tenant_id'      => $tenantId,
            'descricao'      => $descricao,
            'evidencia'      => $evidencia,
            'accao_sugerida' => $accao,
        ];
    }

    private function verificarSubscricaoExpiradaActiva(?int $tenantId): array
    {
        $q = Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now());

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($s) => $this->achado(
            'subscricao_expirada_activa',
            $s->tenant_id,
            'A subscrição terminou mas continua marcada como activa.',
            ['subscription_id' => $s->id, 'ends_at' => (string) $s->ends_at],
            'Confirmar se o cliente renova; caso contrário marcar como expirada no painel.'
        ))->all();
    }

    private function verificarTesteExpiradoEmUso(?int $tenantId): array
    {
        $q = Subscription::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now());

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($s) => $this->achado(
            'teste_expirado_em_uso',
            $s->tenant_id,
            'O período de teste terminou e a subscrição continua em teste.',
            ['subscription_id' => $s->id, 'trial_ends_at' => (string) $s->trial_ends_at],
            'Contactar a empresa para converter num plano pago.'
        ))->all();
    }

    private function verificarSubscricaoSemPrazo(?int $tenantId): array
    {
        $q = Subscription::where('status', 'active')->whereNull('ends_at');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($s) => $this->achado(
            'subscricao_sem_prazo',
            $s->tenant_id,
            'Subscrição activa sem data de fim — nunca expira nem volta a cobrar.',
            ['subscription_id' => $s->id],
            'Definir a data de fim conforme o ciclo contratado.'
        ))->all();
    }

    private function verificarPedidosPendentesAntigos(?int $tenantId): array
    {
        $q = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(3));

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($o) => $this->achado(
            'pedidos_pendentes_antigos',
            $o->tenant_id,
            'Pedido por decidir há mais de 3 dias.',
            [
                'order_id'         => $o->id,
                'dias'             => (int) $o->created_at->diffInDays(now()),
                'tem_comprovativo' => !empty($o->payment_proof),
            ],
            'Rever o comprovativo e decidir, ou pedir ao cliente o comprovativo em falta.'
        ))->all();
    }

    private function verificarPedidosDuplicados(?int $tenantId): array
    {
        $q = Order::select('tenant_id', 'plan_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'pending')
            ->groupBy('tenant_id', 'plan_id')
            ->havingRaw('COUNT(*) > 1');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($linha) => $this->achado(
            'pedidos_duplicados',
            $linha->tenant_id,
            'A mesma empresa tem vários pedidos pendentes do mesmo plano.',
            ['plan_id' => $linha->plan_id, 'pendentes' => (int) $linha->total],
            'Aprovar um só e recusar os restantes, senão a empresa é cobrada em duplicado.'
        ))->all();
    }

    private function verificarTenantSemSubscricao(?int $tenantId): array
    {
        $q = Tenant::whereDoesntHave('subscriptions');

        if ($tenantId) {
            $q->where('id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($t) => $this->achado(
            'tenant_sem_subscricao',
            $t->id,
            'Empresa registada sem subscrição nenhuma.',
            ['criada_em' => (string) $t->created_at],
            'Confirmar se o registo ficou a meio; se sim, acompanhar o cliente.'
        ))->all();
    }
}
