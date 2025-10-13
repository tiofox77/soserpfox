<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\Period;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DepreciationService
{
    /**
     * Calcula e registra depreciação mensal
     */
    public function calculateMonthlyDepreciation($tenantId, $periodId)
    {
        $period = Period::findOrFail($periodId);
        $assets = FixedAsset::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
        
        $depreciations = [];
        
        foreach ($assets as $asset) {
            $depreciation = $this->calculateAssetDepreciation($asset, $period);
            
            if ($depreciation['amount'] > 0) {
                $depreciations[] = $this->recordDepreciation($asset, $period, $depreciation);
            }
        }
        
        return $depreciations;
    }
    
    /**
     * Calcula depreciação de um ativo
     */
    protected function calculateAssetDepreciation($asset, $period)
    {
        $depreciableValue = $asset->acquisition_value - $asset->residual_value;
        $monthsUsed = $this->getMonthsUsed($asset, $period);
        
        return match($asset->depreciation_method) {
            'linear' => $this->calculateLinearDepreciation($depreciableValue, $asset->useful_life_years, $monthsUsed, $asset->accumulated_depreciation),
            'declining_balance' => $this->calculateDecliningBalance($asset, $monthsUsed),
            'units_of_production' => $this->calculateUnitsOfProduction($asset, $monthsUsed),
            default => ['amount' => 0, 'accumulated' => $asset->accumulated_depreciation, 'book_value' => $asset->book_value]
        };
    }
    
    /**
     * Método Linear (quotas constantes)
     */
    protected function calculateLinearDepreciation($depreciableValue, $usefulLifeYears, $monthsUsed, $accumulated)
    {
        $totalMonths = $usefulLifeYears * 12;
        $monthlyDepreciation = $depreciableValue / $totalMonths;
        
        // Depreciar só 1 mês
        $amount = $monthlyDepreciation;
        
        // Verificar se não excede valor depreciável
        if ($accumulated + $amount > $depreciableValue) {
            $amount = $depreciableValue - $accumulated;
        }
        
        return [
            'amount' => round($amount, 2),
            'accumulated' => $accumulated + $amount,
            'book_value' => $depreciableValue + ($accumulated + $amount) * -1,
        ];
    }
    
    /**
     * Método Quotas Decrescentes (declining balance)
     */
    protected function calculateDecliningBalance($asset, $monthsUsed)
    {
        $rate = $asset->depreciation_rate ?? (200 / $asset->useful_life_years); // Double declining balance
        $bookValue = $asset->book_value;
        
        $monthlyRate = $rate / 100 / 12;
        $amount = $bookValue * $monthlyRate;
        
        // Não depreciar abaixo do valor residual
        if ($bookValue - $amount < $asset->residual_value) {
            $amount = $bookValue - $asset->residual_value;
        }
        
        return [
            'amount' => round($amount, 2),
            'accumulated' => $asset->accumulated_depreciation + $amount,
            'book_value' => $bookValue - $amount,
        ];
    }
    
    /**
     * Método Unidades de Produção
     */
    protected function calculateUnitsOfProduction($asset, $monthsUsed)
    {
        // Simplificado - requer tracking de unidades produzidas
        return [
            'amount' => 0,
            'accumulated' => $asset->accumulated_depreciation,
            'book_value' => $asset->book_value,
        ];
    }
    
    /**
     * Registra depreciação
     */
    protected function recordDepreciation($asset, $period, $depreciation)
    {
        return DB::transaction(function() use ($asset, $period, $depreciation) {
            // Criar registro de depreciação
            $assetDepreciation = FixedAssetDepreciation::create([
                'fixed_asset_id' => $asset->id,
                'period_id' => $period->id,
                'depreciation_date' => $period->date_end,
                'depreciation_amount' => $depreciation['amount'],
                'accumulated_depreciation' => $depreciation['accumulated'],
                'book_value' => $depreciation['book_value'],
                'status' => 'draft',
            ]);
            
            // Atualizar ativo
            $asset->update([
                'accumulated_depreciation' => $depreciation['accumulated'],
                'book_value' => $depreciation['book_value'],
                'status' => $depreciation['book_value'] <= $asset->residual_value ? 'fully_depreciated' : 'active',
            ]);
            
            return $assetDepreciation;
        });
    }
    
    /**
     * Gera lançamento contabilístico de depreciação
     */
    public function postDepreciation($depreciationId)
    {
        $depreciation = FixedAssetDepreciation::with('fixedAsset')->findOrFail($depreciationId);
        $asset = $depreciation->fixedAsset;
        
        return DB::transaction(function() use ($depreciation, $asset) {
            // Buscar diário de ajustes
            $journal = \App\Models\Accounting\Journal::where('tenant_id', $asset->tenant_id)
                ->where('type', 'general')
                ->first();
            
            // Criar lançamento
            $move = Move::create([
                'tenant_id' => $asset->tenant_id,
                'journal_id' => $journal->id,
                'period_id' => $depreciation->period_id,
                'date' => $depreciation->depreciation_date,
                'ref' => 'DEP-' . $asset->code,
                'narration' => 'Depreciação ' . $asset->name,
                'state' => 'posted',
            ]);
            
            // Linha 1: Débito Gasto Depreciação
            MoveLine::create([
                'tenant_id' => $asset->tenant_id,
                'move_id' => $move->id,
                'account_id' => $asset->depreciation_account_id,
                'name' => 'Depreciação ' . $asset->name,
                'debit' => $depreciation->depreciation_amount,
                'credit' => 0,
            ]);
            
            // Linha 2: Crédito Depreciação Acumulada
            MoveLine::create([
                'tenant_id' => $asset->tenant_id,
                'move_id' => $move->id,
                'account_id' => $asset->accumulated_depreciation_account_id,
                'name' => 'Depreciação Acumulada ' . $asset->name,
                'debit' => 0,
                'credit' => $depreciation->depreciation_amount,
            ]);
            
            // Atualizar depreciação
            $depreciation->update([
                'move_id' => $move->id,
                'status' => 'posted',
            ]);
            
            return $move;
        });
    }
    
    /**
     * Calcula meses de uso
     */
    protected function getMonthsUsed($asset, $period)
    {
        $acquisitionDate = Carbon::parse($asset->acquisition_date);
        $periodEnd = Carbon::parse($period->date_end);
        
        return $acquisitionDate->diffInMonths($periodEnd);
    }
    
    /**
     * Processa depreciações em lote
     */
    public function batchProcessDepreciations($tenantId, $periodId)
    {
        $depreciations = FixedAssetDepreciation::where('status', 'draft')
            ->whereHas('fixedAsset', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('period_id', $periodId)
            ->get();
        
        $results = [];
        
        foreach ($depreciations as $depreciation) {
            try {
                $move = $this->postDepreciation($depreciation->id);
                $results[] = [
                    'success' => true,
                    'depreciation_id' => $depreciation->id,
                    'move_id' => $move->id,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'depreciation_id' => $depreciation->id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
}
