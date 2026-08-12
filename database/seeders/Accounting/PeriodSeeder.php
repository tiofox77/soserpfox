<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use App\Models\Accounting\Period;
use App\Models\Tenant;

class PeriodSeeder extends Seeder
{
    /** Meses do exercício, na ordem em que são criados. */
    private const MESES = [
        1  => ['JAN', 'Janeiro'],
        2  => ['FEV', 'Fevereiro'],
        3  => ['MAR', 'Março'],
        4  => ['ABR', 'Abril'],
        5  => ['MAI', 'Maio'],
        6  => ['JUN', 'Junho'],
        7  => ['JUL', 'Julho'],
        8  => ['AGO', 'Agosto'],
        9  => ['SET', 'Setembro'],
        10 => ['OUT', 'Outubro'],
        11 => ['NOV', 'Novembro'],
        12 => ['DEZ', 'Dezembro'],
    ];

    public function run(): void
    {
        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            $criados = $this->runForTenant($tenant->id);
            echo "✅ {$tenant->name}: {$criados} períodos criados (" . now()->year . ")\n";
        }
    }

    /**
     * Cria os 12 períodos mensais de um exercício para uma empresa.
     *
     * INCREMENTAL: só cria os meses em falta. Um período já existente é
     * preservado tal como está — pode ter sido fechado, e reabri-lo em silêncio
     * corromperia a contabilidade.
     *
     * @param  int|null $ano  Exercício a criar. Por omissão, o ano corrente.
     * @return int            Número de períodos efectivamente criados.
     */
    public function runForTenant(int $tenantId, ?int $ano = null): int
    {
        $ano = $ano ?: (int) now()->year;

        // Códigos já existentes deste exercício — evita o "Duplicate entry" do
        // índice único (tenant_id, code), que era o que rebentava ao repetir.
        $existentes = array_flip(
            Period::where('tenant_id', $tenantId)
                ->where('code', 'like', '%/' . $ano)
                ->pluck('code')
                ->all()
        );

        $criados = 0;
        foreach (self::MESES as $mes => [$sigla, $nome]) {
            $code = $sigla . '/' . $ano;
            if (isset($existentes[$code])) {
                continue;
            }

            $primeiroDia = sprintf('%04d-%02d-01', $ano, $mes);

            Period::create([
                'tenant_id'  => $tenantId,
                'code'       => $code,
                'name'       => $nome . ' ' . $ano,
                'date_start' => $primeiroDia,
                'date_end'   => date('Y-m-t', strtotime($primeiroDia)),
                'state'      => 'open',
            ]);
            $criados++;
        }

        return $criados;
    }
}
