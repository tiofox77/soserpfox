<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class ImportChartOfAccounts extends Command
{
    protected $signature = 'accounting:import-chart {file}';
    protected $description = 'Importar plano de contas de arquivo Excel';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("❌ Arquivo não encontrado: {$filePath}");
            return 1;
        }

        $this->info("📄 Lendo arquivo: {$filePath}");
        
        try {
            // Carregar Excel
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Obter dados
            $rows = $sheet->toArray();
            
            if (empty($rows)) {
                $this->error('❌ Arquivo vazio');
                return 1;
            }

            // Primeira linha = cabeçalho
            $header = array_shift($rows);
            
            $this->info("\n📋 CABEÇALHO DETECTADO:");
            $this->table(['Coluna', 'Nome'], array_map(fn($i, $col) => [$i, $col], array_keys($header), $header));
            
            // Detectar estrutura automaticamente
            $structure = $this->detectStructure($header);
            
            $this->info("\n🔍 ESTRUTURA DETECTADA:");
            foreach ($structure as $field => $column) {
                $this->line("  {$field} => Coluna {$column} ({$header[$column]})");
            }
            
            // Processar dados
            $this->info("\n📊 PROCESSANDO DADOS...\n");
            $accounts = [];
            $rowNumber = 2; // Linha 1 é cabeçalho
            
            foreach ($rows as $row) {
                // Pular linhas vazias
                if (empty(array_filter($row))) {
                    $rowNumber++;
                    continue;
                }
                
                try {
                    $account = $this->parseRow($row, $structure, $header);
                    
                    if ($account) {
                        $accounts[] = $account;
                        $this->line("✓ Linha {$rowNumber}: [{$account['code']}] {$account['name']}");
                    }
                } catch (\Exception $e) {
                    $this->warn("⚠️  Linha {$rowNumber}: {$e->getMessage()}");
                }
                
                $rowNumber++;
            }
            
            // Mostrar resumo
            $this->info("\n📈 RESUMO:");
            $this->line("  Total de contas: " . count($accounts));
            
            // Agrupar por tipo
            $byType = [];
            foreach ($accounts as $acc) {
                $type = $acc['type'] ?? 'unknown';
                $byType[$type] = ($byType[$type] ?? 0) + 1;
            }
            
            $this->table(['Tipo', 'Quantidade'], array_map(fn($k, $v) => [$k, $v], array_keys($byType), $byType));
            
            // Salvar em arquivo PHP
            $this->info("\n💾 GERANDO SEEDER...");
            $this->generateSeeder($accounts);
            
            $this->info("\n✅ Importação concluída!");
            
        } catch (Exception $e) {
            $this->error("❌ Erro ao ler Excel: {$e->getMessage()}");
            return 1;
        } catch (\Exception $e) {
            $this->error("❌ Erro: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Detectar estrutura do Excel baseado no cabeçalho
     */
    private function detectStructure(array $header): array
    {
        $structure = [];
        
        foreach ($header as $index => $columnName) {
            $normalized = strtolower(trim($columnName));
            
            // Código da conta
            if (preg_match('/c[óo]dig|code|conta|num/i', $columnName)) {
                $structure['code'] = $index;
            }
            // Nome/Descrição
            elseif (preg_match('/nome|descri[çc]|name|design|t[íi]tulo/i', $columnName)) {
                $structure['name'] = $index;
            }
            // Tipo
            elseif (preg_match('/tipo|type|class|natureza/i', $columnName)) {
                $structure['type'] = $index;
            }
            // Natureza (débito/crédito)
            elseif (preg_match('/nature|d[ée]bit|cr[ée]dit|saldo/i', $columnName)) {
                $structure['nature'] = $index;
            }
            // Nível
            elseif (preg_match('/n[íi]vel|level|grau/i', $columnName)) {
                $structure['level'] = $index;
            }
            // Integração
            elseif (preg_match('/integra[çc]|key|chave/i', $columnName)) {
                $structure['integration_key'] = $index;
            }
            // Visualização
            elseif (preg_match('/view|vis[ãa]o|grupo/i', $columnName)) {
                $structure['is_view'] = $index;
            }
            // Bloqueado
            elseif (preg_match('/bloq|block|lock/i', $columnName)) {
                $structure['blocked'] = $index;
            }
            // IVA/Tax
            elseif (preg_match('/^iva$|tax|imposto/i', $columnName)) {
                $structure['iva'] = $index;
            }
            // Reflexão Débito
            elseif (preg_match('/reflex.*d[ée]b|debit.*refl|db/i', $columnName)) {
                $structure['debit_reflection'] = $index;
            }
            // Reflexão Crédito
            elseif (preg_match('/reflex.*cr[ée]d|credit.*refl|cr(?!$)/i', $columnName)) {
                $structure['credit_reflection'] = $index;
            }
            // Centro de Custo
            elseif (preg_match('/c\.?\s?custo|centro.*custo|cost.*cent/i', $columnName)) {
                $structure['cost_center'] = $index;
            }
            // Chave (Chv)
            elseif (preg_match('/^chv$|^key$|account.*key/i', $columnName)) {
                $structure['account_key'] = $index;
            }
            // Custo Fixo
            elseif (preg_match('/c\.?\s?fixo|custo.*fix|fixed.*cost/i', $columnName)) {
                $structure['is_fixed_cost'] = $index;
            }
            // Tipo/Subtipo (Tp)
            elseif (preg_match('/^tp$|subtipo|subtype/i', $columnName)) {
                $structure['account_subtype'] = $index;
            }
        }
        
        return $structure;
    }

    /**
     * Processar linha do Excel
     */
    private function parseRow(array $row, array $structure, array $header): ?array
    {
        // Código é obrigatório
        if (!isset($structure['code']) || empty($row[$structure['code']])) {
            return null;
        }

        $code = trim($row[$structure['code']]);
        $name = isset($structure['name']) ? trim($row[$structure['name']]) : '';
        
        if (empty($name)) {
            throw new \Exception("Nome vazio para código {$code}");
        }

        // Determinar tipo baseado no código
        $type = $this->determineType($code, $row, $structure);
        
        // Determinar natureza
        $nature = $this->determineNature($code, $type, $row, $structure);
        
        // Calcular nível baseado no comprimento do código
        $level = isset($structure['level']) && !empty($row[$structure['level']]) 
            ? (int)$row[$structure['level']]
            : strlen($code);
        
        return [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'nature' => $nature,
            'level' => $level,
            'is_view' => $this->parseBoolean($row, $structure, 'is_view', false),
            'blocked' => $this->parseBoolean($row, $structure, 'blocked', false),
            'parent_id' => null,
            'integration_key' => $this->parseIntegrationKey($row, $structure, $code, $name),
            // Novos campos do Excel
            'iva' => $this->parseString($row, $structure, 'iva'),
            'debit_reflection' => $this->parseString($row, $structure, 'debit_reflection'),
            'credit_reflection' => $this->parseString($row, $structure, 'credit_reflection'),
            'cost_center' => $this->parseString($row, $structure, 'cost_center'),
            'account_key' => $this->parseString($row, $structure, 'account_key'),
            'is_fixed_cost' => $this->parseBoolean($row, $structure, 'is_fixed_cost', false),
            'account_subtype' => $this->parseString($row, $structure, 'account_subtype'),
        ];
    }

    /**
     * Determinar tipo da conta baseado no código
     */
    private function determineType(string $code, array $row, array $structure): string
    {
        // Se tem coluna de tipo, usar
        if (isset($structure['type']) && !empty($row[$structure['type']])) {
            $typeValue = strtolower(trim($row[$structure['type']]));
            
            if (preg_match('/activ|asset|ativo/i', $typeValue)) return 'asset';
            if (preg_match('/passiv|liab|divida/i', $typeValue)) return 'liability';
            if (preg_match('/capit|equit|patrim/i', $typeValue)) return 'equity';
            if (preg_match('/rend|revenue|income|venda/i', $typeValue)) return 'revenue';
            if (preg_match('/gast|expense|cost|custo/i', $typeValue)) return 'expense';
        }

        // Baseado no primeiro dígito (PGC Angola)
        $firstDigit = substr($code, 0, 1);
        
        return match($firstDigit) {
            '1', '2', '3', '4' => 'asset',      // Classe 1,2,3,4 = Ativo
            '5' => 'equity',                     // Classe 5 = Capital Próprio
            '6' => 'expense',                    // Classe 6 = Gastos
            '7' => 'revenue',                    // Classe 7 = Rendimentos
            '8' => 'equity',                     // Classe 8 = Resultados
            default => 'asset'
        };
    }

    /**
     * Determinar natureza (débito/crédito)
     */
    private function determineNature(string $code, string $type, array $row, array $structure): string
    {
        // Se tem coluna de natureza, usar
        if (isset($structure['nature']) && !empty($row[$structure['nature']])) {
            $natureValue = strtolower(trim($row[$structure['nature']]));
            
            if (preg_match('/d[ée]bit|devedor/i', $natureValue)) return 'debit';
            if (preg_match('/cr[ée]dit|credor/i', $natureValue)) return 'credit';
        }

        // Baseado no tipo
        return match($type) {
            'asset', 'expense' => 'debit',
            'liability', 'equity', 'revenue' => 'credit',
            default => 'debit'
        };
    }

    /**
     * Parse booleano
     */
    private function parseBoolean(array $row, array $structure, string $field, bool $default): bool
    {
        if (!isset($structure[$field])) {
            return $default;
        }

        $value = strtolower(trim($row[$structure[$field]] ?? ''));
        
        if (empty($value)) return $default;
        
        return in_array($value, ['sim', 'yes', 'true', '1', 's', 'y']);
    }
    
    /**
     * Parse string
     */
    private function parseString(array $row, array $structure, string $field): ?string
    {
        if (!isset($structure[$field])) {
            return null;
        }

        $value = trim($row[$structure[$field]] ?? '');
        
        return empty($value) ? null : $value;
    }

    /**
     * Parse integration key
     */
    private function parseIntegrationKey(array $row, array $structure, string $code, string $name): ?string
    {
        // Se tem coluna específica
        if (isset($structure['integration_key']) && !empty($row[$structure['integration_key']])) {
            return trim($row[$structure['integration_key']]);
        }

        // Detectar automaticamente baseado no nome
        $nameLower = strtolower($name);
        
        if (preg_match('/caixa/i', $name) && strlen($code) <= 3) return 'cash';
        if (preg_match('/banco|dep[óo]sito/i', $name)) return 'bank';
        if (preg_match('/cliente/i', $name) && !preg_match('/duv|cobr/i', $name)) return 'receivables';
        if (preg_match('/fornecedor/i', $name)) return 'payables';
        if (preg_match('/invent[áa]rio|mercador|stock/i', $name)) return 'inventory';
        if (preg_match('/imobili|fixed/i', $name)) return 'fixed_assets';
        if (preg_match('/capital.*social/i', $name)) return 'share_capital';
        if (preg_match('/resultado.*transit/i', $name)) return 'retained_earnings';
        if (preg_match('/resultado.*l[íi]quido/i', $name)) return 'net_income';
        if (preg_match('/venda/i', $name) && strlen($code) <= 2) return 'sales';
        if (preg_match('/servi[çc]o.*prestad/i', $name)) return 'services';
        if (preg_match('/custo.*mercador|cmvmc|cogs/i', $name)) return 'cogs';
        if (preg_match('/pessoal|payroll|sal[áa]rio/i', $name) && preg_match('/gast/i', $name)) return 'payroll';
        if (preg_match('/remunera.*pagar|sal[áa]rio.*pagar/i', $name)) return 'salaries_payable';
        if (preg_match('/deprecia[çc]/i', $name)) return 'depreciation';
        if (preg_match('/iva.*liquid|vat.*collect/i', $name)) return 'vat_collected';
        if (preg_match('/iva.*dedut|vat.*paid/i', $name)) return 'vat_paid';
        if (preg_match('/iva.*apura|vat.*settle/i', $name)) return 'vat_settlement';
        if (preg_match('/reten.*irt/i', $name)) return 'withholding_irt';
        if (preg_match('/reten.*servi/i', $name)) return 'withholding_services';
        if (preg_match('/inss.*empregado|inss.*employee/i', $name)) return 'inss_employee';
        if (preg_match('/inss.*empregador|inss.*employer/i', $name)) return 'inss_employer';
        
        return null;
    }

    /**
     * Gerar arquivo de seeder
     */
    private function generateSeeder(array $accounts): void
    {
        $output = "<?php\n\n";
        $output .= "// Gerado automaticamente em " . date('Y-m-d H:i:s') . "\n";
        $output .= "// Total de contas: " . count($accounts) . "\n\n";
        $output .= "return [\n";
        
        foreach ($accounts as $account) {
            $output .= "    [\n";
            $output .= "        'code' => '{$account['code']}',\n";
            $output .= "        'name' => '" . addslashes($account['name']) . "',\n";
            $output .= "        'type' => '{$account['type']}',\n";
            $output .= "        'nature' => '{$account['nature']}',\n";
            $output .= "        'level' => {$account['level']},\n";
            $output .= "        'is_view' => " . ($account['is_view'] ? 'true' : 'false') . ",\n";
            $output .= "        'blocked' => " . ($account['blocked'] ? 'true' : 'false') . ",\n";
            $output .= "        'parent_id' => null,\n";
            $output .= "        'integration_key' => " . ($account['integration_key'] ? "'{$account['integration_key']}'" : 'null') . ",\n";
            $output .= "    ],\n";
        }
        
        $output .= "];\n";
        
        $outputPath = database_path('seeders/Accounting/imported_accounts.php');
        file_put_contents($outputPath, $output);
        
        $this->info("✅ Arquivo gerado: {$outputPath}");
    }
}
