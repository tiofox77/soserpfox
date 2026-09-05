<?php

namespace App\Models\Projetos;

use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma linha de folha de horas: quem, em que projeto, em que dia, quanto tempo.
 *
 * O `valor_hora` CONGELA no lançamento. Se a tabela de preços mudar amanhã, o
 * que já foi trabalhado continua a valer o que valia — senão uma actualização
 * de preços reescreveria facturas por emitir.
 *
 * Depois de facturada (`facturado_em` preenchido) a linha é história: não se
 * edita nem se apaga, porque está a sustentar uma factura emitida.
 */
class HoraLancada extends Model
{
    use BelongsToTenant;

    protected $table = 'projeto_horas';

    protected $fillable = [
        'tenant_id', 'projeto_id', 'tarefa_id', 'user_id', 'data', 'horas',
        'descricao', 'facturavel', 'valor_hora', 'facturado_em', 'sales_invoice_id',
    ];

    protected $casts = [
        'data' => 'date',
        'horas' => 'decimal:2',
        'valor_hora' => 'decimal:2',
        'facturavel' => 'boolean',
        'facturado_em' => 'datetime',
    ];

    public function projeto()
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class, 'tarefa_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function factura()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function jaFacturada(): bool
    {
        return $this->facturado_em !== null;
    }

    /** O que esta linha vale, ao preço congelado no dia. */
    public function valor(): float
    {
        return round((float) $this->horas * (float) $this->valor_hora, 2);
    }

    /** Por facturar: é facturável, tem preço, e ainda não foi. */
    public function scopePorFacturar($query)
    {
        return $query->where('facturavel', true)
            ->whereNull('facturado_em')
            ->where('valor_hora', '>', 0);
    }
}
