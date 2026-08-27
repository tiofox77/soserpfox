<?php

namespace App\Livewire\HR;

use App\Models\HR\Department;
use App\Services\HR\MapaDeIRT as ServicoMapaDeIRT;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mapa de IRT: o imposto retido aos trabalhadores num mês, na forma em que se
 * declara e se paga à AGT.
 *
 * Não recalcula imposto nenhum — lê o que ficou retido no processamento da
 * folha. Se recalculasse, o mapa podia dizer um valor e os recibos que os
 * trabalhadores têm em casa dizerem outro.
 */
#[Layout('layouts.app')]
#[Title('Mapa de IRT')]
class MapaDeIRT extends Component
{
    public int $ano;
    public int $mes;
    public $departamento = '';

    public function mount(): void
    {
        // Abre no mês PASSADO: o IRT retido entrega-se depois do mês fechar,
        // por isso é esse que se está a preparar quando se abre este ecrã.
        $anterior = now()->subMonthNoOverflow();
        $this->ano = (int) $anterior->year;
        $this->mes = (int) $anterior->month;
    }

    public function mesAnterior(): void
    {
        $d = \Carbon\Carbon::create($this->ano, $this->mes, 1)->subMonthNoOverflow();
        $this->ano = (int) $d->year;
        $this->mes = (int) $d->month;
    }

    public function mesSeguinte(): void
    {
        $d = \Carbon\Carbon::create($this->ano, $this->mes, 1)->addMonthNoOverflow();
        $this->ano = (int) $d->year;
        $this->mes = (int) $d->month;
    }

    public function render()
    {
        $mapa = app(ServicoMapaDeIRT::class)->paraMes(
            activeTenantId(),
            $this->ano,
            $this->mes,
            ['departamento' => $this->departamento ?: null]
        );

        return view('livewire.hr.mapa-de-irt', [
            'mapa'          => $mapa,
            'departamentos' => Department::where('tenant_id', activeTenantId())
                ->orderBy('name')->get(),
            'anos'          => range((int) now()->year, (int) now()->year - 6),
            'meses'         => [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ],
        ]);
    }
}
