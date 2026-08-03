<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\Guest;
use App\Models\Hotel\ReservationItem;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Treasury\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Checkout extends Component
{
    public $search = '';
    public $reservationId = null;
    public $reservation = null;
    
    // Modal de checkout
    public $showCheckoutModal = false;
    
    // Consumos extras
    public $extras = [];
    public $newExtraDescription = '';
    public $newExtraAmount = 0;
    public $newExtraQuantity = 1;
    
    // Totais
    public $roomTotal = 0;
    public $extrasTotal = 0;
    public $discountAmount = 0;
    public $taxAmount = 0;
    public $grandTotal = 0;
    public $paidAmount = 0;
    public $balanceDue = 0;
    
    // Pagamento
    public $paymentAmount = 0;
    public $paymentMethodId = null;
    public $paymentMethods = [];
    
    // Fatura
    public $generateInvoice = true;
    public $sendEmail = false;
    public $invoiceNotes = '';

    /** Documentos fiscais que esta reserva já tem (adiantamentos). */
    public $facturasExistentes = [];

    /** true quando o que já foi facturado cobre a estadia toda. */
    public $estadiaJaFacturada = false;

    /** Base (sem imposto) já documentada em facturas anteriores. */
    public $valorJaFacturado = 0;

    /** Fomos NÓS que desligámos a emissão (e não o utilizador). */
    public $desligadaAutomaticamente = false;
    
    // Resultado
    public $checkoutComplete = false;
    public $generatedInvoice = null;

    public function mount($reservationId = null)
    {
        if ($reservationId) {
            $this->loadReservation($reservationId);
        }
        $this->loadPaymentMethods();
    }

    protected function loadPaymentMethods()
    {
        $tenantId = activeTenantId();
        $this->paymentMethods = PaymentMethod::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function loadReservation($id)
    {
        $tenantId = activeTenantId();
        
        $this->reservation = Reservation::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with(['guest', 'room', 'roomType'])
            ->first();
            
        if ($this->reservation) {
            $this->reservationId = $id;
            $this->calculateTotals();
            $this->showCheckoutModal = true;
        }
    }

    /**
     * Esta reserva já tem documentos fiscais emitidos?
     *
     * O operador tem de saber ANTES de carregar em "Gerar Fatura": se o sinal
     * já cobre a estadia, emitir outra é criar um documento a mais na cadeia
     * SAFT — só anulável por nota de crédito. Nesse caso o que se quer é
     * reimprimir o que já existe, não emitir.
     */
    protected function verificarFacturasExistentes(): void
    {
        $this->facturasExistentes = $this->reservation
            ? $this->reservation->adiantamentosFacturados()
            : collect();

        $jaFacturado = (float) $this->facturasExistentes->sum(fn ($f) => (float) $f->net_total);

        // Base da estadia SEM imposto — exactamente como createInvoice() monta
        // as linhas. Usar $this->roomTotal seria comparar coisas diferentes:
        // esse vem de reservation->total, que já traz o imposto incluído, e o
        // que já foi facturado é net_total (sem imposto).
        $baseEstadia = max(0, $this->baseEstadiaSemImposto());

        $this->estadiaJaFacturada = $jaFacturado > 0 && $jaFacturado >= $baseEstadia;
        $this->valorJaFacturado   = $jaFacturado;

        // Já está tudo documentado: não propor a emissão de mais um documento.
        if ($this->estadiaJaFacturada) {
            $this->generateInvoice = false;
            $this->desligadaAutomaticamente = true;

            return;
        }

        // Deixou de estar coberta (juntou-se um consumo, por exemplo): repor a
        // emissão. Sem isto a caixa ficava desligada da vez anterior e o extra
        // saía sem factura — precisamente o contrário do que se pretende.
        if ($this->desligadaAutomaticamente) {
            $this->generateInvoice = true;
            $this->desligadaAutomaticamente = false;
        }
    }

    /**
     * Base tributável da estadia (alojamento + extras − desconto), sem imposto.
     *
     * Tem de coincidir com as linhas que createInvoice() envia ao emissor,
     * senão o aviso do ecrã e a decisão de facturar divergem.
     */
    /**
     * Consome o valor já facturado contra as linhas, sem nunca gerar negativos.
     *
     * A linha totalmente coberta sai do documento; a parcialmente coberta entra
     * só pelo valor em falta (quantidade 1, para o valor unitário continuar a
     * corresponder ao que se cobra); as restantes ficam como estão.
     *
     * @param  array<int, array>  $linhas
     * @return array<int, array>
     */
    protected function consumirAdiantamento(array $linhas, float $porAbater): array
    {
        $restantes = [];

        foreach ($linhas as $linha) {
            $valorLinha = (float) $linha['unit_price'] * (float) $linha['quantity'];

            if ($porAbater <= 0 || $valorLinha <= 0) {
                $restantes[] = $linha;
                continue;
            }

            // Linha inteiramente coberta pelo adiantamento: não vai ao documento.
            if ($porAbater >= $valorLinha) {
                $porAbater -= $valorLinha;
                continue;
            }

            // Coberta em parte: fica o que falta.
            $linha['name']       = $linha['name'] . ' (parte remanescente)';
            $linha['quantity']   = 1;
            $linha['unit_price'] = round($valorLinha - $porAbater, 2);
            $porAbater = 0;

            $restantes[] = $linha;
        }

        return $restantes;
    }

    protected function baseEstadiaSemImposto(): float
    {
        // roomTotal e extrasTotal já vêm sem imposto de calculateTotals(), e
        // extrasTotal inclui tanto os consumos do folio (já gravados) como os
        // acrescentados neste modal.
        return max(0, $this->roomTotal + $this->extrasTotal - $this->discountAmount);
    }

    public function searchReservations()
    {
        // Buscar reservas para check-out
    }

    public function openCheckout($id)
    {
        $this->loadReservation($id);
    }

    public function addExtra()
    {
        if (empty($this->newExtraDescription) || $this->newExtraAmount <= 0) {
            return;
        }
        
        $this->extras[] = [
            'description' => $this->newExtraDescription,
            'quantity' => $this->newExtraQuantity,
            'unit_price' => $this->newExtraAmount,
            'total' => $this->newExtraAmount * $this->newExtraQuantity,
        ];
        
        $this->reset(['newExtraDescription', 'newExtraAmount', 'newExtraQuantity']);
        $this->newExtraQuantity = 1;
        $this->calculateTotals();
    }

    public function removeExtra($index)
    {
        unset($this->extras[$index]);
        $this->extras = array_values($this->extras);
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        if (!$this->reservation) return;
        
        // Cálculo alinhado com Reservation::calculateTotals(), que corre no
        // `saving` do modelo e é quem grava o valor final.
        //
        // Antes o ecrã somava extras SEM imposto a um roomTotal que JÁ o
        // incluía (reservation->total) e punha taxAmount a 0. O total mostrado
        // nunca coincidia com o gravado: o hóspede pagava exactamente o que
        // estava no ecrã e a reserva ficava à mesma em "Parcial", com um saldo
        // fantasma que ninguém conseguia liquidar.

        // Alojamento, sem imposto
        $this->roomTotal = (float) ($this->reservation->room_rate ?? 0)
            * (float) ($this->reservation->nights ?: 1);

        // Extras, sem imposto
        $this->extrasTotal = collect($this->extras)->sum('total')
            + (float) $this->reservation->items()->sum('total');

        // Desconto
        $this->discountAmount = (float) ($this->reservation->discount ?? 0);

        $base = max(0, $this->roomTotal + $this->extrasTotal - $this->discountAmount);

        // Imposto pelo regime da empresa — o mesmo que o modelo aplica.
        $taxa = (float) \App\Services\Invoicing\TaxResolver::forProduct(
            null,
            $this->reservation->tenant_id ?? activeTenantId()
        )['rate'];

        $this->taxAmount = round($base * $taxa / 100, 2);

        // Total geral
        $this->grandTotal = $base + $this->taxAmount;
        
        // Já pago
        $this->paidAmount = $this->reservation->paid_amount ?? 0;
        
        // Saldo a pagar
        $this->balanceDue = max(0, $this->grandTotal - $this->paidAmount);
        
        // Valor padrão do pagamento
        $this->paymentAmount = $this->balanceDue;

        // Refazer sempre a seguir: acrescentar um extra aumenta a estadia e
        // pode deixar de estar totalmente coberta pelos adiantamentos.
        $this->verificarFacturasExistentes();
    }

    public function processCheckout()
    {
        if (!$this->reservation) {
            session()->flash('error', 'Reserva não encontrada');
            return;
        }

        // Uma estadia só fecha uma vez.
        //
        // Sem este guarda, repetir o check-out voltava a facturar tudo e — pior
        // — a factura final do fecho anterior passava a contar como
        // adiantamento na dedução, embaralhando as contas.
        if ($this->reservation->status === Reservation::STATUS_CHECKED_OUT) {
            session()->flash('error', 'Esta reserva já fez check-out em '
                . optional($this->reservation->actual_check_out)->format('d/m/Y H:i') . '.');
            return;
        }

        // Validar ANTES de tocar em seja o que for.
        //
        // Sem cliente, o createInvoice() lança lá dentro da transacção e o
        // rollback desfazia o check-out INTEIRO: a reserva não passava a
        // checked_out, o quarto não ia para limpeza e os consumos lançados
        // desapareciam. O operador via só um erro e perdia o trabalho todo.
        if ($this->generateInvoice && !$this->reservation->client_id) {
            session()->flash('error',
                'A reserva não tem cliente associado — associe um cliente antes de faturar, '
                . 'ou desligue "Gerar Fatura" para fechar a estadia sem documento.');
            return;
        }

        $tenantId = activeTenantId();

        try {
            DB::beginTransaction();

            // Persistir extras adicionados no modal como ReservationItems
            foreach ($this->extras as $extra) {
                ReservationItem::create([
                    'reservation_id' => $this->reservation->id,
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

            // Atualizar reserva
            $this->reservation->update([
                'status' => 'checked_out',
                'actual_check_out' => now(),
                'extras_total' => $this->extrasTotal,
                'total' => $this->grandTotal,
                'paid_amount' => $this->paidAmount + $this->paymentAmount,
                'payment_status' => ($this->paidAmount + $this->paymentAmount) >= $this->grandTotal ? 'paid' : 'partial',
            ]);

            // Award loyalty points if guest is linked
            if ($this->reservation->guest_id && $this->reservation->guest) {
                $this->reservation->guest->awardLoyalty($this->grandTotal);
            }

            // Marcar quarto para limpeza
            if ($this->reservation->room_id) {
                Room::find($this->reservation->room_id)->update([
                    'status' => 'cleaning',
                    'housekeeping_status' => 'dirty'
                ]);
            }

            // Gerar fatura se solicitado
            if ($this->generateInvoice) {
                $this->generatedInvoice = $this->createInvoice();
            }

            DB::commit();

            $this->checkoutComplete = true;

            // Sem factura nova quando os adiantamentos já cobriam a estadia —
            // dizê-lo, senão parece que a facturação falhou.
            session()->flash('success', ($this->generateInvoice && !$this->generatedInvoice)
                ? 'Check-out realizado. Não foi emitida nova fatura: a estadia já estava integralmente faturada nos adiantamentos.'
                : 'Check-out realizado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro no checkout', ['error' => $e->getMessage()]);
            session()->flash('error', 'Erro ao processar check-out: ' . $e->getMessage());
        }
    }

    /**
     * Emite a factura do check-out.
     *
     * Estava partida de três formas, e o check-out INTEIRO falhava por causa
     * disso (tudo corre numa transacção e o catch faz rollback: a reserva não
     * passava a checked_out, o quarto não ia para limpeza, os extras
     * desapareciam):
     *
     *  · client_id vinha de $reservation->guest?->client_id — a tabela
     *    hotel_guests NÃO tem essa coluna. Devolvia sempre null e a coluna
     *    client_id da factura é NOT NULL. O cliente está na própria reserva.
     *  · as linhas não passavam product_name, que é NOT NULL, e passavam
     *    'discount' e 'tax', que não existem na tabela.
     *  · o número era gerado por contagem de facturas, ignorando a série
     *    fiscal — repetia-se assim que uma factura fosse apagada e produzia
     *    documentos fora da numeração da AGT.
     */
    protected function createInvoice()
    {
        $tenantId = activeTenantId();

        // O cliente vive na reserva (hotel_reservations.client_id).
        $clientId = $this->reservation->client_id;

        if (!$clientId) {
            throw new \RuntimeException(
                'A reserva não tem cliente associado — associe um cliente antes de facturar.'
            );
        }

        // A fiscalidade toda (imposto por linha, isenções, região do adquirente,
        // retenção, totais SAFT, hash) vem do emissor partilhado com a
        // facturação — o hotel só descreve o que vendeu.
        //
        // A cópia que aqui estava punha tax_amount do documento a 0 enquanto as
        // linhas levavam IVA (netTotal + taxPayable ≠ grossTotal ⇒ a AGT recusa),
        // fixava a região em 'AO' (Cabinda tem regime próprio), não gerava hash
        // SAFT e não ligava nenhuma linha ao catálogo de artigos.
        $linhas = [[
            'name'       => 'Hospedagem - ' . ($this->reservation->roomType?->name ?? 'Alojamento'),
            'quantity'   => $this->reservation->nights ?: 1,
            'unit_price' => (float) ($this->reservation->room_rate ?? 0),
            'is_service' => true,
        ]];

        // Consumos: ler os que estão GRAVADOS na reserva.
        //
        // Quando isto corre, o processCheckout() já persistiu os extras deste
        // modal como ReservationItem, portanto esta consulta apanha-os a todos
        // — incluindo os lançados no folio durante a estadia (minibar,
        // lavandaria, restaurante), que antes NUNCA eram facturados: o
        // check-out só olhava para o array do modal e o hóspede saía sem pagar
        // os consumos.
        foreach ($this->reservation->items()->get() as $consumo) {
            $linhas[] = [
                'name'       => mb_substr((string) ($consumo->description ?: 'Consumo'), 0, 255),
                'quantity'   => (float) $consumo->quantity,
                'unit_price' => (float) $consumo->unit_price,
                'is_service' => true,
            ];
        }

        // ── Facturar apenas o que AINDA NÃO foi facturado ──
        //
        // Sem isto, quem pagava sinal recebia uma FT do sinal e, no check-out,
        // outra FT pela estadia INTEIRA: a mesma base tributada duas vezes, em
        // dois documentos finalizados, encadeados por hash e comunicados à AGT.
        //
        // NUNCA por linha negativa: uma FT com quantidade positiva e valor
        // negativo é uma estrutura que a AGT não prevê para este tipo de
        // documento (a rectificação de valor faz-se por NC/ND, não dentro da
        // factura). Também não serve desconto de documento: o imposto é somado
        // linha a linha ANTES dos descontos, pelo que a base descia e ficava o
        // IVA da estadia inteira.
        //
        // O que se faz é CONSUMIR o adiantamento contra as linhas, por ordem:
        // a linha totalmente coberta desaparece do documento, a parcialmente
        // coberta entra só pelo valor que falta, e as não cobertas ficam
        // intactas. O documento fica com valores todos positivos e a soma certa.
        //
        // Abate-se o que foi FACTURADO, nunca o que foi PAGO: a caixa "gerar
        // fatura" pode estar desmarcada e existir dinheiro recebido sem
        // documento. Abater pagamentos transformaria a sobre-liquidação numa
        // SUB-liquidação de IVA, que é infracção pior.
        $adiantamentos = $this->reservation->adiantamentosFacturados();
        $totalAbatido  = (float) $adiantamentos->sum(fn ($a) => max(0, (float) $a->net_total));

        // Base da estadia antes de abater.
        $baseEstadia = collect($linhas)->sum(fn ($l) => $l['unit_price'] * $l['quantity'])
            - (float) $this->discountAmount;

        // Sinal cobriu tudo: não há nada a facturar. Emitir uma FT de total zero
        // seria pior do que não emitir — a estadia já está integralmente
        // documentada pelos adiantamentos.
        if ($totalAbatido > 0 && $totalAbatido >= $baseEstadia) {
            \Log::info('Check-out sem nova fatura: adiantamentos já cobrem a estadia', [
                'reserva'    => $this->reservation->reservation_number,
                'abatido'    => $totalAbatido,
                'estadia'    => $baseEstadia,
                'documentos' => $adiantamentos->pluck('invoice_number')->all(),
            ]);

            return null;
        }

        if ($totalAbatido > 0) {
            $linhas = $this->consumirAdiantamento($linhas, $totalAbatido);

            $referencias = $adiantamentos->pluck('invoice_number')->implode(', ');
            $this->invoiceNotes = trim(($this->invoiceNotes ?: '')
                . ' Adiantamentos já faturados e abatidos: ' . $referencias . '.');
        }

        $invoice = app(\App\Services\Invoicing\ModuleInvoiceService::class)->emitir([
            'tenant_id'           => $tenantId,
            'client_id'           => $clientId,
            'lines'               => $linhas,
            'discount_commercial' => (float) $this->discountAmount,
            'status'              => $this->paymentAmount >= ($baseEstadia - $totalAbatido) ? 'paid' : 'partial',
            'origem_modulo'       => 'hotel',
            'origem'              => $this->reservation->reservation_number,
            'notes'               => $this->invoiceNotes ?: "Reserva: {$this->reservation->reservation_number}",
        ]);

        // Só o que foi recebido CONTRA ESTE documento. Somar o que já tinha sido
        // pago (e facturado) no sinal contava o mesmo dinheiro duas vezes nos
        // recebimentos.
        $invoice->paid_amount = min((float) $this->paymentAmount, (float) $invoice->total);
        $invoice->save();

        // Atualizar reserva com ID da fatura (a ÚLTIMA; o histórico completo
        // está em $reservation->invoices()).
        $this->reservation->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }

    // generateInvoiceNumber() foi removido de propósito.
    //
    // Gerava o número contando as facturas do ano, o que repete o número assim
    // que uma seja apagada e ignora por completo a série fiscal. A numeração é
    // do foro da AGT: quem a atribui é o boot do modelo SalesInvoice, a partir
    // da série registada — junto com o ATCUD.

    public function closeModal()
    {
        $this->reset(['showCheckoutModal', 'reservation', 'reservationId', 'extras', 'checkoutComplete', 'generatedInvoice']);
    }

    public function newCheckout()
    {
        $this->reset(['showCheckoutModal', 'reservation', 'reservationId', 'extras', 'checkoutComplete', 'generatedInvoice']);
    }

    public function render()
    {
        $tenantId = activeTenantId();

        // Reservas prontas para check-out (checked_in)
        $reservations = Reservation::where('tenant_id', $tenantId)
            ->where('status', 'checked_in')
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('reservation_number', 'like', "%{$this->search}%")
                          ->orWhereHas('guest', fn($g) => $g->where('name', 'like', "%{$this->search}%"))
                          ->orWhereHas('room', fn($r) => $r->where('room_number', 'like', "%{$this->search}%"));
                });
            })
            ->with(['guest', 'room', 'roomType'])
            ->orderBy('check_out_date')
            ->get();

        // Agrupar por data de check-out
        $todayCheckouts = $reservations->filter(fn($r) => Carbon::parse($r->check_out_date)->isToday());
        $overdueCheckouts = $reservations->filter(fn($r) => Carbon::parse($r->check_out_date)->isPast() && !Carbon::parse($r->check_out_date)->isToday());
        $futureCheckouts = $reservations->filter(fn($r) => Carbon::parse($r->check_out_date)->isFuture());

        return view('livewire.hotel.checkout', [
            'todayCheckouts' => $todayCheckouts,
            'overdueCheckouts' => $overdueCheckouts,
            'futureCheckouts' => $futureCheckouts,
            'totalReservations' => $reservations->count(),
        ])->layout('layouts.app');
    }
}
