<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\MetaIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A LIGAÇÃO AO META — Facebook, Instagram e WhatsApp desta empresa.
 *
 * OS TOKENS SÃO SEGREDOS E NUNCA VOLTAM AO ECRÃ. Quando já estão configurados,
 * o ecrã diz «configurado» e o campo só os SUBSTITUI se alguém escrever um
 * valor novo: deixar em branco mantém o que lá está. O resto — ids, números,
 * nomes de página — é identificação pública e vê-se normalmente.
 *
 * O TOKEN DE VERIFICAÇÃO GRAVA-SE À PRIMEIRA ABERTURA. Sem isso ficava só em
 * memória e o aperto de mão do Meta falhava enquanto ninguém carregasse em
 * Guardar — um erro que não se explicava a olhar para o ecrã.
 */
class MetaApiController extends Controller
{
    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('crm.integrations.manage'), 403,
            __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request);

        $m = MetaIntegration::paraTenant(activeTenantId());

        if (empty($m->webhook_verify_token)) {
            $m->webhook_verify_token = 'sos-'.Str::random(16);
            $m->save();
        }

        return response()->json([
            'data' => [
                'app_id' => (string) $m->app_id,
                'webhook_verify_token' => (string) $m->webhook_verify_token,

                'facebook_enabled' => (bool) $m->facebook_enabled,
                'facebook_page_id' => (string) $m->facebook_page_id,
                'facebook_page_name' => (string) $m->facebook_page_name,

                'instagram_enabled' => (bool) $m->instagram_enabled,
                'instagram_account_id' => (string) $m->instagram_account_id,
                'instagram_username' => (string) $m->instagram_username,

                'whatsapp_enabled' => (bool) $m->whatsapp_enabled,
                'whatsapp_phone_number_id' => (string) $m->whatsapp_phone_number_id,
                'whatsapp_business_account_id' => (string) $m->whatsapp_business_account_id,
                'whatsapp_display_number' => (string) $m->whatsapp_display_number,

                'criar_leads' => (bool) $m->criar_leads,
                'lead_ads_enabled' => (bool) $m->lead_ads_enabled,
            ],
            /*
             * O QUE JÁ ESTÁ GUARDADO, sem dizer o quê.
             *
             * O ecrã precisa de saber que há um segredo lá dentro para não
             * pedir outra vez o que já está configurado — mas o valor não sai
             * daqui nem para o browser de quem o escreveu.
             */
            'segredos' => [
                'app_secret' => ! empty($m->app_secret),
                'facebook_page_token' => ! empty($m->facebook_page_token),
                'whatsapp_token' => ! empty($m->whatsapp_token),
            ],
            'url_do_webhook' => $m->urlDoWebhook(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request);

        $dados = $request->validate([
            'app_id' => ['nullable', 'string', 'max:100'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'webhook_verify_token' => ['required', 'string', 'min:8', 'max:100'],

            'facebook_enabled' => ['boolean'],
            'facebook_page_id' => ['nullable', 'string', 'max:100'],
            'facebook_page_name' => ['nullable', 'string', 'max:150'],
            'facebook_page_token' => ['nullable', 'string', 'max:1000'],

            'instagram_enabled' => ['boolean'],
            'instagram_account_id' => ['nullable', 'string', 'max:100'],
            'instagram_username' => ['nullable', 'string', 'max:100'],

            'whatsapp_enabled' => ['boolean'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_display_number' => ['nullable', 'string', 'max:40'],
            'whatsapp_token' => ['nullable', 'string', 'max:1000'],

            'criar_leads' => ['boolean'],
            'lead_ads_enabled' => ['boolean'],
        ], [], ['webhook_verify_token' => __('token de verificação')]);

        $m = MetaIntegration::paraTenant(activeTenantId());

        $m->app_id = ($dados['app_id'] ?? '') ?: null;
        $m->webhook_verify_token = $dados['webhook_verify_token'];

        $m->facebook_enabled = (bool) ($dados['facebook_enabled'] ?? false);
        $m->facebook_page_id = ($dados['facebook_page_id'] ?? '') ?: null;
        $m->facebook_page_name = ($dados['facebook_page_name'] ?? '') ?: null;

        $m->instagram_enabled = (bool) ($dados['instagram_enabled'] ?? false);
        $m->instagram_account_id = ($dados['instagram_account_id'] ?? '') ?: null;
        $m->instagram_username = ($dados['instagram_username'] ?? '') ?: null;

        $m->whatsapp_enabled = (bool) ($dados['whatsapp_enabled'] ?? false);
        $m->whatsapp_phone_number_id = ($dados['whatsapp_phone_number_id'] ?? '') ?: null;
        $m->whatsapp_business_account_id = ($dados['whatsapp_business_account_id'] ?? '') ?: null;
        $m->whatsapp_display_number = ($dados['whatsapp_display_number'] ?? '') ?: null;

        $m->criar_leads = (bool) ($dados['criar_leads'] ?? true);
        $m->lead_ads_enabled = (bool) ($dados['lead_ads_enabled'] ?? false);

        // Os segredos só se sobrescrevem se vier um valor novo: em branco
        // mantém o que lá está, e é o que acontece sempre que alguém abre o
        // ecrã só para mudar o nome da página.
        foreach (['app_secret', 'facebook_page_token', 'whatsapp_token'] as $segredo) {
            if (trim($dados[$segredo] ?? '') !== '') {
                $m->{$segredo} = trim($dados[$segredo]);
            }
        }

        $m->save();

        return response()->json(['message' => __('Integração Meta guardada.')]);
    }

    /** Um token de verificação novo — não se grava até se guardar o resto. */
    public function novoToken(Request $request): JsonResponse
    {
        $this->exigir($request);

        return response()->json(['webhook_verify_token' => 'sos-'.Str::random(16)]);
    }

    /**
     * O TESTE PERGUNTA AO GRAPH PELO PRÓPRIO NÚMERO.
     *
     * Confirma que o token e o phone_number_id são válidos e NÃO ENVIA NADA a
     * ninguém — um teste que manda uma mensagem de teste a um cliente é pior do
     * que não ter teste nenhum.
     */
    public function testarWhatsApp(Request $request): JsonResponse
    {
        $this->exigir($request);

        $m = MetaIntegration::paraTenant(activeTenantId());

        if (empty($m->whatsapp_phone_number_id) || empty($m->whatsapp_token)) {
            return response()->json([
                'ok' => false,
                'message' => __('Preencha o Phone Number ID e o Token do WhatsApp e guarde primeiro.'),
            ]);
        }

        try {
            $r = Http::withToken($m->whatsapp_token)
                ->timeout(15)
                ->get("https://graph.facebook.com/v21.0/{$m->whatsapp_phone_number_id}", [
                    'fields' => 'display_phone_number,verified_name,quality_rating',
                ]);

            if ($r->successful()) {
                return response()->json([
                    'ok' => true,
                    'message' => __('Ligado — :nome (:numero).', [
                        'nome' => $r->json('verified_name') ?? '—',
                        'numero' => $r->json('display_phone_number') ?? '—',
                    ]),
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => __('O Meta recusou — :erro', [
                    'erro' => $r->json('error.message') ?? ('HTTP '.$r->status()),
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => __('Não foi possível contactar o Meta — :erro', ['erro' => $e->getMessage()]),
            ]);
        }
    }
}
