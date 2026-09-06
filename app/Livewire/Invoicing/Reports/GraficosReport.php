<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\UsaRelatorio;
use App\Services\Invoicing\Relatorios\Periodo;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Relatório em gráficos: o mesmo que os outros relatórios contam em tabelas,
 * mas visto de relance. Serve a pergunta anterior a "quanto exactamente":
 * onde é que vale a pena olhar. Os números vêm do `Graficos`, partilhado
 * com o React; aqui ficam os atalhos do período.
 */
#[Layout('layouts.app')]
#[Title('Relatório em Gráficos')]
class GraficosReport extends Component
{
    use UsaRelatorio;

    public const RELATORIO = 'charts';

    public string $de = '';
    public string $ate = '';
    public string $atalho = 'ano';

    public function mount(): void
    {
        $this->aplicarAtalho('ano');
    }

    public function aplicarAtalho(string $qual): void
    {
        $this->atalho = $qual;
        $hoje = Carbon::today();

        [$de, $ate] = match ($qual) {
            'mes' => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()],
            'trimestre' => [$hoje->copy()->subMonthsNoOverflow(2)->startOfMonth(), $hoje->copy()->endOfMonth()],
            'ano_passado' => [$hoje->copy()->subYear()->startOfYear(), $hoje->copy()->subYear()->endOfYear()],
            default => [$hoje->copy()->startOfYear(), $hoje->copy()->endOfYear()],
        };

        $this->de = $de->format('Y-m-d');
        $this->ate = $ate->format('Y-m-d');
    }

    /**
     * Datas escritas à mão deixam de corresponder a um atalho. E fim antes
     * do início troca-se em silêncio, pela mesma regra do serviço.
     */
    public function updatedDe(): void
    {
        $this->corrigirIntervalo();
    }

    public function updatedAte(): void
    {
        $this->corrigirIntervalo();
    }

    private function corrigirIntervalo(): void
    {
        $this->atalho = '';
        [$this->de, $this->ate] = Periodo::intervalo('custom', $this->de, $this->ate, 'year');
    }

    public function render()
    {
        // Fim antes do início é trocado em silêncio pelo serviço.
        $dados = $this->dadosDoRelatorio(['dateFrom' => $this->de, 'dateTo' => $this->ate]);

        return view('livewire.invoicing.reports.graficos-report', ['g' => $dados['g']]);
    }
}
