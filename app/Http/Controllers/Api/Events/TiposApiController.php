<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * OS TIPOS DE EVENTO — casamento, conferência, espectáculo.
 *
 * Cada tipo traz um ÍCONE e uma COR, e é a cor que pinta o evento no
 * calendário: é por ela que se lê o mês de relance. A ORDEM daqui é a ordem por
 * que aparecem nas listas — e decide-se a olhar para elas, arrastando, não
 * escrevendo números numa caixa.
 */
class TiposApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.types.view');

        $contagens = Event::forTenant()
            ->selectRaw('type_id, COUNT(*) as total')
            ->groupBy('type_id')
            ->pluck('total', 'type_id');

        return response()->json([
            'data' => EventType::forTenant()->orderBy('order')->orderBy('name')->get()
                ->map(fn (EventType $t) => [
                    'id' => $t->id,
                    'nome' => $t->name,
                    'icone' => $t->icon ?: '📌',
                    'cor' => $t->color ?: '#8b5cf6',
                    'descricao' => $t->description,
                    'ordem' => (int) $t->order,
                    'activo' => (bool) $t->is_active,
                    'eventos' => (int) ($contagens[$t->id] ?? 0),
                ])->values(),
            'emojis' => AgendaApiController::EMOJIS,
            'resumo' => [
                'total' => EventType::forTenant()->count(),
                'activos' => EventType::forTenant()->where('is_active', true)->count(),
            ],
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('events.types.manage')],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.types.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', 'string', 'max:7'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => __('nome'), 'icon' => __('ícone'), 'color' => __('cor'),
        ]);

        $valores = [
            'name' => $dados['name'],
            'icon' => $dados['icon'],
            'color' => $dados['color'],
            'description' => ($dados['description'] ?? '') ?: null,
            'order' => $dados['order'] ?? ((int) EventType::forTenant()->max('order') + 1),
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        $tipo = $id
            ? tap(EventType::forTenant()->findOrFail($id))->update($valores)
            : EventType::create($valores + ['tenant_id' => activeTenantId()]);

        return response()->json([
            'message' => $id ? __('Tipo actualizado.') : __('Tipo criado.'),
            'data' => ['id' => $tipo->id, 'nome' => $tipo->name, 'icone' => $tipo->icon, 'cor' => $tipo->color],
        ], $id ? 200 : 201);
    }

    /** Sobe ou desce um tipo — a ordem vê-se, não se escreve. */
    public function mover(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.types.manage');

        $dados = $request->validate(['direccao' => ['required', 'in:cima,baixo']]);

        $lista = EventType::forTenant()->orderBy('order')->orderBy('name')->get();
        $posicao = $lista->search(fn (EventType $t) => $t->id === $id);

        if ($posicao === false) {
            abort(404);
        }

        $vizinho = $dados['direccao'] === 'cima' ? $posicao - 1 : $posicao + 1;

        if ($vizinho < 0 || $vizinho >= $lista->count()) {
            return response()->json(['message' => __('Já está no fim.')]);
        }

        /*
         * REESCREVE-SE A ORDEM TODA, e não só as duas.
         *
         * Trocar dois números presume que eles são diferentes — e não são: uma
         * lista criada de enfiada fica toda a zero, e a troca não mexia em
         * nada. Numerar de novo do princípio é sempre certo.
         */
        $ordenada = $lista->values();
        $item = $ordenada->pull($posicao);

        $ordenada = $ordenada->values();
        $ordenada->splice($vizinho, 0, [$item]);

        foreach ($ordenada->values() as $i => $t) {
            $t->update(['order' => $i]);
        }

        return response()->json(['message' => __('Ordem actualizada.')]);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.types.manage');

        $tipo = EventType::forTenant()->findOrFail($id);
        $tipo->update(['is_active' => ! $tipo->is_active]);

        return response()->json([
            'message' => $tipo->is_active ? __('Tipo activo.') : __('Tipo desligado.'),
            'activo' => (bool) $tipo->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.types.manage');

        $tipo = EventType::forTenant()->findOrFail($id);

        if (Event::forTenant()->where('type_id', $tipo->id)->exists()) {
            $this->recusa(__('Este tipo tem eventos. Desligue-o em vez de o apagar.'));
        }

        $tipo->delete();

        return response()->json(['message' => __('Tipo removido.')]);
    }
}
