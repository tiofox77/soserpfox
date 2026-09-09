<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Api\Workshop\RelatoriosApiController;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Workshop\MapasDaOficina;
use Illuminate\Http\Request;

/**
 * OS MAPAS DA OFICINA EM PAPEL E EM EXCEL.
 *
 * Os dois botões existiam no ecrã em Livewire e respondiam «Funcionalidade de
 * exportação em desenvolvimento». Passam a fazer o que o nome diz.
 *
 * SAI EXACTAMENTE O QUE ESTÁ NO ECRÃ: os filtros viajam no URL, são validados
 * pelo mesmo método do ecrã, e as colunas vêm do próprio mapa. Um papel que
 * mostrasse outra coisa do que se estava a ver seria pior do que não haver
 * papel — quem imprime está a levar aquilo a uma reunião.
 */
class MapaExportController extends Controller
{
    public function __construct(private readonly MapasDaOficina $mapas) {}

    public function imprimir(Request $request)
    {
        [$mapa, $filtros] = $this->preparar($request);

        return view('pdf.workshop.mapa', [
            'titulo' => __(MapasDaOficina::MAPAS[$filtros['mapa']]['rotulo']) . ' — ' . __('Oficina'),
            'descricao' => $this->descrever($filtros, $mapa),
            'quando' => now(),
            'colunas' => $mapa['colunas'],
            'linhas' => $mapa['linhas'],
            'totais' => $mapa['totais'],
            'nada' => $mapa['nada'],
            'empresa' => Tenant::find(activeTenantId()),
            // O formatador vai para a vista para que o Blade não repita a
            // decisão por coluna — é a mesma que o Excel usa.
            'celula' => fn (array $linha, array $coluna) => self::texto($linha, $coluna),
        ]);
    }

    public function excel(Request $request)
    {
        [$mapa, $filtros] = $this->preparar($request);

        $folha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $pagina = $folha->getActiveSheet();
        $nome = __(MapasDaOficina::MAPAS[$filtros['mapa']]['rotulo']);
        // O nome do separador não aceita mais de 31 caracteres nem `: \ / ? * [ ]`.
        $pagina->setTitle(mb_substr(preg_replace('#[:\\\\/?*\[\]]#', ' ', $nome), 0, 31));

        $pagina->setCellValue('A1', mb_strtoupper($nome . ' — ' . __('Oficina')));
        $pagina->setCellValue('A2', $this->descrever($filtros, $mapa));
        $pagina->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        foreach ($mapa['colunas'] as $i => $c) {
            $pagina->setCellValue([$i + 1, 4], __($c['rotulo']));
        }

        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($mapa['colunas']));
        $pagina->getStyle("A4:{$ultima}4")->getFont()->setBold(true);

        $linha = 5;

        foreach ($mapa['linhas'] as $l) {
            foreach ($mapa['colunas'] as $i => $c) {
                // Os números vão como NÚMEROS, para se poderem somar do outro
                // lado; o resto vai como texto já formatado.
                $pagina->setCellValue([$i + 1, $linha], in_array($c['formato'], ['numero', 'dinheiro'], true)
                    ? (float) ($l[$c['chave']] ?? 0)
                    : self::texto($l, $c));
            }

            $linha++;
        }

        if ($mapa['totais']) {
            foreach ($mapa['colunas'] as $i => $c) {
                $pagina->setCellValue([$i + 1, $linha], in_array($c['formato'], ['numero', 'dinheiro'], true)
                    ? (float) ($mapa['totais'][$c['chave']] ?? 0)
                    : self::texto($mapa['totais'], $c));
            }

            $pagina->getStyle("A{$linha}:{$ultima}{$linha}")->getFont()->setBold(true);
        }

        foreach ($mapa['colunas'] as $i => $c) {
            $coluna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $pagina->getColumnDimension($coluna)->setAutoSize(true);

            if ($c['formato'] === 'dinheiro') {
                $pagina->getStyle("{$coluna}5:{$coluna}{$linha}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        $ficheiro = 'oficina_' . $filtros['mapa'] . '_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($folha) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($folha))->save('php://output');
            $folha->disconnectWorksheets();
        }, $ficheiro, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{0: array, 1: array} o mapa e os filtros que o pediram */
    private function preparar(Request $request): array
    {
        abort_unless($request->user()?->can('workshop.reports.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = RelatoriosApiController::validarFiltros($request);

        return [$this->mapas->mapa($filtros['mapa'], activeTenantId(), $filtros), $filtros];
    }

    /** Uma célula como se lê: a data em dd/mm/aaaa, o dinheiro com separadores. */
    private static function texto(array $linha, array $coluna): string
    {
        $v = $linha[$coluna['chave']] ?? null;

        if ($v === null || $v === '') {
            return '';
        }

        return match ($coluna['formato']) {
            'dinheiro' => number_format((float) $v, 2, ',', '.'),
            'numero' => number_format((float) $v, (float) $v == (int) $v ? 0 : 2, ',', '.'),
            'data' => \Carbon\Carbon::parse($v)->format('d/m/Y'),
            // O estado sai por extenso: `in_progress` numa folha impressa não
            // é uma resposta a ninguém.
            'estado' => (string) ($linha[$coluna['chave'] . '_rotulo'] ?? __(MapasDaOficina::ESTADOS[$v] ?? $v)),
            default => (string) $v,
        };
    }

    /**
     * O QUE SE PEDIU, POR EXTENSO no cabeçalho — sem isto, um mapa impresso é
     * um mapa que ninguém consegue conferir daqui a uma semana.
     */
    private function descrever(array $filtros, array $mapa): string
    {
        $partes = [__('Período: :de a :ate', [
            'de' => \Carbon\Carbon::parse($mapa['periodo']['de'])->format('d/m/Y'),
            'ate' => \Carbon\Carbon::parse($mapa['periodo']['ate'])->format('d/m/Y'),
        ])];

        if (! empty($filtros['estado'])) {
            $partes[] = __('Estado: :estado', ['estado' => __(MapasDaOficina::ESTADOS[$filtros['estado']])]);
        }

        $partes[] = __(':quantas linha(s)', ['quantas' => count($mapa['linhas'])]);

        return implode(' · ', $partes);
    }
}
