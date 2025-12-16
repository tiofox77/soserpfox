<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\WorkOrderAttachment;
use App\Models\Workshop\WorkOrderHistory;

#[Layout('layouts.app')]
class WorkOrderManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $statusFilter = '';
    public $priorityFilter = '';
    
    // Modals
    public $showModal = false;
    public $showViewModal = false;
    public $showItemModal = false;
    public $showUploadModal = false;
    public $showInvoiceConfirmModal = false;
    public $editMode = false;
    public $workOrderId;
    public $invoiceWorkOrderId;
    
    // Upload
    public $uploadFiles = [];
    public $uploadCategory = 'other';
    public $uploadDescription = '';
    
    // Item Management
    public $viewingWorkOrder;
    public $itemType = 'service'; // service or part
    public $itemServiceId = '';
    public $itemProductId = '';
    public $itemCode = '';
    public $itemName = '';
    public $itemDescription = '';
    public $itemQuantity = 1;
    public $itemUnitPrice = 0;
    public $itemDiscountPercent = 0;
    public $itemHours = 0;
    public $itemMechanicId = '';
    public $itemPartNumber = '';
    public $itemBrand = '';
    public $itemIsOriginal = false;
    public $editingItemId = null;
    public $productStock = null;
    public $lowStockWarning = false;
    
    // Form Fields
    public $vehicle_id = '';
    public $mechanic_id = '';
    public $received_at = '';
    public $scheduled_for = '';
    public $mileage_in = 0;
    public $problem_description = '';
    public $diagnosis = '';
    public $work_performed = '';
    public $recommendations = '';
    public $status = 'pending';
    public $priority = 'normal';
    public $notes = '';

    protected $rules = [
        'vehicle_id' => 'required|exists:workshop_vehicles,id',
        'received_at' => 'required|date',
        'problem_description' => 'required|string',
        'status' => 'required|in:pending,scheduled,in_progress,waiting_parts,completed,delivered,cancelled',
        'priority' => 'required|in:low,normal,high,urgent',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $workOrder = WorkOrder::with(['vehicle', 'mechanic'])->findOrFail($id);
        
        $this->workOrderId = $workOrder->id;
        $this->vehicle_id = $workOrder->vehicle_id;
        $this->mechanic_id = $workOrder->mechanic_id;
        $this->received_at = $workOrder->received_at ? $workOrder->received_at->format('Y-m-d H:i') : '';
        $this->scheduled_for = $workOrder->scheduled_for ? $workOrder->scheduled_for->format('Y-m-d H:i') : '';
        $this->mileage_in = $workOrder->mileage_in;
        $this->problem_description = $workOrder->problem_description;
        $this->diagnosis = $workOrder->diagnosis;
        $this->work_performed = $workOrder->work_performed;
        $this->recommendations = $workOrder->recommendations;
        $this->status = $workOrder->status;
        $this->priority = $workOrder->priority;
        $this->notes = $workOrder->notes;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => auth()->user()->activeTenantId(),
            'vehicle_id' => $this->vehicle_id,
            'mechanic_id' => $this->mechanic_id,
            'received_at' => $this->received_at,
            'scheduled_for' => $this->scheduled_for,
            'mileage_in' => $this->mileage_in,
            'problem_description' => $this->problem_description,
            'diagnosis' => $this->diagnosis,
            'work_performed' => $this->work_performed,
            'recommendations' => $this->recommendations,
            'status' => $this->status,
            'priority' => $this->priority,
            'notes' => $this->notes,
        ];

        if ($this->editMode) {
            $workOrder = WorkOrder::findOrFail($this->workOrderId);
            $workOrder->update($data);
            $this->dispatch('success', message: 'Ordem de Serviço atualizada com sucesso!');
        } else {
            // Gerar número da OS
            $data['order_number'] = 'OS-' . str_pad(WorkOrder::count() + 1, 5, '0', STR_PAD_LEFT);
            WorkOrder::create($data);
            $this->dispatch('success', message: 'Ordem de Serviço criada com sucesso!');
        }

        $this->closeModal();
    }

    public function view($id)
    {
        $this->viewingWorkOrder = WorkOrder::with([
            'vehicle',
            'mechanic',
            'items.service',
            'items.product',
            'items.mechanic',
            'history.user',
            'attachments.user',
            'invoice.client'
        ])->findOrFail($id);
        $this->showViewModal = true;
    }
    
    public function delete($id)
    {
        $workOrder = WorkOrder::findOrFail($id);
        $workOrder->delete();
        
        $this->dispatch('success', message: 'Ordem de Serviço removida com sucesso!');
    }
    
    public function openItemModal($type = 'service')
    {
        $this->resetItemForm();
        $this->itemType = $type;
        $this->showItemModal = true;
    }
    
    public function addItem()
    {
        $this->validate([
            'itemType' => 'required|in:service,part',
            'itemName' => 'required|string',
            'itemQuantity' => 'required|numeric|min:0.01',
            'itemUnitPrice' => 'required|numeric|min:0',
        ]);
        
        $data = [
            'work_order_id' => $this->viewingWorkOrder->id,
            'type' => $this->itemType,
            'service_id' => $this->itemServiceId ?: null,
            'product_id' => $this->itemProductId ?: null,
            'code' => $this->itemCode,
            'name' => $this->itemName,
            'description' => $this->itemDescription,
            'quantity' => $this->itemQuantity,
            'unit_price' => $this->itemUnitPrice,
            'discount_percent' => $this->itemDiscountPercent,
            'hours' => $this->itemHours,
            'mechanic_id' => $this->itemMechanicId ?: null,
            'part_number' => $this->itemPartNumber,
            'brand' => $this->itemBrand,
            'is_original' => $this->itemIsOriginal,
        ];
        
        \App\Models\Workshop\WorkOrderItem::create($data);
        
        $this->viewingWorkOrder->refresh();
        $this->showItemModal = false;
        $this->resetItemForm();
        
        $this->dispatch('success', message: 'Item adicionado com sucesso!');
    }
    
    public function deleteItem($itemId)
    {
        $item = \App\Models\Workshop\WorkOrderItem::findOrFail($itemId);
        $item->delete();
        
        $this->viewingWorkOrder->refresh();
        $this->dispatch('success', message: 'Item removido com sucesso!');
    }
    
    public function updateDiscount()
    {
        $this->viewingWorkOrder->update([
            'discount' => $this->viewingWorkOrder->discount
        ]);
        $this->viewingWorkOrder->calculateTotals();
        $this->viewingWorkOrder->refresh();
    }
    
    private function resetItemForm()
    {
        $this->itemServiceId = '';
        $this->itemProductId = '';
        $this->itemCode = '';
        $this->itemName = '';
        $this->itemDescription = '';
        $this->itemQuantity = 1;
        $this->itemUnitPrice = 0;
        $this->itemDiscountPercent = 0;
        $this->itemHours = 0;
        $this->itemMechanicId = '';
        $this->itemPartNumber = '';
        $this->itemBrand = '';
        $this->itemIsOriginal = false;
        $this->editingItemId = null;
    }
    
    public function loadServiceData()
    {
        if ($this->itemServiceId) {
            $service = \App\Models\Workshop\Service::find($this->itemServiceId);
            if ($service) {
                $this->itemCode = $service->code ?? '';
                $this->itemName = $service->name;
                $this->itemDescription = $service->description ?? '';
                $this->itemUnitPrice = $service->labor_cost;
                $this->itemHours = $service->estimated_hours ?? 0;
            }
        }
    }
    
    public function loadProductData()
    {
        if ($this->itemProductId) {
            $product = \App\Models\Product::find($this->itemProductId);
            if ($product) {
                $this->itemCode = $product->sku ?? '';
                $this->itemName = $product->name;
                $this->itemDescription = $product->description ?? '';
                $this->itemUnitPrice = $product->selling_price;
                
                // Verificar estoque disponível
                $totalStock = \App\Models\Invoicing\Stock::where('product_id', $product->id)
                    ->where('tenant_id', activeTenantId())
                    ->sum('quantity');
                
                $this->productStock = $totalStock;
                $this->lowStockWarning = ($totalStock < 5 || $totalStock < $this->itemQuantity);
            }
        }
    }
    
    public function updateStatus($id, $newStatus)
    {
        $workOrder = WorkOrder::findOrFail($id);
        
        // Usar métodos do Model para processar status corretamente
        if ($newStatus === 'in_progress') {
            $workOrder->markAsInProgress();
        } elseif ($newStatus === 'completed') {
            $workOrder->markAsCompleted(); // Isso irá processar baixa de estoque automaticamente
        } elseif ($newStatus === 'delivered') {
            $workOrder->markAsDelivered();
        } else {
            // Outros status (pending, waiting_parts, cancelled)
            $workOrder->update(['status' => $newStatus]);
        }
        
        $message = 'Status atualizado com sucesso!';
        if ($newStatus === 'completed') {
            $message .= ' Estoque baixado automaticamente.';
        }
        $this->dispatch('success', message: $message);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
    
    public function openInvoiceConfirmModal($id)
    {
        $this->invoiceWorkOrderId = $id;
        $this->showInvoiceConfirmModal = true;
    }
    
    public function closeInvoiceConfirmModal()
    {
        $this->showInvoiceConfirmModal = false;
        $this->invoiceWorkOrderId = null;
    }
    
    public function generateInvoice()
    {
        try {
            \Log::info("Workshop: Iniciando geração de fatura para OS ID: {$this->invoiceWorkOrderId}");
            
            $workOrder = WorkOrder::with(['vehicle', 'items'])->findOrFail($this->invoiceWorkOrderId);
            
            \Log::info("Workshop: OS encontrada: {$workOrder->order_number}");
            
            // Converter para fatura
            $invoice = $workOrder->convertToInvoice();
            
            \Log::info("Workshop: Fatura criada: {$invoice->invoice_number} (ID: {$invoice->id})");
            
            // Fechar modals
            $this->closeInvoiceConfirmModal();
            $this->closeViewModal();
            
            // Redirecionar para o PREVIEW da fatura
            return redirect()->route('invoicing.sales.invoices.preview', $invoice->id)
                ->with('success', "✅ Fatura {$invoice->invoice_number} gerada com sucesso!");
            
        } catch (\Exception $e) {
            \Log::error("Workshop: Erro ao gerar fatura: {$e->getMessage()}");
            \Log::error($e->getTraceAsString());
            
            $this->closeInvoiceConfirmModal();
            $this->dispatch('error', message: 'Erro ao gerar fatura: ' . $e->getMessage());
        }
    }
    
    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingWorkOrder = null;
    }
    
    public function closeItemModal()
    {
        $this->showItemModal = false;
        $this->resetItemForm();
    }
    
    public function openUploadModal()
    {
        $this->uploadFiles = [];
        $this->uploadCategory = 'other';
        $this->uploadDescription = '';
        $this->showUploadModal = true;
    }
    
    public function uploadAttachments()
    {
        $this->validate([
            'uploadFiles.*' => 'required|file|max:10240', // Max 10MB por arquivo
            'uploadCategory' => 'required|string',
        ]);
        
        if (!$this->viewingWorkOrder) {
            $this->dispatch('error', message: 'Ordem de serviço não encontrada.');
            return;
        }
        
        $uploaded = 0;
        
        foreach ($this->uploadFiles as $file) {
            try {
                // Gerar nome único
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                // Salvar no storage
                $path = $file->storeAs('workshop/attachments/' . $this->viewingWorkOrder->id, $filename, 'public');
                
                // Criar registro no banco
                WorkOrderAttachment::create([
                    'work_order_id' => $this->viewingWorkOrder->id,
                    'user_id' => auth()->id(),
                    'filename' => $filename,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $this->getFileType($file->getMimeType()),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'category' => $this->uploadCategory,
                    'description' => $this->uploadDescription,
                ]);
                
                // Registrar no histórico
                WorkOrderHistory::logAction(
                    $this->viewingWorkOrder->id,
                    WorkOrderHistory::ACTION_COMMENT,
                    "Arquivo anexado: {$file->getClientOriginalName()}",
                    [
                        'category' => $this->uploadCategory,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ]
                );
                
                $uploaded++;
            } catch (\Exception $e) {
                \Log::error("Erro ao fazer upload de arquivo: " . $e->getMessage());
            }
        }
        
        // Recarregar anexos
        $this->viewingWorkOrder->load('attachments.user');
        
        $this->dispatch('success', message: "{$uploaded} arquivo(s) anexado(s) com sucesso!");
        $this->closeUploadModal();
    }
    
    public function deleteAttachment($id)
    {
        try {
            $attachment = WorkOrderAttachment::findOrFail($id);
            
            if ($attachment->work_order_id !== $this->viewingWorkOrder->id) {
                $this->dispatch('error', message: 'Anexo não pertence a esta OS.');
                return;
            }
            
            // Registrar no histórico antes de deletar
            WorkOrderHistory::logAction(
                $this->viewingWorkOrder->id,
                WorkOrderHistory::ACTION_COMMENT,
                "Arquivo removido: {$attachment->original_filename}",
                ['category' => $attachment->category]
            );
            
            // Deletar arquivo e registro
            $attachment->deleteFile();
            
            // Recarregar anexos
            $this->viewingWorkOrder->load('attachments.user');
            
            $this->dispatch('success', message: 'Anexo removido com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao remover anexo: ' . $e->getMessage());
        }
    }
    
    public function closeUploadModal()
    {
        $this->showUploadModal = false;
        $this->uploadFiles = [];
        $this->uploadCategory = 'other';
        $this->uploadDescription = '';
    }
    
    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (in_array($mimeType, ['application/pdf'])) {
            return 'document';
        } elseif (in_array($mimeType, ['application/zip', 'application/x-rar'])) {
            return 'archive';
        }
        return 'other';
    }

    private function resetForm()
    {
        $this->workOrderId = null;
        $this->vehicle_id = '';
        $this->mechanic_id = '';
        $this->received_at = now()->format('Y-m-d\TH:i');
        $this->scheduled_for = '';
        $this->mileage_in = 0;
        $this->problem_description = '';
        $this->diagnosis = '';
        $this->work_performed = '';
        $this->recommendations = '';
        $this->status = 'pending';
        $this->priority = 'normal';
        $this->notes = '';
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $workOrders = WorkOrder::with(['vehicle', 'mechanic'])
            ->where('tenant_id', $tenantId)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('order_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('vehicle', function($q) {
                          $q->where('plate', 'like', '%' . $this->search . '%')
                            ->orWhere('owner_name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->priorityFilter, function($query) {
                $query->where('priority', $this->priorityFilter);
            })
            ->latest()
            ->paginate(10);

        $vehicles = Vehicle::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('plate')
            ->get();
            
        $mechanics = Mechanic::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $services = \App\Models\Workshop\Service::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $products = \App\Models\Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.workshop.work-orders.work-orders', [
            'workOrders' => $workOrders,
            'vehicles' => $vehicles,
            'mechanics' => $mechanics,
            'services' => $services,
            'products' => $products,
        ]);
    }
}
