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
            'nif_invalido' => [
                'descricao'  => 'Empresa com NIF ausente ou que não parece um NIF de empresa',
                'severidade' => 'alta',
            ],
            'agt_fila_parada' => [
                'descricao'  => 'Documentos à espera de resposta da AGT há demasiado tempo',
                'severidade' => 'alta',
            ],
            'documentos_por_comunicar' => [
                'descricao'  => 'Facturas emitidas sem estado de comunicação à AGT',
                'severidade' => 'media',
            ],
            'stock_negativo' => [
                'descricao'  => 'Artigos com quantidade negativa em armazém',
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

    /**
     * NIF de empresa ausente ou que não parece um NIF de empresa.
     *
     * O NIF vai em cada documento fiscal comunicado à AGT — um NIF errado só
     * aparece quando as facturas começam a ser recusadas, altura em que já há
     * documentos emitidos com o número errado. O agente assinala; o número
     * sai sempre MASCARADO (ver App\Support\NifAngolano).
     */
    private function verificarNifInvalido(?int $tenantId): array
    {
        $q = Tenant::query();

        if ($tenantId) {
            $q->where('id', $tenantId);
        }

        $achados = [];
        foreach ($q->limit(500)->get(['id', 'name', 'nif']) as $t) {
            $c = \App\Support\NifAngolano::classificar($t->nif);

            if ($c['estado'] === \App\Support\NifAngolano::VALIDO) {
                continue;
            }

            $achados[] = $this->achado(
                'nif_invalido',
                $t->id,
                "NIF {$c['estado']}: {$c['motivo']}.",
                // Por inteiro: o agente tem de o poder verificar e comparar.
                ['nif' => $c['nif'], 'estado' => $c['estado']],
                $c['estado'] === \App\Support\NifAngolano::AUSENTE
                    ? 'Pedir o NIF da empresa antes de emitir documentos.'
                    : 'Confirmar o NIF com o cliente; corrigir antes de haver mais documentos emitidos.'
            );
        }

        return $achados;
    }

    /**
     * Documentos presos na fila da AGT.
     *
     * Submetidos ou por submeter, sem resposta confirmada. A antiguidade do
     * mais antigo diz há quanto tempo está preso — sinal de chaves em falta,
     * fila sem worker, ou a AGT em baixo.
     */
    private function verificarAgtFilaParada(?int $tenantId): array
    {
        $q = DB::table('agt_submissions')
            ->whereIn('status', ['pending', 'submitted'])
            ->where('created_at', '<', now()->subHours(6))
            ->selectRaw('tenant_id, COUNT(*) total, MIN(created_at) mais_antigo')
            ->groupBy('tenant_id');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($linha) => $this->achado(
            'agt_fila_parada',
            $linha->tenant_id,
            "{$linha->total} documento(s) sem resposta da AGT.",
            ['presos' => (int) $linha->total, 'mais_antigo' => (string) $linha->mais_antigo],
            'Verificar as chaves AGT da empresa e se a fila está a ser processada.'
        ))->all();
    }

    /**
     * Facturas emitidas sem estado de comunicação à AGT.
     *
     * Conta só as facturas de venda (a tabela principal). Cresce em silêncio
     * quando a comunicação automática está ligada mas falta configuração.
     */
    private function verificarDocumentosPorComunicar(?int $tenantId): array
    {
        $q = DB::table('invoicing_sales_invoices')
            ->whereNull('deleted_at')
            ->where(function ($w) {
                $w->whereNull('agt_status')->orWhere('agt_status', '');
            })
            ->selectRaw('tenant_id, COUNT(*) total')
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) > 0');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($linha) => $this->achado(
            'documentos_por_comunicar',
            $linha->tenant_id,
            "{$linha->total} factura(s) de venda sem estado de comunicação à AGT.",
            ['por_comunicar' => (int) $linha->total],
            'Confirmar se a comunicação automática está ligada e as chaves configuradas.'
        ))->all();
    }

    /**
     * Stock negativo: vendeu-se mais do que havia num armazém.
     *
     * As linhas de invoicing_stocks são a fonte de verdade. Nunca corrigir a
     * partir daqui — o stock:reconcile é de um humano.
     */
    private function verificarStockNegativo(?int $tenantId): array
    {
        $q = DB::table('invoicing_stocks')
            ->where('quantity', '<', 0)
            ->selectRaw('tenant_id, COUNT(*) total')
            ->groupBy('tenant_id');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->limit(200)->get()->map(fn ($linha) => $this->achado(
            'stock_negativo',
            $linha->tenant_id,
            "{$linha->total} artigo(s) com stock negativo.",
            ['negativos' => (int) $linha->total],
            'Investigar a origem; correr stock:reconcile é decisão de um humano.'
        ))->all();
    }
}
