<?php

namespace App\Models\Projetos;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um projeto (PRJ).
 *
 * Não é documento fiscal. O que o distingue de uma lista de tarefas é o
 * dinheiro: tem orçamento, e as horas lançadas contra ele consomem-no. É por
 * isso que o consumido se calcula das HORAS e não se guarda numa coluna —
 * uma coluna de total é uma verdade que envelhece sozinha.
 */
class Projeto extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'projetos';

    protected $fillable = [
        'tenant_id', 'codigo', 'nome', 'client_id', 'responsavel_id', 'estado',
        'data_inicio', 'data_fim_prevista', 'data_fim_real', 'orcamento',
        'valor_hora', 'descricao', 'created_by',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim_prevista' => 'date',
        'data_fim_real' => 'date',
        'orcamento' => 'decimal:2',
        'valor_hora' => 'decimal:2',
    ];

    public const ESTADOS = [
        'rascunho' => 'Rascunho',
        'activo' => 'Activo',
        'em_pausa' => 'Em pausa',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];

    /** Estados em que ainda se trabalha — e portanto se lançam horas. */
    public const ABERTOS = ['activo', 'em_pausa'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $p) {
            if (empty($p->codigo)) {
                $p->codigo = static::gerarCodigo($p->tenant_id);
            }
        });
    }

    /** Código sequencial por empresa: PRJ-AAAA-NNNNNN. */
    public static function gerarCodigo(?int $tenantId): string
    {
        $prefixo = 'PRJ-'.now()->year.'-';

        $ultimo = static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('codigo', 'like', $prefixo.'%')
            ->orderByDesc('id')
            ->first();

        $proximo = $ultimo ? ((int) str_replace($prefixo, '', $ultimo->codigo)) + 1 : 1;

        return $prefixo.str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
    }

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tarefas()
    {
        return $this->hasMany(Tarefa::class, 'projeto_id')->orderBy('ordem');
    }

    public function horas()
    {
        return $this->hasMany(HoraLancada::class, 'projeto_id');
    }

    public function estadoRotulo(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Só se lançam horas contra um projeto onde ainda se trabalha. */
    public function aceitaHoras(): bool
    {
        return in_array($this->estado, self::ABERTOS, true);
    }

    /** Custo consumido: soma das horas pelo valor CONGELADO de cada lançamento. */
    public function consumido(): float
    {
        return (float) $this->horas()->selectRaw('COALESCE(SUM(horas * COALESCE(valor_hora, 0)), 0) v')->value('v');
    }

    public function horasLancadas(): float
    {
        return (float) $this->horas()->sum('horas');
    }

    /**
     * Quanto do orçamento já foi. Devolve null quando não há orçamento —
     * «0%» seria mentira num projeto que nunca teve tecto.
     */
    public function percentagemDoOrcamento(): ?float
    {
        $orcamento = (float) $this->orcamento;

        if ($orcamento <= 0) {
            return null;
        }

        return round($this->consumido() / $orcamento * 100, 1);
    }

    public function acimaDoOrcamento(): bool
    {
        return ($this->percentagemDoOrcamento() ?? 0) > 100;
    }

    /**
     * Quanto deste projeto já foi FACTURADO ao cliente.
     *
     * Sai das horas já facturadas, pelo valor congelado de cada uma — a mesma
     * conta que fez as linhas da factura. Não se lê o total do documento: uma
     * factura leva impostos e pode ser anulada, e o que aqui interessa é o
     * trabalho deste projeto que já saiu para cobrança.
     */
    public function facturado(): float
    {
        return (float) $this->horas()
            ->whereNotNull('facturado_em')
            ->selectRaw('COALESCE(SUM(horas * COALESCE(valor_hora, 0)), 0) v')
            ->value('v');
    }

    /** Horas já facturadas (as que sustentam um documento emitido). */
    public function horasFacturadas(): float
    {
        return (float) $this->horas()->whereNotNull('facturado_em')->sum('horas');
    }

    /**
     * As facturas que nasceram deste projeto.
     *
     * A ligação verdadeira está nas HORAS (`sales_invoice_id`), não no
     * `source_reference` da factura: é a marca que se põe no momento em que se
     * factura, e é ela que impede a dupla facturação. Fechar o laço aqui é o
     * que responde à pergunta que o módulo deixava sem resposta — «o que já
     * cobrei deste projeto?».
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Invoicing\SalesInvoice>
     */
    public function facturas()
    {
        $ids = $this->horas()
            ->whereNotNull('sales_invoice_id')
            ->distinct()
            ->pluck('sales_invoice_id')
            ->filter()
            ->all();

        if (! $ids) {
            return collect();
        }

        return \App\Models\Invoicing\SalesInvoice::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->whereIn('id', $ids)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();
    }
}
