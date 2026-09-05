<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        // Como foi acordada — a renovação repete o acordo (ver migração
        // subscricao_a_medida).
        'com_oferta',
        'dias_personalizados',
        'amount',
        'preco_por_utilizador',
        'utilizadores_cobrados',
        // Tecto de documentos emitidos. NULL = sem limite. Copia-se do plano
        // quando a subscrição nasce, para que uma mudança de política hoje
        // não reescreva o que se prometeu ontem.
        'max_documentos',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'ends_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOnTrial($query)
    {
        return $query->where('status', 'trial')
            ->where('trial_ends_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<', now());
    }

    // Métodos auxiliares
    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isOnTrial()
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function hasExpired()
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => $this->current_period_end,
        ]);
    }

    public function resume()
    {
        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
        ]);
    }

    /**
     * O fim do período seguinte, a repetir o ACORDO desta subscrição.
     *
     * Dias à medida ganham ao ciclo; e a oferta do anual só entra se foi
     * acordada. Sem isto, uma empresa fechada «sem oferta» ganhava os dois
     * meses na primeira renovação, à socapa — e a fechada a 364 dias voltava
     * aos 14 meses.
     */
    public function fimDoPeriodoSeguinte(\Carbon\Carbon $inicio): \Carbon\Carbon
    {
        if ((int) $this->dias_personalizados > 0) {
            // Até ao fim do N-ésimo dia, como no acordo inicial.
            return $inicio->copy()->addDays((int) $this->dias_personalizados)->endOfDay();
        }

        return \App\Support\CicloDeFacturacao::fim(
            $inicio,
            $this->billing_cycle,
            $this->com_oferta ?? true
        );
    }

    public function renew()
    {
        $periodStart = ($this->current_period_end ?? now())->copy();
        $periodEnd = $this->fimDoPeriodoSeguinte($periodStart);

        $this->update([
            'status' => 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'ends_at' => $periodEnd,
        ]);

        // BUG-12 FIX: Propagar renovação para outros tenants do user
        $this->propagateRenewalToUserTenants($periodStart, $periodEnd);

        return $this;
    }
    
    /**
     * BUG-12 FIX: Propagar renovação para todos os outros tenants do user
     */
    protected function propagateRenewalToUserTenants($periodStart, $periodEnd)
    {
        try {
            $tenant = $this->tenant;
            if (!$tenant) return;
            
            // Encontrar o OWNER da subscription (quem criou o pedido/pagou)
            // Primeiro: procurar na última order aprovada para este plano+tenant
            $ownerOrder = \App\Models\Order::where('tenant_id', $tenant->id)
                ->where('plan_id', $this->plan_id)
                ->where('status', 'approved')
                ->latest()
                ->first();
            
            if ($ownerOrder && $ownerOrder->user) {
                $owners = collect([$ownerOrder->user]);
            } else {
                // Fallback: usar apenas o primeiro admin do tenant (criador)
                $owners = $tenant->users()->limit(1)->get();
            }
            
            foreach ($owners as $user) {
                $otherTenants = $user->tenants()->where('tenants.id', '!=', $tenant->id)->get();
                
                foreach ($otherTenants as $otherTenant) {
                    // Encontrar subscription do mesmo plano no outro tenant
                    $otherSub = $otherTenant->subscriptions()
                        ->where('plan_id', $this->plan_id)
                        ->latest()
                        ->first();
                    
                    if ($otherSub) {
                        $otherSub->update([
                            'status' => 'active',
                            'current_period_start' => $periodStart,
                            'current_period_end' => $periodEnd,
                            'ends_at' => $periodEnd,
                        ]);
                    } else {
                        // Criar subscription clone
                        $otherTenant->subscriptions()->create([
                            'plan_id'              => $this->plan_id,
                            'status'               => 'active',
                            'billing_cycle'        => $this->billing_cycle,
                            'amount'               => $this->amount,
                            'current_period_start' => $periodStart,
                            'current_period_end'   => $periodEnd,
                            'ends_at'              => $periodEnd,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Subscription::renew propagation error', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function daysUntilDue()
    {
        if (!$this->current_period_end) {
            return null;
        }

        return now()->diffInDays($this->current_period_end, false);
    }
}
