<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Events\Event;

#[Layout('layouts.client')]
#[Title('Portal do Cliente')]
class ClientDashboard extends Component
{
    public function logout()
    {
        Auth::guard('client')->logout();
        session()->invalidate();
        session()->regenerateToken();
        
        return redirect()->route('client.login');
    }

    public function render()
    {
        $client = Auth::guard('client')->user();
        
        // Faturas do cliente
        $invoices = SalesInvoice::where('client_id', $client->id)
                                   ->orderBy('invoice_date', 'desc')
                                   ->limit(5)
                                   ->get();
        
        // Próximos eventos
        $upcomingEvents = Event::where('client_id', $client->id)
                               ->where('start_date', '>=', now())
                               ->orderBy('start_date', 'asc')
                               ->limit(3)
                               ->with(['venue', 'type'])
                               ->get();
        
        // Estatísticas
        $stats = [
            'total_invoices' => SalesInvoice::where('client_id', $client->id)->count(),
            'pending_invoices' => SalesInvoice::where('client_id', $client->id)
                                                 ->where('status', 'pending')
                                                 ->count(),
            'paid_invoices' => SalesInvoice::where('client_id', $client->id)
                                              ->where('status', 'paid')
                                              ->count(),
            'total_amount' => SalesInvoice::where('client_id', $client->id)
                                            ->sum('total'),
            'total_events' => Event::where('client_id', $client->id)->count(),
            'upcoming_events' => Event::where('client_id', $client->id)
                                      ->where('start_date', '>=', now())
                                      ->count(),
        ];
        
        return view('livewire.client.client-dashboard', compact('client', 'invoices', 'upcomingEvents', 'stats'));
    }
}
