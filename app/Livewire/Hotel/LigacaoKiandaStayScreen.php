<?php

namespace App\Livewire\Hotel;

use App\Models\Hotel\LigacaoKiandaStay;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\RoomType;
use App\Services\Hotel\KiandaStay;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O ecrã que liga esta casa ao KiandaStay.
 *
 * Três passos, por esta ordem, porque cada um precisa do anterior: dizer onde
 * fica o site e com que chave; escolher qual dos hotéis do site é esta casa; e
 * ligar — que é quando o sistema se regista no site para receber as reservas.
 *
 * O mapa dos tipos de quarto fica para o fim de propósito: sem ele as reservas
 * entram na mesma, no primeiro tipo da casa. Uma ligação que só funcionasse
 * depois de tudo mapeado ficaria por fazer.
 */
#[Layout('layouts.app')]
#[Title('KiandaStay — Hotel')]
class LigacaoKiandaStayScreen extends Component
{
    public string $base_url = '';
    public string $api_key = '';
    public bool $activa = false;
    public bool $criar_hospede = true;
    public string $estado_inicial = 'pending';

    public $property_id = null;
    public array $mapa_tipos = [];

    /** O que se leu do site, para escolher. */
    public array $hoteisDoSite = [];
    public array $tiposDoSite = [];

    public ?array $diagnostico = null;

    public function mount(): void
    {
        $l = $this->ligacao();

        $this->base_url       = (string) $l->base_url;
        $this->api_key        = $l->api_key ? '' : '';           // nunca se devolve a chave ao ecrã
        $this->activa         = (bool) $l->activa;
        $this->criar_hospede  = (bool) $l->criar_hospede;
        $this->estado_inicial = $l->estado_inicial ?: 'pending';
        $this->property_id    = $l->property_id;
        $this->mapa_tipos     = $l->mapa_tipos ?: [];

        if ($l->configurada()) {
            $this->carregarDoSite();
        }
    }

    private function ligacao(): LigacaoKiandaStay
    {
        return LigacaoKiandaStay::paraTenant(activeTenantId());
    }

    /**
     * «Entrar com o KiandaStay»: manda autorizar e volta ligado.
     *
     * O `state` é assinado e guardado na sessão — é o que impede alguém de
     * mandar a esta casa um código de uma autorização que não foi ela a
     * pedir.
     */
    public function entrarComKiandaStay()
    {
        $this->validate(['base_url' => 'required|url'], [], ['base_url' => __('endereço do site')]);

        $l = $this->ligacao();
        $l->base_url = rtrim($this->base_url, '/');
        $l->save();

        $estado = \Illuminate\Support\Str::random(40);
        session(['kiandastay_state' => $estado]);

        return redirect()->away($l->base() . '/ligar/soserp?' . http_build_query([
            'redirect_uri' => route('hotel.kiandastay.retorno'),
            'state'        => $estado,
        ]));
    }

    /* ── Passo 1: onde fica o site ───────────────────────────────────── */

    public function guardarCredenciais(): void
    {
        $this->validate([
            'base_url' => 'required|url',
            'api_key'  => 'nullable|string|max:255',
        ], [], [
            'base_url' => __('endereço do site'),
            'api_key'  => __('chave da API'),
        ]);

        $l = $this->ligacao();

        $l->base_url = rtrim($this->base_url, '/');

        // A chave só se grava quando o utilizador escreve uma nova: o ecrã
        // nunca a mostra, para não a devolver ao browser a cada render.
        if ($this->api_key !== '') {
            $l->api_key = $this->api_key;
        }

        $l->save();

        $this->api_key = '';

        $this->carregarDoSite();

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Credenciais guardadas.')]);
    }

    public function testar(): void
    {
        $this->diagnostico = KiandaStay::para($this->ligacao())->estado();

        $this->dispatch('notify', [
            'type'    => $this->diagnostico['ok'] ? 'success' : 'error',
            'message' => $this->diagnostico['ok']
                ? __('O site respondeu.')
                : __('O site não respondeu: :erro', ['erro' => $this->diagnostico['erro'] ?? '?']),
        ]);
    }

    /* ── Passo 2: qual dos hotéis do site é esta casa ─────────────────── */

    public function carregarDoSite(): void
    {
        $l = $this->ligacao();

        if (! $l->configurada()) {
            return;
        }

        $api = KiandaStay::para($l);

        $this->hoteisDoSite = $api->hoteis();

        if ($this->property_id) {
            $this->tiposDoSite = $api->tiposDeQuarto($this->property_id);
        }
    }

    public function updatedPropertyId(): void
    {
        $l = $this->ligacao();

        $nome = collect($this->hoteisDoSite)->firstWhere('id', (int) $this->property_id)['name'] ?? null;

        $l->forceFill([
            'property_id'   => $this->property_id ?: null,
            'property_name' => $nome,
        ])->save();

        $this->tiposDoSite = $this->property_id
            ? KiandaStay::para($l)->tiposDeQuarto($this->property_id)
            : [];
    }

    /* ── Passo 3: ligar ──────────────────────────────────────────────── */

    /**
     * Regista este sistema no site — é isto que torna a ligação automática.
     */
    public function ligar(): void
    {
        $l = $this->ligacao();

        if (! $l->configurada()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Falta o endereço do site ou a chave da API.')]);

            return;
        }

        $r = KiandaStay::para($l)->registarWebhook();

        if (! $r['ok']) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Não foi possível ligar: :erro', ['erro' => $r['erro']])]);

            return;
        }

        $l->forceFill(['activa' => true])->save();
        $this->activa = true;

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Ligado. As reservas do site passam a entrar sozinhas.')]);
    }

    public function guardarOpcoes(): void
    {
        $this->ligacao()->forceFill([
            'activa'         => $this->activa,
            'criar_hospede'  => $this->criar_hospede,
            'estado_inicial' => in_array($this->estado_inicial, ['pending', 'confirmed'], true) ? $this->estado_inicial : 'pending',
            'mapa_tipos'     => array_filter($this->mapa_tipos),
        ])->save();

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Guardado.')]);
    }

    public function render()
    {
        $l = $this->ligacao();

        return view('livewire.hotel.ligacao-kiandastay', [
            'ligacao'    => $l,
            'tiposLocal' => RoomType::where('tenant_id', activeTenantId())->orderBy('name')->get(),
            'ultimas'    => Reservation::where('tenant_id', activeTenantId())
                ->where('external_source', 'kiandastay')
                ->with('guest')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
