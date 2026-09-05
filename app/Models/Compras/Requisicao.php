<?php

namespace App\Models\Compras;

use App\Models\Invoicing\Warehouse;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Requisição de compra (REQ) — o pedido interno.
 *
 * Não sai da empresa e não tem preço fechado: é a passagem de «alguém precisa»
 * para «vamos comprar». O número é por empresa, no formato REQ-AAAA-NNNNNN,
 * sem série fiscal — não é documento da AGT.
 */
class Requisicao extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'compras_requisicoes';

    protected $fillable = [
        'tenant_id',
        'numero',
        'warehouse_id',
        'necessaria_em',
        'estado',
        'justificacao',
        'motivo_recusa',
        'decidida_por',
        'decidida_em',
        'created_by',
    ];

    protected $casts = [
        'necessaria_em' => 'date',
        'decidida_em' => 'datetime',
    ];

    /** Estados por que a requisição passa, e o rótulo que o utilizador lê. */
    public const ESTADOS = [
        'rascunho' => 'Rascunho',
        'submetida' => 'Submetida',
        'aprovada' => 'Aprovada',
        'rejeitada' => 'Rejeitada',
        'encomendada' => 'Encomendada',
        'cancelada' => 'Cancelada',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $req) {
            if (empty($req->numero)) {
                $req->numero = static::gerarNumero($req->tenant_id);
            }
        });
    }

    /**
     * Número sequencial por empresa: REQ-AAAA-NNNNNN.
     *
     * Sem série fiscal de propósito. A unicidade fica garantida pelo índice
     * composto (tenant_id, numero) — ver tenant-scoped-unique-indexes.
     */
    public static function gerarNumero(?int $tenantId): string
    {
        $prefixo = 'REQ-'.now()->year.'-';

        $ultimo = static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('numero', 'like', $prefixo.'%')
            ->orderByDesc('id')
            ->first();

        $proximo = $ultimo ? ((int) str_replace($prefixo, '', $ultimo->numero)) + 1 : 1;

        return $prefixo.str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
    }

    public function itens()
    {
        return $this->hasMany(RequisicaoItem::class, 'requisicao_id')->orderBy('ordem');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decisor()
    {
        return $this->belongsTo(User::class, 'decidida_por');
    }

    public function encomendas()
    {
        return $this->hasMany(Encomenda::class, 'requisicao_id');
    }

    /** Só um rascunho se edita; depois de submetida a requisição está em jogo. */
    public function podeEditar(): bool
    {
        return $this->estado === 'rascunho';
    }

    /** Aprovada e ainda com linhas por encomendar. */
    public function podeEncomendar(): bool
    {
        return in_array($this->estado, ['aprovada', 'encomendada'], true)
            && $this->itens->contains(fn ($i) => $i->porEncomendar() > 0);
    }

    public function estadoRotulo(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
