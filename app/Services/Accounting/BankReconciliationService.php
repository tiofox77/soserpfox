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
            // A DATA DO EXTRACTO É A MAIS RECENTE das linhas, e não a da última
            // linha do ficheiro: um CSV por ordem inversa datava o extracto no
            // dia mais antigo, e o saldo contabilístico era buscado a esse dia.
            'statement_date' => self::ultimoDia($items),
            'closing_balance' => $closingBalance,
            'items' => $items,
        ];
    }

    /** Uma etiqueta SGML do OFX, que não fecha as suas etiquetas. */
    private static function etiquetaOfx(string $bloco, string $nome): ?string
    {
        return preg_match('/<'.$nome.'>([^<\r\n]*)/i', $bloco, $m)
            ? trim($m[1])
            : null;
    }

    /** O dia mais recente de um conjunto de linhas — a data do extracto. */
    private static function ultimoDia(array $items): string
    {
        $dias = array_filter(array_column($items, 'date'));

        return $dias ? max($dias) : now()->format('Y-m-d');
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
            'statement_date' => self::ultimoDia($items),
            'closing_balance' => $closingBalance,
            'items' => $items,
        ];
    }

    /**
     * Parse OFX.
     *
     * O CORPO DESTE MÉTODO ERA UM COMENTÁRIO: «// Parse transactions...». Lia o
     * saldo final e devolvia ZERO transacções — importar um OFX criava uma
     * conciliação vazia que dava «reconciliada» por não ter nada para conciliar,
     * sem um aviso em lado nenhum.
     *
     * O OFX é SGML com um bloco `<STMTTRN>` por transacção; o sinal do montante
     * diz se é entrada ou saída, como no CSV.
     */
    protected function parseOFX($file)
    {
        $content = file_get_contents($file->getRealPath());

        preg_match('/<BALAMT>([\d.-]+)/', $content, $balance);
        $closingBalance = isset($balance[1]) ? (float) $balance[1] : 0;

        $items = [];

        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $content, $blocos);

        foreach ($blocos[1] ?? [] as $bloco) {
            $valor = self::etiquetaOfx($bloco, 'TRNAMT');

            if ($valor === null) {
                continue;
            }

            $montante = (float) str_replace(',', '.', $valor);
            $dia = self::etiquetaOfx($bloco, 'DTPOSTED') ?? self::etiquetaOfx($bloco, 'DTUSER');

            $items[] = [
                // A data do OFX vem como `20260912` ou `20260912120000[-1:AO]`.
                'date' => $dia
                    ? Carbon::createFromFormat('Ymd', substr(preg_replace('/\D/', '', $dia), 0, 8))->format('Y-m-d')
                    : now()->format('Y-m-d'),
                'reference' => self::etiquetaOfx($bloco, 'FITID') ?? self::etiquetaOfx($bloco, 'CHECKNUM') ?? '',
                'description' => self::etiquetaOfx($bloco, 'MEMO') ?? self::etiquetaOfx($bloco, 'NAME') ?? 'Transação bancária',
                'amount' => abs($montante),
                'type' => $montante >= 0 ? 'credit' : 'debit',
            ];
        }

        return [
            'statement_date' => self::ultimoDia($items),
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
        /*
         * A JANELA É A DATA DO LANÇAMENTO, não a de quando a linha foi inserida.
         *
         * Era `whereBetween('created_at', ...)`: um lançamento escrito hoje para
         * uma transacção do mês passado NUNCA aparecia como sugestão, e era isso
         * que fazia a conciliação automática não encontrar nada — o caso normal
         * numa contabilidade que se lança depois de o extracto chegar.
         */
        $moveLines = MoveLine::where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereHas('move', fn ($q) => $q->where('state', 'posted')
                ->whereBetween('date', [
                    Carbon::parse($item->transaction_date)->subDays(5)->format('Y-m-d'),
                    Carbon::parse($item->transaction_date)->addDays(5)->format('Y-m-d'),
                ]))
            ->whereDoesntHave('bankReconciliationItem')
            ->with('move:id,date,ref')
            ->get();
        
        $suggestions = [];

        foreach ($moveLines as $line) {
            $confidence = 0;

            /*
             * Match por valor (peso: 60%).
             *
             * O LADO ESTAVA TROCADO. Um extracto fala da perspectiva do BANCO:
             * dinheiro que ENTRA na conta é um «crédito» para ele — mas na
             * contabilidade da empresa é um DÉBITO na conta do banco, porque o
             * activo aumenta. A comparação era `credit` contra `credit` e
             * `debit` contra `debit`, pelo que só encontrava par quando os dois
             * sinais estavam ao contrário — ou seja, praticamente nunca.
             */
            $lineAmount = $item->type === 'credit' ? $line->debit : $line->credit;
            if (abs($lineAmount - $item->amount) < 0.01) {
                $confidence += 60;
            } elseif (abs($lineAmount - $item->amount) < 10) {
                $confidence += 30;
            }

            /*
             * Match por data (peso: 20%).
             *
             * O `diffInDays` DEVOLVE UM FLOAT no Carbon 3, e a comparação era
             * `=== 0`: `0.0 === 0` é falso, pelo que os vinte pontos da data
             * NUNCA eram dados. Uma linha com valor e data exactos ficava em 60
             * (ou 80 com a descrição) e não passava o limiar dos 90 do
             * auto-match — que é o mesmo que dizer que o auto-match não existia.
             */
            $dateDiff = (int) abs(Carbon::parse($item->transaction_date)->diffInDays($line->move->date));

            if ($dateDiff === 0) {
                $confidence += 20;
            } elseif ($dateDiff <= 2) {
                $confidence += 10;
            }

            /*
             * Match por descrição (peso: 20%).
             *
             * A REFERÊNCIA VAZIA DAVA SEMPRE 20 PONTOS. Em PHP 8,
             * `stripos($qualquerCoisa, '')` devolve 0 — que não é `false` — e o
             * `!== false` passava: todas as linhas ganhavam os vinte pontos da
             * descrição, e um extracto sem coluna de referência (o MT940 e o OFX
             * não a trazem) empurrava a linha errada acima dos 90 e
             * CONCILIAVA-A SOZINHO com o lançamento errado.
             *
             * Agora só compara quando há texto dos dois lados.
             */
            $referencia = trim((string) $item->reference);
            $descricao = trim((string) $item->description);
            $nomeDaLinha = trim((string) $line->name);

            $parecido = ($referencia !== '' && $nomeDaLinha !== '' && stripos($nomeDaLinha, $referencia) !== false)
                || ($descricao !== '' && $nomeDaLinha !== '' && stripos($descricao, $nomeDaLinha) !== false);

            if ($parecido) {
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
     * Match manual.
     *
     * O ESCOPO DE EMPRESA FAZ-SE PELA CONCILIAÇÃO: a tabela dos itens não tem
     * `tenant_id`, e o `findOrFail($itemId)` sozinho aceitava o id de uma linha
     * de outra companhia. A linha de lançamento também tem de ser desta casa e
     * da MESMA CONTA — conciliar o extracto do banco com um lançamento de caixa
     * é dar por certo o que não está.
     */
    public function manualMatch($itemId, $moveLineId, ?int $tenantId = null)
    {
        $item = BankReconciliationItem::query()
            ->when($tenantId, fn ($q) => $q->whereHas(
                'reconciliation',
                fn ($w) => $w->where('tenant_id', $tenantId)
            ))
            ->findOrFail($itemId);

        if ($tenantId) {
            MoveLine::where('tenant_id', $tenantId)
                ->where('account_id', $item->reconciliation->account_id)
                ->findOrFail($moveLineId);
        }

        $item->update([
            'move_line_id' => $moveLineId,
            'status' => 'matched',
            'match_confidence' => 100,
        ]);

        $this->recalculateDifference($item->reconciliation);

        return $item->fresh();
    }

    /** Desfazer um match: a linha volta a estar por conciliar. */
    public function desfazer(BankReconciliationItem $item): BankReconciliationItem
    {
        $item->update([
            'move_line_id' => null,
            'status' => 'unmatched',
            'match_confidence' => null,
        ]);

        $this->recalculateDifference($item->reconciliation);

        return $item->fresh();
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
            /*
             * UM EXTRACTO VAZIO NÃO ESTÁ CONCILIADO.
             *
             * `$matched === $total` com os dois a zero dava «reconciliado» a uma
             * importação sem uma única linha — que é exactamente o que um
             * ficheiro OFX produzia, porque o leitor não extraía transacção
             * nenhuma.
             */
            'status' => ($total > 0 && $matched === $total) ? 'reconciled' : 'draft',
        ]);
    }

    /** O `recalculateDifference` é interno; a API precisa de o poder chamar. */
    public function recalcular(BankReconciliation $reconciliation): void
    {
        $this->recalculateDifference($reconciliation);
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
