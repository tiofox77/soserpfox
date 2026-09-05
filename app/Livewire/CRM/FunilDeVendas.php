<?php

namespace App\Livewire\CRM;

use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O funil: as oportunidades abertas em colunas, uma por etapa.
 *
 * É o quadro da parede — o que se vê numa reunião de segunda-feira. Cada
 * coluna soma o que lá está; mover um cartão é um toque na seta, e a
 * probabilidade acompanha a etapa para o valor ponderado não mentir.
 */
#[Layout('layouts.app')]
#[Title('Funil de Vendas - CRM')]
class FunilDeVendas extends Component
{
    /** Move a oportunidade uma etapa para o lado. */
    public function mover(int $id, string $direccao): void
    {
        $tenantId = activeTenantId();

        $o = Opportunity::where('tenant_id', $tenantId)->where('status', 'open')->findOrFail($id);

        $etapas = Stage::doTenant($tenantId)->values();
        $indice = $etapas->search(fn ($e) => (int) $e->id === (int) $o->stage_id);

        $destino = $etapas->get($direccao === 'frente' ? $indice + 1 : $indice - 1);

        if (! $destino) {
            return;
        }

        // A probabilidade acompanha a etapa: é ela que faz o valor ponderado
        // do funil dizer a verdade sem ninguém estimar à mão.
        $o->update(['stage_id' => $destino->id, 'probability' => (int) $destino->probability]);
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $etapas = Stage::doTenant($tenantId);

        $abertas = Opportunity::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->with(['client:id,name', 'assignee:id,name'])
            ->orderByDesc('amount')
            ->get()
            ->groupBy('stage_id');

        $colunas = $etapas->map(fn ($etapa) => [
            'etapa' => $etapa,
            'cartoes' => $abertas->get($etapa->id, collect()),
            'total' => (float) $abertas->get($etapa->id, collect())->sum('amount'),
            'ponderado' => (float) $abertas->get($etapa->id, collect())->sum(fn ($o) => $o->weighted_amount),
        ]);

        return view('livewire.crm.funil-de-vendas', compact('colunas'));
    }
}
