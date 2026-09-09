<?php

namespace App\Services\Hotel;

use App\Models\Hotel\MaintenanceOrder;
use App\Models\Hotel\Staff;

/**
 * A MANUTENÇÃO DO HOTEL — os vocabulários e a mudança de estado.
 *
 * O ecrã em Livewire tinha estas listas espalhadas por cinco `match` no modelo
 * e por outros tantos `<option>` no Blade. Aqui ficam num sítio só, que é o
 * que faz o ecrã, os filtros e o quadro falarem todos a mesma língua.
 *
 * O QUE MUDA COM A MIGRAÇÃO — e é grande: O ECRÃ NUNCA CONSEGUIU GRAVAR UMA
 * ORDEM. O modelo declarava colunas que a tabela não tem e o insert respondia
 * «Unknown column 'type'». Ver a migração de 2026-09-09 e o cabeçalho do
 * modelo.
 */
final class OrdensDeManutencao
{
    public const TIPOS = [
        'preventive' => 'Preventiva',
        'corrective' => 'Correctiva',
        'emergency' => 'Urgência',
    ];

    public const PRIORIDADES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    public const CATEGORIAS = [
        'electrical' => 'Eléctrica',
        'plumbing' => 'Canalização',
        'hvac' => 'Ar condicionado',
        'furniture' => 'Mobiliário',
        'appliance' => 'Equipamentos',
        'structural' => 'Estrutural',
        'other' => 'Outro',
    ];

    public const ESTADOS = [
        'pending' => 'Pendente',
        'in_progress' => 'Em curso',
        'waiting_parts' => 'À espera de peças',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
    ];

    /** As quatro colunas do quadro — «cancelada» não é uma coluna, é um fim. */
    public const COLUNAS_DO_QUADRO = ['pending', 'in_progress', 'waiting_parts', 'completed'];

    /**
     * MUDAR O ESTADO — o ponto único.
     *
     * Passar a «em curso» marca a hora de início; concluir marca a de fim. É
     * daí que sai o tempo REAL do arranjo, e é por isso que estas duas datas
     * não se escrevem à mão em lado nenhum.
     */
    public function aplicarEstado(MaintenanceOrder $ordem, string $novo, array $fecho = []): string
    {
        if ($ordem->status === $novo) {
            return __('O estado já era esse.');
        }

        $dados = ['status' => $novo];

        if ($novo === 'in_progress' && ! $ordem->started_at) {
            $dados['started_at'] = now();
        }

        if ($novo === 'completed') {
            $dados['completed_at'] = now();

            /*
             * O QUE SE ESCREVE AO FECHAR: o que se fez e quanto custou.
             *
             * O ecrã de sempre gravava-os em `resolution_notes` e
             * `actual_cost`, que não são colunas — a ordem nunca fechava com a
             * resolução escrita.
             */
            if (array_key_exists('resolucao', $fecho)) {
                $dados['resolution'] = $fecho['resolucao'] ?: null;
            }

            if (array_key_exists('custo', $fecho)) {
                $dados['cost'] = ($fecho['custo'] ?? '') === '' ? null : (float) $fecho['custo'];
            }
        }

        $ordem->update($dados);

        return match ($novo) {
            'completed' => __('Ordem concluída.'),
            'cancelled' => __('Ordem cancelada.'),
            'in_progress' => __('Ordem em curso.'),
            default => __('Estado alterado.'),
        };
    }

    /**
     * A ORDEM PASSA A MIM — o botão «atribuir-me» do ecrã de sempre.
     *
     * Só funciona para quem TEM ficha de pessoal do hotel ligada à sua conta.
     * O ecrã de sempre não dizia nada quando não havia: o botão parecia
     * partido. Aqui devolve-se a razão.
     */
    public function atribuirA(MaintenanceOrder $ordem, ?int $utilizador, int $tenantId): ?string
    {
        $ficha = Staff::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $utilizador)
            ->first();

        if (! $ficha) {
            return null;
        }

        $ordem->update(['assigned_to' => $ficha->id]);

        return $ficha->name;
    }

    /** O TEMPO REAL do arranjo, em minutos — de quando começou a quando acabou. */
    public static function minutosGastos(MaintenanceOrder $ordem): ?int
    {
        return $ordem->started_at && $ordem->completed_at
            ? (int) $ordem->started_at->diffInMinutes($ordem->completed_at)
            : null;
    }
}
