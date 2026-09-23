<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\PosShift;
use App\Models\User;
use App\Services\POS\ProdutosDoTurno;
use App\Services\POS\TurnosDoPos;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS TURNOS DO POS, para os ecrãs em React: o do balcão (abrir e fechar o
 * turno próprio) e o histórico. Tudo pela `TurnosDoPos`, a mesma que os
 * ecrãs Livewire usam.
 */
class TurnosApiController extends Controller
{
    public function estado(Request $request): JsonResponse
    {
        $t = $this->turnos($request);

        return response()->json([
            'turno' => ($turno = $t->actual()) ? $this->turno($turno, true) : null,
            // O último fecho deste operador, para reimprimir o talão/PDF sem ir ao histórico.
            'ultimo_fechado' => ($ultimo = $t->ultimoFechado()) ? $this->turno($ultimo, true) : null,
            'caixa' => ($caixa = $t->caixaAtribuida()) ? ['id' => $caixa->id, 'nome' => $caixa->name, 'estado' => $caixa->status] : null,
            'pode_ver_todos' => $this->veTodos($request),
        ]);
    }

    public function abrir(Request $request): JsonResponse
    {
        $d = $request->validate(TurnosDoPos::regrasDeAbertura(), TurnosDoPos::mensagensDeAbertura());

        try {
            $turno = $this->turnos($request)->abrir((float) $d['opening_balance'], $d['opening_notes'] ?? null, $request->ip());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['turno' => $this->turno($turno->load('transactions'), true), 'message' => __('Turno aberto com sucesso!')], 201);
    }

    public function fechar(Request $request): JsonResponse
    {
        $d = $request->validate(TurnosDoPos::regrasDeFecho(), TurnosDoPos::mensagensDeFecho());

        try {
            $turno = $this->turnos($request)->fechar((float) $d['actual_cash'], $d['closing_notes'] ?? null, $d['difference_reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['turno' => $this->turno($turno, true), 'message' => __('Turno fechado com sucesso!')]);
    }

    public function historico(Request $request): JsonResponse
    {
        $f = $request->validate([
            'dateFrom' => ['nullable', 'date'], 'dateTo' => ['nullable', 'date'],
            'userId' => ['nullable', 'integer'], 'status' => ['nullable', 'in:open,closed'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $veTodos = $this->veTodos($request);

        $pagina = $this->turnos($request)->historico($f, $veTodos)->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (PosShift $s) => $this->turno($s))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
            // A lista de nomes é ela própria informação: quem só vê os seus
            // turnos não precisa da lista de colegas nem do filtro.
            //
            // AS PESSOAS DA EMPRESA pela `tenant_user`, e não por `users.tenant_id`
            // (22/09/2026): esse é o da empresa onde a pessoa se registou, e um
            // operador convidado de outra empresa não aparecia no filtro. Com
            // quem já saiu incluído — os turnos dele continuam no histórico.
            'utilizadores' => $veTodos
                ? User::whereIn('id', \Illuminate\Support\Facades\DB::table('tenant_user')->where('tenant_id', activeTenantId())->select('user_id'))
                    ->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'nome' => $u->name])->values()
                : [],
            'pode_ver_todos' => $veTodos,
        ]);
    }

    public function mostrar(Request $request, int $id): JsonResponse
    {
        $turno = $this->turnos($request)->turno($id, $this->veTodos($request));
        abort_unless($turno, 404, __('Turno não encontrado ou sem acesso.'));

        return response()->json(['turno' => $this->turno($turno, true)]);
    }

    /** O FECHO COM PRODUTOS: o que se vendeu artigo a artigo, os totais e os documentos. */
    public function produtos(Request $request, int $id): JsonResponse
    {
        $turno = $this->turnos($request)->turno($id, $this->veTodos($request));
        abort_unless($turno, 404, __('Turno não encontrado ou sem acesso.'));

        return response()->json(ProdutosDoTurno::de($turno));
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function turnos(Request $request): TurnosDoPos
    {
        return new TurnosDoPos((int) activeTenantId(), (int) $request->user()->id);
    }

    /** Sem o direito de ver todos, fica preso aos seus. */
    private function veTodos(Request $request): bool
    {
        return (bool) $request->user()?->can('invoicing.pos.reports.all');
    }

    private function turno(PosShift $s, bool $comMovimentos = false): array
    {
        $linha = [
            'id' => $s->id,
            'shift_number' => $s->shift_number,
            'status' => $s->status,
            'status_label' => $s->status_label,
            'operador' => $s->user?->name,
            'fechado_por' => $s->closedBy?->name,
            'opened_at' => optional($s->opened_at)->format('d/m/Y H:i'),
            'closed_at' => optional($s->closed_at)->format('d/m/Y H:i'),
            'duration' => $s->duration,
            'opening_balance' => (float) $s->opening_balance,
            'opening_notes' => $s->opening_notes,
            'cash_sales' => (float) $s->cash_sales,
            'card_sales' => (float) $s->card_sales,
            'bank_transfer_sales' => (float) $s->bank_transfer_sales,
            'other_sales' => (float) $s->other_sales,
            'total_sales' => (float) $s->total_sales,
            /*
             * BRUTO, DEVOLVIDO E LÍQUIDO — três números e não um.
             *
             * O turno só sabia de facturas: 1177 movimentos gravados, todos
             * `invoice`, e nem um a dizer que saiu dinheiro. Um turno de
             * 150.000 com 50.000 devolvidos lia-se igual a um de 150.000 sem
             * devolução nenhuma.
             */
            'credit_notes_amount' => (float) $s->credit_notes_amount,
            'net_sales' => $s->net_sales,
            'total_invoices' => (int) $s->total_invoices,
            'total_receipts' => (int) $s->total_receipts,
            'total_credit_notes' => (int) $s->total_credit_notes,
            // O esperado em caixa: o fundo, mais o que entrou em dinheiro, mais
            // as entradas e menos as saídas da gaveta pela tesouraria (23/09).
            'expected_cash' => $s->status === 'closed' ? (float) $s->expected_cash : $s->dinheiroEsperado(),
            'saidas_da_gaveta' => ($g = $s->movimentosDaGaveta())['saidas'],
            'entradas_na_gaveta' => $g['entradas'],
            'actual_cash' => $s->actual_cash !== null ? (float) $s->actual_cash : null,
            'cash_difference' => $s->cash_difference !== null ? (float) $s->cash_difference : null,
            'closing_notes' => $s->closing_notes,
            'difference_reason' => $s->difference_reason,
            // O resumido e o com produtos: o mesmo documento, com e sem a
            // lista dos artigos e dos documentos.
            'exportar' => [
                'pdf' => route('invoicing.pos.export.shift-pdf', $s->id),
                'talao' => route('invoicing.pos.export.shift-ticket', $s->id),
                'pdf_produtos' => route('invoicing.pos.export.shift-pdf', [$s->id, 'detalhe' => 'produtos']),
                'talao_produtos' => route('invoicing.pos.export.shift-ticket', [$s->id, 'detalhe' => 'produtos']),
            ],
        ];

        if ($comMovimentos) {
            $linha['movimentos'] = $s->transactions->sortByDesc('created_at')->values()->map(fn ($m) => [
                'id' => $m->id,
                'quando' => optional($m->created_at)->format('d/m/Y H:i'),
                'tipo' => $m->type,
                'tipo_rotulo' => $m->type_label,
                'meio' => $m->payment_method_label,
                'amount' => (float) $m->amount,
                'reference_number' => $m->reference_number,
                'description' => $m->description,
            ])->all();
            $linha['movimentos_n'] = $s->transactions->count();
        }

        return $linha;
    }
}
