<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O REGISTO DOS EMAILS QUE A PLATAFORMA ENVIOU.
 *
 * Duas coisas mudaram em relação ao componente:
 *
 *  · OS DADOS DO MODELO LEVAM SENHAS. O email de credenciais grava a senha
 *    provisória em `template_data`, e o detalhe mostrava-a inteira a quem
 *    abrisse o registo. Continua a ver-se o resto; os campos de segredo
 *    aparecem tapados.
 *  · OS NÚMEROS DO TOPO são uma consulta agrupada, e não quatro.
 */
class RegistoDeEmailsApiController extends Controller
{
    /** Chaves de `template_data` que nunca se mostram. */
    private const SEGREDOS = '/(pass|senha|token|secret|segredo|pin|chave|key)/i';

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:sent,failed,pending'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $termo = trim((string) ($f['procura'] ?? ''));

        $pagina = EmailLog::query()
            ->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('to_email', 'like', "%{$termo}%")
                ->orWhere('subject', 'like', "%{$termo}%")
                ->orWhere('template_slug', 'like', "%{$termo}%")))
            ->when(! empty($f['estado']), fn ($q) => $q->where('status', $f['estado']))
            ->when(! empty($f['modelo']), fn ($q) => $q->where('template_slug', $f['modelo']))
            ->when(! empty($f['de']), fn ($q) => $q->whereDate('created_at', '>=', $f['de']))
            ->when(! empty($f['ate']), fn ($q) => $q->whereDate('created_at', '<=', $f['ate']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'pagina', $f['pagina'] ?? 1);

        $contagens = EmailLog::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'registos' => collect($pagina->items())->map(fn (EmailLog $l) => [
                'id' => $l->id,
                'para' => $l->to_email,
                'nome' => $l->to_name,
                'assunto' => $l->subject,
                'modelo' => $l->template_slug,
                'estado' => $l->status,
                'criado_em' => $l->created_at?->toIso8601String(),
            ]),
            'numeros' => [
                'total' => (int) $contagens->sum(),
                'enviados' => (int) ($contagens['sent'] ?? 0),
                'falhados' => (int) ($contagens['failed'] ?? 0),
                'pendentes' => (int) ($contagens['pending'] ?? 0),
            ],
            'modelos' => EmailLog::query()->whereNotNull('template_slug')->distinct()->orderBy('template_slug')->pluck('template_slug'),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function ver(int $id): JsonResponse
    {
        $l = EmailLog::with(['emailTemplate:id,name', 'smtpSetting:id,host', 'user:id,name', 'tenant:id,name'])->findOrFail($id);

        return response()->json(['registo' => [
            'id' => $l->id,
            'estado' => $l->status,
            'enviado_em' => $l->sent_at?->toIso8601String(),
            'falhou_em' => $l->failed_at?->toIso8601String(),
            'erro' => $l->error_message,
            'para' => $l->to_email,
            'para_nome' => $l->to_name,
            'de' => $l->from_email,
            'de_nome' => $l->from_name,
            'modelo' => $l->template_slug,
            'modelo_nome' => $l->emailTemplate?->name,
            'criado_em' => $l->created_at?->toIso8601String(),
            'assunto' => $l->subject,
            'previa' => $l->body_preview,
            'dados' => $l->template_data ? $this->taparSegredos($l->template_data) : null,
            'empresa' => $l->tenant?->name,
            'utilizador' => $l->user?->name,
            'servidor' => $l->smtpSetting?->host,
            'identificador' => $l->message_id,
        ]]);
    }

    public function apagar(int $id): JsonResponse
    {
        EmailLog::findOrFail($id)->delete();

        return response()->json(['message' => __('Log excluído com sucesso!')]);
    }

    /** Os registos com mais de 90 dias. */
    public function limparAntigos(): JsonResponse
    {
        $apagados = EmailLog::where('created_at', '<', now()->subDays(90))->delete();

        return response()->json([
            'message' => __('Foram excluídos :n logs antigos!', ['n' => $apagados]),
            'apagados' => $apagados,
        ]);
    }

    private function taparSegredos(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $dados[$chave] = $this->taparSegredos($valor);
            } elseif (is_string($chave) && preg_match(self::SEGREDOS, $chave) && filled($valor)) {
                $dados[$chave] = '••••••';
            }
        }

        return $dados;
    }
}
