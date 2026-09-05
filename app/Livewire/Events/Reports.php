<?php

namespace App\Livewire\Events;

use App\Models\Events\Event;
use App\Models\Client;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Relatórios de Eventos')]
class Reports extends Component
{
    public $startDate;
    public $endDate;
    public $selectedClient = null;
    public $selectedType = null;
    public $selectedStatus = null;
    
    public $chartType = 'month'; // month, year, client, type, status
    
    public function mount()
    {
        // Definir período padrão: últimos 12 meses
        $this->endDate = now()->format('Y-m-d');
        $this->startDate = now()->subMonths(12)->format('Y-m-d');
    }
    
    public function updatedChartType()
    {
        $this->dispatch('chart-updated');
    }
    
    public function getEventsByMonthProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }
    
    public function getEventsByYearProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->selectRaw('YEAR(start_date) as year, COUNT(*) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->get();
    }
    
    public function getEventsByClientProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->select('client_id', DB::raw('COUNT(*) as total'))
            ->with('client:id,name')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }
    
    public function getEventsByTypeProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->select('type_id', DB::raw('COUNT(*) as total'))
            ->with('type:id,name')
            ->groupBy('type_id')
            ->orderByDesc('total')
            ->get();
    }
    
    public function getEventsByStatusProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();
    }
    
    public function getTotalEventsProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->count();
    }
    
    public function getTotalValueProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->sum('total_value');
    }
    
    public function getTotalAttendeesProperty()
    {
        return Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->sum('expected_attendees');
    }
    
    public function getAverageValueProperty()
    {
        $avg = Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->avg('total_value');
        
        return $avg ?? 0;
    }
    
    /*
     * O PDF e o Excel deste relatório NUNCA existiram.
     *
     * Os dois botões redirigiam para endereços que não estão registados —
     * clicar dava erro 500, e o utilizador ficava a olhar para uma página de
     * erro sem perceber que a funcionalidade simplesmente não foi feita. O
     * CSV, esse, funciona e abre em qualquer folha de cálculo.
     *
     * Dizer a verdade e oferecer o que existe é melhor do que rebentar.
     */
    public function exportToPdf()
    {
        $this->dispatch('info', message: __('A exportação em PDF ainda não existe neste relatório. Use CSV — abre no Excel.'));
    }

    public function exportToExcel()
    {
        $this->dispatch('info', message: __('A exportação em Excel ainda não existe neste relatório. Use CSV — abre no Excel.'));
    }
    
    public function exportToCsv()
    {
        $events = Event::whereTenantId(activeTenantId())
            ->whereBetween('start_date', [$this->startDate, $this->endDate])
            ->when($this->selectedClient, fn($q) => $q->where('client_id', $this->selectedClient))
            ->when($this->selectedType, fn($q) => $q->where('type_id', $this->selectedType))
            ->when($this->selectedStatus, fn($q) => $q->where('status', $this->selectedStatus))
            ->with(['client', 'type'])
            ->get();
        
        $filename = 'eventos_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($events) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, ['ID', 'Número', 'Nome', 'Cliente', 'Tipo', 'Status', 'Data Início', 'Data Fim', 'Participantes', 'Valor']);
            
            // Dados
            foreach ($events as $event) {
                fputcsv($file, [
                    $event->id,
                    $event->event_number,
                    $event->name,
                    $event->client->name ?? 'N/A',
                    $event->type?->name ?? 'N/A',
                    $event->status,
                    $event->start_date->format('d/m/Y'),
                    $event->end_date->format('d/m/Y'),
                    $event->expected_attendees ?? 0,
                    number_format($event->total_value ?? 0, 2),
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    public function resetFilters()
    {
        $this->selectedClient = null;
        $this->selectedType = null;
        $this->selectedStatus = null;
        $this->endDate = now()->format('Y-m-d');
        $this->startDate = now()->subMonths(12)->format('Y-m-d');
    }
    
    public function render()
    {
        $clients = Client::whereTenantId(activeTenantId())
            ->orderBy('name')
            ->get();
        
        // Buscar tipos de eventos através do modelo EventType
        $types = \App\Models\Events\EventType::whereTenantId(activeTenantId())
            ->orderBy('name')
            ->get();
        
        $statuses = Event::whereTenantId(activeTenantId())
            ->distinct()
            ->pluck('status')
            ->filter()
            ->sort()
            ->values();
        
        return view('livewire.events.reports', compact('clients', 'types', 'statuses'));
    }
}
