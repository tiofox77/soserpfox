<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * O TERMO DE ENTREGA DE UMA ORDEM — como o carro saiu (15/09/2026, OF-13).
 */
class WorkOrderHandover extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_work_order_handovers';

    protected $fillable = [
        'tenant_id', 'work_order_id', 'mileage_out', 'fuel_level', 'checklist', 'received_by', 'received_by_document',
        'notes', 'signature', 'signed_at', 'signed_hash', 'balance_due', 'user_id',
    ];

    protected $casts = [
        'mileage_out' => 'integer',
        'fuel_level' => 'integer',
        'checklist' => 'array',
        'signed_at' => 'datetime',
        'balance_due' => 'decimal:2',
    ];

    protected $hidden = ['signature'];

    /** O que se confere com o cliente ao entregar. */
    public const CHECKLIST = [
        'trabalho_explicado' => 'Trabalho feito explicado ao cliente',
        'pecas_substituidas' => 'Peças substituídas mostradas ou entregues',
        'teste_estrada' => 'Teste de estrada feito',
        'sem_luzes' => 'Sem luzes de aviso no painel',
        'viatura_limpa' => 'Viatura limpa',
        'objectos_devolvidos' => 'Objectos pessoais no carro',
        'chaves_documentos' => 'Chaves e documentos entregues',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A IMPRESSÃO DO QUE SE ASSINA — km, combustível, o conferido, quem levanta
     * e o total da ordem. As notas internas ficam de fora.
     */
    public function conteudoAssinado(float $total): string
    {
        $conferido = collect($this->checklist ?? [])->map(fn ($c) => (string) $c)->sort()->values()->all();

        return hash('sha256', json_encode([
            $this->mileage_out, $this->fuel_level, $conferido, trim((string) $this->received_by),
            trim((string) $this->received_by_document), number_format($total, 2, '.', ''),
        ]));
    }

    public static function listas(): array
    {
        return [
            'checklist' => collect(self::CHECKLIST)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values()->all(),
        ];
    }
}
