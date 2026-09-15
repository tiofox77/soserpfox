<?php

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Events\Event;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Support\PortalDoCliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O PORTAL DO CLIENTE — o que um cliente da empresa vê quando entra.
 *
 * Eram sete componentes Livewire no guard `client`. O que muda:
 *
 *  · O RASCUNHO NÃO APARECE. Uma factura ou proforma em rascunho ainda não foi
 *    emitida a ninguém; o portal mostrava-a ao cliente e contava-a como dívida
 *    no «saldo devedor».
 *  · O SALDO É O MESMO DA EMPRESA. «Por receber» de uma factura segue a regra do
 *    `SalesInvoiceResource`: uma FR está paga por definição, e o que está pago,
 *    anulado ou creditado não deve nada. O extracto chamava «pago» só ao estado
 *    `paid` e às parciais, e uma FR aparecia como nada recebido.
 *  · A EMPRESA É A DO CLIENTE, escrita na consulta. O escopo de empresa segue o
 *    utilizador do guard `web`; num browser com as duas sessões abertas, as
 *    listas do cliente filtravam pela empresa do utilizador.
 */
class PortalDoClienteApiController extends Controller
{
    private const SEM_DIVIDA = ['draft', 'paid', 'cancelled', 'credited'];

    public function painel(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        $facturas = $this->facturasDe($cliente)->get(['id', 'invoice_number', 'invoice_type', 'invoice_date', 'due_date', 'total', 'paid_amount', 'status']);

        $emAberto = $facturas->filter(fn ($f) => $this->porReceber($f) > 0.009);
        $validas = $facturas->reject(fn ($f) => in_array($f->status, ['cancelled', 'credited'], true));

        $seccoes = PortalDoCliente::doCliente($cliente);
        $veEventos = in_array('eventos', $seccoes, true);
        $veOficina = in_array('oficina', $seccoes, true);

        return response()->json([
            'cliente' => ['nome' => $cliente->name],
            // As áreas deste cliente — o ecrã só desenha os cartões das que ele vê.
            'seccoes' => $seccoes,
            've_facturas' => PortalDoCliente::veFacturas($cliente),
            'oficina' => $veOficina ? \App\Http\Controllers\Api\Cliente\PortalDaOficinaApiController::resumo($cliente) : null,
            'numeros' => [
                'facturas' => $facturas->count(),
                'pendentes' => $emAberto->count(),
                'pagas' => $validas->filter(fn ($f) => $this->porReceber($f) <= 0.009)->count(),
                'facturado' => round((float) $validas->sum('total'), 2),
                'eventos' => $veEventos ? $this->eventosDe($cliente)->count() : 0,
                'proximos_eventos' => $veEventos ? $this->eventosDe($cliente)->where('start_date', '>=', now())->count() : 0,
            ],
            'ultimas_facturas' => $facturas->sortByDesc('invoice_date')->take(5)->values()->map(fn ($f) => $this->linhaDeFactura($f)),
            'proximos_eventos' => ! $veEventos ? [] : $this->eventosDe($cliente)
                ->where('start_date', '>=', now())
                ->with(['venue:id,name', 'type:id,name'])
                ->orderBy('start_date')
                ->limit(3)
                ->get()
                ->map(fn (Event $e) => $this->linhaDeEvento($e)),
        ]);
    }

    public function extrato(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        $f = $request->validate([
            'periodo' => ['nullable', 'in:all,month,quarter,year'],
            'estado' => ['nullable', 'in:pending,sent,partially_paid,paid,overdue,cancelled,credited'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $periodo = $f['periodo'] ?? 'all';

        $consulta = $this->facturasDe($cliente)
            ->when($periodo === 'month', fn ($q) => $q->whereMonth('invoice_date', now()->month)->whereYear('invoice_date', now()->year))
            ->when($periodo === 'quarter', fn ($q) => $q->whereBetween('invoice_date', [now()->startOfQuarter(), now()->endOfQuarter()]))
            ->when($periodo === 'year', fn ($q) => $q->whereYear('invoice_date', now()->year));

        $this->filtrarEstado($consulta, $f['estado'] ?? null);

        $pagina = $consulta->orderByDesc('invoice_date')->orderByDesc('id')->paginate(20, ['*'], 'pagina', $f['pagina'] ?? 1);

        $todas = $this->facturasDe($cliente)->get(['id', 'invoice_type', 'invoice_date', 'due_date', 'total', 'paid_amount', 'status']);
        $validas = $todas->reject(fn ($x) => in_array($x->status, ['cancelled', 'credited'], true));
        $emAberto = $todas->filter(fn ($x) => $this->porReceber($x) > 0.009);
        $atrasadas = $emAberto->filter(fn ($x) => $x->status === 'overdue' || ($x->due_date && $x->due_date->isPast()));
        $parciais = $emAberto->filter(fn ($x) => (float) $x->paid_amount > 0.009);

        $meses = collect(range(5, 0))->map(function ($i) use ($validas) {
            $mes = now()->startOfMonth()->subMonths($i);
            $doMes = $validas->filter(fn ($x) => $x->invoice_date && $x->invoice_date->isSameMonth($mes));

            return [
                'mes' => $mes->format('Y-m'),
                'recebido' => round((float) $doMes->sum(fn ($x) => (float) $x->total - $this->porReceber($x)), 2),
                'em_aberto' => round((float) $doMes->sum(fn ($x) => $this->porReceber($x)), 2),
            ];
        });

        return response()->json([
            'numeros' => [
                'saldo_devedor' => round((float) $emAberto->sum(fn ($x) => $this->porReceber($x)), 2),
                'atrasadas' => $atrasadas->count(),
                'valor_atrasado' => round((float) $atrasadas->sum(fn ($x) => $this->porReceber($x)), 2),
                'parciais_total' => round((float) $parciais->sum('total'), 2),
                'parciais_pago' => round((float) $parciais->sum('paid_amount'), 2),
                'recebido' => round((float) $validas->sum(fn ($x) => (float) $x->total - $this->porReceber($x)), 2),
                'facturado' => round((float) $validas->sum('total'), 2),
            ],
            'meses' => $meses,
            'facturas' => collect($pagina->items())->map(fn ($x) => $this->linhaDeFactura($x)),
            'paginacao' => $this->paginacao($pagina),
        ]);
    }

    public function facturas(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'in:pending,sent,partially_paid,paid,overdue,cancelled,credited'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $consulta = $this->facturasDe($cliente)
            ->when(filled($f['procura'] ?? null), fn ($q) => $q->where('invoice_number', 'like', '%'.trim($f['procura']).'%'));

        $this->filtrarEstado($consulta, $f['estado'] ?? null);

        $pagina = $consulta->orderByDesc('invoice_date')->orderByDesc('id')->paginate(15, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'facturas' => collect($pagina->items())->map(fn ($x) => $this->linhaDeFactura($x)),
            'paginacao' => $this->paginacao($pagina),
        ]);
    }

    public function proformas(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'in:sent,accepted,rejected,expired,converted'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = fn () => SalesProforma::withoutGlobalScope('tenant')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('client_id', $cliente->id)
            ->where('status', '!=', 'draft');

        $pagina = $base()
            ->when(filled($f['procura'] ?? null), fn ($q) => $q->where('proforma_number', 'like', '%'.trim($f['procura']).'%'))
            ->when(filled($f['estado'] ?? null), fn ($q) => $q->where('status', $f['estado']))
            ->orderByDesc('proforma_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'numeros' => [
                'total' => $base()->count(),
                'convertidas' => $base()->where('status', 'converted')->count(),
                'em_aberto' => $base()->whereNotIn('status', ['converted', 'cancelled', 'rejected', 'expired'])->count(),
            ],
            'proformas' => collect($pagina->items())->map(fn (SalesProforma $p) => [
                'id' => $p->id,
                'numero' => $p->proforma_number,
                'data' => $p->proforma_date?->toDateString(),
                'valida_ate' => $p->valid_until?->toDateString(),
                'expirada' => $p->valid_until && $p->valid_until->isPast() && ! in_array($p->status, ['converted', 'rejected'], true),
                'total' => (float) $p->total,
                'estado' => $p->status,
            ]),
            'paginacao' => $this->paginacao($pagina),
        ]);
    }

    public function eventos(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:orcamento,confirmado,em_montagem,em_andamento,concluido,cancelado'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $termo = trim((string) ($f['procura'] ?? ''));

        $pagina = $this->eventosDe($cliente)
            ->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w->where('event_number', 'like', "%{$termo}%")->orWhere('name', 'like', "%{$termo}%")))
            ->when(filled($f['estado'] ?? null), fn ($q) => $q->where('status', $f['estado']))
            ->with(['venue:id,name', 'type:id,name'])
            ->orderByDesc('start_date')
            ->paginate(10, ['*'], 'pagina', $f['pagina'] ?? 1);

        $contagens = $this->eventosDe($cliente)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'numeros' => [
                'total' => (int) $contagens->sum(),
                'confirmados' => (int) ($contagens['confirmado'] ?? 0),
                'em_andamento' => (int) ($contagens['em_andamento'] ?? 0),
                'concluidos' => (int) ($contagens['concluido'] ?? 0),
            ],
            'eventos' => collect($pagina->items())->map(fn (Event $e) => $this->linhaDeEvento($e)),
            'paginacao' => $this->paginacao($pagina),
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        $c = $this->cliente($request);

        return response()->json(['perfil' => ['name' => $c->name, 'email' => $c->email, 'phone' => (string) $c->phone]]);
    }

    public function guardarPerfil(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('invoicing_clients', 'email')->ignore($cliente->id)->where(fn ($q) => $q->where('tenant_id', $cliente->tenant_id)),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $cliente->update($dados);

        return response()->json(['message' => __('Perfil atualizado com sucesso!')]);
    }

    public function mudarSenha(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);

        $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($request->input('current_password'), $cliente->password)) {
            throw ValidationException::withMessages(['current_password' => __('Senha atual incorreta')]);
        }

        $cliente->update(['password' => Hash::make($request->input('new_password')), 'password_changed_at' => now()]);

        return response()->json(['message' => __('Senha alterada com sucesso!')]);
    }

    private function cliente(Request $request): Client
    {
        return $request->user('client');
    }

    /**
     * As facturas do cliente, na empresa dele, sem rascunhos — e só as das
     * áreas que ele vê: sem «Facturas e extracto», só as que saíram dos
     * módulos marcados (a oficina, o hotel, o salão).
     */
    public static function facturasDe(Client $cliente): Builder
    {
        $consulta = SalesInvoice::withoutGlobalScope('tenant')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('client_id', $cliente->id)
            ->where('status', '!=', 'draft');

        if (! PortalDoCliente::veFacturas($cliente)) {
            return $consulta->whereRaw('1 = 0');
        }

        $origens = PortalDoCliente::origensDasFacturas($cliente);

        return $origens === null ? $consulta : $consulta->whereIn('source_module', $origens);
    }

    private function eventosDe(Client $cliente): Builder
    {
        return Event::withoutGlobalScope('tenant')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('client_id', $cliente->id);
    }

    /** O estado «atrasada» é o nome ou o vencimento passado com saldo em aberto. */
    private function filtrarEstado(Builder $consulta, ?string $estado): void
    {
        if (! $estado) {
            return;
        }

        if ($estado !== 'overdue') {
            $consulta->where('status', $estado);

            return;
        }

        $t = (new SalesInvoice())->getTable();
        $falta = \App\Services\Invoicing\SomasDasFacturas::sqlPorReceber($t);

        $consulta->whereRaw("({$falta}) > 0")
            ->where(fn ($w) => $w->where('status', 'overdue')->orWhereDate('due_date', '<', today()));
    }

    /** O `SalesInvoiceResource::porReceber()`, linha a linha. */
    private function porReceber(SalesInvoice $f): float
    {
        if (($f->invoice_type ?? 'FT') === 'FR' || in_array($f->status, self::SEM_DIVIDA, true)) {
            return 0.0;
        }

        return max(round((float) $f->total - (float) $f->paid_amount, 2), 0.0);
    }

    private function linhaDeFactura(SalesInvoice $f): array
    {
        $falta = $this->porReceber($f);

        return [
            'id' => $f->id,
            'numero' => $f->invoice_number,
            'data' => $f->invoice_date?->toDateString(),
            'vencimento' => $f->due_date?->toDateString(),
            'total' => (float) $f->total,
            'pago' => round((float) $f->total - $falta, 2),
            'saldo' => $falta,
            'estado' => $f->status,
            'estado_rotulo' => $f->status_label,
            'atrasada' => $falta > 0.009 && ($f->status === 'overdue' || ($f->due_date && $f->due_date->isPast())),
        ];
    }

    private function linhaDeEvento(Event $e): array
    {
        return [
            'id' => $e->id,
            'numero' => $e->event_number,
            'nome' => $e->name,
            'descricao' => $e->description,
            'inicio' => $e->start_date?->toIso8601String(),
            'fim' => $e->end_date?->toIso8601String(),
            'local' => $e->venue?->name,
            'tipo' => $e->type?->name,
            'participantes' => $e->expected_attendees,
            'estado' => $e->status,
            'estado_rotulo' => __((string) $e->status_label),
            'fase' => $e->phase,
            'fase_rotulo' => $e->phase ? __((string) $e->phase_label) : null,
            'fase_icone' => $e->phase ? $e->phase_icon : null,
            'progresso' => (int) $e->checklist_progress,
            'valor' => $e->total_value !== null ? (float) $e->total_value : null,
        ];
    }

    private function paginacao($pagina): array
    {
        return ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()];
    }
}
