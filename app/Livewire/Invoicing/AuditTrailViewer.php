<?php

namespace App\Livewire\Invoicing;

use App\Models\AuditTrail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Consulta da trilha de auditoria.
 *
 * Só leitura, por construção: a tabela é append-only e o model recusa
 * alterações e eliminações. Não há aqui nenhuma acção que escreva.
 */
#[Layout('layouts.app')]
#[Title('Auditoria')]
class AuditTrailViewer extends Component
{
    use WithPagination;

    public string $pesquisa = '';
    public string $filtroEvento = '';
    public string $filtroCanal = '';
    public string $filtroActor = '';
    public string $dataDe = '';
    public string $dataAte = '';

    /** Linha aberta em detalhe (o diff completo). */
    public ?int $verLinha = null;

    /** Estado da cadeia, calculado a pedido (é uma varredura). */
    public ?array $integridade = null;

    public function updating($campo): void
    {
        if (str_starts_with($campo, 'pesquisa') || str_starts_with($campo, 'filtro') || str_starts_with($campo, 'data')) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['pesquisa', 'filtroEvento', 'filtroCanal', 'filtroActor', 'dataDe', 'dataAte']);
        $this->resetPage();
    }

    public function abrir(int $id): void
    {
        $this->verLinha = $id;
    }

    public function fechar(): void
    {
        $this->verLinha = null;
    }

    /**
     * Verifica a cadeia desta empresa.
     *
     * A pedido e não em cada render: é uma varredura de todas as linhas, e o
     * ecrã é de consulta frequente.
     */
    public function verificarIntegridade(): void
    {
        $problemas = AuditTrail::verificarCadeia(activeTenantId());

        $this->integridade = [
            'ok'        => empty($problemas),
            'problemas' => array_slice($problemas, 0, 20),
            'total'     => count($problemas),
            'em'        => now()->format('d/m/Y H:i'),
        ];
    }

    public function getLinhaAbertaProperty(): ?AuditTrail
    {
        return $this->verLinha
            ? AuditTrail::forTenant()->find($this->verLinha)
            : null;
    }

    public function render()
    {
        $registos = AuditTrail::forTenant()
            ->when($this->pesquisa, function ($q) {
                $termo = '%' . $this->pesquisa . '%';
                $q->where(function ($sub) use ($termo) {
                    $sub->where('auditable_label', 'like', $termo)
                        ->orWhere('actor_name', 'like', $termo)
                        ->orWhere('auditable_type', 'like', $termo);
                });
            })
            ->when($this->filtroEvento, fn ($q) => $q->where('event', $this->filtroEvento))
            ->when($this->filtroCanal, fn ($q) => $q->where('channel', $this->filtroCanal))
            ->when($this->filtroActor, fn ($q) => $q->where('user_id', $this->filtroActor))
            ->when($this->dataDe, fn ($q) => $q->whereDate('created_at', '>=', $this->dataDe))
            ->when($this->dataAte, fn ($q) => $q->whereDate('created_at', '<=', $this->dataAte))
            ->latest('id')
            ->paginate(25);

        // Opções dos filtros, tiradas do que existe de facto.
        $eventos = AuditTrail::forTenant()->distinct()->orderBy('event')->pluck('event');
        $canais  = AuditTrail::forTenant()->distinct()->orderBy('channel')->pluck('channel');
        $actores = AuditTrail::forTenant()
            ->whereNotNull('user_id')
            ->select('user_id', 'actor_name')
            ->distinct()
            ->orderBy('actor_name')
            ->get();

        return view('livewire.invoicing.audit-trail-viewer', compact('registos', 'eventos', 'canais', 'actores'));
    }
}
