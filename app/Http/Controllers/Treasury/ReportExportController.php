<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Treasury\RelatoriosDeTesouraria;
use App\Support\CategoriasDeTesouraria;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Descarregar os relatórios financeiros em PDF e em Excel.
 *
 * Um relatório que só se vê no ecrã não serve para levar ao banco, ao
 * contabilista nem a uma reunião — e era essa a única forma que havia.
 *
 * As contas NÃO são refeitas aqui: vêm do mesmo
 * App\Services\Treasury\RelatoriosDeTesouraria que o ecrã usa. Recalcular do
 * lado da descarga é como o papel e o ecrã passam a discordar.
 */
class ReportExportController extends Controller
{
    public function pdf(Request $request)
    {
        [$tipo, $de, $ate, $empresa, $dados] = $this->preparar($request);

        $pdf = Pdf::loadView('pdf.treasury.report', [
            'tipo'    => $tipo,
            'titulo'  => RelatoriosDeTesouraria::nomeDoTipo($tipo),
            'de'      => $de,
            'ate'     => $ate,
            'empresa' => $empresa,
            'dados'   => $dados,
        ])->setPaper('a4', $tipo === 'cash_flow' || $tipo === 'dre' ? 'portrait' : 'landscape');

        return $pdf->stream($this->ficheiro($tipo, 'pdf'));
    }

    public function excel(Request $request)
    {
        [$tipo, $de, $ate, $empresa, $dados] = $this->preparar($request);

        $ss = new Spreadsheet();
        $folha = $ss->getActiveSheet();
        $folha->setTitle(mb_substr(RelatoriosDeTesouraria::nomeDoTipo($tipo), 0, 31));

        $ultimaColuna = match ($tipo) {
            'receivables', 'payables' => 'H',
            default => 'C',
        };

        // Cabeçalho comum
        $folha->setCellValue('A1', $empresa->name);
        $folha->setCellValue('A2', RelatoriosDeTesouraria::nomeDoTipo($tipo));
        $folha->setCellValue('A3', 'Período: ' . $this->dataPt($de) . ' a ' . $this->dataPt($ate));
        $folha->mergeCells("A1:{$ultimaColuna}1");
        $folha->mergeCells("A2:{$ultimaColuna}2");
        $folha->mergeCells("A3:{$ultimaColuna}3");
        $folha->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $folha->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $folha->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $linha = 5;

        match ($tipo) {
            'cash_flow'   => $this->folhaFluxoDeCaixa($folha, $dados, $linha),
            'dre'         => $this->folhaResultados($folha, $dados, $linha),
            'receivables' => $this->folhaEmAberto($folha, $dados['receivables'], 'Cliente', 'client', $linha),
            'payables'    => $this->folhaEmAberto($folha, $dados['payables'], 'Fornecedor', 'supplier', $linha),
            default       => null,
        };

        foreach (range('A', $ultimaColuna) as $c) {
            $folha->getColumnDimension($c)->setAutoSize(true);
        }

        $nome = $this->ficheiro($tipo, 'xlsx');

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── preparação comum ─────────────────────────────────────────────────────

    /**
     * @return array{0:string,1:string,2:string,3:Tenant,4:array}
     */
    private function preparar(Request $request): array
    {
        abort_unless(auth()->check(), 403);

        $tenantId = (int) activeTenantId();
        abort_unless($tenantId > 0, 403, 'Sem empresa activa.');

        $tipo = (string) $request->query('tipo', 'cash_flow');

        // Allowlist: o tipo vem do URL e alimenta um match. Aceitar o que
        // viesse deixava a porta aberta a pedir relatórios que não existem.
        abort_unless(array_key_exists($tipo, RelatoriosDeTesouraria::TIPOS), 404, 'Relatório desconhecido.');

        $de = $this->data($request->query('de'), now()->startOfMonth());
        $ate = $this->data($request->query('ate'), now()->endOfMonth());

        // Datas trocadas dariam um relatório vazio sem dizer porquê.
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        $empresa = Tenant::findOrFail($tenantId);

        $dados = (new RelatoriosDeTesouraria($tenantId, $de, $ate))->dados($tipo);

        return [$tipo, $de, $ate, $empresa, $dados];
    }

    private function data($valor, $omissao): string
    {
        try {
            return $valor ? \Carbon\Carbon::parse($valor)->format('Y-m-d') : $omissao->format('Y-m-d');
        } catch (\Throwable) {
            return $omissao->format('Y-m-d');
        }
    }

    private function dataPt(string $iso): string
    {
        return \Carbon\Carbon::parse($iso)->format('d/m/Y');
    }

    private function ficheiro(string $tipo, string $ext): string
    {
        return 'tesouraria-' . str_replace('_', '-', $tipo) . '-' . now()->format('Ymd-His') . '.' . $ext;
    }

    // ── folhas ───────────────────────────────────────────────────────────────

    private function folhaFluxoDeCaixa($folha, array $d, int $linha): void
    {
        $this->cabecalho($folha, ['Rubrica', 'Categoria', 'Valor (Kz)'], $linha);
        $linha++;

        $folha->setCellValue("A{$linha}", 'Saldo inicial');
        $folha->setCellValue("C{$linha}", (float) $d['initialBalance']);
        $linha += 2;

        $folha->setCellValue("A{$linha}", 'ENTRADAS');
        $folha->getStyle("A{$linha}")->getFont()->setBold(true);
        $linha++;

        foreach ($d['incomeByCategory'] as $c) {
            $folha->setCellValue("B{$linha}", CategoriasDeTesouraria::nome($c->category));
            $folha->setCellValue("C{$linha}", (float) $c->total);
            $linha++;
        }

        $folha->setCellValue("A{$linha}", 'Total de entradas');
        $folha->setCellValue("C{$linha}", (float) $d['totalIncome']);
        $folha->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true);
        $linha += 2;

        $folha->setCellValue("A{$linha}", 'SAÍDAS');
        $folha->getStyle("A{$linha}")->getFont()->setBold(true);
        $linha++;

        foreach ($d['expenseByCategory'] as $c) {
            $folha->setCellValue("B{$linha}", CategoriasDeTesouraria::nome($c->category));
            $folha->setCellValue("C{$linha}", (float) $c->total);
            $linha++;
        }

        $folha->setCellValue("A{$linha}", 'Total de saídas');
        $folha->setCellValue("C{$linha}", (float) $d['totalExpense']);
        $folha->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true);
        $linha += 2;

        $folha->setCellValue("A{$linha}", 'SALDO FINAL');
        $folha->setCellValue("C{$linha}", (float) $d['finalBalance']);
        $folha->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true)->setSize(12);

        $folha->getStyle('C5:C' . $linha)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    private function folhaResultados($folha, array $d, int $linha): void
    {
        $this->cabecalho($folha, ['Rubrica', 'Detalhe', 'Valor (Kz)'], $linha);
        $linha++;

        $rubricas = [
            ['Receita bruta', $d['grossRevenue'], false],
            ['Deduções', -$d['deductions'], false],
            ['Receita líquida', $d['netRevenue'], true],
            ['Custos operacionais', -$d['operationalCosts'], false],
            ['Lucro bruto', $d['grossProfit'], true],
        ];

        foreach ($rubricas as [$nome, $valor, $negrito]) {
            $folha->setCellValue("A{$linha}", $nome);
            $folha->setCellValue("C{$linha}", (float) $valor);
            if ($negrito) {
                $folha->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true);
            }
            $linha++;
        }

        $linha++;
        $folha->setCellValue("A{$linha}", 'DESPESAS POR CATEGORIA');
        $folha->getStyle("A{$linha}")->getFont()->setBold(true);
        $linha++;

        foreach ($d['expensesByCategory'] as $c) {
            $folha->setCellValue("B{$linha}", CategoriasDeTesouraria::nome($c->category));
            $folha->setCellValue("C{$linha}", (float) $c->total);
            $linha++;
        }

        $folha->setCellValue("A{$linha}", 'Total de despesas');
        $folha->setCellValue("C{$linha}", (float) $d['totalExpenses']);
        $folha->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true);
        $linha += 2;

        $folha->setCellValue("A{$linha}", 'Lucro operacional');
        $folha->setCellValue("C{$linha}", (float) $d['operationalProfit']);
        $linha++;

        $folha->setCellValue("A{$linha}", 'RESULTADO LÍQUIDO');
        $folha->setCellValue("C{$linha}", (float) $d['netProfit']);
        $folha->getStyle("A{$linha}:C{$linha}")->getFont()->setBold(true)->setSize(12);
        $linha += 2;

        // O relatório diz o que não sabe, em vez de deixar o leitor supor.
        $folha->setCellValue("A{$linha}", 'Nota: sem imposto sobre o lucro e sem deduções por notas de crédito.');
        $folha->getStyle("A{$linha}")->getFont()->setItalic(true)->setSize(9);

        $folha->getStyle('C5:C' . $linha)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    private function folhaEmAberto($folha, $linhas, string $rotuloQuem, string $chaveQuem, int $linha): void
    {
        $this->cabecalho($folha, [
            'Documento', $rotuloQuem, 'Data', 'Vencimento', 'Total (Kz)', 'Pago (Kz)', 'Em dívida (Kz)', 'Situação',
        ], $linha);

        $primeira = ++$linha;

        foreach ($linhas as $l) {
            $folha->setCellValue("A{$linha}", $l['invoice_number']);
            $folha->setCellValue("B{$linha}", $l[$chaveQuem] ?? '—');
            $folha->setCellValue("C{$linha}", optional($l['invoice_date'])->format('d/m/Y'));
            $folha->setCellValue("D{$linha}", optional($l['due_date'])->format('d/m/Y'));
            $folha->setCellValue("E{$linha}", (float) $l['total']);
            $folha->setCellValue("F{$linha}", (float) $l['paid']);
            $folha->setCellValue("G{$linha}", (float) $l['balance']);
            $folha->setCellValue("H{$linha}", $l['overdue'] ? 'VENCIDA' : 'Em prazo');

            if ($l['overdue']) {
                $folha->getStyle("H{$linha}")->getFont()->getColor()->setRGB('B91C1C');
            }

            $linha++;
        }

        if ($linha > $primeira) {
            $folha->setCellValue("D{$linha}", 'TOTAL');
            $folha->setCellValue("G{$linha}", '=SUM(G' . $primeira . ':G' . ($linha - 1) . ')');
            $folha->getStyle("D{$linha}:H{$linha}")->getFont()->setBold(true);

            $folha->getStyle("E{$primeira}:G{$linha}")->getNumberFormat()->setFormatCode('#,##0.00');
            $folha->getStyle("A" . ($primeira - 1) . ":H{$linha}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        } else {
            $folha->setCellValue("A{$linha}", 'Sem documentos em aberto neste período.');
        }
    }

    private function cabecalho($folha, array $colunas, int $linha): void
    {
        $letra = 'A';

        foreach ($colunas as $titulo) {
            $folha->setCellValue($letra . $linha, $titulo);
            $letra++;
        }

        $ultima = chr(ord('A') + count($colunas) - 1);
        $folha->getStyle("A{$linha}:{$ultima}{$linha}")->getFont()->setBold(true);
        $folha->getStyle("A{$linha}:{$ultima}{$linha}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    }
}
