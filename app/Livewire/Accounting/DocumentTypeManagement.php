<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Accounting\DocumentType;
use App\Models\Accounting\Journal;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class DocumentTypeManagement extends Component
{
    use WithPagination;

    // Propriedades de busca e filtro
    public $search = '';
    public $filterJournal = '';
    public $filterRecapitulativos = '';
    public $filterRetencaoFonte = '';
    public $filterBalFinanceira = '';
    public $showInactive = false;

    // Propriedades do formulário
    public $documentTypeId;
    public $code;
    public $description;
    public $journal_code;
    public $journal_id;
    public $recapitulativos = false;
    public $retencao_fonte = false;
    public $bal_financeira = true;
    public $bal_analitica = false;
    public $rec_informacao = 0;
    public $tipo_doc_imo = 0;
    public $calculo_fluxo_caixa = 0;
    public $is_active = true;
    public $display_order = 0;

    // Controle de modais
    public $showFormModal = false;
    public $showViewModal = false;
    public $showDeleteModal = false;
    public $viewingDocumentType = null;
    public $deletingDocumentType = null;

    protected $rules = [
        'code' => 'required|string|max:10',
        'description' => 'required|string|max:255',
        'journal_id' => 'nullable|exists:accounting_journals,id',
        'recapitulativos' => 'boolean',
        'retencao_fonte' => 'boolean',
        'bal_financeira' => 'boolean',
        'bal_analitica' => 'boolean',
        'rec_informacao' => 'integer',
        'tipo_doc_imo' => 'integer',
        'calculo_fluxo_caixa' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function render()
    {
        $query = DocumentType::with('journal')
            ->forTenant(activeTenantId());

        // Aplicar filtros
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterJournal) {
            $query->where('journal_id', $this->filterJournal);
        }

        if ($this->filterRecapitulativos !== '') {
            $query->where('recapitulativos', (bool)$this->filterRecapitulativos);
        }

        if ($this->filterRetencaoFonte !== '') {
            $query->where('retencao_fonte', (bool)$this->filterRetencaoFonte);
        }

        if ($this->filterBalFinanceira !== '') {
            $query->where('bal_financeira', (bool)$this->filterBalFinanceira);
        }

        if (!$this->showInactive) {
            $query->active();
        }

        $documentTypes = $query->ordered()->paginate(20);

        $journals = Journal::forTenant(activeTenantId())
            ->active()
            ->ordered()
            ->get();

        return view('livewire.accounting.document-type-management', [
            'documentTypes' => $documentTypes,
            'journals' => $journals,
        ]);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit($id)
    {
        $documentType = DocumentType::forTenant(activeTenantId())->findOrFail($id);
        
        $this->documentTypeId = $documentType->id;
        $this->code = $documentType->code;
        $this->description = $documentType->description;
        $this->journal_code = $documentType->journal_code;
        $this->journal_id = $documentType->journal_id;
        $this->recapitulativos = $documentType->recapitulativos;
        $this->retencao_fonte = $documentType->retencao_fonte;
        $this->bal_financeira = $documentType->bal_financeira;
        $this->bal_analitica = $documentType->bal_analitica;
        $this->rec_informacao = $documentType->rec_informacao;
        $this->tipo_doc_imo = $documentType->tipo_doc_imo;
        $this->calculo_fluxo_caixa = $documentType->calculo_fluxo_caixa;
        $this->is_active = $documentType->is_active;
        $this->display_order = $documentType->display_order;

        $this->showFormModal = true;
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {
            $data = [
                'tenant_id' => activeTenantId(),
                'code' => $this->code,
                'description' => $this->description,
                'journal_code' => $this->journal_code,
                'journal_id' => $this->journal_id,
                'recapitulativos' => $this->recapitulativos,
                'retencao_fonte' => $this->retencao_fonte,
                'bal_financeira' => $this->bal_financeira,
                'bal_analitica' => $this->bal_analitica,
                'rec_informacao' => $this->rec_informacao,
                'tipo_doc_imo' => $this->tipo_doc_imo,
                'calculo_fluxo_caixa' => $this->calculo_fluxo_caixa,
                'is_active' => $this->is_active,
                'display_order' => $this->display_order,
            ];

            if ($this->documentTypeId) {
                $documentType = DocumentType::forTenant(activeTenantId())->findOrFail($this->documentTypeId);
                $documentType->update($data);
                $message = 'Tipo de documento atualizado com sucesso!';
            } else {
                DocumentType::create($data);
                $message = 'Tipo de documento criado com sucesso!';
            }

            session()->flash('success', $message);
        });

        $this->closeFormModal();
    }

    public function view($id)
    {
        $this->viewingDocumentType = DocumentType::with('journal')
            ->forTenant(activeTenantId())
            ->findOrFail($id);
        $this->showViewModal = true;
    }

    public function confirmDelete($id)
    {
        $this->deletingDocumentType = DocumentType::forTenant(activeTenantId())->findOrFail($id);
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if ($this->deletingDocumentType) {
            $this->deletingDocumentType->delete();
            session()->flash('success', 'Tipo de documento excluído com sucesso!');
            $this->closeDeleteModal();
        }
    }

    private function resetForm()
    {
        $this->documentTypeId = null;
        $this->code = '';
        $this->description = '';
        $this->journal_code = '';
        $this->journal_id = null;
        $this->recapitulativos = false;
        $this->retencao_fonte = false;
        $this->bal_financeira = true;
        $this->bal_analitica = false;
        $this->rec_informacao = 0;
        $this->tipo_doc_imo = 0;
        $this->calculo_fluxo_caixa = 0;
        $this->is_active = true;
        $this->display_order = 0;
        $this->resetErrorBag();
    }

    public function closeFormModal()
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingDocumentType = null;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingDocumentType = null;
    }
}
