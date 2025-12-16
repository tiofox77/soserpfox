<?php

namespace App\Livewire\Support;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Support\Ticket;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.app')]
class TicketsManagement extends Component
{
    use WithFileUploads;
    
    public $showModal = false;
    public $subject = '';
    public $description = '';
    public $priority = 'medium';
    public $category = 'other';
    public $images = [];
    
    public function openModal()
    {
        $this->showModal = true;
        $this->reset(['subject', 'description', 'priority', 'category']);
    }
    
    public function closeModal()
    {
        $this->showModal = false;
    }
    
    public function createTicket()
    {
        $this->validate([
            'subject' => 'required|min:5',
            'description' => 'required|min:10',
            'priority' => 'required',
            'category' => 'required',
            'images.*' => 'nullable|image|max:2048', // Max 2MB por imagem
        ]);
        
        // Processar imagens
        $imagePaths = [];
        if ($this->images && count($this->images) > 0) {
            $tenantId = auth()->user()->tenant_id;
            $userId = auth()->id();
            $ticketNumber = 'TKT-' . str_pad(Ticket::count() + 1, 6, '0', STR_PAD_LEFT);
            
            foreach (array_slice($this->images, 0, 5) as $image) {
                $path = $image->store("tickets/{$tenantId}/{$userId}/{$ticketNumber}", 'public');
                $imagePaths[] = $path;
            }
        }
        
        $ticket = Ticket::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'ticket_number' => 'TKT-' . str_pad(Ticket::count() + 1, 6, '0', STR_PAD_LEFT),
            'subject' => $this->subject,
            'description' => $this->description,
            'images' => $imagePaths,
            'priority' => $this->priority,
            'category' => $this->category,
            'status' => 'open',
        ]);
        
        session()->flash('message', 'Ticket criado com sucesso!');
        $this->closeModal();
        $this->reset(['images']);
    }
    
    public function render()
    {
        $tickets = Ticket::where('tenant_id', auth()->user()->tenant_id)
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
            
        return view('livewire.support.tickets-management', [
            'tickets' => $tickets
        ]);
    }
}
