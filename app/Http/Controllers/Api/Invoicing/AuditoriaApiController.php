<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Services\Audit\LeituraDaTrilha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A TRILHA DE AUDITORIA, para o ecrã em React.
 *
 * Só leitura, por construção: a tabela é append-only e o modelo recusa
 * alterações. A frase e os campos de cada linha saem da `LeituraDaTrilha`,
 * que o Livewire também usa — e a cadeia verifica-se a pedido, porque é uma
 * varredura de todas as linhas.
 */
class AuditoriaApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request);

        return response()->json([
            // As opções dos filtros são as que existem de facto.
            'eventos' => AuditTrail::forTenant()->distinct()->orderBy('event')->pluck('event')->filter()->values(),
            'canais' => AuditTrail::forTenant()->distinct()->orderBy('channel')->pluck('channel')->filter()->values(),
            'actores' => AuditTrail::forTenant()->whereNotNull('user_id')->select('user_id', 'actor_name')->distinct()->orderBy('actor_name')->get()
                ->map(fn ($a) => ['id' => $a->user_id, 'nome' => $a->actor_name])->values(),
        ]);
    }

    public function index(Request $request, LeituraDaTrilha $leitura): JsonResponse
    {
        $this->exigir($request);

        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'evento' => ['nullable', 'string', 'max:40'],
            'canal' => ['nullable', 'string', 'max:40'],
            'actor' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = AuditTrail::forTenant()
            ->when($f['procura'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('auditable_label', 'like', "%{$v}%")->orWhere('actor_name', 'like', "%{$v}%")->orWhere('auditable_type', 'like', "%{$v}%")))
            ->when($f['evento'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->when($f['canal'] ?? null, fn ($q, $v) => $q->where('channel', $v))
            ->when($f['actor'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['de'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['ate'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $leitura->preparar($pagina->getCollection());

        return response()->json([
            'data' => collect($pagina->items())->map(fn (AuditTrail $r) => $this->linha($r, $leitura))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
        ]);
    }

    /** Uma linha por inteiro: a frase e os campos que mudaram. */
    public function mostrar(Request $request, LeituraDaTrilha $leitura, int $id): JsonResponse
    {
        $this->exigir($request);

        $r = AuditTrail::forTenant()->findOrFail($id);
        $leitura->preparar(collect([$r]));

        return response()->json(['data' => $this->linha($r, $leitura, true)]);
    }

    /** A cadeia desta empresa, verificada a pedido. */
    public function integridade(Request $request): JsonResponse
    {
        $this->exigir($request);

        $problemas = AuditTrail::verificarCadeia(activeTenantId());

        return response()->json([
            'ok' => empty($problemas),
            'total' => count($problemas),
            'problemas' => array_slice($problemas, 0, 20),
            'em' => now()->format('d/m/Y H:i'),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function linha(AuditTrail $r, LeituraDaTrilha $leitura, bool $completa = false): array
    {
        $linha = [
            'id' => $r->id,
            'quando' => optional($r->created_at)->format('d/m/Y H:i:s'),
            'evento' => $r->event,
            'canal' => $r->channel,
            'quem' => $r->actor_name,
            'modelo' => $leitura->nomeDoModelo((string) $r->auditable_type),
            'registo' => $r->auditable_label,
            'frase' => $leitura->frase($r),
        ];

        if ($completa) {
            $linha['campos'] = collect($leitura->campos($r))->map(fn ($c) => is_array($c) ? $c : (array) $c)->values();
            $linha['ip'] = $r->ip_address ?? null;
        }

        return $linha;
    }

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('invoicing.settings.view'), 403, __('Sem permissão para esta operação.'));
    }
}
