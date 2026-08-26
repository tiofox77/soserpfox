<?php

namespace App\Livewire\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;
use App\Services\Invoicing\Propostas\ModelosDeArranque;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A lista de modelos de proposta da empresa, e a porta para os criar.
 */
#[Layout('layouts.app')]
#[Title('Modelos de Proposta')]
class ModelosDeProposta extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $mostrarNovo = false;

    public function mount(): void
    {
        if (!auth()->user()->can('invoicing.sales.quotes.view')) {
            abort(403);
        }
    }

    public function criarDeArranque(string $chave)
    {
        if (!auth()->user()->can('invoicing.sales.quotes.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar modelos.'));

            return null;
        }

        $modelo = ModelosDeArranque::criarParaEmpresa($chave, activeTenantId(), auth()->id());

        return redirect()->route('invoicing.sales.quote-templates.edit', $modelo->id);
    }

    public function criarVazio()
    {
        if (!auth()->user()->can('invoicing.sales.quotes.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar modelos.'));

            return null;
        }

        // Nem mesmo "vazio" nasce vazio: sem itens e sem totais, o primeiro
        // PDF sairia sem preços e parecia avariado.
        $modelo = QuoteTemplate::create([
            'tenant_id' => activeTenantId(),
            'nome'      => 'Modelo novo',
            'blocos'    => [
                ['id' => 'dc_' . substr(md5(uniqid('', true)), 0, 8), 'tipo' => 'dados_cliente',
                 'mostrar_validade' => true, 'mostrar_nif' => true],
                ['id' => 'it_' . substr(md5(uniqid('', true)), 0, 8), 'tipo' => 'itens',
                 'titulo' => 'Investimento', 'mostrar_descricao' => true,
                 'mostrar_desconto' => true, 'mostrar_imposto' => true],
                ['id' => 'to_' . substr(md5(uniqid('', true)), 0, 8), 'tipo' => 'totais',
                 'mostrar_por_extenso' => false],
            ],
            'estilos'    => QuoteTemplate::ESTILOS_PADRAO,
            'created_by' => auth()->id(),
        ]);

        if (QuoteTemplate::where('tenant_id', activeTenantId())->count() === 1) {
            $modelo->tornarPadrao();
        }

        return redirect()->route('invoicing.sales.quote-templates.edit', $modelo->id);
    }

    public function duplicar(int $id)
    {
        if (!auth()->user()->can('invoicing.sales.quotes.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar modelos.'));

            return null;
        }

        $original = QuoteTemplate::where('tenant_id', activeTenantId())->find($id);
        if (!$original) {
            $this->dispatch('error', message: __('Este modelo não pertence à empresa activa.'));

            return null;
        }

        $copia = $original->replicate(['is_default']);
        $copia->nome = $original->nome . ' (cópia)';
        $copia->is_default = false;
        $copia->created_by = auth()->id();
        $copia->save();

        return redirect()->route('invoicing.sales.quote-templates.edit', $copia->id);
    }

    public function tornarPadrao(int $id): void
    {
        if (!auth()->user()->can('invoicing.sales.quotes.edit')) {
            $this->dispatch('error', message: __('Sem permissão para editar modelos.'));

            return;
        }

        $modelo = QuoteTemplate::where('tenant_id', activeTenantId())->find($id);
        if (!$modelo) {
            $this->dispatch('error', message: __('Este modelo não pertence à empresa activa.'));

            return;
        }

        $modelo->tornarPadrao();
        $this->dispatch('success', message: __('":nome" passa a ser o modelo padrão.', ['nome' => $modelo->nome]));
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->can('invoicing.sales.quotes.delete')) {
            $this->dispatch('error', message: __('Sem permissão para eliminar modelos.'));

            return;
        }

        $modelo = QuoteTemplate::where('tenant_id', activeTenantId())->find($id);
        if (!$modelo) {
            $this->dispatch('error', message: __('Este modelo não pertence à empresa activa.'));

            return;
        }

        // Soft delete: os orçamentos já feitos apontam para aqui e teriam de
        // continuar a saber com que desenho foram impressos.
        $modelo->delete();
        $this->dispatch('success', message: __('Modelo eliminado.'));
    }

    public function render()
    {
        $modelos = QuoteTemplate::where('tenant_id', activeTenantId())
            ->when($this->search !== '', fn ($q) => $q->where('nome', 'like', '%' . $this->search . '%'))
            ->withCount('orcamentos')
            ->orderByDesc('is_default')
            ->orderBy('nome')
            ->paginate(12);

        return view('livewire.invoicing.propostas.modelos-de-proposta', [
            'modelos'  => $modelos,
            'arranque' => ModelosDeArranque::catalogo(),
        ]);
    }
}
