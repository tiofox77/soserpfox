<?php

namespace App\Livewire\Projetos;

use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel dos Projetos.
 *
 * A pergunta a que responde é «o que está a fugir»: projetos a passar o
 * orçamento, tarefas com o prazo em cima, e horas trabalhadas que ninguém
 * facturou.
 */
#[Layout('layouts.app')]
#[Title('Projetos')]
class Dashboard extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();
        $inicioMes = now()->startOfMonth()->toDateString();

        $resumo = [
            'activos' => Projeto::where('tenant_id', $tenantId)->where('estado', 'activo')->count(),
            'horas_mes' => round((float) HoraLancada::where('tenant_id', $tenantId)
                ->where('data', '>=', $inicioMes)->sum('horas'), 2),
            'tarefas_abertas' => Tarefa::where('tenant_id', $tenantId)
                ->whereIn('estado', Tarefa::ABERTAS)->count(),
            'tarefas_atrasadas' => Tarefa::where('tenant_id', $tenantId)
                ->whereIn('estado', Tarefa::ABERTAS)
                ->whereNotNull('prazo')->whereDate('prazo', '<', now()->toDateString())->count(),
        ];

        // Por facturar em toda a casa: horas facturáveis, com preço, ainda sem
        // factura. É dinheiro trabalhado que ninguém cobrou.
        $porFacturar = HoraLancada::where('tenant_id', $tenantId)->porFacturar()
            ->selectRaw('COALESCE(SUM(horas), 0) h, COALESCE(SUM(horas * valor_hora), 0) v')
            ->first();

        $resumo['horas_por_facturar'] = round((float) ($porFacturar->h ?? 0), 2);
        $resumo['valor_por_facturar'] = round((float) ($porFacturar->v ?? 0), 2);

        // Consumo por projeto activo, numa só consulta — o agregado nunca se
        // guarda em coluna, calcula-se sempre das horas.
        $consumo = HoraLancada::where('tenant_id', $tenantId)
            ->selectRaw('projeto_id, SUM(horas) horas, SUM(horas * COALESCE(valor_hora, 0)) valor')
            ->groupBy('projeto_id')->get()->keyBy('projeto_id');

        $activos = Projeto::where('tenant_id', $tenantId)
            ->whereIn('estado', Projeto::ABERTOS)
            ->with('cliente:id,name')
            ->orderBy('nome')->limit(8)->get()
            ->map(function ($p) use ($consumo) {
                $gasto = (float) ($consumo[$p->id]->valor ?? 0);
                $orcamento = (float) $p->orcamento;

                return [
                    'projeto' => $p,
                    'horas' => (float) ($consumo[$p->id]->horas ?? 0),
                    'gasto' => $gasto,
                    // Sem orçamento não há percentagem — «0%» seria mentira.
                    'percentagem' => $orcamento > 0 ? round($gasto / $orcamento * 100, 1) : null,
                ];
            });

        return view('livewire.projetos.dashboard', [
            'resumo' => $resumo,
            'activos' => $activos,
            'estouros' => $activos->filter(fn ($a) => ($a['percentagem'] ?? 0) > 100)->values(),
            'atrasadas' => Tarefa::where('tenant_id', $tenantId)
                ->whereIn('estado', Tarefa::ABERTAS)
                ->whereNotNull('prazo')->whereDate('prazo', '<', now()->toDateString())
                ->with(['projeto:id,codigo', 'responsavel:id,name'])
                ->orderBy('prazo')->limit(6)->get(),
        ]);
    }
}
