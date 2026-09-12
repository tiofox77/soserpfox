<?php

namespace App\Services\Accounting;

use Barryvdh\DomPDF\Facade\Pdf;

/*
 * OITO CLASSES IMPORTADAS AQUI NÃO EXISTIAM — só o `BalanceSheetExport` estava
 * escrito, e mesmo esse era chamado por uma API que a versão instalada do
 * `maatwebsite/excel` (v1.1.5, da era do Laravel 4) não tem. As folhas escrevem-se
 * agora com PhpSpreadsheet, que está instalado e é o que o export das retenções
 * já usava.
 */

class ReportExportService
{
    /**
     * Exporta Mapa de Retenções na Fonte para PDF
     */
    public function exportWithholdingPDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.withholding', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        return $pdf->download('retencoes_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Exporta Mapa de Retenções na Fonte para Excel (.xlsx REAL).
     * Usa PhpSpreadsheet diretamente (já instalado, ^5.1) — o wrapper maatwebsite/excel
     * instalado é v1.x (incompatível com o phpspreadsheet ^5 do projeto).
     */
    public function exportWithholdingExcel($data, $dateFrom, $dateTo)
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Retenções');

        $num = '#,##0.00';
        $r = 1;
        $sheet->setCellValue("A{$r}", 'MAPA DE RETENÇÕES NA FONTE');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A' . (++$r), 'Período: ' . date('d/m/Y', strtotime($dateFrom)) . ' a ' . date('d/m/Y', strtotime($dateTo)));
        $sheet->setCellValue('A' . (++$r), 'Valores em Kwanzas (Kz)');
        $r += 2;

        foreach (['A' => 'Data', 'B' => 'Documento', 'C' => 'Conta', 'D' => 'Descrição', 'E' => 'Valor Retido'] as $col => $h) {
            $sheet->setCellValue("{$col}{$r}", $h);
        }
        $sheet->getStyle("A{$r}:E{$r}")->getFont()->setBold(true);
        $r++;

        foreach (($data['types'] ?? []) as $type) {
            $sheet->setCellValue("A{$r}", $type['name']);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
            foreach ($type['lines'] as $l) {
                $sheet->setCellValue("A{$r}", $l['date'] ? date('d/m/Y', strtotime((string) $l['date'])) : '-');
                $sheet->setCellValue("B{$r}", $l['ref'] ?: '-');
                $sheet->setCellValue("C{$r}", $l['account_code'] . ' - ' . $l['account_name']);
                $sheet->setCellValue("D{$r}", $l['narration'] ?: '-');
                $sheet->setCellValue("E{$r}", (float) $l['amount']);
                $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($num);
                $r++;
            }
            $sheet->setCellValue("D{$r}", 'Subtotal ' . $type['name']);
            $sheet->setCellValue("E{$r}", (float) $type['total']);
            $sheet->getStyle("D{$r}:E{$r}")->getFont()->setBold(true);
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($num);
            $r += 2;
        }

        $sheet->setCellValue("D{$r}", 'TOTAL RETIDO');
        $sheet->setCellValue("E{$r}", (float) ($data['total'] ?? 0));
        $sheet->getStyle("D{$r}:E{$r}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($num);

        foreach (['A', 'B', 'C', 'D', 'E'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($ss) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        }, 'retencoes_' . date('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Exporta Balanço para PDF
     */
    public function exportBalanceSheetPDF($data, $date)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.balance-sheet', [
            'data' => $data,
            'date' => $date,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('balanco_' . date('Y-m-d', strtotime($date)) . '.pdf');
    }
    
    /**
     * Exporta DR por Natureza para PDF
     */
    public function exportIncomeNaturePDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.income-nature', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('dr_natureza_' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Exporta DR por Funções para PDF
     */
    public function exportIncomeFunctionPDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.income-function', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('dr_funcoes_' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Exporta Fluxos de Caixa para PDF
     */
    public function exportCashFlowPDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.cash-flow', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('fluxos_caixa_' . date('Y-m-d') . '.pdf');
    }
    
    /*
     * ─── OS EXPORTS PARA EXCEL ────────────────────────────────────────────
     *
     * NENHUM DELES FUNCIONAVA. Eram seis chamadas a
     * `Excel::download(new XptoExport(...), 'nome.xlsx')`, e duas coisas
     * faltavam de uma vez:
     *
     *  · as CLASSES não existiam — das oito importadas no topo deste ficheiro
     *    só o `BalanceSheetExport` estava escrito, e foi apagado com elas;
     *  · e o `maatwebsite/excel` instalado é a v1.1.5, da era do Laravel 4, que
     *    não tem `Excel::download(Export, nome)` nenhum. A API dessa forma é a
     *    da v3.
     *
     * Ou seja: cada um destes botões atirava «Class not found» ou «Call to
     * undefined method». O único que funcionava era o das retenções, escrito com
     * PhpSpreadsheet à mão — e é esse o caminho que todos seguem agora, por uma
     * FOLHA GENÉRICA (`folha()`), que é menos código do que seis exportadores e
     * não os deixa divergir.
     */

    /**
     * Escreve uma folha de cálculo e devolve-a para descarga.
     *
     * @param  string  $titulo     O que se lê na primeira linha.
     * @param  string  $subtitulo  O período, por baixo.
     * @param  array<int, string>  $cabecalhos
     * @param  array<int, array>   $linhas  Cada linha: valores pela ordem dos
     *                                      cabeçalhos. Uma linha `['__negrito' => true]`
     *                                      sai a negrito (subtotais e títulos de grupo).
     */
    protected function folha(string $nome, string $titulo, string $subtitulo, array $cabecalhos, array $linhas, string $aba = 'Folha')
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(mb_substr($aba, 0, 31));

        $colunas = [];

        for ($i = 0; $i < count($cabecalhos); $i++) {
            $colunas[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        }

        $ultima = end($colunas) ?: 'A';
        $num = '#,##0.00';
        $r = 1;

        $sheet->setCellValue("A{$r}", $titulo);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);

        if ($subtitulo !== '') {
            $sheet->setCellValue('A'.(++$r), $subtitulo);
        }

        $sheet->setCellValue('A'.(++$r), 'Valores em Kwanzas (Kz)');
        $r += 2;

        foreach ($cabecalhos as $i => $h) {
            $sheet->setCellValue($colunas[$i].$r, $h);
        }

        $sheet->getStyle("A{$r}:{$ultima}{$r}")->getFont()->setBold(true);
        $r++;

        foreach ($linhas as $linha) {
            $negrito = ! empty($linha['__negrito']);
            unset($linha['__negrito']);

            $i = 0;

            foreach ($linha as $valor) {
                $celula = ($colunas[$i] ?? 'A').$r;

                $sheet->setCellValue($celula, $valor);

                // OS NÚMEROS SAEM COMO NÚMEROS, com o formato de dinheiro: texto
                // numa folha de cálculo não se soma nem se ordena.
                if (is_int($valor) || is_float($valor)) {
                    $sheet->getStyle($celula)->getNumberFormat()->setFormatCode($num);
                }

                $i++;
            }

            if ($negrito) {
                $sheet->getStyle("A{$r}:{$ultima}{$r}")->getFont()->setBold(true);
            }

            $r++;
        }

        foreach ($colunas as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($ss) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        }, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** O período por extenso, para o subtítulo das folhas. */
    private function periodo($de, $ate): string
    {
        return 'Período: '.date('d/m/Y', strtotime((string) $de)).' a '.date('d/m/Y', strtotime((string) $ate));
    }

    public function exportTrialBalanceExcel($data, $dateFrom, $dateTo)
    {
        $linhas = [];

        foreach (($data['contas'] ?? []) as $c) {
            $linhas[] = [
                $c['codigo'] ?? '', $c['nome'] ?? '',
                (float) ($c['debito'] ?? 0), (float) ($c['credito'] ?? 0), (float) ($c['saldo'] ?? 0),
            ];
        }

        $t = $data['totais'] ?? [];

        $linhas[] = [
            '', 'TOTAIS',
            (float) ($t['debito'] ?? 0), (float) ($t['credito'] ?? 0), (float) ($t['diferenca'] ?? 0),
            '__negrito' => true,
        ];

        return $this->folha(
            'balancete_'.date('Y-m-d').'.xlsx',
            'BALANCETE DE VERIFICAÇÃO',
            $this->periodo($dateFrom, $dateTo),
            ['Código', 'Conta', 'Débito', 'Crédito', 'Saldo'],
            $linhas,
            'Balancete',
        );
    }

    public function exportBalanceSheetExcel($data, $date)
    {
        $linhas = [];

        foreach ([
            'activo' => 'ACTIVO',
            'passivo' => 'PASSIVO',
            'capital_proprio' => 'CAPITAL PRÓPRIO',
        ] as $chave => $rotulo) {
            $bloco = $data[$chave] ?? null;

            if (! $bloco) {
                continue;
            }

            $linhas[] = [$rotulo, '', '__negrito' => true];

            foreach (($bloco['details'] ?? $bloco['detalhes'] ?? []) as $d) {
                $linhas[] = [
                    ($d['code'] ?? $d['codigo'] ?? '').' '.($d['name'] ?? $d['nome'] ?? ''),
                    (float) ($d['balance'] ?? $d['saldo'] ?? 0),
                ];
            }

            $linhas[] = ['Total '.$rotulo, (float) ($bloco['total'] ?? 0), '__negrito' => true];
            $linhas[] = ['', ''];
        }

        return $this->folha(
            'balanco_'.date('Y-m-d', strtotime((string) $date)).'.xlsx',
            'BALANÇO',
            'À data de '.date('d/m/Y', strtotime((string) $date)),
            ['Rubrica', 'Valor'],
            $linhas,
            'Balanço',
        );
    }

    public function exportIncomeNatureExcel($data, $dateFrom, $dateTo)
    {
        return $this->folha(
            'dr_natureza_'.date('Y-m-d').'.xlsx',
            'DEMONSTRAÇÃO DE RESULTADOS POR NATUREZA',
            $this->periodo($dateFrom, $dateTo),
            ['Rubrica', 'Valor'],
            $this->rubricas($data),
            'DR Natureza',
        );
    }

    public function exportIncomeFunctionExcel($data, $dateFrom, $dateTo)
    {
        return $this->folha(
            'dr_funcoes_'.date('Y-m-d').'.xlsx',
            'DEMONSTRAÇÃO DE RESULTADOS POR FUNÇÕES',
            $this->periodo($dateFrom, $dateTo),
            ['Rubrica', 'Valor'],
            $this->rubricas($data),
            'DR Funções',
        );
    }

    public function exportCashFlowExcel($data, $dateFrom, $dateTo)
    {
        return $this->folha(
            'fluxos_caixa_'.date('Y-m-d').'.xlsx',
            'DEMONSTRAÇÃO DE FLUXOS DE CAIXA',
            $this->periodo($dateFrom, $dateTo),
            ['Rubrica', 'Valor'],
            $this->rubricas($data),
            'Fluxos de Caixa',
        );
    }

    /**
     * As rubricas de uma demonstração, aplanadas.
     *
     * Os três serviços devolvem estruturas diferentes — uns com `total` e
     * `details`, outros com valores directos. Em vez de um exportador por
     * serviço, percorre-se o que vier: um número é uma rubrica, um bloco com
     * `total` é um grupo.
     */
    private function rubricas(array $data, int $nivel = 0): array
    {
        $linhas = [];
        $recuo = str_repeat('    ', $nivel);

        foreach ($data as $chave => $valor) {
            if (is_numeric($chave) || in_array($chave, ['details', 'detalhes', 'count'], true)) {
                continue;
            }

            $rotulo = $recuo.ucfirst(str_replace('_', ' ', (string) $chave));

            if (is_numeric($valor)) {
                $linhas[] = [$rotulo, (float) $valor];

                continue;
            }

            if (! is_array($valor)) {
                continue;
            }

            if (array_key_exists('total', $valor)) {
                $linhas[] = [$rotulo, (float) $valor['total'], '__negrito' => $nivel === 0];

                foreach (($valor['details'] ?? $valor['detalhes'] ?? []) as $d) {
                    if (! is_array($d)) {
                        continue;
                    }

                    $linhas[] = [
                        $recuo.'    '.(($d['code'] ?? $d['codigo'] ?? '').' '.($d['name'] ?? $d['nome'] ?? '')),
                        (float) ($d['balance'] ?? $d['saldo'] ?? $d['total'] ?? $d['amount'] ?? 0),
                    ];
                }

                continue;
            }

            $linhas[] = [$rotulo, '', '__negrito' => true];
            $linhas = array_merge($linhas, $this->rubricas($valor, $nivel + 1));
        }

        return $linhas;
    }

    public function exportVatReportExcel($data, $dateFrom, $dateTo)
    {
        $linhas = [];

        foreach ([
            'liquidado' => 'IVA LIQUIDADO (vendas)',
            'dedutivel' => 'IVA DEDUTÍVEL (compras)',
        ] as $chave => $rotulo) {
            $linhas[] = [$rotulo, '', '', '', '__negrito' => true];

            foreach (($data[$chave]['linhas'] ?? []) as $l) {
                $linhas[] = [
                    $l['dia'] ? date('d/m/Y', strtotime((string) $l['dia'])) : '-',
                    $l['ref'] ?: '-',
                    $l['conta'] ?? '',
                    (float) ($l['debito'] ?? 0),
                    (float) ($l['credito'] ?? 0),
                ];
            }

            $linhas[] = ['', '', '', '', ''];
        }

        $t = $data['totais'] ?? [];

        $linhas[] = ['', '', 'IVA liquidado', (float) ($t['liquidado'] ?? 0), '', '__negrito' => true];
        $linhas[] = ['', '', 'IVA dedutível', (float) ($t['dedutivel'] ?? 0), '', '__negrito' => true];
        $linhas[] = ['', '', 'A ENTREGAR AO ESTADO', (float) ($t['a_entregar'] ?? 0), '', '__negrito' => true];

        return $this->folha(
            'mapa_iva_'.date('Y-m-d').'.xlsx',
            'MAPA DE IVA',
            $this->periodo($dateFrom, $dateTo),
            ['Data', 'Documento', 'Conta', 'Débito', 'Crédito'],
            $linhas,
            'Mapa de IVA',
        );
    }

    /**
     * Obtém informações da empresa
     */
    protected function getCompanyInfo()
    {
        $tenant = auth()->user()?->tenant;

        return [
            'name' => $tenant?->name ?? 'Empresa',
            'nif' => $tenant?->nif ?? '',
            'address' => $tenant?->address ?? '',
            'city' => $tenant?->city ?? 'Luanda',
            // No cabeçalho de um relatório o país lê-se por extenso — mas é o
            // da empresa, e não «Angola» fixo: havia empresas com outro.
            'country' => \App\Support\Geografia::nomeDoPais($tenant?->country)
                ?? \App\Support\Geografia::nomeDoPais(\App\Support\Geografia::PAIS_PADRAO),
        ];
    }
    
    /**
     * Formata valor em Kwanzas
     */
    public function formatKz($value)
    {
        return number_format($value, 2, ',', '.') . ' Kz';
    }
    
    /**
     * Formata data em português
     */
    public function formatDate($date)
    {
        return date('d/m/Y', strtotime($date));
    }
}
