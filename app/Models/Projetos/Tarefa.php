<?php

namespace App\Models\Projetos;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarefa de um projeto.
 *
 * Nada se apaga: cancelar é um estado, não um DELETE — as horas já lançadas
 * contra ela continuam a valer, e uma tarefa que desapareceu deixa o passado
 * sem explicação.
 */
class Tarefa extends Model
{
    use BelongsToTenant;

    protected $table = 'projeto_tarefas';

    protected $fillable = [
        'tenant_id', 'projeto_id', 'titulo', 'descricao', 'responsavel_id',
        'estado', 'prioridade', 'prazo', 'horas_estimadas', 'concluida_em',
        'ordem', 'created_by',
    ];

    protected $casts = [
        'prazo' => 'date',
        'concluida_em' => 'datetime',
        'horas_estimadas' => 'decimal:2',
    ];

    public const ESTADOS = [
        'por_fazer' => 'Por fazer',
        'em_curso' => 'Em curso',
        'bloqueada' => 'Bloqueada',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ];

    public const PRIORIDADES = [
        'baixa' => 'Baixa',
        'normal' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    /** Estados em que a tarefa ainda conta como trabalho por fazer. */
    public const ABERTAS = ['por_fazer', 'em_curso', 'bloqueada'];

    public function projeto()
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function horas()
    {
        return $this->hasMany(HoraLancada::class, 'tarefa_id');
    }

    public function estadoRotulo(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function prioridadeRotulo(): string
    {
        return self::PRIORIDADES[$this->prioridade] ?? $this->prioridade;
    }

    /** Prazo passado e ainda por fechar. */
    public function estaAtrasada(): bool
    {
        return $this->prazo !== null
            && in_array($this->estado, self::ABERTAS, true)
            && $this->prazo->isPast();
    }

    public function horasLancadas(): float
    {
        return (float) $this->horas()->sum('horas');
    }
}
