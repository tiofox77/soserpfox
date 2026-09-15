<?php

namespace App\Http\Controllers\Api\Conta;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Audit\AuditRecorder;
use App\Services\Privacidade\Consentimentos;
use App\Services\Privacidade\InventarioDeDados;
use App\Services\Privacidade\OsMeusDados;
use App\Support\Privacidade\Ip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * O SEPARADOR «PRIVACIDADE» DA MINHA CONTA — os direitos do titular, a funcionar.
 *
 *  · VER o que se guarda sobre si: o inventário (o que, para quê, com que
 *    fundamento, durante quanto tempo) e os SEUS dados concretos — sessões
 *    abertas com IP e aparelho, as últimas entradas, as empresas com morada;
 *  · DESCARREGAR tudo em JSON (acesso e portabilidade);
 *  · MUDAR os consentimentos de estatísticas e de marketing;
 *  · TERMINAR as sessões dos outros aparelhos;
 *  · PEDIR rectificação, apagamento, oposição… com prazo de resposta registado.
 *
 * O apagamento não é um botão que apaga: a conta está ligada a documentos
 * fiscais que a lei obriga a guardar. É um pedido, que a plataforma responde
 * dentro do prazo — anonimizando o que puder e dizendo o que tem de ficar.
 */
class PrivacidadeApiController extends Controller
{
    public const TIPOS_DE_PEDIDO = ['acesso', 'rectificacao', 'apagamento', 'oposicao', 'limitacao', 'portabilidade', 'outro'];

    public function mostrar(Request $request, OsMeusDados $dados): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'versao' => Consentimentos::versao(),
            'actualizada_em' => config('privacidade.actualizada_em'),
            'responsavel' => config('privacidade.responsavel'),
            'prazo_de_resposta_dias' => (int) config('privacidade.prazo_de_resposta_dias'),
            'politica' => route('legal.privacidade'),
            'cookies' => route('legal.cookies'),
            'inventario' => InventarioDeDados::categorias(),
            'direitos' => InventarioDeDados::direitos(),
            'dados' => $dados->resumo($u, $request->hasSession() ? $request->session()->getId() : null),
            'escolha_neste_browser' => Consentimentos::doPedido($request),
            'tipos_de_pedido' => collect(self::TIPOS_DE_PEDIDO)->map(fn ($t) => ['valor' => $t, 'rotulo' => self::rotulo($t)])->values(),
        ]);
    }

    /** A cópia de tudo, em JSON — descarrega-se, não se mostra. */
    public function exportar(Request $request, OsMeusDados $dados, AuditRecorder $auditoria): Response
    {
        $u = $request->user();
        $auditoria->acto('privacidade.exportou', activeTenantId(), ['user_id' => $u->id]);

        $nome = 'os-meus-dados-soserp-' . now()->format('Y-m-d') . '.json';

        return response()->json($dados->exportacao($u), 200, [
            'Content-Disposition' => 'attachment; filename="' . $nome . '"',
            'Cache-Control' => 'no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function consentimentos(Request $request): JsonResponse
    {
        $d = $request->validate([
            'estatisticas' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
        ]);

        foreach (['estatisticas', 'marketing'] as $tipo) {
            Consentimentos::registar($tipo, (bool) $d[$tipo], 'minha_conta', $request->user(), null, $request);
        }

        return response()
            ->json([
                'message' => __('Preferências de privacidade guardadas.'),
                'consentimentos' => Consentimentos::actuais($request->user()),
            ])
            ->withCookie(Consentimentos::cookie((bool) $d['estatisticas'], (bool) $d['marketing']));
    }

    /**
     * Fecha as sessões dos outros aparelhos (e, se pedido, os tokens da app).
     *
     * A de quem carrega no botão fica: pôr alguém fora a meio de proteger a
     * própria conta seria uma forma estranha de ajudar.
     */
    public function terminarSessoes(Request $request, AuditRecorder $auditoria): JsonResponse
    {
        $d = $request->validate(['incluir_aplicacao' => ['boolean']]);
        $u = $request->user();
        $actual = $request->hasSession() ? $request->session()->getId() : null;

        $sessoes = Schema::hasTable('sessions')
            ? DB::table('sessions')->where('user_id', $u->id)->when($actual, fn ($q) => $q->where('id', '!=', $actual))->delete()
            : 0;
        $tokens = ! empty($d['incluir_aplicacao']) && Schema::hasTable('api_tokens')
            ? DB::table('api_tokens')->where('user_id', $u->id)->delete()
            : 0;

        $auditoria->acto('privacidade.terminou_sessoes', activeTenantId(), ['sessoes' => $sessoes, 'tokens' => $tokens]);

        return response()->json([
            'message' => trans_choice('{0} Não havia outras sessões abertas.|{1} Terminada :n sessão noutro aparelho.|[2,*] Terminadas :n sessões noutros aparelhos.', $sessoes, ['n' => $sessoes])
                . ($tokens ? ' ' . trans_choice('{1} E :n ligação da aplicação.|[2,*] E :n ligações da aplicação.', $tokens, ['n' => $tokens]) : ''),
            'sessoes' => $sessoes,
            'tokens' => $tokens,
        ]);
    }

    /** Um pedido do titular, com o prazo legal de resposta. */
    public function pedir(Request $request, AuditRecorder $auditoria): JsonResponse
    {
        $d = $request->validate([
            'tipo' => ['required', Rule::in(self::TIPOS_DE_PEDIDO)],
            'mensagem' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => in_array($request->input('tipo'), ['rectificacao', 'oposicao', 'limitacao', 'outro'], true))],
        ], [
            'mensagem.required' => __('Diga-nos o que pretende, para podermos responder.'),
        ]);

        $u = $request->user();
        $prazo = now()->addDays((int) config('privacidade.prazo_de_resposta_dias', 30));

        $id = DB::table('pedidos_de_privacidade')->insertGetId([
            'user_id' => $u->id,
            'nome' => $u->name,
            'email' => $u->email,
            'tipo' => $d['tipo'],
            'mensagem' => $d['mensagem'] ?? null,
            'estado' => 'recebido',
            'prazo_em' => $prazo,
            'ip' => Ip::anonimizar($request->ip()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A plataforma vê-o onde já vê as mensagens: o ecrã de Contactos. Sem
        // isto o pedido existia numa tabela que ninguém abre, e o prazo corria.
        try {
            ContactMessage::create([
                'name' => $u->name,
                'email' => $u->email,
                'company' => optional(activeTenant())->name,
                'message' => __('[Pedido de privacidade #:id — :tipo — responder até :prazo]', ['id' => $id, 'tipo' => self::rotulo($d['tipo']), 'prazo' => $prazo->format('d/m/Y')])
                    . ($d['mensagem'] ? "\n\n" . $d['mensagem'] : ''),
                'status' => 'new',
                'ip_address' => Ip::anonimizar($request->ip()),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $auditoria->acto('privacidade.pediu', activeTenantId(), ['pedido' => $id, 'tipo' => $d['tipo']]);

        return response()->json([
            'message' => __('Pedido registado. Respondemos até :dia.', ['dia' => $prazo->format('d/m/Y')]),
            'id' => $id,
        ], 201);
    }

    public static function rotulo(string $tipo): string
    {
        return match ($tipo) {
            'acesso' => __('Acesso aos dados'),
            'rectificacao' => __('Rectificação'),
            'apagamento' => __('Apagamento da conta e dos dados'),
            'oposicao' => __('Oposição a um tratamento'),
            'limitacao' => __('Limitação do tratamento'),
            'portabilidade' => __('Portabilidade'),
            default => __('Outro pedido'),
        };
    }
}
