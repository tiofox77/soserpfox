<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Reservation;
use App\Models\Treasury\PaymentMethod;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Invoicing\TaxResolver;
use Illuminate\Support\Collection;

/**
 * AS RESERVAS DO HOTEL — o que não é do modelo nem é do ecrã.
 *
 * As transições de estado (confirmar, dar entrada, cancelar, não compareceu)
 * vivem no MODELO, com a sua tabela de transições, e é lá que devem viver: o
 * check-out fecha a estada e mexe no quarto, e ter isso escrito em dois sítios
 * é ter duas verdades sobre a mesma reserva.
 *
 * O QUE VIVE AQUI é o dinheiro: receber um adiantamento e, se for pedido,
 * emitir o documento fiscal desse adiantamento. É a única coisa do ecrã de
 * reservas que não é uma leitura nem uma transição, e é a que se pode enganar
 * de maneiras caras.
 */
class Reservas
{
    public function __construct(private readonly ModuleInvoiceService $facturacao) {}

    /** Os filtros de data da lista — os mesmos nomes dos scopes do modelo. */
    public const FILTROS_DE_DATA = [
        'today' => 'Criadas hoje',
        'checkin_today' => 'Entram hoje',
        'checkout_today' => 'Saem hoje',
        'current' => 'Hospedados agora',
    ];

    /**
     * RECEBER UM ADIANTAMENTO SOBRE A RESERVA.
     *
     * @return array{reserva: Reservation, factura: ?\App\Models\Invoicing\SalesInvoice}
     */
    public function receber(Reservation $reserva, float $valor, PaymentMethod $meio, bool $facturar): array
    {
        /*
         * O TECTO: NÃO SE RECEBE MAIS DO QUE O DEVIDO.
         *
         * Não havia limite nenhum — um zero a mais ficava gravado como pago,
         * punha a reserva em «Pago» com excesso e, com a caixa de facturar
         * ligada, emitia um documento fiscal por esse valor.
         */
        $saldo = round((float) $reserva->total - (float) $reserva->paid_amount, 2);

        if ($valor > $saldo + 0.01) {
            throw new \DomainException(__('O valor excede o saldo em dívida (:saldo Kz).', [
                'saldo' => number_format($saldo, 2, ',', '.'),
            ]));
        }

        $pagoAgora = (float) $reserva->paid_amount + $valor;

        $factura = $facturar && $reserva->client_id
            ? $this->facturarAdiantamento($reserva, $valor)
            : null;

        /*
         * `invoice_id` SÓ SE TOCA QUANDO HÁ FACTURA NOVA.
         *
         * Escrever `$factura?->id` sem condição punha NULL sempre que se
         * recebia sem facturar, apagando a ligação a um documento fiscal que
         * já existia — a reserva ficava órfã da sua própria factura.
         */
        $dados = [
            'paid_amount' => $pagoAgora,
            'payment_status' => $pagoAgora >= (float) $reserva->total ? 'paid' : 'partial',
            'payment_method' => $meio->name,
        ];

        if ($factura) {
            $dados['invoice_id'] = $factura->id;
        }

        $reserva->update($dados);

        // A FIDELIDADE CONTA O DINHEIRO, e não uma visita nova: quem está a
        // pagar já foi contado quando chegou. `incrementStays()` — que era o
        // que aqui estava — soma uma estada de cada vez que se recebe.
        $reserva->client?->registarGasto($valor);

        return ['reserva' => $reserva->fresh(['client', 'room', 'roomType', 'invoice']), 'factura' => $factura];
    }

    /**
     * O DOCUMENTO FISCAL DO ADIANTAMENTO.
     *
     * Passa pelo emissor único da facturação, como o resto do sistema. A
     * versão que aqui esteve era mais uma cópia da fiscalidade e divergia em
     * tudo o que importa: IVA fixo a 14% (uma empresa isenta cobrava imposto
     * que não pode liquidar) e sem código de isenção; a linha não apontava
     * para artigo nenhum do catálogo; faltavam o `tax_code`, a região fiscal
     * do adquirente e o motivo de isenção. E sobretudo dizia «N noites × preço
     * do quarto» enquanto os totais eram só os do sinal pago — o documento
     * descrevia a estada inteira e cobrava uma fracção dela.
     *
     * O VALOR PAGO É BRUTO: o hóspede entrega X Kz com imposto incluído, e a
     * base tributável sai da taxa que o regime da empresa resolver — não de um
     * 14% assumido.
     */
    public function facturarAdiantamento(Reservation $reserva, float $valor)
    {
        $tenantId = activeTenantId();

        $descricao = __('Adiantamento de reserva - :tipo', [
            'tipo' => $reserva->roomType->name ?? __('Alojamento'),
        ]);

        $artigo = $this->facturacao->produtoDoCatalogo($tenantId, $descricao, 0, 'service');
        $taxa = (float) TaxResolver::forProduct($artigo, $tenantId)['rate'];

        $base = $taxa > 0 ? round($valor / (1 + ($taxa / 100)), 2) : $valor;

        return $this->facturacao->emitir([
            'tenant_id' => $tenantId,
            'client_id' => $reserva->client_id,
            'status' => 'paid',
            'origem_modulo' => 'hotel',
            'origem' => $reserva->reservation_number,
            'lines' => [[
                'product_id' => $artigo->id,
                'name' => $descricao,
                'quantity' => 1,
                'unit_price' => $base,
                'is_service' => true,
            ]],
            'notes' => __('Adiantamento sobre a reserva :n | Check-in: :entrada | Check-out: :saida | :noites noite(s) | A deduzir na fatura final do check-out.', [
                'n' => $reserva->reservation_number,
                'entrada' => $reserva->check_in_date->format('d/m/Y'),
                'saida' => $reserva->check_out_date->format('d/m/Y'),
                'noites' => $reserva->nights,
            ]),
        ]);
    }

    /**
     * O AVISO DAS FACTURAS POR REGULARIZAR.
     *
     * Cancelar uma reserva ou marcá-la como «não compareceu» não faz
     * desaparecer um documento fiscal já emitido — só uma nota de crédito o
     * anula. Dizê-lo, em vez de deixar a estada cancelada e o documento vivo
     * sem ninguém dar por isso.
     */
    public function avisoDeFacturas(Collection $porRegularizar): ?string
    {
        if ($porRegularizar->isEmpty()) {
            return null;
        }

        return __('ATENÇÃO: :n factura(s) por regularizar (:quais) — emita nota de crédito.', [
            'n' => $porRegularizar->count(),
            'quais' => $porRegularizar->pluck('invoice_number')->implode(', '),
        ]);
    }
}
