<?php

namespace App\Services\Hotel;

use App\Models\Hotel\RateSeason;
use App\Models\Hotel\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * O PREÇO DE UMA NOITE — época, dia da semana e o dia concreto.
 *
 * Três camadas, por esta ordem: a ÉPOCA muda o preço base de um período
 * (alta/baixa); o DIA DA SEMANA multiplica-o (a sexta e o sábado custam mais);
 * e a TARIFA ESPECIAL de um dia concreto substitui tudo (o dia do jogo, o
 * feriado).
 *
 * ISTO NUNCA FOI APLICADO A PREÇO NENHUM. A conta vivia como método estático
 * de um componente Livewire e o único sítio que a chamava era o calendário do
 * seu próprio ecrã: definir uma época alta não mudava uma reserva. As reservas
 * usavam o `base_price` do tipo de quarto, e mais nada. Aqui a conta sai do
 * ecrã e passa a ser a resposta a «quanto custa esta noite» — que é a pergunta
 * que a recepção faz.
 */
class Tarifas
{
    /** Como uma época mexe no preço base. */
    public const MODIFICADORES = [
        'multiplier' => 'Multiplicador',
        'percentage' => 'Percentagem',
        'fixed' => 'Preço fixo',
    ];

    /** Os dias da semana como o Carbon os numera — domingo é 0. */
    public const DIAS = [
        0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta',
        4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado',
    ];

    /**
     * O PREÇO DE UMA NOITE, com as três camadas aplicadas.
     *
     * @param  float|null  $base  o preço base do tipo; em branco lê-se do tipo
     */
    public function precoDaNoite(int $tenantId, int $tipoId, string|Carbon $dia, ?float $base = null): float
    {
        $data = Carbon::parse($dia);

        $base ??= (float) RoomType::where('tenant_id', $tenantId)->whereKey($tipoId)->value('base_price');

        $preco = $base;

        // 1. A ÉPOCA — a de maior prioridade, quando há mais do que uma.
        if ($epoca = RateSeason::getForDate($tenantId, $data)) {
            $preco = $epoca->applyModifier($preco);
        }

        // 2. O DIA DA SEMANA.
        $doDia = DB::table('hotel_weekday_rates')
            ->where('tenant_id', $tenantId)
            ->where('room_type_id', $tipoId)
            ->where('day_of_week', $data->dayOfWeek)
            ->where('is_active', true)
            ->value('price_modifier');

        if ($doDia) {
            $preco *= (float) $doDia;
        }

        /*
         * 3. A TARIFA ESPECIAL — substitui tudo.
         *
         * A do tipo concreto ganha à que vale para a casa toda: é por isso que
         * a ordenação põe o `room_type_id` preenchido à frente.
         */
        $especial = DB::table('hotel_special_rates')
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $data->toDateString())
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('room_type_id')->orWhere('room_type_id', $tipoId))
            ->orderByRaw('room_type_id IS NULL')
            ->first();

        if ($especial) {
            if ($especial->price !== null && (float) $especial->price > 0) {
                $preco = (float) $especial->price;
            } elseif ($especial->price_modifier !== null) {
                $preco *= (float) $especial->price_modifier;
            }
        }

        return round($preco, 2);
    }

    /**
     * O QUE CUSTAM AS NOITES DE UMA ESTADA — e a média por noite.
     *
     * A noite da SAÍDA não se vende: quem entra a 3 e sai a 5 dorme duas
     * noites, e são essas duas que se contam.
     *
     * @return array{noites: int, total: float, media: float, dias: list<array{dia: string, preco: float}>}
     */
    public function precoDaEstada(int $tenantId, int $tipoId, string $de, string $ate, ?float $base = null): array
    {
        $entrada = Carbon::parse($de);
        $saida = Carbon::parse($ate);

        $base ??= (float) RoomType::where('tenant_id', $tenantId)->whereKey($tipoId)->value('base_price');

        $dias = [];
        $total = 0.0;

        foreach (CarbonPeriod::create($entrada, $saida->copy()->subDay()) as $dia) {
            $preco = $this->precoDaNoite($tenantId, $tipoId, $dia, $base);

            $dias[] = ['dia' => $dia->toDateString(), 'preco' => $preco];
            $total += $preco;
        }

        $noites = count($dias);

        return [
            'noites' => $noites,
            'total' => round($total, 2),
            // A MÉDIA é o que vai para a taxa por noite da reserva: o modelo
            // grava uma taxa só e multiplica-a pelas noites, e é assim que o
            // total bate certo com a soma das noites.
            'media' => $noites > 0 ? round($total / $noites, 2) : $base,
            'dias' => $dias,
        ];
    }

    /**
     * O CALENDÁRIO DE PREÇOS de um mês, por tipo de quarto.
     *
     * @return list<array{id: int, nome: string, base: float, dias: array<string, float>}>
     */
    public function calendario(int $tenantId, string|Carbon $mes, ?int $tipoId = null): array
    {
        $inicio = Carbon::parse($mes)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $tipos = RoomType::where('tenant_id', $tenantId)->where('is_active', true)
            ->when($tipoId, fn ($q) => $q->whereKey($tipoId))
            ->orderBy('name')->get(['id', 'name', 'base_price']);

        return $tipos->map(function (RoomType $t) use ($tenantId, $inicio, $fim) {
            $dias = [];

            foreach (CarbonPeriod::create($inicio, $fim) as $dia) {
                $dias[$dia->toDateString()] = $this->precoDaNoite($tenantId, $t->id, $dia, (float) $t->base_price);
            }

            return [
                'id' => $t->id,
                'nome' => $t->name,
                'base' => (float) $t->base_price,
                'dias' => $dias,
            ];
        })->values()->all();
    }
}
