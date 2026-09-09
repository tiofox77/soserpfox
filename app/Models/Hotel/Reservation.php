<?php

namespace App\Models\Hotel;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'hotel_reservations';

    protected $fillable = [
        'tenant_id',
        'reservation_number',
        'guest_id',
        'client_id',
        'room_id',
        'room_type_id',
        'check_in_date',
        'check_out_date',
        'check_in_time',
        'check_out_time',
        'actual_check_in',
        'actual_check_out',
        'adults',
        'children',
        'extra_beds',
        'status',
        'source',
        'room_rate',
        'nights',
        'subtotal',
        'extras_total',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'payment_status',
        'payment_method',
        'invoice_id',
        'special_requests',
        'internal_notes',
        'confirmation_code',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'cancelled_at' => 'datetime',
        'room_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CHECKED_IN = 'checked_in';
    const STATUS_CHECKED_OUT = 'checked_out';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    const STATUSES = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_CONFIRMED => 'Confirmada',
        self::STATUS_CHECKED_IN => 'Check-in',
        self::STATUS_CHECKED_OUT => 'Check-out',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_NO_SHOW => 'No-show',
    ];

    const SOURCES = [
        'direct' => 'Directo',
        'website' => 'Website',
        'booking' => 'Booking.com',
        'airbnb' => 'Airbnb',
        'phone' => 'Telefone',
        'email' => 'Email',
        'walk_in' => 'Walk-in',
        'other' => 'Outro',
    ];

    const PAYMENT_STATUSES = [
        'pending' => 'Pendente',
        'partial' => 'Parcial',
        'paid' => 'Pago',
        'refunded' => 'Reembolsado',
    ];

    // Boot
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = activeTenantId();
            }
            if (!$model->reservation_number) {
                $model->reservation_number = $model->generateReservationNumber();
            }
            if (!$model->confirmation_code) {
                $model->confirmation_code = strtoupper(substr(md5(uniqid()), 0, 6));
            }
            if (!$model->created_by) {
                $model->created_by = auth()->id();
            }
            
            // Calcular noites
            if ($model->check_in_date && $model->check_out_date) {
                $model->nights = Carbon::parse($model->check_in_date)->diffInDays(Carbon::parse($model->check_out_date));
            }
        });

        static::saving(function ($model) {
            $model->calculateTotals();
        });
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_NO_SHOW]);
    }

    public function scopeToday($query)
    {
        $today = now()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->where('check_in_date', $today)
                ->orWhere('check_out_date', $today);
        });
    }

    public function scopeCheckingInToday($query)
    {
        return $query->where('check_in_date', now()->toDateString())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeCheckingOutToday($query)
    {
        return $query->where('check_out_date', now()->toDateString())
            ->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeCurrentlyStaying($query)
    {
        return $query->where('status', self::STATUS_CHECKED_IN);
    }

    // Relationships
    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    /** ÚLTIMA factura emitida para esta reserva (ver também invoices()). */
    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }

    /**
     * TODAS as facturas desta reserva (adiantamentos + factura final).
     *
     * `invoice_id` é 1:1 e vai sendo sobrescrito — com dois sinais pagos, a
     * factura do primeiro deixava de ser alcançável. A ligação durável está em
     * (source_module, source_reference) na própria factura.
     *
     * O filtro por tenant_id é obrigatório: a referência não é única
     * globalmente (duas empresas podem ter RES-000001).
     */
    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoicing\SalesInvoice::class, 'source_reference', 'reservation_number')
            ->where('invoicing_sales_invoices.tenant_id', $this->tenant_id)
            ->where('source_module', 'hotel');
    }

    /**
     * Facturas de ADIANTAMENTO já emitidas, excluindo documentos anulados.
     *
     * Chamada ANTES de emitir a factura final: nesse momento tudo o que já
     * existe para esta reserva é, por definição, adiantamento.
     *
     * Não filtrar por `invoice_id`: depois de pagar o sinal é a factura DESSE
     * sinal que lá está, pelo que excluí-la deixava a lista vazia e o
     * check-out voltava a facturar a estadia inteira.
     */
    public function adiantamentosFacturados()
    {
        return $this->invoices()
            ->where('status', '!=', 'cancelled')
            ->where('invoice_status', '!=', 'A')
            ->with('items')
            ->get();
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function items()
    {
        return $this->hasMany(ReservationItem::class, 'reservation_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'cancelled_by');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_CHECKED_IN => 'green',
            self::STATUS_CHECKED_OUT => 'gray',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_NO_SHOW => 'orange',
            default => 'gray',
        };
    }

    public function getSourceLabelAttribute()
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function getPaymentStatusLabelAttribute()
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'pending' => 'yellow',
            'partial' => 'orange',
            'paid' => 'green',
            'refunded' => 'red',
            default => 'gray',
        };
    }

    public function getBalanceDueAttribute()
    {
        return $this->total - $this->paid_amount;
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total, 2, ',', '.') . ' Kz';
    }

    public function getStayDurationAttribute()
    {
        return $this->nights . ' ' . ($this->nights === 1 ? 'noite' : 'noites');
    }

    // Methods
    public function generateReservationNumber()
    {
        $prefix = 'RES';
        $year = now()->format('y');
        $month = now()->format('m');
        $count = static::where('tenant_id', $this->tenant_id ?? activeTenantId())
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        
        return "{$prefix}{$year}{$month}" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals()
    {
        // AS NOITES PRIMEIRO. Isto corre no `saving`, e o `creating` que as
        // calcula só corre DEPOIS: numa reserva criada por código sem passar
        // `nights` — a que entra pelo KiandaStay, por exemplo — o subtotal
        // saía a zero e a estadia ficava gravada como se não custasse nada.
        if (! $this->nights && $this->check_in_date && $this->check_out_date) {
            $this->nights = Carbon::parse($this->check_in_date)->diffInDays(Carbon::parse($this->check_out_date));
        }

        // Calcular subtotal (quarto × noites)
        $this->subtotal = $this->room_rate * $this->nights;
        
        // Calcular extras
        $this->extras_total = $this->items()->sum('total');
        
        // Calcular total antes de impostos.
        //
        // Nunca negativo: um desconto maior do que a estadia punha o total
        // abaixo de zero e, como `paid_amount (0) >= total (negativo)`, a
        // reserva aparecia como PAGA sem ninguém ter pago nada.
        $subtotalWithExtras = max(0, $this->subtotal + $this->extras_total - $this->discount);

        // Imposto pelo regime da empresa, não por um 14% fixo — o mesmo
        // resolvedor que a facturação usa. Numa empresa isenta, este 14%
        // inventava imposto que ela não pode liquidar, e a reserva mostrava ao
        // hóspede um total que a factura depois não confirmava.
        $taxa = (float) \App\Services\Invoicing\TaxResolver::forProduct(
            null,
            $this->tenant_id ?? activeTenantId()
        )['rate'];

        $this->tax = round($subtotalWithExtras * $taxa / 100, 2);

        // Total final
        $this->total = $subtotalWithExtras + $this->tax;

        // Estado de pagamento: as três hipóteses.
        //
        // Faltava o ramo 'pending'. O estado só subia e nunca descia: bastava
        // acrescentar extras depois de a reserva estar "Paga" para ela
        // continuar "Paga" com saldo por receber.
        if ($this->total > 0 && $this->paid_amount >= $this->total) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'pending';
        }
    }

    /**
     * Marca a reserva como "não compareceu".
     *
     * O estado existia (constante, cor e filtro na lista) mas NENHUMA acção o
     * atribuía: um hóspede que não aparecia ficava eternamente "confirmado" e o
     * quarto continuava bloqueado para novas reservas, porque
     * Room::isAvailableForDates() só ignora 'cancelled' e 'no_show'.
     */
    public function marcarNaoCompareceu($userId = null)
    {
        $this->garantirTransicao(self::STATUS_NO_SHOW);

        $this->status = self::STATUS_NO_SHOW;
        $this->cancelled_at = now();
        $this->cancelled_by = $userId ?? auth()->id();
        $this->cancellation_reason = 'Não compareceu (no-show)';
        $this->save();

        // Libertar o quarto, tal como no cancelamento.
        if ($this->room && !$this->actual_check_in
            && in_array($this->room->status, [Room::STATUS_RESERVED, Room::STATUS_OCCUPIED], true)) {
            $this->room->update(['status' => Room::STATUS_AVAILABLE]);
        }

        return $this->invoices()
            ->where('status', '!=', 'cancelled')
            ->where('invoice_status', '!=', 'A')
            ->get();
    }

    /**
     * Transições de estado permitidas.
     *
     * As únicas guardas que existiam eram `@if` na blade — qualquer pedido
     * Livewire forjado, ou um caminho de código que não passasse pela lista,
     * conseguia levar uma reserva cancelada directamente a "entregue".
     */
    public const TRANSICOES = [
        self::STATUS_PENDING     => [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_CONFIRMED   => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_CHECKED_IN  => [self::STATUS_CHECKED_OUT],
        self::STATUS_CHECKED_OUT => [],
        self::STATUS_CANCELLED   => [],
        self::STATUS_NO_SHOW     => [],
    ];

    public function podeTransitarPara(string $novo): bool
    {
        if ($novo === $this->status) {
            return true; // idempotente
        }

        return in_array($novo, self::TRANSICOES[$this->status] ?? [], true);
    }

    /** Lança quando a transição não é permitida. */
    protected function garantirTransicao(string $novo): void
    {
        if (!$this->podeTransitarPara($novo)) {
            throw new \DomainException(
                'Transição não permitida: ' . (self::STATUSES[$this->status] ?? $this->status)
                . ' → ' . (self::STATUSES[$novo] ?? $novo) . '.'
            );
        }
    }

    public function checkIn($roomId = null)
    {
        $this->garantirTransicao(self::STATUS_CHECKED_IN);

        if ($roomId) {
            $this->room_id = $roomId;
        }

        $this->status = self::STATUS_CHECKED_IN;
        $this->actual_check_in = now();
        $this->save();

        // Atualizar status do quarto
        if ($this->room) {
            $this->room->update(['status' => Room::STATUS_OCCUPIED]);
        }

        // Incrementar estadias do hóspede/cliente
        $guestOrClient = $this->client ?? $this->guest;
        if ($guestOrClient && method_exists($guestOrClient, 'incrementStays')) {
            $guestOrClient->incrementStays();
        }
    }

    public function checkOut()
    {
        $this->garantirTransicao(self::STATUS_CHECKED_OUT);

        $this->status = self::STATUS_CHECKED_OUT;
        $this->actual_check_out = now();
        $this->save();

        // Atualizar status do quarto para limpeza
        if ($this->room) {
            $this->room->update(['status' => Room::STATUS_CLEANING]);
        }

        // Notificação de pós-estadia (thank you)
        try {
            $settings = HotelSettings::getForTenant($this->tenant_id);
            if (($settings->notify_post_stay ?? true) && $this->guest && $this->guest->email) {
                $this->guest->notify(new \App\Notifications\Hotel\PostStayThankYou($this));
            }
        } catch (\Throwable $e) {
            \Log::warning('Post-stay notification failed', ['reservation' => $this->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Anula a reserva.
     *
     * NÃO toca em documentos fiscais: uma factura emitida só se anula por nota
     * de crédito. Devolve a lista de documentos que ficam por regularizar, para
     * quem chama poder avisar o operador — antes o cancelamento era silencioso
     * e a estadia ficava cancelada com a factura viva.
     *
     * @return \Illuminate\Support\Collection Facturas que exigem nota de crédito.
     */
    public function cancel($reason = null, $userId = null)
    {
        $this->garantirTransicao(self::STATUS_CANCELLED);

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancelled_by = $userId ?? auth()->id();
        $this->cancellation_reason = $reason;
        $this->save();

        // Libertar o quarto.
        //
        // A condição anterior exigia status === 'reserved', mas NADA no sistema
        // põe um quarto nesse estado: o ramo nunca corria e o quarto ficava
        // bloqueado depois de a reserva ser anulada. Liberta-se também o que
        // estava ocupado por esta reserva, desde que o hóspede não tenha
        // chegado a entrar.
        if ($this->room && in_array($this->room->status, [Room::STATUS_RESERVED, Room::STATUS_OCCUPIED], true)) {
            if (!$this->actual_check_in) {
                $this->room->update(['status' => Room::STATUS_AVAILABLE]);
            }
        }

        // O SITE TEM DE SABER. Uma reserva vinda do KiandaStay que a casa
        // anula aqui continuava, do lado de lá, a ocupar o quarto — e o site
        // deixava de o vender a quem quer que fosse.
        $this->avisarOCanalExterno($reason);

        return $this->invoices()
            ->where('status', '!=', 'cancelled')
            ->where('invoice_status', '!=', 'A')
            ->get();
    }

    /**
     * Devolve ao canal de origem o cancelamento feito aqui.
     *
     * Nunca deita a anulação abaixo: se o site estiver em baixo, a reserva
     * fica anulada na mesma e o erro fica no registo da ligação. Recusar o
     * cancelamento por causa do site seria dar o problema dele a esta casa.
     */
    private function avisarOCanalExterno($motivo = null): void
    {
        if ($this->external_source !== 'kiandastay' || ! $this->external_id) {
            return;
        }

        try {
            $ligacao = \App\Models\Hotel\LigacaoKiandaStay::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->first();

            if ($ligacao && $ligacao->aReceber()) {
                \App\Services\Hotel\KiandaStay::para($ligacao)->cancelar($this, $motivo);
            }
        } catch (\Throwable $e) {
            \Log::warning('[KiandaStay] não foi possível cancelar no site', [
                'reserva' => $this->id,
                'erro'    => $e->getMessage(),
            ]);
        }
    }

    public function confirm()
    {
        $this->garantirTransicao(self::STATUS_CONFIRMED);

        $this->status = self::STATUS_CONFIRMED;
        $this->save();

        try {
            $settings = HotelSettings::getForTenant($this->tenant_id);
            if (($settings->notify_reservation_confirmed ?? true) && $this->guest && $this->guest->email) {
                $this->guest->notify(new \App\Notifications\Hotel\ReservationConfirmed($this));
            }
        } catch (\Throwable $e) {
            \Log::warning('Reservation confirmed notification failed', ['reservation' => $this->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Returns QR URL for check-in (used in voucher / email).
     */
    public function getCheckInQrUrlAttribute(): string
    {
        return url('/hotel/reservations/' . $this->id . '/checkin/' . $this->confirmation_code);
    }
}
