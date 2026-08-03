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
        
        // Estatísticas (pendentes = faturas com saldo em aberto, não apenas status 'pending')
        $allInvoices = SalesInvoice::where('client_id', $client->id)->get();
        $outstanding = $allInvoices->filter(fn ($inv) =>
            !in_array($inv->status, ['paid', 'cancelled', 'credited'], true)
            && round(($inv->total ?? 0) - ($inv->paid_amount ?? 0), 2) > 0.009
        );

        $stats = [
            'total_invoices' => $allInvoices->count(),
            'pending_invoices' => $outstanding->count(),
            'paid_invoices' => $allInvoices->where('status', 'paid')->count(),
            // Total faturado exclui canceladas e creditadas
            'total_amount' => $allInvoices->whereNotIn('status', ['cancelled', 'credited'])->sum('total'),
            'total_events' => Event::where('client_id', $client->id)->count(),
            'upcoming_events' => Event::where('client_id', $client->id)
                                      ->where('start_date', '>=', now())
                                      ->count(),
        ];
        
        return view('livewire.client.client-dashboard', compact('client', 'invoices', 'upcomingEvents', 'stats'));
    }
}
