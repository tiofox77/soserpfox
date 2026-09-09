<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Reservation;
use App\Models\Hotel\ReservationItem;
use App\Models\Hotel\Room;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Invoicing\TaxResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O CHECK-OUT — fechar a estada e emitir o documento.
 *
 * É o sítio do módulo onde o dinheiro se torna um documento fiscal, e onde os
 * erros custam mais: um documento a mais na cadeia SAFT só se anula por nota
 * de crédito.
 *
 * A REGRA CENTRAL É NÃO TRIBUTAR A MESMA BASE DUAS VEZES. Quem pagou sinal já
 * tem uma factura; a do check-out não pode voltar a cobrar a estada inteira. E
 * o abatimento NÃO se faz por linha negativa — uma FT com valor negativo é uma
 * estrutura que a AGT não prevê, e a rectificação faz-se por NC/ND. O que se
 * faz é CONSUMIR o adiantamento contra as linhas: a linha coberta desaparece
 * do documento, a parcialmente coberta entra só pelo que falta.
 *
 * ABATE-SE O QUE FOI FACTURADO, nunca o que foi PAGO: pode haver dinheiro
 * recebido sem documento (a caixa de facturar desligada), e abater pagamentos
 * transformaria a sobre-liquidação de IVA numa SUB-liquidação, que é pior.
 */
class FechoDeEstada
{
    public function __construct(private readonly ModuleInvoiceService $facturacao) {}

    /**
     * A CONTA DA ESTADA, tal como será gravada.
     *
     * O ecrã de sempre somava extras SEM imposto a um alojamento que JÁ o
     * incluía e punha o imposto a zero: o total mostrado nunca coincidia com o
     * gravado. O hóspede pagava exactamente o que estava no ecrã e a reserva
     * ficava à mesma em «Parcial», com um saldo fantasma que ninguém conseguia
     * liquidar.
     *
     * @param  list<array{description: string, quantity: float, unit_price: float}>  $novos
     */
    public function conta(Reservation $reserva, array $novos = []): array
    {
        $alojamento = (float) ($reserva->room_rate ?? 0) * max(1, (int) $reserva->nights);

        $consumos = (float) $reserva->items()->sum('total');
        $porLancar = collect($novos)->sum(fn ($x) => (float) $x['quantity'] * (float) $x['unit_price']);

        $desconto = (float) ($reserva->discount ?? 0);
        $base = max(0, $alojamento + $consumos + $porLancar - $desconto);

        // O imposto pelo regime da empresa — o mesmo que o modelo aplica ao
        // gravar, e não um 14% escrito à mão.
        $taxa = (float) TaxResolver::forProduct(null, $reserva->tenant_id ?? activeTenantId())['rate'];
        $imposto = round($base * $taxa / 100, 2);

        $total = $base + $imposto;
        $pago = (float) $reserva->paid_amount;

        $jaFacturadas = $reserva->adiantamentosFacturados();
        $jaFacturado = (float) $jaFacturadas->sum(fn ($f) => max(0, (float) $f->net_total));

        return [
            'alojamento' => $alojamento,
            'consumos' => $consumos + $porLancar,
            'desconto' => $desconto,
            'base' => $base,
            'imposto' => $imposto,
            'total' => $total,
            'pago' => $pago,
            'por_receber' => max(0, $total - $pago),
            /*
             * O QUE JÁ ESTÁ DOCUMENTADO. Comparado com a BASE (sem imposto),
             * porque `net_total` é sem imposto — comparar com o total seria
             * comparar coisas diferentes e a estada nunca parecia coberta.
             */
            'ja_facturado' => $jaFacturado,
            'ja_facturada' => $jaFacturado > 0 && $jaFacturado >= $base,
            'facturas' => $jaFacturadas->map(fn (SalesInvoice $f) => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'data' => $f->invoice_date?->toDateString(),
                'base' => (float) $f->net_total,
                'total' => (float) $f->total,
            ])->values()->all(),
        ];
    }

    /**
     * FECHAR A ESTADA — numa transacção só.
     *
     * @param  list<array{description: string, quantity: float, unit_price: float}>  $novos
     * @return array{reserva: Reservation, factura: ?SalesInvoice, conta: array}
     */
    public function fechar(
        Reservation $reserva,
        array $novos,
        float $pagamento,
        bool $facturar,
        ?string $notas = null,
    ): array {
        // UMA ESTADA SÓ FECHA UMA VEZ. Sem este travão, repetir o check-out
        // voltava a facturar tudo e — pior — a factura final do fecho anterior
        // passava a contar como adiantamento na dedução, embaralhando as
        // contas.
        if ($reserva->status === Reservation::STATUS_CHECKED_OUT) {
            throw new \DomainException(__('Esta reserva já fez check-out em :quando.', [
                'quando' => $reserva->actual_check_out?->format('d/m/Y H:i') ?? '—',
            ]));
        }

        /*
         * O CLIENTE VERIFICA-SE ANTES DE TOCAR EM SEJA O QUE FOR.
         *
         * Sem ele a emissão rebentava DENTRO da transacção, e o rollback
         * desfazia o check-out inteiro: a reserva não passava a fechada, o
         * quarto não ia para limpeza e os consumos lançados desapareciam. O
         * operador via um erro e perdia o trabalho todo.
         */
        if ($facturar && ! $reserva->client_id) {
            throw new \DomainException(__('A reserva não tem hóspede associado — associe um antes de facturar, ou feche a estada sem documento.'));
        }

        $conta = $this->conta($reserva, $novos);

        return DB::transaction(function () use ($reserva, $novos, $pagamento, $facturar, $notas, $conta) {
            foreach ($novos as $extra) {
                ReservationItem::create([
                    'reservation_id' => $reserva->id,
                    'type' => 'other',
                    'category' => 'other',
                    'description' => $extra['description'],
                    'quantity' => $extra['quantity'],
                    'unit_price' => $extra['unit_price'],
                    'date' => now()->toDateString(),
                    'charged_at' => now(),
                    'charged_by' => auth()->id(),
                ]);
            }

            $pagoAgora = $conta['pago'] + $pagamento;

            $reserva->update([
                'status' => Reservation::STATUS_CHECKED_OUT,
                'actual_check_out' => now(),
                'extras_total' => $conta['consumos'],
                'total' => $conta['total'],
                'paid_amount' => $pagoAgora,
                'payment_status' => $pagoAgora >= $conta['total'] ? 'paid' : 'partial',
            ]);

            /*
             * A FIDELIDADE CONTA O QUE SE GASTOU, e não uma visita nova: a
             * visita já foi contada à entrada. O ecrã de sempre chamava
             * `awardLoyalty()` no `guest` — a ficha antiga, que está vazia —
             * pelo que uma estada de um cliente nunca contava para nada.
             */
            $reserva->client?->registarGasto($conta['total']);

            /*
             * O QUARTO FICA SUJO E EM LIMPEZA — os dois campos.
             *
             * O `checkOut()` do modelo põe o `status` em limpeza e deixa o
             * `housekeeping_status` como estava: o quadro da limpeza não via o
             * quarto que acabou de vagar.
             */
            if ($reserva->room_id) {
                Room::where('tenant_id', $reserva->tenant_id)->where('id', $reserva->room_id)
                    ->update(['status' => Room::STATUS_CLEANING, 'housekeeping_status' => 'dirty']);
            }

            $factura = $facturar ? $this->facturar($reserva, $pagamento, $notas) : null;

            return ['reserva' => $reserva->fresh(['client', 'room', 'roomType']), 'factura' => $factura, 'conta' => $conta];
        });
    }

    /**
     * A FACTURA DO FECHO — só do que ainda não foi facturado.
     *
     * A fiscalidade toda (imposto por linha, isenções, região do adquirente,
     * retenção, totais SAFT, hash) vem do emissor partilhado com a facturação:
     * o hotel só descreve o que vendeu.
     */
    private function facturar(Reservation $reserva, float $pagamento, ?string $notas): ?SalesInvoice
    {
        $linhas = [[
            'name' => __('Hospedagem - :tipo', ['tipo' => $reserva->roomType?->name ?? __('Alojamento')]),
            'quantity' => max(1, (int) $reserva->nights),
            'unit_price' => (float) ($reserva->room_rate ?? 0),
            'is_service' => true,
        ]];

        /*
         * OS CONSUMOS LIDOS DA RESERVA, e não do formulário.
         *
         * Quando isto corre, os extras deste ecrã já foram gravados — portanto
         * esta consulta apanha-os a todos, incluindo os lançados no folio
         * durante a estada (minibar, lavandaria, restaurante), que antes NUNCA
         * eram facturados: o check-out só olhava para o que estava no ecrã e o
         * hóspede saía sem pagar os consumos.
         */
        foreach ($reserva->items()->get() as $consumo) {
            $linhas[] = [
                'name' => mb_substr((string) ($consumo->description ?: __('Consumo')), 0, 255),
                'quantity' => (float) $consumo->quantity,
                'unit_price' => (float) $consumo->unit_price,
                'is_service' => true,
            ];
        }

        $desconto = (float) ($reserva->discount ?? 0);

        $adiantamentos = $reserva->adiantamentosFacturados();
        $abatido = (float) $adiantamentos->sum(fn ($a) => max(0, (float) $a->net_total));

        $baseEstada = collect($linhas)->sum(fn ($l) => $l['unit_price'] * $l['quantity']) - $desconto;

        /*
         * O SINAL COBRIU TUDO: não há nada a facturar.
         *
         * Emitir uma FT de total zero é pior do que não emitir — a estada já
         * está integralmente documentada pelos adiantamentos.
         */
        if ($abatido > 0 && $abatido >= $baseEstada) {
            Log::info('Check-out sem nova factura: adiantamentos cobrem a estada', [
                'reserva' => $reserva->reservation_number,
                'abatido' => $abatido,
                'estada' => $baseEstada,
                'documentos' => $adiantamentos->pluck('invoice_number')->all(),
            ]);

            return null;
        }

        if ($abatido > 0) {
            $linhas = $this->consumirAdiantamento($linhas, $abatido);

            $notas = trim(($notas ?: '') . ' ' . __('Adiantamentos já facturados e abatidos: :quais.', [
                'quais' => $adiantamentos->pluck('invoice_number')->implode(', '),
            ]));
        }

        $factura = $this->facturacao->emitir([
            'tenant_id' => $reserva->tenant_id ?? activeTenantId(),
            'client_id' => $reserva->client_id,
            'lines' => $linhas,
            'discount_commercial' => $desconto,
            'status' => $pagamento >= ($baseEstada - $abatido) ? 'paid' : 'partial',
            'origem_modulo' => 'hotel',
            'origem' => $reserva->reservation_number,
            'notes' => $notas ?: __('Reserva: :n', ['n' => $reserva->reservation_number]),
        ]);

        // SÓ O QUE FOI RECEBIDO CONTRA ESTE DOCUMENTO. Somar o que já tinha
        // sido pago (e facturado) no sinal contava o mesmo dinheiro duas vezes
        // nos recebimentos.
        $factura->paid_amount = min($pagamento, (float) $factura->total);
        $factura->save();

        // A ÚLTIMA factura fica na reserva; o histórico está em `invoices()`.
        $reserva->update(['invoice_id' => $factura->id]);

        return $factura;
    }

    /**
     * CONSUMIR O ADIANTAMENTO contra as linhas, sem nunca gerar negativos.
     *
     * A linha totalmente coberta sai do documento; a parcialmente coberta entra
     * só pelo valor em falta (quantidade 1, para o valor unitário continuar a
     * corresponder ao que se cobra); as restantes ficam como estão.
     *
     * @param  list<array>  $linhas
     * @return list<array>
     */
    private function consumirAdiantamento(array $linhas, float $porAbater): array
    {
        $restantes = [];

        foreach ($linhas as $linha) {
            $valor = (float) $linha['unit_price'] * (float) $linha['quantity'];

            if ($porAbater <= 0 || $valor <= 0) {
                $restantes[] = $linha;

                continue;
            }

            if ($porAbater >= $valor) {
                $porAbater -= $valor;

                continue;
            }

            $linha['name'] = $linha['name'] . ' ' . __('(parte remanescente)');
            $linha['quantity'] = 1;
            $linha['unit_price'] = round($valor - $porAbater, 2);
            $porAbater = 0;

            $restantes[] = $linha;
        }

        return $restantes;
    }
}
