<?php

namespace App\Services\Accounting;

use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationItem;
use App\Models\Accounting\MoveLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BankReconciliationService
{
    /**
     * Importa extrato bancário de arquivo
     * Suporta: CSV, MT940, OFX
     */
    public function importStatementFile($file, $tenantId, $accountId, $fileType = 'csv')
    {
        $path = $file->store('bank_statements/' . $tenantId);
        
        $transactions = match($fileType) {
            'csv' => $this->parseCSV($file),
            'mt940' => $this->parseMT940($file),
            'ofx' => $this->parseOFX($file),
            default => throw new \Exception('Tipo de arquivo não suportado')
        };
        
        return DB::transaction(function() use ($transactions, $tenantId, $accountId, $path, $fileType) {
            // Criar reconciliação
            $reconciliation = BankReconciliation::create([
                'tenant_id' => $tenantId,
                'account_id' => $accountId,
                'statement_date' => $transactions['statement_date'],
                'statement_balance' => $transactions['closing_balance'],
                'book_balance' => $this->getBookBalance($tenantId, $accountId, $transactions['statement_date']),
                'difference' => 0, // calculado depois
                'status' => 'draft',
                'file_path' => $path,
                'file_type' => $fileType,
            ]);
            
            // Criar itens
            foreach ($transactions['items'] as $item) {
                BankReconciliationItem::create([
                    'reconciliation_id' => $reconciliation->id,
                    'transaction_date' => $item['date'],
                    'reference' => $item['reference'] ?? null,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'type' => $item['type'], // debit ou credit
                    'status' => 'unmatched',
                ]);
            }
            
            // Auto-matching
            $this->autoMatch($reconciliation);
            
            // Recalcular diferença
            $this->recalculateDifference($reconciliation);
            
            return $reconciliation;
        });
    }
    
    /**
     * Parse CSV simples
     */
    protected function parseCSV($file)
    {
        $content = file_get_contents($file->getRealPath());
        $lines = explode("\n", $content);
        
        $items = [];
        $closingBalance = 0;
        
        foreach ($lines as $index => $line) {
            if ($index === 0 || empty(trim($line))) continue; // Skip header
            
            $parts = str_getcsv($line);
            if (count($parts) < 4) continue;
            
            $amount = (float) str_replace([',', ' '], ['', ''], $parts[3]);
            $isCredit = $amount > 0;
            
            $items[] = [
                'date' => Carbon::parse($parts[0])->format('Y-m-d'),
                'reference' => $parts[1] ?? '',
                'description' => $parts[2] ?? '',
                'amount' => abs($amount),
                'type' => $isCredit ? 'credit' : 'debit',
            ];
            
            $closingBalance += $amount;
        }
        
        return [
            'statement_date' => isset($items[count($items)-1]) ? $items[count($items)-1]['date'] : now()->format('Y-m-d'),
            'closing_balance' => $closingBalance,
            'items' => $items,
        ];
    }
    
    /**
     * Parse MT940 (formato bancário standard)
     */
    protected function parseMT940($file)
    {
        // Implementação simplificada do MT940
        $content = file_get_contents($file->getRealPath());
        
        // Extrair saldo final (:62F:)
        preg_match('/:62F:[CD](\d{6})([A-Z]{3})([\d,]+)/', $content, $balanceMatch);
        $closingBalance = isset($balanceMatch[3]) ? (float) str_replace(',', '.', $balanceMatch[3]) : 0;
        
        // Extrair transações (:61:)
        preg_match_all('/:61:(\d{6})(\d{4})?([CD])(\d+,\d+)/', $content, $transactions, PREG_SET_ORDER);
        
        $items = [];
        foreach ($transactions as $trans) {
            $date = Carbon::createFromFormat('ymd', $trans[1])->format('Y-m-d');
            $type = $trans[3] === 'C' ? 'credit' : 'debit';
            $amount = (float) str_replace(',', '.', $trans[4]);
            
            $items[] = [
                'date' => $date,
                'reference' => '',
                'description' => 'Transação bancária',
                'amount' => $amount,
                'type' => $type,
            ];
        }
        
        return [
            'statement_date' => now()->format('Y-m-d'),
            'closing_balance' => $closingBalance,
            'items' => $items,
        ];
    }
    
    /**
     * Parse OFX
     */
    protected function parseOFX($file)
    {
        // Implementação básica OFX
        $content = file_get_contents($file->getRealPath());
        
        // Simplificado - em produção usar biblioteca XML
        preg_match('/<BALAMT>([\d.-]+)/', $content, $balance);
        $closingBalance = isset($balance[1]) ? (float) $balance[1] : 0;
        
        $items = [];
        // Parse transactions...
        
        return [
            'statement_date' => now()->format('Y-m-d'),
            'closing_balance' => $closingBalance,
            'items' => $items,
        ];
    }
    
    /**
     * Auto-matching inteligente
     */
    public function autoMatch($reconciliation)
    {
        $items = BankReconciliationItem::where('reconciliation_id', $reconciliation->id)
            ->where('status', 'unmatched')
            ->get();
        
        foreach ($items as $item) {
            $suggestions = $this->findMatchingSuggestions($item, $reconciliation->tenant_id, $reconciliation->account_id);
            
            if (!empty($suggestions) && $suggestions[0]['confidence'] > 90) {
                // Auto-match com alta confiança
                $item->update([
                    'move_line_id' => $suggestions[0]['move_line_id'],
                    'status' => 'matched',
                    'match_confidence' => $suggestions[0]['confidence'],
                ]);
            }
        }
    }
    
    /**
     * Encontra sugestões de matching
     */
    public function findMatchingSuggestions($item, $tenantId, $accountId)
    {
        // Buscar move_lines similares
        $moveLines = MoveLine::where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereHas('move', fn($q) => $q->where('state', 'posted'))
            ->whereDoesntHave('bankReconciliationItem')
            ->whereBetween('created_at', [
                Carbon::parse($item->transaction_date)->subDays(5),
                Carbon::parse($item->transaction_date)->addDays(5)
            ])
            ->get();
        
        $suggestions = [];
        
        foreach ($moveLines as $line) {
            $confidence = 0;
            
            // Match por valor (peso: 60%)
            $lineAmount = $item->type === 'debit' ? $line->debit : $line->credit;
            if (abs($lineAmount - $item->amount) < 0.01) {
                $confidence += 60;
            } elseif (abs($lineAmount - $item->amount) < 10) {
                $confidence += 30;
            }
            
            // Match por data (peso: 20%)
            $dateDiff = abs(Carbon::parse($item->transaction_date)->diffInDays($line->move->date));
            if ($dateDiff === 0) {
                $confidence += 20;
            } elseif ($dateDiff <= 2) {
                $confidence += 10;
            }
            
            // Match por descrição (peso: 20%)
            if (stripos($line->name, $item->reference) !== false || stripos($item->description, $line->name) !== false) {
                $confidence += 20;
            }
            
            if ($confidence > 50) {
                $suggestions[] = [
                    'move_line_id' => $line->id,
                    'move_line' => $line,
                    'confidence' => $confidence,
                ];
            }
        }
        
        // Ordenar por confiança
        usort($suggestions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        return $suggestions;
    }
    
    /**
     * Match manual
     */
    public function manualMatch($itemId, $moveLineId)
    {
        $item = BankReconciliationItem::findOrFail($itemId);
        
        $item->update([
            'move_line_id' => $moveLineId,
            'status' => 'matched',
            'match_confidence' => 100,
        ]);
        
        $this->recalculateDifference($item->reconciliation);
    }
    
    /**
     * Recalcular diferença
     */
    protected function recalculateDifference($reconciliation)
    {
        $matched = BankReconciliationItem::where('reconciliation_id', $reconciliation->id)
            ->where('status', 'matched')
            ->count();
        
        $total = BankReconciliationItem::where('reconciliation_id', $reconciliation->id)
            ->count();
        
        $unmatched = BankReconciliationItem::where('reconciliation_id', $reconciliation->id)
            ->where('status', 'unmatched')
            ->get();
        
        $difference = $unmatched->sum(function($item) {
            return $item->type === 'credit' ? $item->amount : -$item->amount;
        });
        
        $reconciliation->update([
            'difference' => $difference,
            'status' => ($matched === $total) ? 'reconciled' : 'draft',
        ]);
    }
    
    /**
     * Obter saldo contabilístico
     */
    protected function getBookBalance($tenantId, $accountId, $date)
    {
        $debit = MoveLine::where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereHas('move', fn($q) => $q->where('state', 'posted')->where('date', '<=', $date))
            ->sum('debit');
        
        $credit = MoveLine::where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereHas('move', fn($q) => $q->where('state', 'posted')->where('date', '<=', $date))
            ->sum('credit');
        
        return $debit - $credit;
    }
}
