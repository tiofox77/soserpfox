<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Guest;
use App\Models\Hotel\LigacaoKiandaStay;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\RoomType;
use App\Services\Invoicing\TaxResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O que chega do KiandaStay vira uma reserva desta casa.
 *
 * REGRA QUE ATRAVESSA TUDO: uma reserva do site NUNCA se perde por causa de
 * configuração em falta. Sem mapa de tipos de quarto, sem hóspede, sem preço —
 * entra na mesma, marcada, e a recepção completa. Recusá-la deixava um hóspede
 * a chegar sem ninguém saber, que é muito pior do que uma reserva incompleta.
 *
 * O evento entra uma única vez e o site não repete (5 s de espera, sem
 * retentativa). Por isso: nada de excepções para fora, e a mesma reserva
 * reentregue actualiza em vez de duplicar — a chave é
 * (empresa, external_source, external_id).
 */
class ReceberDaKiandaStay
{
    /** Os estados do site e os desta casa são os mesmos. Fica escrito, à mesma. */
    private const ESTADOS = [
        'pending'     => Reservation::STATUS_PENDING,
        'confirmed'   => Reservation::STATUS_CONFIRMED,
        'checked_in'  => Reservation::STATUS_CHECKED_IN,
        'checked_out' => Reservation::STATUS_CHECKED_OUT,
        'cancelled'   => Reservation::STATUS_CANCELLED,
        'no_show'     => Reservation::STATUS_NO_SHOW,
    ];

    /**
     * Trata um evento do site.
     *
     * @param  array  $evento  { event, timestamp, data: {...} }
     * @return Reservation|null  a reserva criada ou actualizada
     */
    public function processar(LigacaoKiandaStay $ligacao, array $evento): ?Reservation
    {
        $tipo  = (string) ($evento['event'] ?? '');
        $dados = $evento['data'] ?? [];

        if (! is_array($dados) || empty($dados)) {
            return null;
        }

        // Só as reservas do hotel desta empresa. O site pode ter muitos hotéis
        // e o webhook é global: sem esta guarda, uma empresa via as reservas
        // das outras casas do mesmo site.
        $hotelDoEvento = $dados['hotel']['id'] ?? null;

        if ($ligacao->property_id && $hotelDoEvento && (int) $hotelDoEvento !== (int) $ligacao->property_id) {
            return null;
        }

        $externo = (string) ($dados['confirmation_code'] ?? $dados['id'] ?? '');

        if ($externo === '') {
            return null;
        }

        return DB::transaction(function () use ($ligacao, $tipo, $dados, $externo) {
            $reserva = Reservation::withoutGlobalScopes()
                ->where('tenant_id', $ligacao->tenant_id)
                ->where('external_source', 'kiandastay')
                ->where('external_id', $externo)
                ->lockForUpdate()
                ->first();

            $campos = $this->camposDaReserva($ligacao, $dados, $reserva);

            if ($reserva) {
                // Já cá está: só se actualiza o que o site manda.
                $reserva->fill($campos)->save();
            } else {
                $reserva = new Reservation(array_merge($campos, [
                    'external_source' => 'kiandastay',
                    'external_id'     => $externo,
                ]));

                $reserva->tenant_id       = $ligacao->tenant_id;
                $reserva->external_source = 'kiandastay';
                $reserva->external_id     = $externo;
                $reserva->save();
            }

            $ligacao->forceFill([
                'ultimo_evento_em'  => now(),
                'eventos_recebidos' => (int) $ligacao->eventos_recebidos + 1,
            ])->save();

            Log::info('[KiandaStay] ' . $tipo, [
                'tenant'  => $ligacao->tenant_id,
                'codigo'  => $externo,
                'reserva' => $reserva->id,
            ]);

            return $reserva;
        });
    }

    /* ── A tradução, campo a campo ───────────────────────────────────── */

    private function camposDaReserva(LigacaoKiandaStay $ligacao, array $d, ?Reservation $existente): array
    {
        $entrada = isset($d['check_in']) ? Carbon::parse($d['check_in']) : null;
        $saida   = isset($d['check_out']) ? Carbon::parse($d['check_out']) : null;
        $noites  = ($entrada && $saida) ? max(1, $entrada->diffInDays($saida)) : 1;

        $campos = [
            'room_type_id'     => $this->tipoDeQuarto($ligacao, $d),
            'adults'           => max(1, (int) ($d['guests'] ?? 1)),
            'source'           => 'kiandastay',
            'special_requests' => $d['special_requests'] ?? null,
        ];

        if ($entrada) {
            $campos['check_in_date'] = $entrada->toDateString();
        }

        if ($saida) {
            $campos['check_out_date'] = $saida->toDateString();
        }

        $campos['room_rate'] = $this->precoPorNoite($ligacao, $d, $noites);

        // O estado do site manda — excepto no primeiro contacto, em que a casa
        // pode ter escolhido receber tudo por confirmar.
        $estadoNoSite = self::ESTADOS[$d['status'] ?? ''] ?? null;

        if ($estadoNoSite) {
            $campos['status'] = ($existente === null && $estadoNoSite === Reservation::STATUS_PENDING)
                ? ($ligacao->estado_inicial ?: Reservation::STATUS_PENDING)
                : $estadoNoSite;
        }

        if (($d['payment_status'] ?? null) === 'paid') {
            $campos['payment_status'] = 'paid';
        }

        // O código que o hóspede tem na mão é o do site.
        $campos['confirmation_code'] = (string) ($d['confirmation_code'] ?? '');

        $hospede = $this->hospede($ligacao, $d);

        if ($hospede) {
            $campos['guest_id'] = $hospede->id;
        }

        $campos['internal_notes'] = $this->nota($d, $hospede === null);

        return $campos;
    }

    /**
     * O tipo de quarto desta casa.
     *
     * Pelo mapa, se houver. Senão, o primeiro activo — e fica dito na nota
     * interna, porque a coluna não aceita nulo e uma reserva sem tipo não podia
     * sequer ser gravada.
     */
    private function tipoDeQuarto(LigacaoKiandaStay $ligacao, array $d): int
    {
        $local = $ligacao->tipoDeQuartoLocal($d['room_type_id'] ?? null);

        if ($local) {
            return $local;
        }

        $qualquer = RoomType::withoutGlobalScopes()
            ->where('tenant_id', $ligacao->tenant_id)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->value('id');

        if ($qualquer) {
            return (int) $qualquer;
        }

        // Nem um tipo de quarto tem esta casa ainda. Cria-se um, em vez de
        // deixar cair a reserva: a coluna não aceita nulo, e um hóspede a
        // chegar sem ninguém saber é bem pior do que um tipo por preencher.
        $novo = new RoomType([
            'name'        => __('Por classificar'),
            'code'        => 'KS',
            'description' => __('Criado ao receber a primeira reserva do KiandaStay. Substitua pelos tipos de quarto reais.'),
            'base_price'  => 0,
            'capacity'    => 2,
            'is_active'   => true,
        ]);

        $novo->tenant_id = $ligacao->tenant_id;
        $novo->save();

        return (int) $novo->id;
    }

    /**
     * O preço por noite, SEM imposto.
     *
     * O site anuncia um total que o hóspede aceitou e que já inclui o imposto.
     * Esta casa calcula o total a partir do preço por noite e ACRESCENTA o
     * imposto por cima (`calculateTotals`). Se se pusesse aqui o total do site
     * dividido pelas noites, a factura do check-out saía mais cara do que o
     * hóspede reservou — pelo imposto, duas vezes.
     */
    private function precoPorNoite(LigacaoKiandaStay $ligacao, array $d, int $noites): float
    {
        $total = (float) ($d['total_price'] ?? 0);

        if ($total <= 0) {
            return 0.0;
        }

        $taxa = (float) TaxResolver::forProduct(null, $ligacao->tenant_id)['rate'];

        $semImposto = $taxa > 0 ? $total / (1 + $taxa / 100) : $total;

        return round($semImposto / max(1, $noites), 2);
    }

    /**
     * A ficha do hóspede.
     *
     * O KiandaStay ainda não manda o nome nem o contacto no evento — só o
     * pedido especial, com o telefone lá dentro quando a reserva veio por API.
     * Quando começar a mandar um bloco `customer`, é lido daqui sem mais nada
     * mudar. Enquanto não manda, a reserva entra sem ficha e a nota interna diz
     * onde ir buscar o hóspede.
     */
    private function hospede(LigacaoKiandaStay $ligacao, array $d): ?Guest
    {
        if (! $ligacao->criar_hospede) {
            return null;
        }

        $c = $d['customer'] ?? $d['guest'] ?? null;

        if (! is_array($c) || empty($c['name'])) {
            return null;
        }

        $email    = $c['email'] ?? null;
        $telefone = $c['phone'] ?? null;

        $existente = Guest::withoutGlobalScopes()
            ->where('tenant_id', $ligacao->tenant_id)
            ->when($email, fn ($q) => $q->where('email', $email))
            ->when(! $email && $telefone, fn ($q) => $q->where('phone', $telefone))
            ->first();

        if ($existente) {
            return $existente;
        }

        $novo = new Guest([
            'name'  => $c['name'],
            'email' => $email,
            'phone' => $telefone,
            'notes' => 'Hóspede vindo do KiandaStay.',
        ]);

        $novo->tenant_id = $ligacao->tenant_id;
        $novo->save();

        return $novo;
    }

    /** A nota interna: tudo o que a recepção precisa e o evento não traz. */
    private function nota(array $d, bool $semHospede): string
    {
        $linhas = ['Reserva do KiandaStay — código ' . ($d['confirmation_code'] ?? '?') . '.'];

        if (isset($d['total_price'])) {
            $linhas[] = 'Total acordado no site: '
                . number_format((float) $d['total_price'], 2, ',', '.') . ' ' . ($d['currency'] ?? 'AKZ') . '.';
        }

        if ($semHospede) {
            $linhas[] = 'O site não enviou os dados do hóspede: confirme o nome pelo código, no painel do KiandaStay.';
        }

        return implode(' ', $linhas);
    }
}
