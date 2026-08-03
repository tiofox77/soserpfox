<?php

namespace App\Console\Commands\AGT;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use App\Services\AGT\RegisterService;
use Illuminate\Console\Command;

/**
 * AGT v1.2 — Cenário C1 (Conformidade Estrutural Factura/Recibo).
 *
 * Gera um payload completo, assinado, com 5 cenários fiscais distintos:
 *  1) Angola continental, IVA 14% normal
 *  2) Cabinda, IVA reduzido 1%
 *  3) Consumidor Final, IEC + IVA
 *  4) Cliente estrangeiro (PT), isento (M30 - exportação serviços)
 *  5) Adquirente obrigado a cativar, retenção IRT 6,5%
 *
 * Uso:
 *   php artisan agt:test-c1 --tenant=1
 *   php artisan agt:test-c1 --tenant=1 --type=FT  (Factura simples)
 *   php artisan agt:test-c1 --tenant=1 --type=NC  (Nota de Crédito)
 *   php artisan agt:test-c1 --tenant=1 --output=c:/path/to/file.json
 */
class TestC1Command extends Command
{
    protected $signature = 'agt:test-c1
        {--tenant= : ID do tenant (default: 1)}
        {--nif= : NIF a usar como taxRegistrationNumber (override; default: NIF do tenant)}
        {--customer-nif= : NIF angolano real para os 4 clientes AO (default: NIFs fictícios)}
        {--customer-name= : Nome da empresa para os 4 clientes AO (default: nomes fictícios)}
        {--type=FR : Tipo de documento (FR, FT, NC)}
        {--output= : Caminho do ficheiro JSON (default: scripts/agt_<type>_C1.json)}';

    protected $description = 'Gera JSON AGT v1.2 conforme com 5 cenários fiscais para certificação (C1).';

    public function handle(): int
    {
        $tenantId = (int) ($this->option('tenant') ?? 1);
        $type     = strtoupper($this->option('type') ?? 'FR');
        $output   = $this->option('output')
            ?: base_path('scripts/agt_' . strtolower($type) . '_C1.json');

        $tenant   = Tenant::findOrFail($tenantId);
        $settings = InvoicingSettings::forTenant($tenantId);

        // Garantir defaults para o softwareInfo se vazios
        $this->ensureSoftwareInfo($settings);

        $overrideNif = $this->option('nif');
        $usedNif     = $overrideNif ?: $tenant->nif;

        $this->info("Tenant: {$tenant->name} · NIF: {$tenant->nif}");
        if ($overrideNif) {
            $this->warn("⚠ NIF override aplicado: {$overrideNif} (em vez de {$tenant->nif})");
        }
        $this->info("Tipo: {$type} · Output: {$output}");

        // A AGT recusa NIFs que não existam no sistema dela ("Número fiscal
        // Angolano ... é desconhecido"), por isso os NIFs fictícios 54000000xx
        // não servem. Por omissão usa-se o NIF da própria empresa: é real e a
        // AGT já o reconhece, visto que o aceita como emissor.
        $customerNif = $this->option('customer-nif') ?: ($overrideNif ?: $tenant->nif);
        if (!$this->option('customer-nif')) {
            $this->warn("⚠ Clientes AO com o NIF da própria empresa ({$customerNif}) — "
                . 'NIFs fictícios são recusados pela AGT. Use --customer-nif para outro.');
        }

        $documents = $this->buildScenarios(
            $type,
            $customerNif,
            $this->option('customer-name')
        );

        $register = new RegisterService($settings);
        $payload  = $register->buildPayload($documents, null, $overrideNif);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @mkdir(dirname($output), 0775, true);
        file_put_contents($output, $json);

        $this->newLine();
        $this->info("✅ JSON gerado: {$output}");
        $this->info("📦 Tamanho: " . strlen($json) . ' bytes · ' . count($documents) . ' documentos');
        $this->newLine();

        // Resumo
        $this->table(
            ['#', 'Doc', 'Cliente', 'IVA', 'Bruto', 'Retenção'],
            collect($payload['documents'])->map(fn($d, $i) => [
                $i + 1,
                $d['documentNo'],
                substr($d['companyName'], 0, 30),
                ($d['lines'][0]['taxes'][0]['taxPercentage'] ?? 0) . '%',
                number_format($d['documentTotals']['grossTotal'], 2, ',', '.') . ' Kz',
                isset($d['withholdingTaxList']) ? ($d['withholdingTaxList'][0]['withholdingTaxAmount'] . ' Kz') : '—',
            ])->all()
        );

        return self::SUCCESS;
    }

    /** Define defaults para softwareInfo se nunca configurado (necessário para JWS). */
    private function ensureSoftwareInfo(InvoicingSettings $settings): void
    {
        $changed = false;
        if (empty($settings->agt_product_id)) {
            $settings->agt_product_id = 'SOS ERP - SOLUÇÕES EMPRESARIAIS';
            $changed = true;
        }
        if (empty($settings->agt_product_version)) {
            $settings->agt_product_version = '1.0';
            $changed = true;
        }
        if (empty($settings->agt_software_validation_number)) {
            $settings->agt_software_validation_number = 'C_PENDING';
            $changed = true;
        }
        if (empty($settings->agt_schema_version)) {
            $settings->agt_schema_version = '1.2';
            $changed = true;
        }
        if ($changed) {
            $settings->save();
            $this->warn('softwareInfo defaults aplicados em invoicing_settings.');
        }
    }

    /** 5 cenários fiscais distintos. */
    private function buildScenarios(string $type, ?string $aoNif = null, ?string $aoName = null): array
    {
        $year     = date('Y');
        $issueDate = now()->toDateString();
        $entry    = fn(int $offsetMin) => now()->addMinutes($offsetMin)->utc()->format('Y-m-d\TH:i:s\Z');

        $prefix = match ($type) {
            'FT' => 'FT',
            'NC' => 'NC',
            default => 'FR',
        };

        // Override de cliente angolano (para docs 1, 2, 3, 5).
        // Se fornecido, todos os clientes AO usam o mesmo NIF/nome — útil quando AGT só aceita NIFs reais.
        $customerAONif  = $aoNif  ?: null;
        $customerAOName = $aoName ?: null;

        // Para NC (Nota de Crédito) a lógica fiscal inverte: usa debitAmount em vez de creditAmount.
        $isCreditNote = ($type === 'NC');

        $scenarios = [
            // 1) Angola continental, IVA 14% NOR
            [
                'documentNo'      => "{$prefix} {$year}/000001",
                'documentType'    => $type,
                'documentDate'    => $issueDate,
                'systemEntryDate' => $entry(0),
                'eacCode'         => '62010',
                'customerTaxID'   => $customerAONif ?? '5400000001',
                'customerCountry' => 'AO',
                'companyName'     => $customerAOName ?? 'Cliente Angola Lda',
                'lines' => [[
                    'lineNumber'         => 1,
                    'productCode'        => 'SRV001',
                    'productDescription' => 'Serviço de consultoria informática',
                    'quantity'           => 1,
                    'unitOfMeasure'      => 'UN',
                    'unitPrice'          => 100000,
                    'unitPriceBase'      => 100000,
                    'creditAmount'       => 100000,
                    'taxes' => [[
                        'taxType'         => 'IVA',
                        'taxCountryRegion' => 'AO',
                        'taxCode'         => 'NOR',
                        'taxPercentage'   => 14,
                        'taxContribution' => 14000,
                    ]],
                ]],
                'documentTotals' => [
                    'taxPayable' => 14000,
                    'netTotal'   => 100000,
                    'grossTotal' => 114000,
                ],
            ],

            // 2) Cabinda, IVA reduzido 1%
            [
                'documentNo'      => "{$prefix} {$year}/000002",
                'documentType'    => $type,
                'documentDate'    => $issueDate,
                'systemEntryDate' => $entry(15),
                'eacCode'         => '46900',
                'customerTaxID'   => $customerAONif ?? '5400000002',
                'customerCountry' => 'AO',
                'companyName'     => $customerAOName ?? 'Empresa Cabinda SA',
                'lines' => [[
                    'lineNumber'         => 1,
                    'productCode'        => 'PRD002',
                    'productDescription' => 'Equipamento eléctrico (regime Cabinda)',
                    'quantity'           => 2,
                    'unitOfMeasure'      => 'UN',
                    'unitPrice'          => 50000,
                    'unitPriceBase'      => 50000,
                    'creditAmount'       => 100000,
                    'taxes' => [[
                        'taxType'         => 'IVA',
                        'taxCountryRegion' => 'AO-CAB',
                        'taxCode'         => 'RED',
                        'taxPercentage'   => 1,
                        'taxContribution' => 1000,
                    ]],
                ]],
                'documentTotals' => [
                    'taxPayable' => 1000,
                    'netTotal'   => 100000,
                    'grossTotal' => 101000,
                ],
            ],

            // 3) Consumidor Final, IEC + IVA
            [
                'documentNo'      => "{$prefix} {$year}/000003",
                'documentType'    => $type,
                'documentDate'    => $issueDate,
                'systemEntryDate' => $entry(30),
                'eacCode'         => '47190',
                // Consumidor Final: o NIF genérico 999999999 é o correcto e a AGT
                // aceita-o. Não recebe o override — o cenário é "venda sem NIF".
                'customerTaxID'   => '999999999',
                'customerCountry' => 'AO',
                'companyName'     => 'Consumidor Final',
                'lines' => [[
                    'lineNumber'         => 1,
                    'productCode'        => 'PRD003',
                    'productDescription' => 'Bebida alcoólica (com IEC)',
                    'quantity'           => 10,
                    'unitOfMeasure'      => 'UN',
                    'unitPrice'          => 5000,
                    'unitPriceBase'      => 5000,
                    'creditAmount'       => 50000,
                    'taxes' => [
                        [
                            'taxType'         => 'IVA',
                            'taxCountryRegion' => 'AO',
                            'taxCode'         => 'NOR',
                            'taxPercentage'   => 14,
                            'taxContribution' => 8400,
                        ],
                        [
                            'taxType'         => 'IEC',
                            'taxCountryRegion' => 'AO',
                            'taxCode'         => 'NOR',
                            'taxPercentage'   => 20,
                            'taxContribution' => 10000,
                        ],
                    ],
                ]],
                'documentTotals' => [
                    'taxPayable' => 18400,
                    'netTotal'   => 50000,
                    'grossTotal' => 68400,
                ],
            ],

            // 4) Estrangeiro (PT), isento exportação M30
            [
                'documentNo'      => "{$prefix} {$year}/000004",
                'documentType'    => $type,
                'documentDate'    => $issueDate,
                'systemEntryDate' => $entry(45),
                'eacCode'         => '62010',
                'customerTaxID'   => '999999999',
                'customerCountry' => 'PT',
                'companyName'     => 'Foreign Client Ltd',
                'lines' => [[
                    'lineNumber'         => 1,
                    'productCode'        => 'SRV004',
                    'productDescription' => 'Exportação de serviços (isento IVA)',
                    'quantity'           => 1,
                    'unitOfMeasure'      => 'UN',
                    'unitPrice'          => 200000,
                    'unitPriceBase'      => 200000,
                    'creditAmount'       => 200000,
                    'taxes' => [[
                        'taxType'             => 'IVA',
                        'taxCountryRegion'    => 'AO',
                        'taxCode'             => 'ISE',
                        'taxPercentage'       => 0,
                        'taxContribution'     => 0,
                        'taxExemptionCode'    => 'M30',
                        'taxExemptionReason'  => 'Exportação de serviços - Art. 12º CIVA',
                    ]],
                ]],
                'documentTotals' => [
                    'taxPayable' => 0,
                    'netTotal'   => 200000,
                    'grossTotal' => 200000,
                ],
            ],

            // 5) Adquirente obrigado a cativar — retenção IRT 6,5%
            [
                'documentNo'      => "{$prefix} {$year}/000005",
                'documentType'    => $type,
                'documentDate'    => $issueDate,
                'systemEntryDate' => $entry(60),
                'eacCode'         => '62010',
                'customerTaxID'   => $customerAONif ?? '5400000005',
                'customerCountry' => 'AO',
                'companyName'     => $customerAOName ?? 'Empresa Pública Obrigada Cativar',
                'lines' => [[
                    'lineNumber'         => 1,
                    'productCode'        => 'SRV005',
                    'productDescription' => 'Serviço técnico (sujeito a retenção IRT)',
                    'quantity'           => 1,
                    'unitOfMeasure'      => 'UN',
                    'unitPrice'          => 500000,
                    'unitPriceBase'      => 500000,
                    'creditAmount'       => 500000,
                    'taxes' => [[
                        'taxType'         => 'IVA',
                        'taxCountryRegion' => 'AO',
                        'taxCode'         => 'NOR',
                        'taxPercentage'   => 14,
                        'taxContribution' => 70000,
                    ]],
                ]],
                'documentTotals' => [
                    'taxPayable' => 70000,
                    'netTotal'   => 500000,
                    'grossTotal' => 570000,
                ],
                'withholdingTaxList' => [[
                    'withholdingTaxType'        => 'IRT',
                    'withholdingTaxDescription' => 'Imposto sobre o Rendimento do Trabalho - Prestação de serviços',
                    'withholdingTaxAmount'      => 32500,
                ]],
            ],

            // 6) Imposto de Selo — verba 6 (escritos de quitação), 1%.
            // A especificação da AGT exige IVA, IEC E IS; o IS faltava. A verba 6
            // aplica-se a quitações, que é exactamente o que uma Fatura-Recibo é.
            [
                'documentNo'      => "{$prefix} {$year}/000006",
                'documentType'    => $type,
                'documentDate'    => $issueDate,
                'systemEntryDate' => $entry(75),
                'eacCode'         => '64190',
                'customerTaxID'   => $customerAONif ?? '999999999',
                'customerCountry' => 'AO',
                'companyName'     => $customerAOName ?? 'Cliente Operação Financeira',
                'lines' => [[
                    'lineNumber'         => 1,
                    'productCode'        => 'SRV006',
                    'productDescription' => 'Comissão de quitação (sujeita a Imposto de Selo verba 6)',
                    'quantity'           => 1,
                    'unitOfMeasure'      => 'UN',
                    'unitPrice'          => 300000,
                    'unitPriceBase'      => 300000,
                    'creditAmount'       => 300000,
                    'taxes' => [
                        [
                            'taxType'          => 'IVA',
                            'taxCountryRegion' => 'AO',
                            'taxCode'          => 'NOR',
                            'taxPercentage'    => 14,
                            'taxContribution'  => 42000,
                        ],
                        [
                            'taxType'          => 'IS',
                            'taxCountryRegion' => 'AO',
                            'taxCode'          => 'NOR',
                            'taxPercentage'    => 1,
                            'taxContribution'  => 3000,
                        ],
                    ],
                ]],
                'documentTotals' => [
                    'taxPayable' => 45000,   // 42.000 IVA + 3.000 IS
                    'netTotal'   => 300000,
                    'grossTotal' => 345000,
                ],
            ],
        ];

        // Pós-processamento para NC (Nota de Crédito):
        // Inverte creditAmount -> debitAmount em todas as linhas.
        // referenceInfo é um OBJECT por linha (spec AGT v1.2), com reference + reason.
        if ($isCreditNote) {
            foreach ($scenarios as $i => &$doc) {
                $refInvoice = 'FT ' . $year . '/' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
                foreach ($doc['lines'] as &$line) {
                    $amount                = $line['creditAmount'] ?? 0;
                    $line['debitAmount']   = $amount;
                    $line['creditAmount']  = 0;
                    $line['referenceInfo'] = [
                        'reference' => $refInvoice,
                        'reason'    => 'Devolução parcial / correcção de valor',
                    ];
                }
                unset($line);
            }
            unset($doc);
        }

        return $scenarios;
    }
}
