<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\LigacaoKiandaStay;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\RoomType;
use App\Services\Hotel\KiandaStay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A LIGAÇÃO AO KIANDASTAY — as reservas do site a entrarem sozinhas.
 *
 * Três passos, por esta ordem, porque cada um precisa do anterior: dizer onde
 * fica o site e com que chave; escolher qual dos hotéis do site é esta casa; e
 * LIGAR, que é quando o sistema se regista no site para receber as reservas.
 *
 * O MAPA DOS TIPOS DE QUARTO fica para o fim de propósito: sem ele as reservas
 * entram na mesma, no primeiro tipo da casa. Uma ligação que só funcionasse
 * depois de tudo mapeado ficaria por fazer.
 *
 * A CHAVE DA API NUNCA VOLTA AO BROWSER. Só se grava quando alguém escreve uma
 * nova; o ecrã mostra que existe, e não qual é.
 */
class KiandaStayApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function ligacao(): LigacaoKiandaStay
    {
        return LigacaoKiandaStay::paraTenant(activeTenantId());
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.view');

        $l = $this->ligacao();

        $doSite = ['hoteis' => [], 'tipos' => []];

        if ($l->configurada()) {
            $api = KiandaStay::para($l);

            $doSite['hoteis'] = $api->hoteis();
            $doSite['tipos'] = $l->property_id ? $api->tiposDeQuarto($l->property_id) : [];
        }

        return response()->json([
            'ligacao' => $this->linha($l),
            'do_site' => $doSite,
            'tipos_locais' => RoomType::where('tenant_id', activeTenantId())->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (RoomType $t) => ['valor' => (string) $t->id, 'rotulo' => $t->name])->values(),
            /*
             * AS ÚLTIMAS QUE ENTRARAM PELO SITE.
             *
             * É o que responde a «a ligação está mesmo a funcionar?» — melhor
             * do que qualquer luz verde. O ecrã de sempre lia o nome pela ficha
             * antiga (`hotel_guests`), que está vazia: a lista mostrava as
             * reservas sem dizer de quem eram.
             */
            'ultimas' => Reservation::where('tenant_id', activeTenantId())
                ->where('external_source', 'kiandastay')
                ->with(['client:id,name', 'roomType:id,name'])
                ->latest('id')->limit(10)->get()
                ->map(fn (Reservation $r) => [
                    'id' => $r->id,
                    'numero' => $r->reservation_number,
                    'externa' => $r->external_id,
                    'hospede' => $r->nome_do_hospede,
                    'tipo_de_quarto' => $r->roomType?->name,
                    'entrada' => $r->check_in_date?->toDateString(),
                    'saida' => $r->check_out_date?->toDateString(),
                    'estado' => $r->status,
                    'estado_rotulo' => __(Reservation::STATUSES[$r->status] ?? (string) $r->status),
                    'total' => (float) $r->total,
                    'quando' => $r->created_at?->toIso8601String(),
                ])->values(),
            'permissoes' => [
                'pode_editar' => (bool) $request->user()?->can('hotel.settings.edit'),
            ],
        ]);
    }

    /* ── Passo 1: onde fica o site ────────────────────────────────────── */

    public function credenciais(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate([
            'base_url' => ['required', 'url', 'max:255', new \App\Rules\EnderecoPublico()],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $l = $this->ligacao();
        $l->base_url = rtrim($dados['base_url'], '/');

        // A CHAVE SÓ SE GRAVA QUANDO VEM UMA NOVA: em branco quer dizer «deixa
        // a que lá está», e não «apaga».
        if (! empty($dados['api_key'])) {
            $l->api_key = $dados['api_key'];
        }

        $l->save();

        return response()->json([
            'ligacao' => $this->linha($l->fresh()),
            'message' => __('Credenciais guardadas.'),
        ]);
    }

    /**
     * «ENTRAR COM O KIANDASTAY» — manda autorizar e volta ligado.
     *
     * O `state` é aleatório e fica na sessão: é o que impede alguém de mandar
     * a esta casa o código de uma autorização que não foi ela a pedir.
     */
    public function autorizar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate(['base_url' => ['required', 'url', 'max:255', new \App\Rules\EnderecoPublico()]]);

        $l = $this->ligacao();
        $l->base_url = rtrim($dados['base_url'], '/');
        $l->save();

        $estado = Str::random(40);

        session(['kiandastay_state' => $estado]);

        return response()->json([
            'url' => $l->base() . '/ligar/soserp?' . http_build_query([
                'redirect_uri' => route('hotel.kiandastay.retorno'),
                'state' => $estado,
            ]),
        ]);
    }

    public function testar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.view');

        $diagnostico = KiandaStay::para($this->ligacao())->estado();

        return response()->json([
            'diagnostico' => $diagnostico,
            'message' => $diagnostico['ok']
                ? __('O site respondeu.')
                : __('O site não respondeu: :erro', ['erro' => $diagnostico['erro'] ?? '?']),
        ]);
    }

    /* ── Passo 2: qual dos hotéis do site é esta casa ─────────────────── */

    public function escolherHotel(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate(['property_id' => ['nullable', 'integer']]);

        $l = $this->ligacao();

        abort_unless($l->configurada(), 422, __('Falta o endereço do site ou a chave da API.'));

        $api = KiandaStay::para($l);

        $nome = collect($api->hoteis())->firstWhere('id', (int) ($dados['property_id'] ?? 0))['name'] ?? null;

        $l->forceFill([
            'property_id' => $dados['property_id'] ?: null,
            'property_name' => $nome,
        ])->save();

        return response()->json([
            'ligacao' => $this->linha($l->fresh()),
            'tipos' => $l->property_id ? $api->tiposDeQuarto($l->property_id) : [],
        ]);
    }

    /* ── Passo 3: ligar ───────────────────────────────────────────────── */

    /** Regista este sistema no site — é isto que torna a ligação automática. */
    public function ligar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $l = $this->ligacao();

        abort_unless($l->configurada(), 422, __('Falta o endereço do site ou a chave da API.'));

        $r = KiandaStay::para($l)->registarWebhook();

        abort_unless($r['ok'], 422, __('Não foi possível ligar: :erro', ['erro' => $r['erro'] ?? '?']));

        $l->forceFill(['activa' => true])->save();

        return response()->json([
            'ligacao' => $this->linha($l->fresh()),
            'message' => __('Ligado. As reservas do site passam a entrar sozinhas.'),
        ]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate([
            'activa' => ['nullable', 'boolean'],
            'criar_hospede' => ['nullable', 'boolean'],
            'estado_inicial' => ['required', Rule::in(['pending', 'confirmed'])],
            'mapa_tipos' => ['nullable', 'array'],
            'mapa_tipos.*' => ['nullable', 'integer'],
        ]);

        $tenantId = activeTenantId();

        /*
         * O MAPA APONTA PARA TIPOS DESTA CASA.
         *
         * Os ids vêm do browser e decidem em que quarto entra uma reserva do
         * site: um id de outro hotel punha as reservas a cair num tipo que não
         * é desta casa.
         */
        $meus = RoomType::where('tenant_id', $tenantId)->pluck('id')->all();

        $mapa = collect($dados['mapa_tipos'] ?? [])
            ->filter(fn ($local) => $local && in_array((int) $local, $meus, true))
            ->all();

        $this->ligacao()->forceFill([
            'activa' => (bool) ($dados['activa'] ?? false),
            'criar_hospede' => (bool) ($dados['criar_hospede'] ?? false),
            'estado_inicial' => $dados['estado_inicial'],
            'mapa_tipos' => $mapa,
        ])->save();

        return response()->json([
            'ligacao' => $this->linha($this->ligacao()),
            'message' => __('Guardado.'),
        ]);
    }

    private function linha(LigacaoKiandaStay $l): array
    {
        return [
            'base_url' => (string) $l->base_url,
            // O FACTO, nunca a chave: ela não volta ao browser.
            'tem_chave' => ! empty($l->api_key),
            'activa' => (bool) $l->activa,
            'configurada' => $l->configurada(),
            'criar_hospede' => (bool) $l->criar_hospede,
            'estado_inicial' => $l->estado_inicial ?: 'pending',
            'property_id' => $l->property_id,
            'property_name' => $l->property_name,
            'mapa_tipos' => (array) ($l->mapa_tipos ?: []),
            'webhook_url' => $l->property_id ? route('webhooks.kiandastay', ['tenant' => activeTenantId()]) : null,
        ];
    }
}
