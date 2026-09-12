<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AS MENSAGENS DO FORMULÁRIO DE CONTACTO DO SITE.
 *
 * O componente contava os três estados com três consultas escritas dentro da
 * vista; aqui é uma só, agrupada. E os números do topo contam sempre tudo, e
 * não o que a procura deixou ver — um contador que encolhe com o filtro faz
 * crer que não há mensagens novas quando só não aparecem.
 */
class ContactosApiController extends Controller
{
    private const ESTADOS = ['new', 'read', 'replied'];

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:' . implode(',', self::ESTADOS)],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $termo = trim((string) ($f['procura'] ?? ''));

        $pagina = ContactMessage::query()
            ->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$termo}%")
                ->orWhere('email', 'like', "%{$termo}%")
                ->orWhere('company', 'like', "%{$termo}%")))
            ->when(! empty($f['estado']), fn ($q) => $q->where('status', $f['estado']))
            ->latest()
            ->paginate(20, ['*'], 'pagina', $f['pagina'] ?? 1);

        $contagens = ContactMessage::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'mensagens' => collect($pagina->items())->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'nome' => $m->name,
                'email' => $m->email,
                'telefone' => $m->phone,
                'empresa' => $m->company,
                'mensagem' => $m->message,
                'estado' => $m->status ?: 'new',
                'ip' => $m->ip_address,
                'recebida_em' => $m->created_at?->toIso8601String(),
            ]),
            'numeros' => [
                'total' => (int) $contagens->sum(),
                'novas' => (int) ($contagens['new'] ?? 0),
                'lidas' => (int) ($contagens['read'] ?? 0),
                'respondidas' => (int) ($contagens['replied'] ?? 0),
            ],
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    /** Lida ou respondida. Uma respondida não volta a lida por engano de clique. */
    public function marcar(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['estado' => ['required', 'in:read,replied']]);
        $m = ContactMessage::findOrFail($id);

        if ($dados['estado'] === 'read' && $m->status === 'replied') {
            return response()->json(['message' => __('Esta mensagem já foi respondida.')]);
        }

        $m->update(['status' => $dados['estado']]);

        return response()->json([
            'message' => $dados['estado'] === 'read' ? __('Mensagem marcada como lida!') : __('Mensagem marcada como respondida!'),
        ]);
    }

    public function apagar(int $id): JsonResponse
    {
        ContactMessage::findOrFail($id)->delete();

        return response()->json(['message' => __('Mensagem excluída!')]);
    }
}
