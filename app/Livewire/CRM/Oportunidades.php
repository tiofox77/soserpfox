<?php

namespace App\Livewire\CRM;

use App\Models\Client;
use App\Models\CRM\Activity;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use App\Services\CRM\FacturarOportunidade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * As oportunidades: os negócios com nome, valor e etapa.
 *
 * A lista é o registo; o funil (ecrã próprio) é o quadro. Ganhar e perder
 * ficam AQUI com motivo e data — é daqui que saem a taxa de conversão e a
 * resposta a «porque é que perdemos».
 */
#[Layout('layouts.app')]
#[Title('Oportunidades - CRM')]
class Oportunidades extends Component
{
    use WithPagination;

    public string $procurar = '';

    public string $filtroEstado = 'open';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $titulo = '';

    public ?int $clienteId = null;

    public ?int $etapaId = null;

    public ?string $valor = null;

    public ?string $fechoPrevisto = null;

    public string $notas = '';

    // Perder pede motivo; ganhar pede confirmação.
    public ?int $paraPerder = null;

    public string $motivoPerda = '';

    // A actividade rápida.
    public ?int $paraActividade = null;

    public string $actTipo = 'chamada';

    public string $actAssunto = '';

    public function criar(): void
    {
        $this->reset(['editingId', 'titulo', 'clienteId', 'valor', 'fechoPrevisto', 'notas']);
        $this->etapaId = Stage::doTenant(activeTenantId())->first()?->id;
        $this->showForm = true;
    }

    public function editar(int $id): void
    {
        $o = Opportunity::where('tenant_id', activeTenantId())->findOrFail($id);

        $this->editingId = $o->id;
        $this->titulo = $o->title;
        $this->clienteId = $o->client_id;
        $this->etapaId = $o->stage_id;
        $this->valor = $o->amount > 0 ? number_format((float) $o->amount, 2, ',', '.') : null;
        $this->fechoPrevisto = $o->expected_close_date?->format('Y-m-d');
        $this->notas = (string) $o->notes;
        $this->showForm = true;
    }

    public function guardar(): void
    {
        $dados = $this->validate([
            'titulo' => ['required', 'string', 'min:3', 'max:200'],
            'clienteId' => ['nullable', 'integer'],
            'etapaId' => ['required', 'integer'],
            'valor' => ['nullable', 'string', 'max:30'],
            'fechoPrevisto' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], [], ['titulo' => 'título', 'etapaId' => 'etapa']);

        $tenantId = activeTenantId();

        $etapa = Stage::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($this->etapaId);

        if ($this->clienteId) {
            Client::where('tenant_id', $tenantId)->findOrFail($this->clienteId);
        }

        $valores = [
            'title' => trim($dados['titulo']),
            'client_id' => $this->clienteId,
            'stage_id' => $etapa->id,
            'amount' => $this->valor !== null && $this->valor !== ''
                ? max(0, (float) str_replace(['.', ','], ['', '.'], $this->valor))
                : 0,
            'expected_close_date' => $this->fechoPrevisto ?: null,
            'notes' => trim($this->notas) ?: null,
        ];

        if ($this->editingId) {
            $o = Opportunity::where('tenant_id', $tenantId)->findOrFail($this->editingId);

            // Mudar de etapa pelo formulário acompanha a probabilidade da
            // etapa nova — o mesmo que o funil faz ao mover o cartão.
            if ((int) $o->stage_id !== (int) $etapa->id) {
                $valores['probability'] = (int) $etapa->probability;
            }

            $o->update($valores);
        } else {
            Opportunity::create($valores + [
                'tenant_id' => $tenantId,
                'probability' => (int) $etapa->probability,
                'status' => 'open',
                'assigned_to' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: 'No funil.');
    }

    public function ganhar(int $id): void
    {
        $o = Opportunity::where('tenant_id', activeTenantId())->where('status', 'open')->findOrFail($id);

        $o->update(['status' => 'won', 'probability' => 100, 'closed_at' => now()]);

        $this->dispatch('notify', type: 'success',
            message: __('Ganho! Já pode gerar a factura aqui mesmo — nasce em rascunho, para conferir.'));
    }

    /**
     * O negócio ganho vira documento, e a oportunidade fica a saber qual.
     *
     * Sem isto o CRM dizia «vá à Facturação» e o valor era escrito duas vezes:
     * uma no funil, outra no documento. Duas verdades sobre o mesmo negócio
     * divergem à primeira correcção — e o funil nunca sabia quanto se COBROU,
     * só quanto se ganhou.
     */
    public function facturar(int $id, FacturarOportunidade $servico): void
    {
        if (! auth()->user()?->can('invoicing.sales.invoices.create')) {
            $this->dispatch('notify', type: 'error', message: __('Não tem permissão para emitir facturas.'));

            return;
        }

        $o = Opportunity::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $factura = $servico->facturar($o, activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('notify', type: 'success', message: __('Factura :numero criada em rascunho.', [
            'numero' => $factura->invoice_number,
        ]));
    }

    public function perder(): void
    {
        $this->validate(['motivoPerda' => ['required', 'string', 'min:3', 'max:255']], [], ['motivoPerda' => 'motivo']);

        Opportunity::where('tenant_id', activeTenantId())->where('status', 'open')
            ->findOrFail($this->paraPerder)
            ->update([
                'status' => 'lost',
                'probability' => 0,
                'closed_at' => now(),
                'lost_reason' => trim($this->motivoPerda),
            ]);

        $this->reset(['paraPerder', 'motivoPerda']);
        $this->dispatch('notify', type: 'info', message: 'Registado. Os motivos somados dizem onde se perde.');
    }

    public function reabrir(int $id): void
    {
        $o = Opportunity::where('tenant_id', activeTenantId())
            ->whereIn('status', ['won', 'lost'])->findOrFail($id);

        $o->update([
            'status' => 'open',
            'closed_at' => null,
            'lost_reason' => null,
            'probability' => (int) ($o->stage?->probability ?? 50),
        ]);
    }

    public function registarActividade(): void
    {
        $this->validate([
            'actAssunto' => ['required', 'string', 'min:2', 'max:200'],
            'actTipo' => ['required', 'in:'.implode(',', array_keys(Activity::TIPOS))],
        ], [], ['actAssunto' => 'assunto']);

        $o = Opportunity::where('tenant_id', activeTenantId())->findOrFail($this->paraActividade);

        Activity::create([
            'tenant_id' => activeTenantId(),
            'type' => $this->actTipo,
            'subject' => trim($this->actAssunto),
            'opportunity_id' => $o->id,
            'done' => true,
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        $this->reset(['paraActividade', 'actAssunto']);
        $this->dispatch('notify', type: 'success', message: 'Registado no histórico.');
    }

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $etapas = Stage::doTenant($tenantId);

        $oportunidades = Opportunity::where('tenant_id', $tenantId)
            // A factura vem junto: a lista mostra, em cada negócio ganho, o
            // documento que dele nasceu — ou o botão para o criar.
            ->with(['stage:id,name', 'client:id,name', 'assignee:id,name',
                'factura:id,invoice_number,invoice_date,status,total'])
            ->withCount('activities')
            ->when(in_array($this->filtroEstado, ['open', 'won', 'lost'], true),
                fn ($q) => $q->where('status', $this->filtroEstado))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $t)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $t)));
            })
            ->latest()
            ->paginate(20);

        $clientes = Client::where('tenant_id', $tenantId)
            ->where('is_active', true)->orderBy('name')->limit(300)->get(['id', 'name']);

        return view('livewire.crm.oportunidades', compact('oportunidades', 'etapas', 'clientes'));
    }
}
