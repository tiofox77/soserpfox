<?php

namespace App\Services\Hotel;

use App\Models\Hotel\LigacaoKiandaStay;
use App\Models\Hotel\Reservation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A conversa com o KiandaStay — o lado de FORA da ligação.
 *
 * O que o site oferece (API v1, estudada no próprio código):
 *
 *   GET  /api/v1/status                 — saúde, público
 *   GET  /api/v1/hotels                 — os hotéis do site, público
 *   GET  /api/v1/hotels/{slug|id}       — detalhe, com os tipos de quarto
 *   POST /api/v1/webhooks               — regista quem recebe os eventos  🔒
 *   GET  /api/v1/webhooks               — os que já estão registados       🔒
 *   POST /api/v1/bookings/{code}/cancel — cancela uma reserva              🔒
 *   GET  /api/v1/bookings/{code}        — consulta uma reserva             🔒
 *
 * O 🔒 é a chave da API no cabeçalho `X-API-Key`.
 *
 * NÃO existe endpoint que liste as reservas de um hotel. Por isso o webhook é o
 * único caminho de entrada, e é preciso tratá-lo como tal: ver a nota em
 * `registarWebhook()`.
 */
class KiandaStay
{
    public function __construct(private LigacaoKiandaStay $ligacao)
    {
    }

    public static function para(LigacaoKiandaStay $ligacao): self
    {
        return new self($ligacao);
    }

    /* ── A conversa ──────────────────────────────────────────────────── */

    private function http(bool $comChave = true): PendingRequest
    {
        $pedido = Http::acceptJson()
            ->timeout(15)
            ->retry(2, 300, throw: false);

        if ($comChave && $this->ligacao->api_key) {
            $pedido = $pedido->withHeaders(['X-API-Key' => $this->ligacao->api_key]);
        }

        return $pedido;
    }

    /** O site está de pé? Não precisa de chave. */
    public function estado(): array
    {
        try {
            $r = $this->http(false)->get($this->ligacao->base() . '/api/v1/status');

            return $r->successful()
                ? ['ok' => true, 'dados' => $r->json()]
                : ['ok' => false, 'erro' => 'HTTP ' . $r->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Os hotéis do site, para a empresa escolher qual é o seu. */
    public function hoteis(string $procura = ''): array
    {
        try {
            $r = $this->http(false)->get($this->ligacao->base() . '/api/v1/hotels', [
                'q'        => $procura ?: null,
                'per_page' => 100,
            ]);

            return $r->successful() ? ($r->json('data') ?? []) : [];
        } catch (\Throwable $e) {
            $this->registarErro('Não foi possível ler os hotéis: ' . $e->getMessage());

            return [];
        }
    }

    /** Os tipos de quarto de um hotel do site, para o mapa. */
    public function tiposDeQuarto($hotel): array
    {
        try {
            $r = $this->http(false)->get($this->ligacao->base() . '/api/v1/hotels/' . $hotel);

            if (! $r->successful()) {
                return [];
            }

            return $r->json('data.room_types') ?? $r->json('room_types') ?? [];
        } catch (\Throwable $e) {
            $this->registarErro('Não foi possível ler os tipos de quarto: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Regista este sistema para receber os eventos do site.
     *
     * É isto que faz a ligação ser AUTOMÁTICA: a partir daqui, cada reserva
     * feita no site entra sozinha na recepção. O `secret` só é devolvido nesta
     * resposta — guarda-se logo, cifrado, porque é ele que prova, em cada
     * evento, que quem chamou foi mesmo o site.
     *
     * AVISO SOBRE O QUE ESTÁ DO OUTRO LADO: o KiandaStay entrega o evento uma
     * única vez, com 5 segundos de espera e sem repetição; ao fim de 10 falhas
     * seguidas desliga o webhook. Uma manutenção mais longa do que isso perde
     * reservas — e como o site não tem endpoint que liste reservas, não há como
     * ir buscá-las depois. Está dito no ecrã da ligação.
     */
    public function registarWebhook(array $eventos = ['reservation.created', 'reservation.status_changed', 'reservation.cancelled']): array
    {
        try {
            $r = $this->http()->post($this->ligacao->base() . '/api/v1/webhooks', [
                'url'    => $this->ligacao->urlDoWebhook(),
                'events' => $eventos,
                'name'   => 'SOS ERP — ' . ($this->ligacao->tenant->name ?? 'Hotel'),
            ]);

            if (! $r->successful()) {
                $erro = $r->json('message') ?? ('HTTP ' . $r->status());
                $this->registarErro('Registo do webhook falhou: ' . $erro);

                return ['ok' => false, 'erro' => $erro];
            }

            $dados = $r->json('data') ?? $r->json();

            $segredo = $dados['secret'] ?? null;

            if (! $segredo) {
                return ['ok' => false, 'erro' => 'O site não devolveu o segredo do webhook.'];
            }

            $this->ligacao->forceFill([
                'webhook_secret' => $segredo,
                'webhook_id'     => (string) ($dados['id'] ?? ''),
                'ultimo_erro'    => null,
            ])->save();

            return ['ok' => true, 'dados' => $dados];
        } catch (\Throwable $e) {
            $this->registarErro('Registo do webhook falhou: ' . $e->getMessage());

            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Troca o bilhete de uma autorização pelo token da ligação.
     *
     * É isto que substitui a chave escrita à mão: o hoteleiro autorizou no
     * site, voltou com um código de dez minutos, e aqui troca-se por um
     * token que vale só para a casa dele. Numa volta fica tudo — token,
     * hotel e o segredo com que os eventos passam a vir assinados.
     */
    public function trocarCodigo(string $codigo): array
    {
        try {
            $r = $this->http(false)->post($this->ligacao->base() . '/api/v1/ligacoes/token', [
                'code'        => $codigo,
                'webhook_url' => $this->ligacao->urlDoWebhook(),
            ]);

            if (! $r->successful()) {
                $erro = $r->json('message') ?? ('HTTP ' . $r->status());
                $this->registarErro('Troca do código falhou: ' . $erro);

                return ['ok' => false, 'erro' => $erro];
            }

            $d = $r->json();

            if (empty($d['token'])) {
                return ['ok' => false, 'erro' => 'O site não devolveu o token da ligação.'];
            }

            $this->ligacao->forceFill([
                'api_key'        => $d['token'],
                'webhook_secret' => $d['webhook_secret'] ?? $this->ligacao->webhook_secret,
                'property_id'    => $d['hotel']['id'] ?? null,
                'property_name'  => $d['hotel']['name'] ?? null,
                'activa'         => true,
                'ultimo_erro'    => null,
            ])->save();

            return ['ok' => true, 'dados' => $d];
        } catch (\Throwable $e) {
            $this->registarErro('Troca do código falhou: ' . $e->getMessage());

            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Os webhooks já registados no site (para ver se o nosso lá está). */
    public function webhooks(): array
    {
        try {
            $r = $this->http()->get($this->ligacao->base() . '/api/v1/webhooks');

            return $r->successful() ? ($r->json('data') ?? $r->json() ?? []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Uma reserva do site, pelo código de confirmação. */
    public function reserva(string $codigo): ?array
    {
        try {
            $r = $this->http()->get($this->ligacao->base() . '/api/v1/bookings/' . $codigo);

            return $r->successful() ? ($r->json('data') ?? $r->json()) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cancela no site uma reserva que a casa cancelou aqui.
     *
     * Sem isto o quarto continuava à venda como reservado no site enquanto a
     * recepção já o tinha libertado.
     */
    public function cancelar(Reservation $reserva, ?string $motivo = null): array
    {
        $codigo = $reserva->external_id ?: $reserva->confirmation_code;

        if (! $codigo) {
            return ['ok' => false, 'erro' => 'A reserva não tem código do site.'];
        }

        try {
            $r = $this->http()->post($this->ligacao->base() . '/api/v1/bookings/' . $codigo . '/cancel', [
                'reason' => $motivo,
            ]);

            if ($r->successful()) {
                return ['ok' => true];
            }

            $erro = $r->json('message') ?? ('HTTP ' . $r->status());
            $this->registarErro('Cancelamento no site falhou: ' . $erro);

            return ['ok' => false, 'erro' => $erro];
        } catch (\Throwable $e) {
            $this->registarErro('Cancelamento no site falhou: ' . $e->getMessage());

            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    private function registarErro(string $mensagem): void
    {
        Log::warning('[KiandaStay] ' . $mensagem, ['tenant' => $this->ligacao->tenant_id]);

        $this->ligacao->forceFill(['ultimo_erro' => mb_substr($mensagem, 0, 500)])->save();
    }
}
