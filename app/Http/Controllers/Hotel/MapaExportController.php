<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Api\Hotel\RelatoriosApiController;
use App\Http\Controllers\Controller;
use App\Models\Hotel\RoomType;
use App\Models\Tenant;
use App\Services\Hotel\MapasDoHotel;
use Illuminate\Http\Request;

/**
 * OS MAPAS DO HOTEL EM PAPEL E EM EXCEL.
 *
 * Os dois botões existiam no ecrã em Livewire e respondiam «Exportação em
 * desenvolvimento». Passam a fazer o que o nome diz.
 *
 * SAI EXACTAMENTE O QUE ESTÁ NO ECRÃ — os filtros viajam no URL, são validados
 * pelo mesmo método, e as colunas vêm do próprio mapa. E os três números da
 * hotelaria (ocupação, ADR, RevPAR) vão no cabeçalho, que é a razão de se
 * imprimir um mapa destes.
 */
class MapaExportController extends Controller
{
    public function __construct(private readonly MapasDoHotel $mapas) {}

    public function imprimir(Request $request)
    {
        [$mapa, $filtros] = $this->preparar($request);

        return view('pdf.hotel.mapa', [
            'titulo' => __(MapasDoHotel::MAPAS[$filtros['mapa']]['rotulo']) . ' — ' . __('Hotel'),
            'descricao' => $this->descrever($filtros, $mapa),
            'quando' => now(),
            'kpis' => $mapa['kpis'],
            'colunas' => $mapa['colunas'],
            'linhas' => $mapa['linhas'],
            'totais' => $mapa['totais'],
            'nada' => $mapa['nada'],
            'empresa' => Tenant::find(activeTenantId()),
            'celula' => fn (array $linha, array $coluna) => self::texto($linha, $coluna),
        ]);
    }

    public function excel(Request $request)
    {
        [$mapa, $filtros] = $this->preparar($request);

        $folha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $pagina = $folha->getActiveSheet();
        $nome = __(MapasDoHotel::MAPAS[$filtros['mapa']]['rotulo']);
        $pagina->setTitle(mb_substr(preg_replace('#[:\\\\/?*\[\]]#', ' ', $nome), 0, 31));

        $pagina->setCellValue('A1', mb_strtoupper($nome . ' — ' . __('Hotel')));
        $pagina->setCellValue('A2', $this->descrever($filtros, $mapa));
        $pagina->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        // OS TRÊS NÚMEROS DA HOTELARIA vão na folha: quem trabalha o mapa noutro
        // sítio precisa deles tanto como das linhas.
        $pagina->setCellValue('A3', __('Ocupação: :o% · ADR: :a Kz · RevPAR: :r Kz', [
            'o' => $mapa['kpis']['ocupacao'],
            'a' => number_format($mapa['kpis']['adr'], 2, ',', '.'),
            'r' => number_format($mapa['kpis']['revpar'], 2, ',', '.'),
        ]));

        foreach ($mapa['colunas'] as $i => $c) {
            $pagina->setCellValue([$i + 1, 5], __($c['rotulo']));
        }

        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($mapa['colunas']));
        $pagina->getStyle("A5:{$ultima}5")->getFont()->setBold(true);

        $linha = 6;

        foreach ($mapa['linhas'] as $l) {
            foreach ($mapa['colunas'] as $i => $c) {
                $pagina->setCellValue([$i + 1, $linha], self::ehNumero($c)
                    ? (float) ($l[$c['chave']] ?? 0)
                    : self::texto($l, $c));
            }

            $linha++;
        }

        if ($mapa['totais']) {
            foreach ($mapa['colunas'] as $i => $c) {
                $pagina->setCellValue([$i + 1, $linha], self::ehNumero($c)
                    ? (float) ($mapa['totais'][$c['chave']] ?? 0)
                    : self::texto($mapa['totais'], $c));
            }

            $pagina->getStyle("A{$linha}:{$ultima}{$linha}")->getFont()->setBold(true);
        }

        foreach ($mapa['colunas'] as $i => $c) {
            $coluna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $pagina->getColumnDimension($coluna)->setAutoSize(true);

            if ($c['formato'] === 'dinheiro') {
                $pagina->getStyle("{$coluna}6:{$coluna}{$linha}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        $ficheiro = 'hotel_' . $filtros['mapa'] . '_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($folha) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($folha))->save('php://output');
            $folha->disconnectWorksheets();
        }, $ficheiro, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{0: array, 1: array} */
    private function preparar(Request $request): array
    {
        abort_unless($request->user()?->can('hotel.reports.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = RelatoriosApiController::validarFiltros($request);

        return [$this->mapas->mapa($filtros['mapa'], activeTenantId(), $filtros), $filtros];
    }

    private static function ehNumero(array $coluna): bool
    {
        return in_array($coluna['formato'], ['numero', 'dinheiro', 'percentagem'], true);
    }

    private static function texto(array $linha, array $coluna): string
    {
        $v = $linha[$coluna['chave']] ?? null;

        if ($v === null || $v === '') {
            return '';
        }

        return match ($coluna['formato']) {
            'dinheiro' => number_format((float) $v, 2, ',', '.'),
            'percentagem' => number_format((float) $v, 1, ',', '.') . '%',
            'numero' => number_format((float) $v, (float) $v == (int) $v ? 0 : 2, ',', '.'),
            'data' => \Carbon\Carbon::parse($v)->format('d/m/Y'),
            default => (string) $v,
        };
    }

    private function descrever(array $filtros, array $mapa): string
    {
        $partes = [__('Período: :de a :ate', [
            'de' => \Carbon\Carbon::parse($mapa['periodo']['de'])->format('d/m/Y'),
            'ate' => \Carbon\Carbon::parse($mapa['periodo']['ate'])->format('d/m/Y'),
        ])];

        if (! empty($filtros['tipo_de_quarto'])) {
            $nome = RoomType::where('tenant_id', activeTenantId())
                ->where('id', $filtros['tipo_de_quarto'])->value('name');

            $partes[] = __('Tipo de quarto: :nome', ['nome' => $nome ?: '—']);
        }

        $partes[] = __(':quantas linha(s)', ['quantas' => count($mapa['linhas'])]);

        return implode(' · ', $partes);
    }
}
