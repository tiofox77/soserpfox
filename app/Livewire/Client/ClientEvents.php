<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Events\Event;

#[Layout('layouts.client')]
#[Title('Meus Eventos')]
class ClientEvents extends Component
{
    use WithPagination;

    public $statusFilter = '';
    public $search = '';

    public function render()
    {
        $client = Auth::guard('client')->user();
        
        $events = Event::where('client_id', $client->id)
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('event_number', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function($query) {
                $query->where('status', $this->statusFilter);
            })
            ->with(['venue', 'type'])
            ->orderBy('start_date', 'desc')
            ->paginate(10);
        
        // Estatísticas
        $stats = [
            'total' => Event::where('client_id', $client->id)->count(),
            'confirmados' => Event::where('client_id', $client->id)->where('status', 'confirmado')->count(),
            'em_andamento' => Event::where('client_id', $client->id)->where('status', 'em_andamento')->count(),
            'concluidos' => Event::where('client_id', $client->id)->where('status', 'concluido')->count(),
        ];
        
        return view('livewire.client.client-events', compact('events', 'stats'));
    }
}
