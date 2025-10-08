<?php

namespace App\Livewire\SuperAdmin;

use App\Models\EmailLog;
use Livewire\Component;
use Livewire\WithPagination;

class EmailLogs extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $templateFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    
    public $showDetailModal = false;
    public $selectedLog;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingTemplateFilter()
    {
        $this->resetPage();
    }

    public function viewDetails($id)
    {
        $this->selectedLog = EmailLog::with(['emailTemplate', 'smtpSetting', 'user', 'tenant'])
            ->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
    }

    public function delete($id)
    {
        EmailLog::findOrFail($id)->delete();
        session()->flash('success', 'Log excluído com sucesso!');
    }

    public function clearOldLogs()
    {
        // Excluir logs mais antigos que 90 dias
        $deleted = EmailLog::where('created_at', '<', now()->subDays(90))->delete();
        session()->flash('success', "Foram excluídos {$deleted} logs antigos!");
    }

    public function render()
    {
        $logs = EmailLog::query()
            ->with(['emailTemplate', 'user', 'tenant'])
            ->when($this->search, function($query) {
                $query->where(function($q) {
                    $q->where('to_email', 'like', '%' . $this->search . '%')
                      ->orWhere('subject', 'like', '%' . $this->search . '%')
                      ->orWhere('template_slug', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->templateFilter, fn($q) => $q->where('template_slug', $this->templateFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $templates = EmailLog::distinct()->pluck('template_slug')->filter();
        
        $stats = [
            'total' => EmailLog::count(),
            'sent' => EmailLog::sent()->count(),
            'failed' => EmailLog::failed()->count(),
            'pending' => EmailLog::pending()->count(),
        ];

        return view('livewire.super-admin.email-logs', [
            'logs' => $logs,
            'templates' => $templates,
            'stats' => $stats,
        ])->layout('layouts.superadmin');
    }
}
