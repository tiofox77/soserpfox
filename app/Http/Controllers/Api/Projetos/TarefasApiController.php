<?php

namespace App\Http\Controllers\Api\Projetos;

use App\Http\Controllers\Controller;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS TAREFAS DOS PROJETOS.
 *
 * NADA SE APAGA: cancelar é um estado. As horas já lançadas contra uma tarefa
 * continuam a valer, e uma tarefa que desaparecesse deixava-as sem explicação
 * — horas cobradas a um cliente por um trabalho que já não existe em lado
 * nenhum.
 *
 * A ORDEM DA LISTA é urgente primeiro, depois o prazo mais próximo, e as sem
 * prazo no fim: quem não tem data não compete com quem tem.
 */
class TarefasApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'projetos.tarefas.view');

        $tenantId = activeTenantId();

        return response()->json([
            'estados' => collect(Tarefa::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'prioridades' => collect(Tarefa::PRIORIDADES)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'projetos' => Projeto::forTenant()->whereIn('estado', Projeto::ABERTOS)
                ->orderBy('nome')->get(['id', 'codigo', 'nome'])
                ->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => $p->codigo.' · '.$p->nome])->values(),
            'pessoas' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->values(),
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('projetos.tarefas.manage')],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'projetos.tarefas.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'projeto' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string', 'max:20'],
            'prioridade' => ['nullable', Rule::in(array_keys(Tarefa::PRIORIDADES))],
            'so_minhas' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $estado = $filtros['estado'] ?? 'abertas';

        $base = fn () => Tarefa::forTenant()
            ->when($filtros['projeto'] ?? null, fn ($q, $p) => $q->where('projeto_id', $p))
            ->when($filtros['so_minhas'] ?? false, fn ($q) => $q->where('responsavel_id', $request->user()?->id))
            ->when($filtros['prioridade'] ?? null, fn ($q, $p) => $q->where('prioridade', $p))
            ->when($estado === 'abertas', fn ($q) => $q->whereIn('estado', Tarefa::ABERTAS))
            ->when(array_key_exists($estado, Tarefa::ESTADOS), fn ($q) => $q->where('estado', $estado))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('titulo', 'like', $t)->orWhere('descricao', 'like', $t));
            });

        $lista = $base()
            ->with(['projeto:id,codigo,nome', 'responsavel:id,name'])
            // URGENTE PRIMEIRO, depois o prazo mais próximo. As sem prazo vão
            // para o fim: quem não tem data não compete com quem tem.
            ->orderByRaw("FIELD(prioridade, 'urgente', 'alta', 'normal', 'baixa')")
            ->orderByRaw('prazo IS NULL, prazo ASC')
            ->paginate($filtros['por_pagina'] ?? 20);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Tarefa $t) => $this->linha($t))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'abertas' => Tarefa::forTenant()->whereIn('estado', Tarefa::ABERTAS)->count(),
                'atrasadas' => Tarefa::forTenant()->whereIn('estado', Tarefa::ABERTAS)
                    ->whereNotNull('prazo')->whereDate('prazo', '<', today())->count(),
                'minhas' => Tarefa::forTenant()
                    ->where('responsavel_id', $request->user()?->id)
                    ->whereIn('estado', Tarefa::ABERTAS)->count(),
            ],
        ]);
    }

    private function linha(Tarefa $t): array
    {
        $aberta = in_array($t->estado, Tarefa::ABERTAS, true);

        return [
            'id' => $t->id,
            'titulo' => $t->titulo,
            'descricao' => $t->descricao,
            'projeto_id' => $t->projeto_id,
            'projeto' => $t->projeto?->codigo,
            'projeto_nome' => $t->projeto?->nome,
            'responsavel_id' => $t->responsavel_id,
            'responsavel' => $t->responsavel?->name,
            'estado' => $t->estado,
            'estado_rotulo' => __(Tarefa::ESTADOS[$t->estado] ?? $t->estado),
            'prioridade' => $t->prioridade,
            'prioridade_rotulo' => __(Tarefa::PRIORIDADES[$t->prioridade] ?? $t->prioridade),
            'prazo' => $t->prazo?->format('Y-m-d'),
            'atrasada' => $aberta && $t->prazo && $t->prazo->lt(today()),
            'horas_estimadas' => $t->horas_estimadas !== null ? (float) $t->horas_estimadas : null,
            'concluida_em' => $t->concluida_em?->format('Y-m-d H:i'),
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'projetos.tarefas.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'min:2', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'projeto_id' => ['required', Rule::exists('projetos', 'id')->where('tenant_id', $tenantId)],
            'responsavel_id' => ['nullable', 'integer'],
            'prioridade' => ['required', Rule::in(array_keys(Tarefa::PRIORIDADES))],
            'prazo' => ['nullable', 'date'],
            'horas_estimadas' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ], [], [
            'titulo' => __('título'), 'projeto_id' => __('projeto'), 'prioridade' => __('prioridade'),
        ]);

        $valores = [
            'projeto_id' => $dados['projeto_id'],
            'titulo' => trim($dados['titulo']),
            'descricao' => trim($dados['descricao'] ?? '') ?: null,
            'responsavel_id' => $dados['responsavel_id'] ?? null,
            'prioridade' => $dados['prioridade'],
            'prazo' => $dados['prazo'] ?? null,
            'horas_estimadas' => $dados['horas_estimadas'] ?? null,
        ];

        if ($id) {
            $t = Tarefa::forTenant()->findOrFail($id);
            $t->update($valores);
        } else {
            $t = Tarefa::create($valores + [
                'tenant_id' => $tenantId,
                'estado' => 'por_fazer',
                'created_by' => $request->user()?->id,
                'ordem' => (int) Tarefa::forTenant()
                    ->where('projeto_id', $dados['projeto_id'])->max('ordem') + 1,
            ]);
        }

        return response()->json([
            'message' => $id ? __('Tarefa actualizada.') : __('Tarefa criada.'),
            'data' => $this->linha($t->fresh(['projeto', 'responsavel'])),
        ], $id ? 200 : 201);
    }

    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'projetos.tarefas.manage');

        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Tarefa::ESTADOS))],
        ]);

        $t = Tarefa::forTenant()->findOrFail($id);

        $t->update([
            'estado' => $dados['estado'],
            // REABRIR LIMPA A DATA DE FECHO: uma tarefa que voltou a andar não
            // pode continuar a dizer que fechou naquele dia.
            'concluida_em' => $dados['estado'] === 'concluida' ? now() : null,
        ]);

        return response()->json([
            'message' => __('Tarefa: :estado.', ['estado' => __(Tarefa::ESTADOS[$t->estado] ?? $t->estado)]),
            'data' => $this->linha($t->fresh(['projeto', 'responsavel'])),
        ]);
    }
}
