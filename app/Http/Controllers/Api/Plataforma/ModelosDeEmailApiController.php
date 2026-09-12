<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * OS MODELOS DE EMAIL DA PLATAFORMA.
 *
 * As regras que o componente não tinha e que a base de dados impunha de outra
 * maneira:
 *
 *  · O IDENTIFICADOR É ÚNICO na tabela. Repetir um dava erro 500 em vez de
 *    dizer que já existia.
 *  · O ASSUNTO cabe em 255 caracteres, que é a coluna; a validação deixava 500
 *    e a gravação rebentava.
 *  · O IDENTIFICADOR NÃO MUDA DEPOIS DE CRIADO. É por ele que o sistema pede o
 *    modelo (`welcome`, `password-reset`, `plan_updated`…): mudar-lhe o nome
 *    fazia esse email deixar de sair, sem erro nenhum à vista. É a mesma regra
 *    dos modelos de SMS.
 */
class ModelosDeEmailApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $termo = trim((string) ($f['procura'] ?? ''));

        $pagina = EmailTemplate::query()
            ->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$termo}%")
                ->orWhere('slug', 'like', "%{$termo}%")
                ->orWhere('subject', 'like', "%{$termo}%")))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'pagina', $f['pagina'] ?? 1);

        // Quando foi usado pela última vez: é o que diz se apagar um modelo
        // vai calar um email que ainda sai.
        $usos = EmailLog::query()
            ->whereIn('template_slug', collect($pagina->items())->pluck('slug'))
            ->selectRaw('template_slug, max(created_at) as ultimo, count(*) as total')
            ->groupBy('template_slug')
            ->get()
            ->keyBy('template_slug');

        return response()->json([
            'modelos' => collect($pagina->items())->map(fn (EmailTemplate $m) => [
                'id' => $m->id,
                'slug' => $m->slug,
                'nome' => $m->name,
                'assunto' => $m->subject,
                'descricao' => $m->description,
                'activo' => (bool) $m->is_active,
                'actualizado_em' => $m->updated_at?->toIso8601String(),
                'envios' => (int) ($usos[$m->slug]->total ?? 0),
                'ultimo_envio' => isset($usos[$m->slug]) ? \Carbon\Carbon::parse($usos[$m->slug]->ultimo)->toIso8601String() : null,
            ]),
            'variaveis' => array_keys($this->exemplo()),
            'o_meu_email' => auth()->user()?->email,
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function ficha(int $id): JsonResponse
    {
        $m = EmailTemplate::findOrFail($id);

        return response()->json(['ficha' => [
            'id' => $m->id,
            'slug' => $m->slug,
            'name' => $m->name,
            'subject' => $m->subject,
            'body_html' => $m->body_html,
            'body_text' => (string) $m->body_text,
            'description' => (string) $m->description,
            'is_active' => (bool) $m->is_active,
        ]]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $modelo = $id ? EmailTemplate::findOrFail($id) : null;

        $regras = [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];

        if (! $modelo) {
            $regras['slug'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9_\-]*$/', Rule::unique('email_templates', 'slug')];
        }

        $dados = $request->validate($regras, [
            'slug.regex' => __('Só letras minúsculas, números, hífen e sublinhado.'),
            'slug.unique' => __('Já existe um modelo com este identificador.'),
        ]);

        $campos = [
            'name' => $dados['name'],
            'subject' => $dados['subject'],
            'body_html' => $dados['body_html'],
            'body_text' => $dados['body_text'] ?? null,
            'description' => $dados['description'] ?? null,
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        if ($modelo) {
            $modelo->update($campos);

            return response()->json(['message' => __('Template atualizado com sucesso!')]);
        }

        EmailTemplate::create($campos + ['slug' => $dados['slug']]);

        return response()->json(['message' => __('Template criado com sucesso!')]);
    }

    public function alternar(int $id): JsonResponse
    {
        $m = EmailTemplate::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);

        return response()->json(['message' => __('Status atualizado com sucesso!'), 'activo' => (bool) $m->is_active]);
    }

    public function apagar(int $id): JsonResponse
    {
        EmailTemplate::findOrFail($id)->delete();

        return response()->json(['message' => __('Template excluído com sucesso!')]);
    }

    /** O modelo com dados de exemplo, tal como sai. */
    public function previsualizar(int $id): JsonResponse
    {
        $r = EmailTemplate::findOrFail($id)->render($this->exemplo());

        return response()->json([
            'assunto' => $r['subject'],
            'html' => $r['body_html'],
            'texto' => $r['body_text'],
        ]);
    }

    public function enviarTeste(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']]);
        $m = EmailTemplate::findOrFail($id);

        try {
            // O mesmo método estático do registo e dos avisos: o teste passa
            // exactamente pelo mesmo caminho que o email a sério.
            EmailTemplate::sendEmail(
                templateSlug: $m->slug,
                toEmail: $dados['email'],
                data: $this->exemplo(auth()->user()?->name, __('Teste de envio de email')),
                tenantId: null,
            );
        } catch (\Throwable $e) {
            Log::error('Modelos de email: o envio de teste falhou.', ['modelo' => $m->slug, 'erro' => $e->getMessage()]);

            return response()->json(['message' => __('Erro ao enviar email: :erro', ['erro' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => __('Email de teste enviado com sucesso para :email!', ['email' => $dados['email']])]);
    }

    private function exemplo(?string $nome = null, ?string $motivo = null): array
    {
        return [
            'user_name' => $nome ?: 'João Silva',
            'tenant_name' => 'Empresa Demo LTDA',
            'app_name' => config('app.name', 'SOS ERP'),
            'plan_name' => 'Plano Premium',
            'old_plan_name' => 'Plano Básico',
            'new_plan_name' => 'Plano Premium',
            'reason' => $motivo ?: 'Pagamento não identificado',
            'support_email' => config('mail.from.address') ?: 'suporte@soserp.com',
            'login_url' => route('login'),
        ];
    }
}
