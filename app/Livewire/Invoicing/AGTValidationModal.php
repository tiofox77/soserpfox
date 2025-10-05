<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\SalesInvoice;
use App\Helpers\AGTHelper;
use Livewire\Component;

class AGTValidationModal extends Component
{
    public $showModal = false;
    public $document = null;
    public $documentId = null;
    public $documentType = 'invoice'; // invoice, proforma, credit_note, etc
    
    public $validation = [];
    public $agt_category = '';
    public $agt_notes = '';
    
    // Checklist items
    public $checks = [
        'hash' => false,
        'footer_message' => false,
        'period' => false,
        'totals' => false,
        'client' => false,
    ];
    
    protected $listeners = [
        'openAGTValidation' => 'open',
    ];
    
    public function open($documentId, $documentType = 'invoice')
    {
        $this->documentId = $documentId;
        $this->documentType = $documentType;
        $this->loadDocument();
        $this->validateDocument();
        $this->showModal = true;
    }
    
    public function loadDocument()
    {
        switch ($this->documentType) {
            case 'invoice':
                $this->document = SalesInvoice::where('tenant_id', activeTenantId())
                    ->findOrFail($this->documentId);
                break;
            // Adicionar outros tipos conforme necessário
        }
    }
    
    public function validateDocument()
    {
        if (!$this->document) {
            return;
        }
        
        $this->validation = AGTHelper::validateAGT($this->document);
        
        // Auto-check itens válidos
        $this->checks['hash'] = !empty($this->document->hash);
        $this->checks['period'] = !empty($this->document->invoice_date);
        $this->checks['client'] = !empty($this->document->client_id);
        $this->checks['footer_message'] = !empty(AGTHelper::getFooterMessage($this->document));
    }
    
    public function markAsCompliant()
    {
        // Verificar se todos os checks estão marcados
        if (!collect($this->checks)->every(fn($checked) => $checked === true)) {
            $this->dispatch('error', message: 'Marque todos os itens do checklist antes de aprovar');
            return;
        }
        
        if (empty($this->agt_category)) {
            $this->dispatch('error', message: 'Selecione a categoria de teste AGT');
            return;
        }
        
        // Aqui você pode salvar no banco se adicionar os campos
        // Por enquanto apenas fecha o modal
        
        $this->dispatch('success', message: 'Documento validado para conformidade AGT!');
        $this->close();
    }
    
    public function close()
    {
        $this->showModal = false;
        $this->reset(['document', 'documentId', 'validation', 'checks', 'agt_category', 'agt_notes']);
    }
    
    public function render()
    {
        $testCategories = AGTHelper::getTestCategories();
        
        return view('livewire.invoicing.a-g-t-validation-modal', [
            'testCategories' => $testCategories,
        ]);
    }
}
