<?php

namespace App\Services\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\HRSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as DataDoExcel;

/**
 * IMPORTAR AS PICAGENS DO RELÓGIO DE PONTO.
 *
 * As empresas com biométrico exportam um ficheiro do ZKTeco ou do Hikvision e
 * é dele que sai o ponto do mês. Escrever trezentas picagens à mão é o que
 * este ficheiro poupa.
 *
 * ESTAVA DENTRO DO COMPONENTE LIVEWIRE, e por isso não era chamável de lado
 * nenhum — nem pela API, nem por um ensaio: duzentas linhas de leitura de
 * folha de cálculo sem uma única prova. Mudou-se para aqui tal como estava.
 *
 * O QUE ELE DECIDE POR SI, e que é preciso saber ao olhar para o resultado:
 *
 *  · SEM ENTRADA NEM SAÍDA, a pessoa fica AUSENTE nesse dia.
 *  · MAIS DE 15 MINUTOS depois da hora do turno é ATRASO, e conta os minutos.
 *    O turno é o do funcionário; quem não tem turno mede-se contra as 08:00.
 *  · O QUE PASSA DAS HORAS DIÁRIAS conta como hora extra na linha do ponto.
 *  · UMA PICAGEM QUE ATRAVESSA A MEIA-NOITE (saída antes da entrada) é do dia
 *    seguinte — sem isto, um turno da noite dava horas negativas.
 *
 * É IDEMPOTENTE: reimportar o mesmo ficheiro actualiza as linhas em vez de as
 * duplicar. A chave é (empresa, funcionário, dia).
 */
class ImportarPresencas
{
    /**
     * As colunas de cada sistema. Hoje os dois exportam na mesma ordem; ficam
     * separados porque foi assim que o ecrã sempre perguntou, e porque o dia
     * em que um mudar não se mexe no outro.
     */
    private const COLUNAS = [
        'zkteco' => ['funcionario' => 'A', 'data' => 'B', 'entrada' => 'C', 'saida' => 'D'],
        'hikvision' => ['funcionario' => 'A', 'data' => 'B', 'entrada' => 'C', 'saida' => 'D'],
    ];

    /**
     * @return array{importados: int, ignorados: int, erros: array<int, string>}
     */
    public function importar(string $caminho, string $sistema, int $tenantId): array
    {
        $mapa = self::COLUNAS[$sistema] ?? self::COLUNAS['zkteco'];

        $folha = IOFactory::load($caminho)->getActiveSheet();
        $linhas = $folha->toArray(null, true, true, true);

        $importados = 0;
        $ignorados = 0;
        $erros = [];
        $cabecalhoSaltado = false;

        $horasPorDia = (float) HRSetting::get('working_hours_per_day', 8);

        foreach ($linhas as $numero => $linha) {
            if (! $cabecalhoSaltado) {
                $cabecalhoSaltado = true;
                continue;
            }

            $identificador = trim((string) ($linha[$mapa['funcionario']] ?? ''));

            if ($identificador === '') {
                continue;
            }

            /*
             * O FUNCIONÁRIO RECONHECE-SE POR TRÊS COISAS: o número, o nome
             * completo, ou só o primeiro nome. Os relógios exportam qualquer
             * uma delas, conforme quem os configurou.
             */
            $empregado = Employee::where('tenant_id', $tenantId)
                ->where(function ($q) use ($identificador) {
                    $q->where('employee_number', $identificador)
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), $identificador)
                        ->orWhere('first_name', $identificador);
                })
                ->first();

            if (! $empregado) {
                $ignorados++;
                $erros[] = __('Linha :n: funcionário «:quem» não encontrado.', ['n' => $numero, 'quem' => $identificador]);
                continue;
            }

            $bruta = $linha[$mapa['data']] ?? '';
            $dia = self::data($bruta);

            if (! $dia) {
                $ignorados++;
                $erros[] = __('Linha :n: data inválida «:data».', ['n' => $numero, 'data' => (string) $bruta]);
                continue;
            }

            $entrada = self::hora($linha[$mapa['entrada']] ?? '');
            $saida = self::hora($linha[$mapa['saida']] ?? '');

            $horas = null;

            if ($entrada && $saida) {
                $de = Carbon::parse($dia . ' ' . $entrada);
                $ate = Carbon::parse($dia . ' ' . $saida);

                // A saída antes da entrada é do dia seguinte: o turno da noite
                // atravessa a meia-noite, e sem isto dava horas negativas.
                if ($ate->lt($de)) {
                    $ate->addDay();
                }

                $horas = round($de->diffInMinutes($ate) / 60, 2);
            }

            $estado = ($entrada || $saida) ? 'present' : 'absent';

            // O ATRASO mede-se contra o turno da pessoa — e 15 minutos de
            // tolerância, como o ecrã de sempre.
            $atrasado = false;
            $minutosDeAtraso = 0;
            $inicioDoTurno = $empregado->shift?->start_time
                ? Carbon::parse($empregado->shift->start_time)->format('H:i')
                : '08:00';

            if ($entrada && $entrada > $inicioDoTurno) {
                $diferenca = Carbon::parse($dia . ' ' . $inicioDoTurno)
                    ->diffInMinutes(Carbon::parse($dia . ' ' . $entrada));

                if ($diferenca > 15) {
                    $atrasado = true;
                    $minutosDeAtraso = $diferenca;
                    $estado = 'late';
                }
            }

            $extras = ($horas && $horas > $horasPorDia) ? round($horas - $horasPorDia, 2) : 0;

            Attendance::updateOrCreate(
                ['tenant_id' => $tenantId, 'employee_id' => $empregado->id, 'date' => $dia],
                [
                    'check_in' => $entrada,
                    'check_out' => $saida,
                    'time_in' => $entrada,
                    'time_out' => $saida,
                    'hours_worked' => $horas,
                    'overtime_hours' => $extras,
                    'status' => $estado,
                    'is_late' => $atrasado,
                    'late_minutes' => $minutosDeAtraso,
                    'affects_payroll' => true,
                    'remarks' => __('Importado de :sistema', ['sistema' => mb_strtoupper($sistema)]),
                ],
            );

            $importados++;
        }

        Log::info('Importação de presenças concluída', [
            'tenant_id' => $tenantId,
            'sistema' => $sistema,
            'importados' => $importados,
            'ignorados' => $ignorados,
        ]);

        return [
            'importados' => $importados,
            'ignorados' => $ignorados,
            // Dez erros chegam para perceber o que correu mal; trezentos não
            // se lêem e só enchem o ecrã.
            'erros' => array_slice($erros, 0, 10),
        ];
    }

    /** As datas vêm em cinco formatos, e às vezes como número de série do Excel. */
    private static function data(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            try {
                return DataDoExcel::excelToDateTimeObject((int) $valor)->format('Y-m-d');
            } catch (\Throwable) {
                // Não era uma data do Excel; tenta-se como texto.
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, trim((string) $valor))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** A hora vem como texto, ou como fracção do dia (0,354167 = 08:30). */
    private static function hora(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor) && (float) $valor < 1) {
            $minutos = (int) round((float) $valor * 24 * 60);

            return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
        }

        $texto = trim((string) $valor);

        return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $texto) ? substr($texto, 0, 5) : null;
    }
}
