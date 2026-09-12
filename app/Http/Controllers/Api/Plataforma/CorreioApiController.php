<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\SmtpSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OS SERVIDORES DE CORREIO (SMTP) — o da plataforma e os de cada empresa.
 *
 * TRÊS DEFEITOS QUE O ECRÃ ANTIGO TINHA:
 *
 *  · EDITAR OBRIGAVA A REESCREVER A PALAVRA-PASSE. O formulário esvaziava-a ao
 *    abrir («não mostrar por segurança», e bem) mas a validação continuava a
 *    exigi-la — o comentário ao lado dizia «só actualizar se foi preenchida».
 *    Mudar a porta de um servidor exigia ir buscar a senha a quem a tinha.
 *  · DUAS CONFIGURAÇÕES PADRÃO. Marcar «padrão» no formulário não tirava a
 *    marca à que já a tinha; `getForTenant(null)` apanhava uma delas ao calhas.
 *    E o botão «definir como padrão» tirava a marca a TODAS, incluindo as das
 *    empresas — que não são padrão de nada, são a de cada empresa.
 *  · APAGAR A PADRÃO sem aviso deixava a plataforma sem correio: nenhum aviso
 *    de suspensão, nenhuma credencial de utilizador novo saía.
 */
class CorreioApiController extends Controller
{
    public function index(): JsonResponse
    {
        $configuracoes = SmtpSetting::with(['tenant' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('is_default')->orderByDesc('created_at')->get();

        // OS ENVIOS DE CADA SERVIDOR numa consulta só.
        $envios = DB::table('email_logs')
            ->select('smtp_setting_id',
                DB::raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviados"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as falhados"))
            ->whereNotNull('smtp_setting_id')
            ->groupBy('smtp_setting_id')->get()->keyBy('smtp_setting_id');

        return response()->json([
            'configuracoes' => $configuracoes->map(fn (SmtpSetting $s) => [
                'id' => $s->id,
                'empresa' => $s->tenant?->name,
                'empresa_id' => $s->tenant_id,
                'host' => $s->host,
                'porta' => (int) $s->port,
                'utilizador' => $s->username,
                'encriptacao' => $s->encryption,
                'remetente' => $s->from_email,
                'nome_do_remetente' => $s->from_name,
                'padrao' => (bool) $s->is_default,
                'activa' => (bool) $s->is_active,
                'testada_em' => $s->last_tested_at?->format('d/m/Y H:i'),
                'enviados' => (int) ($envios[$s->id]->enviados ?? 0),
                'falhados' => (int) ($envios[$s->id]->falhados ?? 0),
            ])->values(),
            'empresas' => Tenant::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => $t->name])->values(),
            // A da plataforma é a que manda os avisos de conta e as credenciais.
            'plataforma_tem_correio' => SmtpSetting::whereNull('tenant_id')->where('is_default', true)->where('is_active', true)->exists(),
            'o_meu_email' => auth()->user()?->email,
        ]);
    }

    public function ficha(int $id): JsonResponse
    {
        $s = SmtpSetting::findOrFail($id);

        return response()->json([
            'ficha' => [
                'id' => $s->id,
                'tenant_id' => $s->tenant_id,
                'host' => $s->host,
                'port' => (int) $s->port,
                'username' => $s->username,
                // A palavra-passe NUNCA vai para o browser: diz-se só se existe.
                'password' => '',
                'tem_password' => filled($s->getRawOriginal('password')),
                'encryption' => $s->encryption,
                'from_email' => $s->from_email,
                'from_name' => $s->from_name,
                'is_default' => (bool) $s->is_default,
                'is_active' => (bool) $s->is_active,
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $conf = $id ? SmtpSetting::findOrFail($id) : null;

        $d = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            // A PALAVRA-PASSE SÓ É OBRIGATÓRIA A CRIAR, ou quando a que existe
            // está vazia. A editar, vazio é «manter a que está».
            'password' => [($conf && filled($conf->getRawOriginal('password'))) ? 'nullable' : 'required', 'string', 'max:255'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ], [], [
            'host' => __('servidor'),
            'port' => __('porta'),
            'username' => __('utilizador'),
            'password' => __('palavra-passe'),
            'from_email' => __('email do remetente'),
            'from_name' => __('nome do remetente'),
        ]);

        $campos = [
            'tenant_id' => $d['tenant_id'] ?? null,
            'host' => trim($d['host']),
            'port' => $d['port'],
            'username' => trim($d['username']),
            'encryption' => $d['encryption'],
            'from_email' => $d['from_email'],
            'from_name' => $d['from_name'],
            'is_default' => (bool) ($d['is_default'] ?? false),
            'is_active' => (bool) ($d['is_active'] ?? true),
        ];

        if (filled($d['password'] ?? null)) {
            $campos['password'] = $d['password'];
        }

        $conf = DB::transaction(function () use ($conf, $campos) {
            $conf ? $conf->update($campos) : $conf = SmtpSetting::create($campos);

            if ($conf->is_default) {
                $this->soUmaPadrao($conf);
            }

            return $conf;
        });

        return response()->json([
            'message' => $id ? __('Servidor de correio guardado.') : __('Servidor de correio criado.'),
            'id' => $conf->id,
        ], $id ? 200 : 201);
    }

    public function alternar(int $id): JsonResponse
    {
        $s = SmtpSetting::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);

        return response()->json([
            'message' => $s->is_active ? __('Servidor de correio ligado.') : __('Servidor de correio desligado.'),
        ]);
    }

    public function padrao(int $id): JsonResponse
    {
        $s = SmtpSetting::findOrFail($id);

        DB::transaction(function () use ($s) {
            $s->update(['is_default' => true]);
            $this->soUmaPadrao($s);
        });

        return response()->json(['message' => __('Servidor :host é agora o padrão.', ['host' => $s->host])]);
    }

    public function apagar(int $id): JsonResponse
    {
        $s = SmtpSetting::findOrFail($id);

        // A PADRÃO DA PLATAFORMA, ACTIVA, E ÚNICA não se apaga: sem ela nenhum
        // aviso de conta nem credencial de utilizador novo sai.
        $outras = SmtpSetting::whereNull('tenant_id')->where('is_active', true)->where('id', '!=', $s->id)->exists();

        if ($s->tenant_id === null && $s->is_default && $s->is_active && ! $outras) {
            throw ValidationException::withMessages([
                'id' => __('Este é o único servidor de correio activo da plataforma. Crie ou ligue outro antes de o apagar.'),
            ]);
        }

        $s->delete();

        return response()->json(['message' => __('Servidor de correio apagado.')]);
    }

    /** Uma ligação ao servidor, sem mandar email nenhum. */
    public function testar(int $id): JsonResponse
    {
        $resultado = SmtpSetting::findOrFail($id)->testConnection();

        return response()->json([
            'sucesso' => (bool) $resultado['success'],
            'message' => $resultado['message'],
        ], $resultado['success'] ? 200 : 422);
    }

    /** Um email de teste, registado no histórico de envios como os outros. */
    public function enviarTeste(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $s = SmtpSetting::findOrFail($id);

        $assunto = __('Teste do servidor de correio — :app', ['app' => config('app.name')]);
        $corpo = __("Este é um email de teste enviado através do servidor :host:\n\nPorta: :porta\nEncriptação: :enc\nRemetente: :de\n\nSe o recebeu, o servidor está a funcionar.\n\nData e hora: :quando", [
            'host' => $s->host, 'porta' => $s->port, 'enc' => $s->encryption,
            'de' => $s->from_email, 'quando' => now()->format('d/m/Y H:i:s'),
        ]);

        $registo = EmailLog::createLog([
            'tenant_id' => null,
            'email_template_id' => null,
            'smtp_setting_id' => $s->id,
            'to_email' => $d['email'],
            'from_email' => $s->from_email,
            'from_name' => $s->from_name,
            'subject' => $assunto,
            'body_preview' => Str::limit($corpo, 200),
            'template_slug' => 'smtp_test',
            'template_data' => ['host' => $s->host, 'port' => $s->port, 'encryption' => $s->encryption],
        ]);

        try {
            $s->configure();

            Mail::raw($corpo, fn ($m) => $m->to($d['email'])->subject($assunto));

            $registo?->markAsSent();
        } catch (\Throwable $e) {
            $registo?->markAsFailed($e->getMessage());
            Log::warning('Email de teste do SMTP falhou', ['smtp_id' => $s->id, 'erro' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'email' => __('O email não saiu: :erro', ['erro' => $e->getMessage()]),
            ]);
        }

        return response()->json(['message' => __('Email de teste enviado para :email.', ['email' => $d['email']])]);
    }

    /**
     * SÓ UMA PADRÃO no mesmo âmbito. A marca é da plataforma (tenant_id nulo)
     * ou de uma empresa — tirá-la a todas apagava a das empresas também.
     */
    private function soUmaPadrao(SmtpSetting $s): void
    {
        SmtpSetting::where('id', '!=', $s->id)
            ->when($s->tenant_id === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $s->tenant_id))
            ->update(['is_default' => false]);
    }
}
