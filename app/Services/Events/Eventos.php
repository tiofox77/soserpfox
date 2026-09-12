<?php

namespace App\Services\Events;

use App\Models\Events\Checklist;
use App\Models\Events\Event;
use Illuminate\Validation\ValidationException;

/**
 * AS REGRAS DE UM EVENTO — a porta única.
 *
 * Um evento tem DUAS coisas que parecem a mesma e não são: o ESTADO (orçamento,
 * confirmado, concluído — o que o cliente sabe) e a FASE (planeamento,
 * pré-produção, montagem, operação, desmontagem — onde a equipa está). O ecrã
 * antigo deixava mexer nos dois à mão e sem ordem nenhuma:
 *
 *  · dava para pôr um evento CONCLUÍDO de volta em ORÇAMENTO, e com isso apagar
 *    a data de conclusão que a facturação usa;
 *  · e dava para o CANCELAR e continuar a avançar-lhe as fases, montando um
 *    evento que já ninguém ia fazer.
 *
 * A FASE ANDA PARA A FRENTE, uma de cada vez, e só quando as tarefas
 * obrigatórias da fase actual estão feitas. Isso já existia; o que não existia
 * era o mesmo cuidado no estado.
 */
class Eventos
{
    /**
     * De que estado se pode ir para qual.
     *
     * O cancelamento é o único que se alcança de quase todo o lado — um evento
     * cai por terra em qualquer altura. O que NÃO se faz é ressuscitar um
     * concluído ou um cancelado: isso é um evento novo.
     */
    public const TRANSICOES = [
        'orcamento' => ['confirmado', 'cancelado'],
        'confirmado' => ['em_montagem', 'em_andamento', 'cancelado'],
        'em_montagem' => ['em_andamento', 'cancelado'],
        'em_andamento' => ['concluido', 'cancelado'],
        'concluido' => [],
        'cancelado' => [],
    ];

    public const ESTADOS = [
        'orcamento' => 'Orçamento',
        'confirmado' => 'Confirmado',
        'em_montagem' => 'Em Montagem',
        'em_andamento' => 'Em Andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];

    public const FASES = [
        'planejamento' => 'Planeamento',
        'pre_producao' => 'Pré-Produção',
        'montagem' => 'Montagem',
        'operacao' => 'Operação',
        'desmontagem' => 'Desmontagem',
        'concluido' => 'Concluído',
    ];

    /** O ícone de cada estado — a cor sozinha não chega a quem não a distingue. */
    public const ICONE_DO_ESTADO = [
        'orcamento' => 'fa-file-lines',
        'confirmado' => 'fa-circle-check',
        'em_montagem' => 'fa-hammer',
        'em_andamento' => 'fa-play',
        'concluido' => 'fa-flag-checkered',
        'cancelado' => 'fa-xmark',
    ];

    /** Os passos que este evento pode dar a seguir, com o rótulo já traduzido. */
    public function seguintes(Event $evento): array
    {
        return collect(self::TRANSICOES[$evento->status] ?? [])
            ->map(fn (string $e) => ['valor' => $e, 'rotulo' => __(self::ESTADOS[$e])])
            ->values()->all();
    }

    /**
     * Muda o estado — e recusa o que não faz sentido.
     *
     * A DATA DE CONCLUSÃO É UM CARIMBO, não um campo: põe-se quando o evento
     * acaba e não se apaga. Voltar atrás apagava-a, e o relatório do mês
     * passava a mentir.
     */
    public function estado(Event $evento, string $novo): Event
    {
        $possiveis = self::TRANSICOES[$evento->status] ?? [];

        if (! in_array($novo, $possiveis, true)) {
            throw ValidationException::withMessages([
                'estado' => [__('Um evento :de não passa a :para.', [
                    'de' => __(self::ESTADOS[$evento->status] ?? $evento->status),
                    'para' => __(self::ESTADOS[$novo] ?? $novo),
                ])],
            ]);
        }

        $evento->status = $novo;

        if ($novo === 'confirmado' && ! $evento->confirmed_at) {
            $evento->confirmed_at = now();
        }

        if ($novo === 'concluido') {
            $evento->completed_at = now();
            $evento->phase = 'concluido';
        }

        $evento->save();

        return $evento->fresh();
    }

    /**
     * Avança uma fase.
     *
     * A guarda das tarefas obrigatórias já estava no modelo; o que faltava era
     * recusar avançar um evento CANCELADO — montar o que ninguém vai fazer.
     */
    public function avancarFase(Event $evento): Event
    {
        if (in_array($evento->status, ['cancelado', 'concluido'], true)) {
            throw ValidationException::withMessages([
                'fase' => [__('Um evento :estado não avança de fase.', [
                    'estado' => __(self::ESTADOS[$evento->status] ?? $evento->status),
                ])],
            ]);
        }

        if (! $evento->canAdvancePhase()) {
            throw ValidationException::withMessages([
                'fase' => [__('Faltam tarefas obrigatórias nesta fase.')],
            ]);
        }

        if (! $evento->advanceToNextPhase()) {
            throw ValidationException::withMessages([
                'fase' => [__('O evento já está na última fase.')],
            ]);
        }

        $evento->updateChecklistProgress();

        return $evento->fresh();
    }

    /**
     * Marca ou desmarca uma tarefa.
     *
     * `events_checklists` NÃO TEM `tenant_id`: pendura-se no evento. A procura
     * tem de passar pelo evento — um id vindo do browser dava para marcar as
     * tarefas do evento de outra empresa.
     */
    public function alternarTarefa(int $id): Checklist
    {
        $tarefa = Checklist::whereHas('event', fn ($q) => $q->where('tenant_id', activeTenantId()))
            ->findOrFail($id);

        if ($tarefa->status === 'concluido') {
            $tarefa->status = 'pendente';
            $tarefa->completed_at = null;
            $tarefa->save();
        } else {
            $tarefa->markAsCompleted();
        }

        $tarefa->event?->updateChecklistProgress();

        return $tarefa->fresh();
    }
}
