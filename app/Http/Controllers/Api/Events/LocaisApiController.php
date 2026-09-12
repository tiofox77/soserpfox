<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * OS LOCAIS — onde os eventos acontecem.
 *
 * O ECRÃ ANTIGO NÃO VERIFICAVA A EMPRESA ao gravar nem ao apagar: o `edit`,
 * o `save` e o `delete` faziam `Venue::findOrFail($id)` com o id que vinha do
 * browser. O escopo de empresa no modelo tapou-o; aqui a procura é explícita,
 * que é o que se lê ao rever o código daqui a um ano.
 *
 * E A CAPACIDADE PASSOU A VALER: um local com 80 lugares recusa um evento de
 * 500 pessoas (ver `AgendaApiController`). Antes marcava-se, e descobria-se no
 * dia com as pessoas à porta.
 */
class LocaisApiController extends Controller
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
        $this->exigir($request, 'events.venues.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Venue::forTenant()
            /*
             * O PARÊNTESIS NO `OR` NÃO É ZELO.
             *
             * A procura antiga era `where(name)->orWhere(city)->orWhere(address)`
             * encostada ao filtro da empresa — e um `or` solto rompe o `and` que
             * está antes: procurar por «Luanda» trazia os locais de TODAS as
             * empresas cuja cidade era Luanda.
             */
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhere('city', 'like', "%{$t}%")
                ->orWhere('address', 'like', "%{$t}%")))
            ->withCount('events')
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Venue $v) => [
                'id' => $v->id,
                'nome' => $v->name,
                'morada' => $v->address,
                'cidade' => $v->city,
                'telefone' => $v->phone,
                'contacto' => $v->contact_person,
                'capacidade' => (int) $v->capacity,
                'notas' => $v->notes,
                'activo' => (bool) $v->is_active,
                'eventos' => (int) $v->events_count,
            ])->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => Venue::forTenant()->count(),
                'activos' => Venue::forTenant()->where('is_active', true)->count(),
                'lugares' => (int) Venue::forTenant()->where('is_active', true)->sum('capacity'),
            ],
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('events.venues.manage')],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.venues.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [], ['name' => __('nome')]);

        $valores = [
            'name' => $dados['name'],
            'address' => ($dados['address'] ?? '') ?: null,
            'city' => ($dados['city'] ?? '') ?: null,
            'phone' => ($dados['phone'] ?? '') ?: null,
            'contact_person' => ($dados['contact_person'] ?? '') ?: null,
            'capacity' => $dados['capacity'] ?? null,
            'notes' => ($dados['notes'] ?? '') ?: null,
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        $local = $id
            ? tap(Venue::forTenant()->findOrFail($id))->update($valores)
            : Venue::create($valores + ['tenant_id' => activeTenantId()]);

        return response()->json([
            'message' => $id ? __('Local actualizado.') : __('Local criado.'),
            'data' => ['id' => $local->id, 'nome' => $local->name],
        ], $id ? 200 : 201);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.venues.manage');

        $local = Venue::forTenant()->findOrFail($id);
        $local->update(['is_active' => ! $local->is_active]);

        return response()->json([
            'message' => $local->is_active ? __('Local activo.') : __('Local desligado.'),
            'activo' => (bool) $local->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.venues.manage');

        $local = Venue::forTenant()->findOrFail($id);

        // Um local com eventos não desaparece: as fichas ficavam sem sítio.
        if (Event::forTenant()->where('venue_id', $local->id)->exists()) {
            $this->recusa(__('Este local tem eventos. Desligue-o em vez de o apagar.'));
        }

        $local->delete();

        return response()->json(['message' => __('Local removido.')]);
    }
}
