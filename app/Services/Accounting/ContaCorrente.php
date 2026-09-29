<?php

namespace App\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A CONTA-CORRENTE DE TERCEIROS — quem nos deve e a quem devemos.
 *
 * Três vistas sobre as mesmas linhas de lançamento (as que levam um terceiro:
 * `partner_id` + `partner_type`):
 *
 *   - saldos()      — um terceiro por linha: débito, crédito e saldo.
 *   - extrato()     — os movimentos de UM terceiro, com saldo acumulado.
 *   - antiguidade() — o saldo em aberto repartido por idade (0–30, 31–60…).
 *
 * O SALDO VEM NO SENTIDO NATURAL DO TERCEIRO: num cliente é débito − crédito
 * (positivo = a receber); num fornecedor é crédito − débito (positivo = a
 * pagar). Assim o ecrã lê sempre «positivo = em dívida», de qualquer lado.
 *
 * Só contam lançamentos CONFIRMADOS (`posted`), como no balancete: um
 * rascunho ainda não é contabilidade.
 *
 * Tudo filtra pela empresa À MÃO (`l.tenant_id`) — o escopo global do modelo só
 * existe com sessão aberta, e isto também corre em comandos e ensaios.
 */
class ContaCorrente
{
    public const TIPOS = ['client', 'supplier'];

    /** Escalões da antiguidade, em dias desde a data do documento. */
    public const ESCALOES = [
        'ate_30'    => [0, 30],
        'de_31_60'  => [31, 60],
        'de_61_90'  => [61, 90],
        'de_91_180' => [91, 180],
        'mais_180'  => [181, null],
    ];

    /**
     * Um terceiro por linha, com o seu saldo à data `$ate`.
     *
     * @return array{linhas: list<array>, totais: array}
     */
    public function saldos(int $tenantId, string $tipo, ?string $ate = null, ?string $procura = null, bool $soComSaldo = false): array
    {
        $this->validarTipo($tipo);
        $ate = $ate ?: now()->toDateString();
        $sinal = $this->sinal($tipo);

        $q = $this->linhas($tenantId, $tipo)
            ->leftJoin($this->tabela($tipo) . ' as p', 'p.id', '=', 'l.partner_id')
            ->where('m.date', '<=', $ate)
            ->groupBy('l.partner_id', 'p.name', 'p.nif')
            ->selectRaw('l.partner_id, p.name as nome, p.nif,
                SUM(l.debit) as debito, SUM(l.credit) as credito,
                COUNT(*) as movimentos, MAX(m.date) as ultimo_movimento');

        if ($procura = trim((string) $procura)) {
            $q->where(fn (Builder $w) => $w
                ->where('p.name', 'like', "%{$procura}%")
                ->orWhere('p.nif', 'like', "%{$procura}%"));
        }

        if ($soComSaldo) {
            $q->havingRaw('ABS(SUM(l.debit) - SUM(l.credit)) >= 0.005');
        }

        $linhas = $q->orderBy('p.name')->get()->map(function ($r) use ($sinal) {
            $debito = round((float) $r->debito, 2);
            $credito = round((float) $r->credito, 2);

            return [
                'id'               => (int) $r->partner_id,
                'nome'             => $r->nome ?? __('Terceiro #:id (apagado)', ['id' => $r->partner_id]),
                'nif'              => $r->nif,
                'debito'           => $debito,
                'credito'          => $credito,
                'saldo'            => round($sinal * ($debito - $credito), 2),
                'movimentos'       => (int) $r->movimentos,
                'ultimo_movimento' => $r->ultimo_movimento,
            ];
        })->values()->all();

        $col = collect($linhas);

        return [
            'linhas' => $linhas,
            'totais' => [
                'terceiros'       => $col->count(),
                'com_saldo'       => $col->filter(fn ($l) => abs($l['saldo']) >= 0.005)->count(),
                'debito'          => round($col->sum('debito'), 2),
                'credito'         => round($col->sum('credito'), 2),
                'saldo'           => round($col->sum('saldo'), 2),
                // O que está do lado contrário: um cliente com crédito a favor
                // (pagou a mais / adiantou), um fornecedor a quem adiantámos.
                'saldo_a_favor'   => round($col->filter(fn ($l) => $l['saldo'] < 0)->sum('saldo'), 2),
            ],
        ];
    }

    /**
     * Os movimentos de UM terceiro entre `$de` e `$ate`, com o saldo que trazia
     * de antes e o saldo acumulado linha a linha.
     */
    public function extrato(int $tenantId, string $tipo, int $terceiroId, ?string $de = null, ?string $ate = null): array
    {
        $this->validarTipo($tipo);
        $ate = $ate ?: now()->toDateString();
        $de = $de ?: now()->startOfYear()->toDateString();
        $sinal = $this->sinal($tipo);

        $anterior = (clone $this->linhas($tenantId, $tipo))
            ->where('l.partner_id', $terceiroId)
            ->where('m.date', '<', $de)
            ->selectRaw('COALESCE(SUM(l.debit), 0) as d, COALESCE(SUM(l.credit), 0) as c')
            ->first();

        $saldoAnterior = round($sinal * ((float) $anterior->d - (float) $anterior->c), 2);

        $linhas = $this->linhas($tenantId, $tipo)
            ->leftJoin('accounting_journals as j', 'j.id', '=', 'm.journal_id')
            ->leftJoin('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.partner_id', $terceiroId)
            ->whereBetween('m.date', [$de, $ate])
            ->orderBy('m.date')->orderBy('m.id')->orderBy('l.id')
            ->get([
                'l.id', 'm.id as lancamento_id', 'm.date', 'm.ref', 'l.document_ref',
                'l.name', 'm.narration', 'j.code as diario', 'a.code as conta',
                'l.debit', 'l.credit',
            ]);

        $saldo = $saldoAnterior;
        $movimentos = [];
        foreach ($linhas as $r) {
            $debito = round((float) $r->debit, 2);
            $credito = round((float) $r->credit, 2);
            $saldo = round($saldo + $sinal * ($debito - $credito), 2);

            $movimentos[] = [
                'id'            => (int) $r->id,
                'lancamento_id' => (int) $r->lancamento_id,
                'data'          => $r->date,
                'documento'     => $r->document_ref ?: $r->ref,
                'descricao'     => $r->name ?: $r->narration,
                'diario'        => $r->diario,
                'conta'         => $r->conta,
                'debito'        => $debito,
                'credito'       => $credito,
                'saldo'         => $saldo,
            ];
        }

        $col = collect($movimentos);

        return [
            'terceiro'       => $this->terceiro($tenantId, $tipo, $terceiroId),
            'de'             => $de,
            'ate'            => $ate,
            'saldo_anterior' => $saldoAnterior,
            'movimentos'     => $movimentos,
            'totais'         => [
                'debito'      => round($col->sum('debito'), 2),
                'credito'     => round($col->sum('credito'), 2),
                'saldo_final' => $saldo,
            ],
        ];
    }

    /**
     * O saldo em aberto de cada terceiro, repartido por idade à data `$data`.
     *
     * FIFO: o que foi pago (recibos, notas de crédito…) abate primeiro às
     * dívidas mais antigas; o que sobra de cada documento fica no escalão da
     * sua idade. Se o terceiro pagou MAIS do que devia, o excesso não tem idade
     * — vai para `a_favor`.
     *
     * A idade conta-se desde a DATA DO DOCUMENTO (as linhas do lançamento não
     * guardam vencimento).
     */
    public function antiguidade(int $tenantId, string $tipo, ?string $data = null, ?int $terceiroId = null): array
    {
        $this->validarTipo($tipo);
        $data = $data ?: now()->toDateString();
        $hoje = CarbonImmutable::parse($data)->startOfDay();
        $cobraADebito = $tipo === 'client';

        $q = $this->linhas($tenantId, $tipo)
            ->where('m.date', '<=', $data)
            ->orderBy('l.partner_id')->orderBy('m.date')->orderBy('l.id')
            ->select(['l.partner_id', 'm.date', 'l.debit', 'l.credit']);

        if ($terceiroId) {
            $q->where('l.partner_id', $terceiroId);
        }

        $porTerceiro = [];
        foreach ($q->cursor() as $r) {
            $id = (int) $r->partner_id;
            $porTerceiro[$id] ??= ['dividas' => [], 'pago' => 0.0];

            $debito = (float) $r->debit;
            $credito = (float) $r->credit;
            $divida = $cobraADebito ? $debito : $credito;
            $pagamento = $cobraADebito ? $credito : $debito;

            if ($divida > 0) {
                $porTerceiro[$id]['dividas'][] = [$r->date, $divida];
            }
            $porTerceiro[$id]['pago'] += $pagamento;
        }

        $nomes = $this->nomes($tenantId, $tipo, array_keys($porTerceiro));
        $vazio = array_fill_keys(array_keys(self::ESCALOES), 0.0);

        $linhas = [];
        foreach ($porTerceiro as $id => $t) {
            $escaloes = $vazio;
            $pago = $t['pago'];

            foreach ($t['dividas'] as [$dataDoc, $valor]) {
                $abate = min($valor, $pago);
                $pago -= $abate;
                $emAberto = $valor - $abate;
                if ($emAberto < 0.005) {
                    continue;
                }
                $dias = max(0, (int) CarbonImmutable::parse($dataDoc)->startOfDay()->diffInDays($hoje, false));
                $escaloes[$this->escalao($dias)] += $emAberto;
            }

            $escaloes = array_map(fn ($v) => round($v, 2), $escaloes);
            $emDivida = round(array_sum($escaloes), 2);
            $aFavor = round($pago, 2);

            if ($emDivida < 0.005 && $aFavor < 0.005) {
                continue; // conta saldada: não entra no mapa
            }

            $linhas[] = [
                'id'       => $id,
                'nome'     => $nomes[$id]['nome'] ?? __('Terceiro #:id (apagado)', ['id' => $id]),
                'nif'      => $nomes[$id]['nif'] ?? null,
                'escaloes' => $escaloes,
                'total'    => $emDivida,
                'a_favor'  => $aFavor,
            ];
        }

        usort($linhas, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totais = $vazio;
        foreach ($linhas as $l) {
            foreach ($l['escaloes'] as $k => $v) {
                $totais[$k] += $v;
            }
        }

        return [
            'data'     => $data,
            'escaloes' => array_keys(self::ESCALOES),
            'linhas'   => $linhas,
            'totais'   => [
                'escaloes' => array_map(fn ($v) => round($v, 2), $totais),
                'total'    => round(array_sum($totais), 2),
                'a_favor'  => round(array_sum(array_column($linhas, 'a_favor')), 2),
            ],
        ];
    }

    // ─────────────────────────── Peças ───────────────────────────

    /** As linhas confirmadas de terceiros de um tipo, nesta empresa. */
    private function linhas(int $tenantId, string $tipo): Builder
    {
        return DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->where('l.partner_type', $tipo)
            ->whereNotNull('l.partner_id');
    }

    private function terceiro(int $tenantId, string $tipo, int $id): array
    {
        $p = DB::table($this->tabela($tipo))
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first(['id', 'name', 'nif', 'email', 'phone']);

        return [
            'id'       => $id,
            'tipo'     => $tipo,
            'nome'     => $p->name ?? __('Terceiro #:id (apagado)', ['id' => $id]),
            'nif'      => $p->nif ?? null,
            'email'    => $p->email ?? null,
            'telefone' => $p->phone ?? null,
        ];
    }

    /** @return array<int, array{nome: string, nif: ?string}> */
    private function nomes(int $tenantId, string $tipo, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return DB::table($this->tabela($tipo))
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'nif'])
            ->mapWithKeys(fn ($p) => [(int) $p->id => ['nome' => $p->name, 'nif' => $p->nif]])
            ->all();
    }

    private function escalao(int $dias): string
    {
        foreach (self::ESCALOES as $chave => [$min, $max]) {
            if ($dias >= $min && ($max === null || $dias <= $max)) {
                return $chave;
            }
        }

        return 'mais_180';
    }

    /** Cliente: débito − crédito. Fornecedor: crédito − débito. */
    private function sinal(string $tipo): int
    {
        return $tipo === 'client' ? 1 : -1;
    }

    private function tabela(string $tipo): string
    {
        return $tipo === 'client' ? 'invoicing_clients' : 'invoicing_suppliers';
    }

    private function validarTipo(string $tipo): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException("Tipo de terceiro desconhecido: {$tipo}");
        }
    }
}
