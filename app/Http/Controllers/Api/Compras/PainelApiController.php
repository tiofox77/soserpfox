<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Encomenda;
use App\Models\Compras\Requisicao;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O PAINEL DAS COMPRAS — o que está parado à minha espera.
 *
 * A pergunta a que este ecrã responde NÃO é «quanto comprámos»: é «o que é que
 * está à espera de mim». Requisições por decidir, encomendas atrasadas,
 * mercadoria recebida ainda por facturar. Um painel de compras que só some
 * valores serve para o relatório do fim do mês e para mais nada.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('compras.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = activeTenantId();
        $abertas = fn () => Encomenda::forTenant()->whereIn('estado', Encomenda::ABERTOS);

        return response()->json([
            'resumo' => [
                'por_decidir' => Requisicao::forTenant()->where('estado', 'submetida')->count(),
                'por_encomendar' => Requisicao::forTenant()->where('estado', 'aprovada')->count(),
                'em_curso' => $abertas()->count(),
                'atrasadas' => $abertas()->whereNotNull('entrega_prevista')
                    ->whereDate('entrega_prevista', '<', today())->count(),
                'por_facturar' => Encomenda::forTenant()
                    ->whereIn('estado', ['parcial', 'recebida'])->whereNull('purchase_invoice_id')->count(),
                'valor_em_curso' => (float) $abertas()->sum('total'),
            ],
            'a_decidir' => Requisicao::forTenant()->where('estado', 'submetida')
                ->with(['autor:id,name'])->withCount('itens')->latest()->limit(6)->get()
                ->map(fn (Requisicao $r) => [
                    'id' => $r->id,
                    'numero' => $r->numero,
                    'autor' => $r->autor?->name,
                    'linhas' => (int) $r->itens_count,
                    'necessaria_em' => $r->necessaria_em?->format('Y-m-d'),
                    'justificacao' => $r->justificacao,
                    'quando' => $r->created_at?->format('Y-m-d'),
                ])->values(),
            'atrasadas' => $abertas()->whereNotNull('entrega_prevista')
                ->whereDate('entrega_prevista', '<', today())
                ->with('fornecedor:id,name')->orderBy('entrega_prevista')->limit(6)->get()
                ->map(fn (Encomenda $e) => [
                    'id' => $e->id,
                    'numero' => $e->numero,
                    'fornecedor' => $e->fornecedor?->name,
                    'prometida' => $e->entrega_prevista?->format('Y-m-d'),
                    'dias' => (int) today()->diffInDays($e->entrega_prevista),
                    'total' => (float) $e->total,
                ])->values(),
            'por_mes' => $this->porMes(),
            'fornecedores' => $this->topFornecedores($tenantId),
        ]);
    }

    /**
     * O gasto encomendado dos últimos seis meses.
     *
     * Os meses sem encomendas ficam a ZERO: o SQL só devolve os que existem, e
     * uma linha que salte um mês encosta Março a Maio e inventa uma subida.
     */
    private function porMes(): array
    {
        $meses = collect(range(5, 0))->map(fn ($atras) => now()->copy()->subMonths($atras)->startOfMonth());

        $porMes = Encomenda::forTenant()
            ->whereNotIn('estado', ['rascunho', 'cancelada'])
            ->where('data_encomenda', '>=', $meses->first())
            ->selectRaw("DATE_FORMAT(data_encomenda, '%Y-%m') m, SUM(total) t")
            ->groupBy('m')->pluck('t', 'm');

        return [
            'etiquetas' => $meses->map(fn (Carbon $m) => $m->format('m/Y'))->all(),
            'valores' => $meses->map(fn (Carbon $m) => (float) ($porMes[$m->format('Y-m')] ?? 0))->all(),
        ];
    }

    /** Quem nos vende mais, no ano corrente. */
    private function topFornecedores(int $tenantId): array
    {
        $linhas = Encomenda::forTenant()
            ->whereNotIn('estado', ['rascunho', 'cancelada'])
            ->whereYear('data_encomenda', now()->year)
            ->join('invoicing_suppliers as s', 's.id', '=', 'compras_encomendas.supplier_id')
            ->selectRaw('s.name nome, SUM(compras_encomendas.total) valor, COUNT(*) quantas')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('valor')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (float) $l->valor)->all(),
            'quantas' => $linhas->map(fn ($l) => (int) $l->quantas)->all(),
        ];
    }
}
