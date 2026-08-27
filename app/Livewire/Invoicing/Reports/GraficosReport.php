<?php

namespace App\Livewire\Invoicing\Reports;

use App\Services\Invoicing\Analytics\GraficosDeFacturacao;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Relatório em gráficos: o mesmo que os outros relatórios contam em tabelas,
 * mas visto de relance.
 *
 * Não substitui nenhum deles — quem precisa de conferir número a número vai ao
 * mapa. Isto serve a pergunta anterior a essa: onde é que vale a pena olhar.
 */
#[Layout('layouts.app')]
#[Title('Relatório em Gráficos')]
class GraficosReport extends Component
{
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
            'mes'       => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()],
            'trimestre' => [$hoje->copy()->subMonthsNoOverflow(2)->startOfMonth(), $hoje->copy()->endOfMonth()],
            'ano'       => [$hoje->copy()->startOfYear(), $hoje->copy()->endOfYear()],
            'ano_passado' => [
                $hoje->copy()->subYear()->startOfYear(),
                $hoje->copy()->subYear()->endOfYear(),
            ],
            default     => [$hoje->copy()->startOfYear(), $hoje->copy()->endOfYear()],
        };

        $this->de = $de->format('Y-m-d');
        $this->ate = $ate->format('Y-m-d');
    }

    /** Datas escritas à mão deixam de corresponder a um atalho. */
    public function updatedDe(): void
    {
        $this->atalho = '';
        $this->corrigirIntervalo();
    }

    public function updatedAte(): void
    {
        $this->atalho = '';
        $this->corrigirIntervalo();
    }

    /**
     * Fim antes do início devolve gráficos vazios e parece avaria. Troca-se em
     * silêncio, que é o que a pessoa queria dizer.
     */
    private function corrigirIntervalo(): void
    {
        if ($this->de && $this->ate && Carbon::parse($this->de)->gt(Carbon::parse($this->ate))) {
            [$this->de, $this->ate] = [$this->ate, $this->de];
        }
    }

    public function render()
    {
        $dados = GraficosDeFacturacao::para(activeTenantId(), $this->de, $this->ate)->tudo();

        return view('livewire.invoicing.reports.graficos-report', [
            'g' => $dados,
        ]);
    }
}
