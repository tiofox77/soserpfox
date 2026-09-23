<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Services\Invoicing\SomasDasFacturas;
use App\Services\Treasury\TreasuryMovementService;
use App\Support\CategoriasDeTesouraria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DA TESOURARIA — onde está o dinheiro, e o que lhe aconteceu.
 *
 * Só lê. Nada aqui escreve, e por isso basta a permissão de VER movimentos —
 * a mesma que abre o extracto.
 *
 * O QUE ESTE PAINEL DIZ E OS OUTROS NÃO: facturar não é receber. Ao lado do
 * dinheiro que entrou e saiu está o volume facturado e o que dele ficou por
 * cobrar — é a ponte entre os documentos e a conta bancária.
 *
 * E DUAS COISAS QUE PRECISAM DE CONSERTO, quando as há: os movimentos que não
 * caíram em conta nem caixa nenhum (o dinheiro existe e não se sabe onde) e
 * as formas de pagamento sem destino configurado, que são a causa dos
 * primeiros. O ecrã leva a quem os arranja.
 */
class PainelApiController extends Controller
{
    private const PERIODOS = ['today', 'week', 'month', 'year'];

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('treasury.transactions.view'), 403, __('Sem permissão para esta operação.'));

        $d = $request->validate([
            'periodo' => ['nullable', 'in:' . implode(',', self::PERIODOS)],
        ]);

        $tenantId = (int) activeTenantId();
        $periodo = $d['periodo'] ?? 'today';
        [$de, $ate] = $this->intervalo($periodo);

        /*
         * O DINHEIRO QUE EXISTE, agora. Não segue o período: um saldo é o que
         * está lá hoje, não o que lá esteve numa semana escolhida.
         */
        $emCaixas = (float) CashRegister::where('tenant_id', $tenantId)->where('is_active', true)->sum('current_balance');
        $emContas = (float) Account::where('tenant_id', $tenantId)->where('is_active', true)->sum('current_balance');

        // O QUE ENTROU E SAIU no período — as duas somas numa consulta só.
        $movimento = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->foraDasInternas()
            ->whereBetween('transaction_date', [$de, $ate])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) as entradas")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) as saidas")
            ->first();

        $entradas = (float) ($movimento->entradas ?? 0);
        $saidas = (float) ($movimento->saidas ?? 0);

        return response()->json([
            'periodo' => $periodo,
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),

            'saldos' => [
                'caixas' => round($emCaixas, 2),
                'contas' => round($emContas, 2),
                'total' => round($emCaixas + $emContas, 2),
            ],

            'movimento' => [
                'entradas' => round($entradas, 2),
                'saidas' => round($saidas, 2),
                'saldo' => round($entradas - $saidas, 2),
            ],

            'facturacao' => $this->facturacao($tenantId, $de, $ate),
            'por_consertar' => $this->porConsertar($tenantId),
            'grafico' => $this->seteDias($tenantId),
            'categorias' => [
                'entradas' => $this->topCategorias($tenantId, 'income', $de, $ate),
                'saidas' => $this->topCategorias($tenantId, 'expense', $de, $ate),
            ],
            'recentes' => $this->recentes($tenantId),
            'caixas' => CashRegister::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderByDesc('current_balance')->get(['id', 'name', 'status', 'current_balance'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'nome' => $c->name, 'estado' => $c->status,
                    'saldo' => (float) $c->current_balance,
                ])->values(),
            'contas' => Account::where('tenant_id', $tenantId)->where('is_active', true)->with('bank')
                ->orderByDesc('current_balance')->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'nome' => $c->account_name, 'banco' => $c->bank->name ?? null,
                    'numero' => $c->account_number, 'saldo' => (float) $c->current_balance,
                ])->values(),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} */
    private function intervalo(string $periodo): array
    {
        return match ($periodo) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * FACTURAR NÃO É RECEBER.
     *
     * O que se facturou, o que disso já entrou, e o que falta. E do outro
     * lado o mesmo: o que se comprou e o que falta pagar.
     *
     * O rascunho fica de fora dos dois lados — um documento por acabar não é
     * receita nem é dívida.
     *
     * @return array<string, float>
     */
    private function facturacao(int $tenantId, $de, $ate): array
    {
        $vendas = SalesInvoice::where('tenant_id', $tenantId)
            ->where('invoice_status', 'F')
            ->whereBetween('invoice_date', [$de, $ate]);

        $compras = PurchaseInvoice::where('tenant_id', $tenantId)
            ->whereNotIn('status', SomasDasFacturas::SEM_NADA_A_RECEBER)
            ->whereBetween('invoice_date', [$de, $ate]);

        $facturado = (float) (clone $vendas)->sum('total');
        $cobrado = (float) (clone $vendas)->sum('paid_amount');
        $comprado = (float) (clone $compras)->sum('total');
        $pago = (float) (clone $compras)->sum('paid_amount');

        return [
            'facturado' => round($facturado, 2),
            'cobrado' => round($cobrado, 2),
            'a_receber' => round(max(0, $facturado - $cobrado), 2),
            'comprado' => round($comprado, 2),
            'pago' => round($pago, 2),
            'a_pagar' => round(max(0, $comprado - $pago), 2),
        ];
    }

    /**
     * O QUE PRECISA DE CONSERTO — e onde se conserta.
     *
     * Um movimento sem conta e sem caixa é dinheiro registado que não mexeu
     * saldo nenhum; acontece quando a forma de pagamento não tem destino
     * configurado. Os dois números andam juntos, e o segundo é a causa.
     *
     * @return array<string, mixed>
     */
    private function porConsertar(int $tenantId): array
    {
        return [
            'movimentos_sem_destino' => Transaction::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereNull('account_id')->whereNull('cash_register_id')->count(),

            // O «Dinheiro» sem caixa por omissão só conta quando a empresa não
            // tem caixa NENHUMA. Com uma caixa por operador, deixá-lo vazio é a
            // configuração certa (o numerário vai para a caixa de quem vendeu)
            // e o painel dizia que faltava configurar — ver
            // TreasuryMovementService::haCaixaActiva.
            'formas_sem_destino' => PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)
                ->where(function ($q) use ($tenantId) {
                    $q->when(
                        ! TreasuryMovementService::haCaixaActiva($tenantId),
                        fn ($q) => $q->orWhere(fn ($caixa) => $caixa->where('type', 'cash')->whereNull('default_cash_register_id')),
                    )->orWhere(fn ($banco) => $banco->where('type', '!=', 'cash')->whereNull('default_account_id'));
                })->count(),
        ];
    }

    /**
     * OS ÚLTIMOS SETE DIAS, numa consulta só.
     *
     * Eram catorze — uma por dia e por sentido, dentro de um ciclo, no painel
     * que toda a gente abre primeiro.
     *
     * Os dias sem movimento vão a ZERO e não desaparecem: uma linha que salta
     * o domingo fechado dá-lhe o valor de segunda.
     *
     * @return array<string, mixed>
     */
    private function seteDias(int $tenantId): array
    {
        $desde = now()->subDays(6)->startOfDay();

        $linhas = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('type', ['income', 'expense'])
            ->foraDasInternas()
            ->where('transaction_date', '>=', $desde)
            ->groupBy('dia', 'type')
            ->selectRaw('DATE(transaction_date) as dia, type, SUM(amount) as total')
            ->get()
            ->keyBy(fn ($l) => $l->dia . '|' . $l->type);

        $dias = [];
        $entradas = [];
        $saidas = [];

        for ($i = 6; $i >= 0; $i--) {
            $data = now()->subDays($i);
            $chave = $data->format('Y-m-d');

            $dias[] = $data->format('d/m');
            $entradas[] = (float) ($linhas[$chave . '|income']->total ?? 0);
            $saidas[] = (float) ($linhas[$chave . '|expense']->total ?? 0);
        }

        return ['dias' => $dias, 'entradas' => $entradas, 'saidas' => $saidas];
    }

    /** @return array<int, array{rotulo: string, valor: float}> */
    private function topCategorias(int $tenantId, string $tipo, $de, $ate): array
    {
        return Transaction::where('tenant_id', $tenantId)
            ->where('type', $tipo)
            ->where('status', 'completed')
            ->foraDasInternas()
            ->whereBetween('transaction_date', [$de, $ate])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'rotulo' => CategoriasDeTesouraria::nome($l->category),
                'valor' => (float) $l->total,
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function recentes(int $tenantId): array
    {
        return Transaction::where('tenant_id', $tenantId)
            ->with(['paymentMethod', 'account', 'cashRegister'])
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->limit(10)->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'numero' => $t->transaction_number,
                'data' => $t->transaction_date?->format('d/m/Y'),
                'descricao' => $t->description,
                'tipo' => $t->type,
                'valor' => (float) $t->amount,
                'moeda' => $t->currency,
                'estado' => $t->status,
                'forma_de_pagamento' => $t->paymentMethod->name ?? null,
                'destino' => $t->cashRegister->name ?? $t->account->account_name ?? null,
            ])->values()->all();
    }
}
