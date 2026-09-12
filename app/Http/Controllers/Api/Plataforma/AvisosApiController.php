<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformMessage;
use App\Models\PlatformMessageRead;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * AS MENSAGENS DO DONO DA PLATAFORMA PARA AS EMPRESAS.
 *
 * As regras vêm do componente:
 *
 *  · «EMPRESAS ESCOLHIDAS» SEM NENHUMA ESCOLHIDA manda para ninguém — e o ecrã
 *    dizia «guardada com sucesso». Recusa-se. O mesmo para os planos.
 *  · A HORA ESCRITA É A HORA A QUE ENTRA NO AR. O campo não tem fuso; passa
 *    por `doRelogioDeParede` à ida e por `noRelogioDeParede` à volta.
 *  · PUBLICAR, RETIRAR OU APAGAR LIMPA A CACHE das mensagens no ar, senão uma
 *    mensagem urgente só aparecia daí a um minuto.
 *
 * E uma que o ecrã passa a dar: o ALCANCE antes de publicar, que o modelo já
 * sabia contar e o formulário nunca mostrou.
 */
class AvisosApiController extends Controller
{
    private const CACHE = 'mensagens-plataforma-no-ar';

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['pagina' => ['nullable', 'integer', 'min:1']]);

        $pagina = PlatformMessage::with('autor:id,name')
            ->withCount([
                'leituras as vistas',
                'leituras as dispensadas' => fn ($q) => $q->whereNotNull('dismissed_at'),
            ])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'mensagens' => collect($pagina->items())->map(fn (PlatformMessage $m) => [
                'id' => $m->id,
                'titulo' => $m->title,
                'corpo' => $m->body,
                'nivel' => $m->level,
                'forma' => $m->display,
                'publico' => $m->audience,
                'alcance' => $m->quantasEmpresas(),
                'vistas' => (int) $m->vistas,
                'dispensadas' => (int) $m->dispensadas,
                'dispensavel' => (bool) $m->dismissible,
                'activa' => (bool) $m->is_active,
                'situacao' => match (true) {
                    ! $m->is_active => 'retirada',
                    $m->ends_at && $m->ends_at->isPast() => 'terminada',
                    $m->starts_at && $m->starts_at->isFuture() => 'agendada',
                    default => 'no_ar',
                },
                'comeca' => $m->noRelogioDeParede('starts_at')?->format('Y-m-d H:i'),
                'termina' => $m->noRelogioDeParede('ends_at')?->format('Y-m-d H:i'),
                'ligacao' => $m->link_url,
                'texto_da_ligacao' => $m->link_label,
                'autor' => $m->autor?->name,
            ]),
            'opcoes' => [
                'niveis' => $this->escolhas(PlatformMessage::NIVEIS),
                'formas' => $this->escolhas(PlatformMessage::FORMAS),
                'publicos' => $this->escolhas(PlatformMessage::PUBLICOS),
                'empresas' => Tenant::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name]),
                'planos' => Plan::where('is_active', true)->orderBy('order')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'nome' => $p->name]),
            ],
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function ficha(int $id): JsonResponse
    {
        $m = PlatformMessage::findOrFail($id);

        return response()->json(['ficha' => [
            'id' => $m->id,
            'title' => $m->title,
            'body' => $m->body,
            'level' => $m->level,
            'display' => $m->display,
            'audience' => $m->audience,
            'tenant_ids' => array_map('intval', $m->tenant_ids ?? []),
            'plan_ids' => array_map('intval', $m->plan_ids ?? []),
            // De volta ao relógio de parede: o campo datetime-local não tem
            // fuso, e o que lá aparece tem de ser a mesma hora que foi escrita.
            'starts_at' => $m->noRelogioDeParede('starts_at')?->format('Y-m-d\TH:i') ?? '',
            'ends_at' => $m->noRelogioDeParede('ends_at')?->format('Y-m-d\TH:i') ?? '',
            'dismissible' => (bool) $m->dismissible,
            'link_url' => (string) $m->link_url,
            'link_label' => (string) $m->link_label,
            'is_active' => (bool) $m->is_active,
        ]]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $mensagem = $id ? PlatformMessage::findOrFail($id) : null;
        $dados = $this->validar($request);

        $campos = [
            'title' => $dados['title'],
            'body' => $dados['body'],
            'level' => $dados['level'],
            'display' => $dados['display'],
            'audience' => $dados['audience'],
            'tenant_ids' => $dados['audience'] === 'empresas' ? array_values(array_map('intval', $dados['tenant_ids'])) : null,
            'plan_ids' => $dados['audience'] === 'planos' ? array_values(array_map('intval', $dados['plan_ids'])) : null,
            'starts_at' => PlatformMessage::doRelogioDeParede($dados['starts_at'] ?? null),
            'ends_at' => PlatformMessage::doRelogioDeParede($dados['ends_at'] ?? null),
            'dismissible' => (bool) ($dados['dismissible'] ?? true),
            'link_url' => ($dados['link_url'] ?? null) ?: null,
            'link_label' => ($dados['link_label'] ?? null) ?: null,
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        if ($mensagem) {
            $mensagem->update($campos);
        } else {
            $mensagem = PlatformMessage::create($campos + ['created_by' => auth()->id()]);
        }

        Cache::forget(self::CACHE);

        return response()->json([
            'message' => $id ? __('Mensagem actualizada.') : __('Mensagem no ar.'),
            'id' => $mensagem->id,
        ]);
    }

    /** A quantas empresas isto chega — antes de carregar em publicar. */
    public function alcance(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'audience' => ['required', 'in:' . implode(',', array_keys(PlatformMessage::PUBLICOS))],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer'],
            'plan_ids' => ['array'],
            'plan_ids.*' => ['integer'],
        ]);

        $m = new PlatformMessage([
            'audience' => $dados['audience'],
            'tenant_ids' => $dados['tenant_ids'] ?? [],
            'plan_ids' => $dados['plan_ids'] ?? [],
        ]);

        return response()->json(['empresas' => $m->quantasEmpresas()]);
    }

    public function alternar(int $id): JsonResponse
    {
        $m = PlatformMessage::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);

        Cache::forget(self::CACHE);

        return response()->json(['message' => $m->is_active ? __('Mensagem no ar.') : __('Mensagem retirada.')]);
    }

    public function apagar(int $id): JsonResponse
    {
        PlatformMessage::findOrFail($id)->delete();

        Cache::forget(self::CACHE);

        return response()->json(['message' => __('Mensagem apagada.')]);
    }

    /** Quem viu e quem dispensou — as cem mais recentes. */
    public function leituras(int $id): JsonResponse
    {
        PlatformMessage::findOrFail($id);

        return response()->json([
            'leituras' => PlatformMessageRead::with(['user:id,name,email'])
                ->where('platform_message_id', $id)
                ->orderByDesc('seen_at')
                ->limit(100)
                ->get()
                ->map(fn (PlatformMessageRead $l) => [
                    'id' => $l->id,
                    'nome' => $l->user?->name,
                    'email' => $l->user?->email,
                    'vista_em' => $l->seen_at?->format('Y-m-d H:i'),
                    'dispensada_em' => $l->dismissed_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
            'level' => ['required', 'in:' . implode(',', array_keys(PlatformMessage::NIVEIS))],
            'display' => ['required', 'in:' . implode(',', array_keys(PlatformMessage::FORMAS))],
            'audience' => ['required', 'in:' . implode(',', array_keys(PlatformMessage::PUBLICOS))],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer'],
            'plan_ids' => ['array'],
            'plan_ids.*' => ['integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'dismissible' => ['boolean'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'link_label' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ], [
            'title.required' => __('A mensagem precisa de um título.'),
            'body.required' => __('Escreva a mensagem — é o que as pessoas vão ler.'),
            'ends_at.after_or_equal' => __('O fim não pode ser antes do início.'),
            'link_url.url' => __('A ligação tem de ser um endereço completo (https://…).'),
        ]);

        if ($dados['audience'] === 'empresas' && empty($dados['tenant_ids'])) {
            throw ValidationException::withMessages(['tenant_ids' => __('Escolha pelo menos uma empresa, ou mude o público para "todas".')]);
        }

        if ($dados['audience'] === 'planos' && empty($dados['plan_ids'])) {
            throw ValidationException::withMessages(['plan_ids' => __('Escolha pelo menos um plano, ou mude o público para "todas".')]);
        }

        return $dados;
    }

    private function escolhas(array $mapa): array
    {
        return collect($mapa)->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => __($rotulo)])->values()->all();
    }
}
