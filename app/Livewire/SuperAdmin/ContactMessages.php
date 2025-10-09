<?php

namespace App\Livewire\SuperAdmin;

use App\Models\ContactMessage;
use Livewire\Component;
use Livewire\WithPagination;

class ContactMessages extends Component
{
    use WithPagination;
    
    public $search = '';
    public $statusFilter = '';
    
    public function markAsRead($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => 'read']);
        
        $this->dispatch('success', message: 'Mensagem marcada como lida!');
    }
    
    public function markAsReplied($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => 'replied']);
        
        $this->dispatch('success', message: 'Mensagem marcada como respondida!');
    }
    
    public function delete($id)
    {
        ContactMessage::findOrFail($id)->delete();
        
        $this->dispatch('success', message: 'Mensagem excluída!');
    }

    public function render()
    {
        $messages = ContactMessage::query()
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('company', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest()
            ->paginate(20);
            
        return view('livewire.super-admin.contact-messages', [
            'messages' => $messages
        ])->layout('layouts.app');
    }
}
