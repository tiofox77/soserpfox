<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Invoicing\StockApiController;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * A LISTA DE STOCK EM PAPEL E EM EXCEL.
 *
 * Sai EXACTAMENTE o que está no ecrã: os mesmos filtros viajam no URL e a
 * consulta é a mesma do `StockApiController`. Um mapa que mostrasse outra
 * coisa que não o que se estava a ver seria pior do que não haver mapa —
 * quem imprime está a conferir a prateleira contra aquilo.
 *
 * SEM PAGINAÇÃO: um inventário parte-se em páginas no ecrã porque não cabe,
 * não porque só interesse a primeira. Em papel e em folha de cálculo vai
 * inteiro.
 */
class StockExportController extends Controller
{
    public function __construct(private StockApiController $api)
    {
    }

    /** O mapa em HTML com botão de imprimir — como os recibos e o mapa de IRT. */
    public function imprimir(Request $request)
    {
        [$linhas, $resumo, $cabecalho] = $this->mapa($request);

        return view('pdf.invoicing.stock', [
            'linhas' => $linhas,
            'resumo' => $resumo,
            'cabecalho' => $cabecalho,
            'empresa' => Tenant::find(activeTenantId()),
        ]);
    }

    /** O mesmo mapa em .xlsx, para quem tem de o trabalhar noutro sítio. */
    public function excel(Request $request)
    {
        [$linhas, $resumo, $cabecalho] = $this->mapa($request);

        $folha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $pagina = $folha->getActiveSheet();
        $pagina->setTitle('Stock');

        $pagina->setCellValue('A1', mb_strtoupper(__('Gestão de Stock')));
        $pagina->setCellValue('A2', $cabecalho['descricao']);
        $pagina->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $colunas = [
            __('Código'), __('Artigo'), __('Armazém'), __('Unidade'),
            __('Quantidade'), __('Reservado'), __('Disponível'),
            __('Mínimo'), __('Custo unitário'), __('Valor'),
        ];

        foreach ($colunas as $i => $titulo) {
            $pagina->setCellValue([$i + 1, 4], $titulo);
        }

        $pagina->getStyle('A4:J4')->getFont()->setBold(true);

        $linha = 5;

        foreach ($linhas as $l) {
            foreach ([
                $l['codigo'] ?? '',
                $l['artigo'] ?? '',
                $l['armazem'] ?? '',
                $l['unidade'] ?? '',
                (float) $l['quantidade'],
                (float) $l['reservado'],
                (float) $l['disponivel'],
                (float) $l['minimo'],
                (float) $l['custo'],
                (float) $l['valor'],
            ] as $i => $valor) {
                // O nome e o código são TEXTO: um «=…» não vira fórmula.
                is_string($valor)
                    ? $pagina->setCellValueExplicit([$i + 1, $linha], $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                    : $pagina->setCellValue([$i + 1, $linha], $valor);
            }

            $linha++;
        }

        $pagina->setCellValue("D{$linha}", mb_strtoupper(__('Total')));
        $pagina->setCellValue("E{$linha}", (float) $resumo['quantidade']);
        $pagina->setCellValue("J{$linha}", (float) $resumo['valor']);
        $pagina->getStyle("D{$linha}:J{$linha}")->getFont()->setBold(true);
        $pagina->getStyle("E5:J{$linha}")->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range('A', 'J') as $coluna) {
            $pagina->getColumnDimension($coluna)->setAutoSize(true);
        }

        $ficheiro = 'stock_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($folha) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($folha))->save('php://output');
            $folha->disconnectWorksheets();
        }, $ficheiro, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * As linhas, o resumo e a descrição do que se pediu.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function mapa(Request $request): array
    {
        abort_unless($request->user()?->can('invoicing.stock.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'armazem' => ['nullable', 'integer'],
            'baixo' => ['nullable', 'boolean'],
            'conservacao' => ['nullable', 'string', 'max:20'],
            'existencia' => ['nullable', \Illuminate\Validation\Rule::in(['', 'com', 'sem'])],
        ]);

        [$linhas, $resumo] = $this->api->paraExportar($filtros);

        return [$linhas, $resumo, [
            'descricao' => $this->descrever($filtros),
            'quando' => now(),
        ]];
    }

    /**
     * O QUE SE PEDIU, ESCRITO POR EXTENSO no cabeçalho.
     *
     * Um mapa impresso sem dizer que filtros levava é um mapa que ninguém
     * consegue conferir daqui a uma semana.
     */
    private function descrever(array $f): string
    {
        $partes = [];

        $armazem = ! empty($f['armazem'])
            ? Warehouse::where('tenant_id', activeTenantId())->where('id', $f['armazem'])->value('name')
            : null;

        $partes[] = $armazem
            ? __('Armazém: :nome', ['nome' => $armazem])
            : __('Todos os armazéns');

        $partes[] = match ($f['existencia'] ?? '') {
            'com' => __('Só com existência'),
            'sem' => __('Só sem existência'),
            default => __('Com e sem existência'),
        };

        if (! empty($f['baixo'])) {
            $partes[] = __('Só abaixo do mínimo');
        }

        if (! empty($f['conservacao'])) {
            $partes[] = __('Conservação: :modo', ['modo' => __(ucfirst($f['conservacao']))]);
        }

        if (! empty($f['procura'])) {
            $partes[] = __('Procura: «:termo»', ['termo' => $f['procura']]);
        }

        return implode(' · ', $partes);
    }
}
