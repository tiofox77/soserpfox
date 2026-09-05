<?php

namespace App\Livewire\Projetos;

use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tarefas dos projetos.
 *
 * Nada se apaga: cancelar é um estado. As horas já lançadas contra uma tarefa
 * continuam a valer, e uma tarefa que desaparecesse deixava-as sem explicação.
 */
#[Layout('layouts.app')]
#[Title('Tarefas de Projeto')]
class Tarefas extends Component
{
    use WithPagination;

    public string $procurar = '';

    public ?int $projetoId = null;

    public string $estado = 'abertas';

    public bool $soMinhas = false;

    // ── Criar / editar ───────────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editandoId = null;

    public ?int $formProjetoId = null;

    public string $titulo = '';

    public string $formDescricao = '';

    public ?int $formResponsavelId = null;

    public string $prioridade = 'normal';

    public string $prazo = '';

    public string $horasEstimadas = '';

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedProjetoId(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function updatedSoMinhas(): void
    {
        $this->resetPage();
    }

    public function novaTarefa(): void
    {
        $this->reset(['editandoId', 'titulo', 'formDescricao', 'formResponsavelId', 'prazo', 'horasEstimadas']);
        $this->prioridade = 'normal';
        $this->formProjetoId = $this->projetoId;
        $this->showForm = true;
    }

    public function editar(int $id): void
    {
        $t = $this->minha($id);

        $this->editandoId = $t->id;
        $this->formProjetoId = $t->projeto_id;
        $this->titulo = $t->titulo;
        $this->formDescricao = (string) $t->descricao;
        $this->formResponsavelId = $t->responsavel_id;
        $this->prioridade = $t->prioridade;
        $this->prazo = $t->prazo?->format('Y-m-d') ?? '';
        $this->horasEstimadas = $t->horas_estimadas !== null ? (string) (float) $t->horas_estimadas : '';
        $this->showForm = true;
    }

    public function guardar(): void
    {
        if (! $this->podeOu('projetos.tarefas.manage')) {
            return;
        }

        $titulo = trim($this->titulo);

        if ($titulo === '') {
            $this->dispatch('notify', type: 'error', message: 'A tarefa precisa de um título.');

            return;
        }

        // O projeto tem de ser desta empresa — nunca se confia no id do browser.
        $projeto = Projeto::where('tenant_id', activeTenantId())->find($this->formProjetoId);

        if (! $projeto) {
            $this->dispatch('notify', type: 'error', message: 'Escolha um projeto.');

            return;
        }

        $valores = [
            'projeto_id' => $projeto->id,
            'titulo' => $titulo,
            'descricao' => trim($this->formDescricao) ?: null,
            'responsavel_id' => $this->formResponsavelId ?: null,
            'prioridade' => array_key_exists($this->prioridade, Tarefa::PRIORIDADES) ? $this->prioridade : 'normal',
            'prazo' => $this->prazo ?: null,
            'horas_estimadas' => $this->horasEstimadas === '' ? null : (float) $this->horasEstimadas,
        ];

        if ($this->editandoId) {
            $this->minha($this->editandoId)->update($valores);
            $mensagem = 'Tarefa actualizada.';
        } else {
            Tarefa::create($valores + [
                'tenant_id' => activeTenantId(),
                'estado' => 'por_fazer',
                'created_by' => auth()->id(),
                'ordem' => (int) Tarefa::where('tenant_id', activeTenantId())
                    ->where('projeto_id', $projeto->id)->max('ordem') + 1,
            ]);
            $mensagem = 'Tarefa criada.';
        }

        $this->showForm = false;
        $this->reset(['editandoId', 'titulo', 'formDescricao']);
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    public function mudarEstado(int $id, string $novo): void
    {
        if (! $this->podeOu('projetos.tarefas.manage')) {
            return;
        }

        if (! array_key_exists($novo, Tarefa::ESTADOS)) {
            return;
        }

        $t = $this->minha($id);

        $t->update([
            'estado' => $novo,
            // Reabrir limpa a data de fecho — uma tarefa que voltou a andar
            // não pode continuar a dizer que fechou naquele dia.
            'concluida_em' => $novo === 'concluida' ? now() : null,
        ]);

        $this->dispatch('notify', type: 'success', message: "Tarefa: {$t->estadoRotulo()}.");
    }

    private function podeOu(string $permissao): bool
    {
        if (auth()->user()?->can($permissao)) {
            return true;
        }

        $this->dispatch('notify', type: 'error', message: 'Não tem permissão para isto.');

        return false;
    }

    private function minha(int $id): Tarefa
    {
        return Tarefa::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $base = Tarefa::where('projeto_tarefas.tenant_id', $tenantId)
            ->when($this->projetoId, fn ($q) => $q->where('projeto_id', $this->projetoId))
            ->when($this->soMinhas, fn ($q) => $q->where('responsavel_id', auth()->id()))
            ->when($this->estado === 'abertas', fn ($q) => $q->whereIn('estado', Tarefa::ABERTAS))
            ->when(! in_array($this->estado, ['abertas', 'todas'], true), fn ($q) => $q->where('estado', $this->estado))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $t)->orWhere('descricao', 'like', $t));
            });

        $resumo = [
            'abertas' => (clone $base)->whereIn('estado', Tarefa::ABERTAS)->count(),
            'atrasadas' => (clone $base)->whereIn('estado', Tarefa::ABERTAS)
                ->whereNotNull('prazo')->whereDate('prazo', '<', now()->toDateString())->count(),
            'minhas' => Tarefa::where('tenant_id', $tenantId)
                ->where('responsavel_id', auth()->id())->whereIn('estado', Tarefa::ABERTAS)->count(),
        ];

        return view('livewire.projetos.tarefas', [
            'tarefas' => $base->with(['projeto:id,codigo,nome', 'responsavel:id,name'])
                // Urgente primeiro, depois o prazo mais próximo. As sem prazo
                // vão para o fim: quem não tem data não compete com quem tem.
                ->orderByRaw("FIELD(prioridade, 'urgente', 'alta', 'normal', 'baixa')")
                ->orderByRaw('prazo IS NULL, prazo ASC')
                ->paginate(20),
            'resumo' => $resumo,
            'projetos' => Projeto::where('tenant_id', $tenantId)
                ->whereIn('estado', Projeto::ABERTOS)->orderBy('nome')->get(['id', 'codigo', 'nome']),
            'utilizadores' => User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }
}
