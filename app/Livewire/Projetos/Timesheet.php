<?php

namespace App\Livewire\Projetos;

use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Services\Projetos\RegistoDeHoras;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Folha de horas — a semana de quem a está a ver.
 *
 * Mostra-se uma semana de cada vez de propósito: quem lança horas lança-as
 * do que se lembra, e a memória não vai além de dias. Uma lista infinita
 * convidava a lançar tudo ao molho no fim do mês.
 */
#[Layout('layouts.app')]
#[Title('Folha de Horas')]
class Timesheet extends Component
{
    /** Segunda-feira da semana em vista (Y-m-d). */
    public string $semana = '';

    // ── Lançar / editar ──────────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editandoId = null;

    public ?int $projetoId = null;

    public ?int $tarefaId = null;

    public string $data = '';

    public string $horas = '';

    public string $descricao = '';

    public bool $facturavel = true;

    public ?int $confirmarApagarId = null;

    public function mount(): void
    {
        $this->semana = now()->startOfWeek()->format('Y-m-d');
        $this->data = now()->format('Y-m-d');
    }

    public function semanaAnterior(): void
    {
        $this->semana = Carbon::parse($this->semana)->subWeek()->format('Y-m-d');
    }

    public function semanaSeguinte(): void
    {
        $this->semana = Carbon::parse($this->semana)->addWeek()->format('Y-m-d');
    }

    public function semanaActual(): void
    {
        $this->semana = now()->startOfWeek()->format('Y-m-d');
    }

    /** Abre o formulário já no dia em que se carregou. */
    public function lancarEm(string $dia): void
    {
        $this->reset(['editandoId', 'tarefaId', 'horas', 'descricao']);
        $this->facturavel = true;
        $this->data = $dia;
        $this->showForm = true;
    }

    public function editar(int $id): void
    {
        $l = $this->minha($id);

        if ($l->jaFacturada()) {
            $this->dispatch('notify', type: 'error',
                message: 'Esta hora já foi facturada — está a sustentar um documento emitido.');

            return;
        }

        $this->editandoId = $l->id;
        $this->projetoId = $l->projeto_id;
        $this->tarefaId = $l->tarefa_id;
        $this->data = $l->data->format('Y-m-d');
        $this->horas = (string) (float) $l->horas;
        $this->descricao = (string) $l->descricao;
        $this->facturavel = (bool) $l->facturavel;
        $this->showForm = true;
    }

    public function guardar(RegistoDeHoras $registo): void
    {
        if (! $this->podeOu('projetos.horas.registar')) {
            return;
        }

        $dados = [
            'projeto_id' => $this->projetoId,
            'tarefa_id' => $this->tarefaId ?: null,
            'data' => $this->data,
            'horas' => $this->horas,
            'descricao' => $this->descricao,
            'facturavel' => $this->facturavel,
        ];

        try {
            if ($this->editandoId) {
                $registo->actualizar($this->minha($this->editandoId), activeTenantId(), $dados);
                $mensagem = 'Lançamento corrigido.';
            } else {
                $registo->lancar(activeTenantId(), auth()->id(), $dados);
                $mensagem = 'Horas lançadas.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->reset(['editandoId', 'horas', 'descricao']);
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    public function apagar(RegistoDeHoras $registo): void
    {
        if (! $this->confirmarApagarId || ! $this->podeOu('projetos.horas.registar')) {
            return;
        }

        try {
            $registo->apagar($this->minha($this->confirmarApagarId), activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
            $this->confirmarApagarId = null;

            return;
        }

        $this->confirmarApagarId = null;
        $this->dispatch('notify', type: 'success', message: 'Lançamento apagado.');
    }

    private function podeOu(string $permissao): bool
    {
        if (auth()->user()?->can($permissao)) {
            return true;
        }

        $this->dispatch('notify', type: 'error', message: 'Não tem permissão para isto.');

        return false;
    }

    /**
     * Só as próprias horas — a menos que se tenha a permissão de gerir as de
     * todos. Sem isto, um id no browser abria a folha de horas dos colegas.
     */
    private function minha(int $id): HoraLancada
    {
        $q = HoraLancada::where('tenant_id', activeTenantId());

        if (! auth()->user()?->can('projetos.horas.gerir')) {
            $q->where('user_id', auth()->id());
        }

        return $q->findOrFail($id);
    }

    /** As tarefas do projeto escolhido, para o formulário. */
    public function getTarefasDoProjetoProperty()
    {
        if (! $this->projetoId) {
            return collect();
        }

        return Tarefa::where('tenant_id', activeTenantId())
            ->where('projeto_id', $this->projetoId)
            ->whereIn('estado', Tarefa::ABERTAS)
            ->orderBy('titulo')
            ->get(['id', 'titulo']);
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $inicio = Carbon::parse($this->semana)->startOfWeek();
        $fim = $inicio->copy()->endOfWeek();

        $linhas = HoraLancada::where('tenant_id', $tenantId)
            ->where('user_id', auth()->id())
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->with(['projeto:id,codigo,nome', 'tarefa:id,titulo'])
            ->orderBy('data')
            ->get();

        $porDia = [];
        foreach (range(0, 6) as $i) {
            $dia = $inicio->copy()->addDays($i);
            $doDia = $linhas->filter(fn ($l) => $l->data->isSameDay($dia));

            $porDia[] = [
                'data' => $dia,
                'linhas' => $doDia,
                'total' => round((float) $doDia->sum('horas'), 2),
                'hoje' => $dia->isToday(),
            ];
        }

        return view('livewire.projetos.timesheet', [
            'porDia' => $porDia,
            'totalSemana' => round((float) $linhas->sum('horas'), 2),
            'facturavelSemana' => round((float) $linhas->where('facturavel', true)->sum('horas'), 2),
            'inicio' => $inicio,
            'fim' => $fim,
            'projetos' => Projeto::where('tenant_id', $tenantId)
                ->whereIn('estado', Projeto::ABERTOS)->orderBy('nome')->get(['id', 'codigo', 'nome']),
        ]);
    }
}
