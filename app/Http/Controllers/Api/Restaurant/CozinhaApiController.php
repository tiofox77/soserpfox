<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\KitchenTicket;
use App\Models\Restaurant\RestaurantSettings;
use App\Services\Restaurant\RestaurantKitchenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * O ECRÃ DA COZINHA — os bilhetes, por posto e por ordem de chegada.
 *
 * O REFRESCO NÃO VEM DAQUI. Quem diz «mudou alguma coisa?» é o
 * `PulsoDaCozinhaController`, uma agregação barata que o ecrã pergunta de três
 * em três segundos; esta lista — com bilhetes, artigos, comanda, mesa e posto
 * — só se vai buscar quando a resposta do pulso muda. Refazê-la em ciclo era
 * o que o ecrã em Blade fazia de quinze em quinze segundos, numa cozinha
 * parada às três da tarde.
 *
 * O TECTO DE 50 BILHETES é de segurança: um serviço muito cheio não pode
 * arrastar centenas de bilhetes a cada mudança.
 */
class CozinhaApiController extends Controller
{
    /** O caminho de um bilhete, do princípio ao fim. */
    private const SEGUINTE = [
        'queued' => 'accepted',
        'accepted' => 'preparing',
        'preparing' => 'ready',
        'ready' => 'served',
    ];

    private const ESTADOS = [
        'queued' => 'Na fila',
        'accepted' => 'Aceite',
        'preparing' => 'Em preparação',
        'ready' => 'Pronto',
        'served' => 'Servido',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.kitchen.view');

        $tenantId = activeTenantId();

        return response()->json([
            'postos' => KitchenStation::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code'])
                ->map(fn (KitchenStation $p) => ['valor' => (string) $p->id, 'rotulo' => $p->name])->values(),
            'estados' => collect(self::ESTADOS)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            // O valor de arranque da impressão automática; cada aparelho
            // decide o seu por cima, porque a impressora está num posto só.
            'impressao_automatica' => (bool) (RestaurantSettings::forTenant($tenantId)->kitchen_auto_print ?? false),
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('restaurant.kitchen.manage')],
        ]);
    }

    public function bilhetes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.kitchen.view');

        $tenantId = activeTenantId();

        $bilhetes = KitchenTicket::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->when($request->integer('posto'), fn ($q, $id) => $q->where('station_id', $id))
            ->whereIn('status', ['queued', 'accepted', 'preparing', 'ready'])
            ->with(['station:id,name', 'order:id,order_number,table_id,channel,guest_count', 'order.table:id,name,code', 'items.orderItem:id,product_name,quantity,unit,notes,kitchen_status'])
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $bilhetes->map(fn (KitchenTicket $b) => [
                'id' => $b->id,
                'numero' => $b->ticket_number,
                'posto' => $b->station?->name,
                'estado' => $b->status,
                'estado_rotulo' => __(self::ESTADOS[$b->status] ?? $b->status),
                'seguinte' => self::SEGUINTE[$b->status] ?? null,
                'seguinte_rotulo' => isset(self::SEGUINTE[$b->status])
                    ? __(self::ESTADOS[self::SEGUINTE[$b->status]]) : null,
                'prioridade' => (int) $b->priority,
                'comanda' => $b->order?->order_number,
                'mesa' => $b->order?->table?->name ?? $b->order?->table?->code,
                'canal' => $b->order?->channel,
                'pessoas' => (int) ($b->order?->guest_count ?? 0),
                'na_fila_desde' => $b->queued_at?->toIso8601String(),
                // Os minutos à espera são o que decide a ordem de trabalho —
                // e o que se vê de longe, sem ler a hora e fazer a conta.
                'minutos' => $b->queued_at ? (int) floor($b->queued_at->diffInMinutes(now())) : 0,
                'artigos' => $b->items->map(fn ($l) => [
                    'id' => $l->id,
                    'nome' => $l->orderItem?->product_name ?? '—',
                    'quantidade' => (float) ($l->orderItem?->quantity ?? 0),
                    'unidade' => $l->orderItem?->unit,
                    'observacoes' => $l->orderItem?->notes,
                    'estado' => $l->status,
                ])->values(),
            ])->values(),
        ]);
    }

    public function avancar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.kitchen.manage');

        $tenantId = activeTenantId();
        $bilhete = KitchenTicket::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);

        $seguinte = self::SEGUINTE[$bilhete->status] ?? null;

        if (! $seguinte) {
            throw ValidationException::withMessages(['geral' => [__('Este bilhete já chegou ao fim.')]]);
        }

        try {
            app(RestaurantKitchenService::class)->transition($bilhete, $seguinte, $tenantId, $request->user()?->id);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
        }

        return response()->json([
            'message' => __('Bilhete em :estado.', ['estado' => mb_strtolower(__(self::ESTADOS[$seguinte]))]),
        ]);
    }
}
