<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\ReservationItem;
use App\Models\Treasury\PaymentMethod;
use App\Services\Hotel\FechoDeEstada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O CHECK-OUT E O FOLIO — a conta da estada, e o seu fecho.
 *
 * São o mesmo assunto visto em dois momentos: o FOLIO é a conta a acumular
 * durante a estada (minibar, lavandaria, restaurante); o CHECK-OUT é o fecho,
 * onde ela se torna documento fiscal.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • A LISTA DE QUEM ESTÁ PARA SAIR REBENTAVA A PROCURAR. O filtro procurava
 *    por `rooms.room_number` — uma coluna que não existe (é `number`) — e por
 *    `guest.name`, a ficha antiga que está vazia. Escrever no campo de procura
 *    dava um erro de SQL.
 *  • A FIDELIDADE do hóspede era dada ao `guest` (ficha vazia): uma estada de
 *    um cliente nunca contava para nada.
 *  • O QUARTO ficava em «limpeza» mas o seu estado de limpeza não mudava — o
 *    quadro da governanta não via o quarto que acabou de vagar.
 */
class FechoApiController extends Controller
{
    public function __construct(private readonly FechoDeEstada $fecho) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        return response()->json([
            // A categoria traz o ÍCONE consigo: é assim que ela está declarada
            // no modelo, e o folio pinta cada consumo pelo que ele é.
            'categorias' => collect(ReservationItem::CATEGORIES)
                ->map(fn (array $c, $v) => [
                    'valor' => (string) $v,
                    'rotulo' => __($c['label']),
                    'icone' => $c['icon'],
                    'cor' => $c['color'],
                ])->values(),
            'meios_de_pagamento' => PaymentMethod::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name])->values(),
            'permissoes' => [
                'pode_fechar' => (bool) $request->user()?->can('hotel.checkout.manage'),
                'pode_lancar' => (bool) $request->user()?->can('hotel.reservations.edit'),
            ],
        ]);
    }

    /**
     * QUEM ESTÁ PARA SAIR — em três grupos: atrasados, hoje, depois.
     *
     * Quem já devia ter saído vem primeiro, e é de propósito: é o quarto que a
     * recepção precisa de libertar.
     */
    public function porSair(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $filtros = $request->validate(['procura' => ['nullable', 'string', 'max:100']]);

        $estadas = Reservation::where('tenant_id', activeTenantId())
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->with(['client:id,name,phone', 'room:id,number', 'roomType:id,name'])
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w
                ->where('reservation_number', 'like', "%{$p}%")
                // A COLUNA É `number`. O ecrã de sempre procurava por
                // `room_number`, que não existe: escrever no campo de procura
                // dava um erro de SQL na cara do recepcionista.
                ->orWhereHas('room', fn ($r) => $r->where('number', 'like', "%{$p}%"))
                // E o nome vinha da ficha antiga, que está vazia.
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$p}%"))))
            ->orderBy('check_out_date')
            ->get();

        $linha = fn (Reservation $r) => [
            'id' => $r->id,
            'numero' => $r->reservation_number,
            'hospede' => $r->nome_do_hospede,
            'telefone' => $r->client?->phone,
            'quarto' => $r->room?->number,
            'tipo_de_quarto' => $r->roomType?->name,
            'entrada' => $r->check_in_date?->toDateString(),
            'saida' => $r->check_out_date?->toDateString(),
            'noites' => (int) $r->nights,
            'total' => (float) $r->total,
            'pago' => (float) $r->paid_amount,
            'por_receber' => (float) $r->balance_due,
        ];

        return response()->json([
            'atrasados' => $estadas->filter(fn ($r) => $r->check_out_date->isPast() && ! $r->check_out_date->isToday())
                ->map($linha)->values(),
            'hoje' => $estadas->filter(fn ($r) => $r->check_out_date->isToday())->map($linha)->values(),
            'depois' => $estadas->filter(fn ($r) => $r->check_out_date->isFuture())->map($linha)->values(),
            'total' => $estadas->count(),
        ]);
    }

    /** A conta de uma estada — o folio e o que falta pagar. */
    public function conta(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $reserva = $this->encontrar($id);
        $conta = $this->fecho->conta($reserva);

        return response()->json([
            'reserva' => $this->ficha($reserva),
            'conta' => $conta,
            'consumos' => $this->consumos($reserva),
            'por_categoria' => $this->porCategoria($reserva),
            /*
             * O FOLIO FECHA COM A ESTADA.
             *
             * Depois do check-out continuava a aceitar consumos: o total da
             * reserva subia, o estado de pagamento caía de «Pago» para
             * «Parcial», e ficava um saldo de um hóspede que já tinha ido
             * embora — sem relação nenhuma com a factura já emitida.
             */
            'aberto' => $this->porqueFechou($reserva) === null,
            'porque_fechou' => $this->porqueFechou($reserva),
        ]);
    }

    /** Lançar um consumo no folio. */
    public function lancar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $dados = $request->validate([
            'category' => ['required', Rule::in(array_keys(ReservationItem::CATEGORIES))],
            'description' => ['required', 'string', 'min:2', 'max:200'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $reserva = $this->encontrar($id);

        if ($porque = $this->porqueFechou($reserva)) {
            throw ValidationException::withMessages(['description' => $porque]);
        }

        ReservationItem::create([
            'reservation_id' => $reserva->id,
            'type' => self::TIPOS[$dados['category']] ?? 'other',
            'category' => $dados['category'],
            'description' => $dados['description'],
            'quantity' => $dados['quantity'],
            'unit_price' => $dados['unit_price'],
            'date' => now()->toDateString(),
            'charged_at' => now(),
            'charged_by' => $request->user()?->id,
            'notes' => ($dados['notes'] ?? '') ?: null,
        ]);

        // O TOTAL DA RESERVA SOBE COM O CONSUMO. Sem isto o consumo ficava
        // gravado e o saldo em dívida não o via.
        $this->recalcular($reserva);

        return response()->json([
            'conta' => $this->fecho->conta($reserva->refresh()),
            'consumos' => $this->consumos($reserva),
            'por_categoria' => $this->porCategoria($reserva),
            'message' => __('Consumo lançado no folio.'),
        ], 201);
    }

    public function apagarConsumo(Request $request, int $id, int $consumo): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $reserva = $this->encontrar($id);

        if ($porque = $this->porqueFechou($reserva)) {
            throw ValidationException::withMessages(['consumo' => $porque]);
        }

        ReservationItem::where('reservation_id', $reserva->id)->findOrFail($consumo)->delete();

        $this->recalcular($reserva);

        return response()->json([
            'conta' => $this->fecho->conta($reserva->refresh()),
            'consumos' => $this->consumos($reserva),
            'por_categoria' => $this->porCategoria($reserva),
            'message' => __('Consumo removido.'),
        ]);
    }

    /** FECHAR A ESTADA — e, se for pedido, emitir o documento. */
    public function fechar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.checkout.manage');

        $dados = $request->validate([
            'extras' => ['nullable', 'array', 'max:50'],
            'extras.*.description' => ['required', 'string', 'min:2', 'max:200'],
            'extras.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'extras.*.unit_price' => ['required', 'numeric', 'min:0'],
            'pagamento' => ['required', 'numeric', 'min:0'],
            'meio' => ['nullable', 'integer'],
            'facturar' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $reserva = $this->encontrar($id);

        if (! empty($dados['meio'])) {
            abort_unless(
                PaymentMethod::where('tenant_id', activeTenantId())->whereKey($dados['meio'])->exists(),
                422, __('Esse meio de pagamento não é desta empresa.')
            );
        }

        try {
            $resultado = $this->fecho->fechar(
                $reserva,
                array_values($dados['extras'] ?? []),
                (float) $dados['pagamento'],
                (bool) ($dados['facturar'] ?? false),
                $dados['notas'] ?? null,
            );
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['pagamento' => $e->getMessage()]);
        }

        $factura = $resultado['factura'];

        return response()->json([
            'reserva' => $this->ficha($resultado['reserva']),
            'factura' => $factura ? [
                'id' => $factura->id,
                'numero' => $factura->invoice_number,
                'total' => (float) $factura->total,
            ] : null,
            'message' => ($dados['facturar'] ?? false) && ! $factura
                // Sem factura nova quando os adiantamentos já cobriam a estada
                // — dizê-lo, senão parece que a facturação falhou.
                ? __('Check-out feito. Não se emitiu nova factura: a estada já estava toda facturada nos adiantamentos.')
                : __('Check-out feito.'),
        ]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    /** A categoria do ecrã para o `type` de sempre da tabela. */
    private const TIPOS = [
        'minibar' => 'minibar',
        'room_service' => 'room_service',
        'restaurant' => 'restaurant',
        'laundry' => 'laundry',
        'transfer' => 'transfer',
        'spa' => 'spa',
        'telephone' => 'service',
        'other' => 'other',
    ];

    private function encontrar(int $id): Reservation
    {
        return Reservation::where('tenant_id', activeTenantId())
            ->with(['client', 'room:id,number', 'roomType:id,name'])
            ->findOrFail($id);
    }

    private function recalcular(Reservation $reserva): void
    {
        $reserva->calculateTotals();
        $reserva->saveQuietly();
    }

    private function porqueFechou(Reservation $r): ?string
    {
        return match ($r->status) {
            Reservation::STATUS_CHECKED_OUT => __('Esta estada já fez check-out — o folio está fechado. Para cobrar um consumo em falta, emita um documento próprio na Facturação.'),
            Reservation::STATUS_CANCELLED => __('Reserva cancelada — o folio está fechado.'),
            Reservation::STATUS_NO_SHOW => __('O hóspede não compareceu — o folio está fechado.'),
            default => null,
        };
    }

    private function ficha(Reservation $r): array
    {
        return [
            'id' => $r->id,
            'numero' => $r->reservation_number,
            'hospede' => $r->nome_do_hospede,
            'client_id' => $r->client_id,
            'telefone' => $r->client?->phone,
            'quarto' => $r->room?->number,
            'tipo_de_quarto' => $r->roomType?->name,
            'entrada' => $r->check_in_date?->toDateString(),
            'saida' => $r->check_out_date?->toDateString(),
            'noites' => (int) $r->nights,
            'taxa' => (float) $r->room_rate,
            'adultos' => (int) $r->adults,
            'criancas' => (int) $r->children,
            'estado' => $r->status,
            'estado_rotulo' => __(Reservation::STATUSES[$r->status] ?? (string) $r->status),
            'estado_de_pagamento' => $r->payment_status,
            'saiu_em' => $r->actual_check_out?->toIso8601String(),
            'invoice_id' => $r->invoice_id,
        ];
    }

    private function consumos(Reservation $r): array
    {
        return $r->items()->with('chargedByUser:id,name')
            ->whereNotNull('category')
            ->orderByDesc('charged_at')->orderByDesc('id')
            ->get()
            ->map(fn (ReservationItem $i) => [
                'id' => $i->id,
                'categoria' => $i->category,
                'categoria_rotulo' => __(ReservationItem::CATEGORIES[$i->category]['label'] ?? (string) $i->category),
                'icone' => ReservationItem::CATEGORIES[$i->category]['icon'] ?? 'fa-tag',
                'descricao' => $i->description,
                'quantidade' => (float) $i->quantity,
                'preco' => (float) $i->unit_price,
                'total' => (float) $i->total,
                'quando' => $i->charged_at?->toIso8601String(),
                'quem' => $i->chargedByUser?->name,
                'notas' => $i->notes,
            ])->values()->all();
    }

    private function porCategoria(Reservation $r): array
    {
        return $r->items()->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as quantos, COALESCE(SUM(total), 0) as total')
            ->groupBy('category')->get()
            ->map(fn ($l) => [
                'categoria' => $l->category,
                'rotulo' => __(ReservationItem::CATEGORIES[$l->category]['label'] ?? (string) $l->category),
                'icone' => ReservationItem::CATEGORIES[$l->category]['icon'] ?? 'fa-tag',
                'quantos' => (int) $l->quantos,
                'total' => (float) $l->total,
            ])->values()->all();
    }
}
