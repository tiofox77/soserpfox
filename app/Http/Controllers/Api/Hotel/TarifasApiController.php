<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\RoomType;
use App\Services\Hotel\Tarifas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AS TARIFAS — quanto custa uma noite, e porquê.
 *
 * Três camadas: a ÉPOCA (alta/baixa) muda o preço base de um período; o DIA DA
 * SEMANA multiplica-o; a TARIFA ESPECIAL de um dia concreto substitui tudo.
 *
 * AS ÉPOCAS vivem no catálogo genérico (`epocas-do-hotel`) — são uma lista com
 * a forma de sempre. O que fica aqui é o que não é lista: as tarifas por dia da
 * semana, as de um dia concreto, e o CALENDÁRIO DE PREÇOS, que é a única
 * maneira de ver o que as três camadas fazem juntas.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • A CONTA DO PREÇO NUNCA FOI APLICADA A NADA. Vivia como método estático de
 *    um componente Livewire, e o único sítio que a chamava era o calendário do
 *    seu próprio ecrã: definir uma época alta não mudava uma reserva.
 *  • As tarifas por dia da semana e as especiais gravavam-se com o id do tipo
 *    de quarto vindo do browser, sem confirmar que era desta casa.
 */
class TarifasApiController extends Controller
{
    public function __construct(private readonly Tarifas $tarifas) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.view');

        return response()->json([
            'tipos_de_quarto' => RoomType::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'base_price'])
                ->map(fn (RoomType $t) => [
                    'valor' => (string) $t->id,
                    'rotulo' => $t->name,
                    'preco' => (float) $t->base_price,
                ])->values(),
            'dias' => collect(Tarifas::DIAS)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('hotel.rates.create'),
                'pode_editar' => (bool) $request->user()?->can('hotel.rates.edit'),
                'pode_apagar' => (bool) $request->user()?->can('hotel.rates.delete'),
            ],
        ]);
    }

    /** O calendário de preços de um mês — as três camadas juntas. */
    public function calendario(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.view');

        $filtros = $request->validate([
            'mes' => ['nullable', 'date'],
            'tipo' => ['nullable', 'integer'],
        ]);

        $tenantId = activeTenantId();
        $mes = Carbon::parse($filtros['mes'] ?? today())->startOfMonth();

        return response()->json([
            'mes' => $mes->toDateString(),
            'dias' => collect(\Carbon\CarbonPeriod::create($mes, $mes->copy()->endOfMonth()))
                ->map(fn (Carbon $d) => [
                    'dia' => $d->toDateString(),
                    'numero' => $d->day,
                    'nome' => $d->locale(app()->getLocale())->shortDayName,
                    'hoje' => $d->isToday(),
                    'fim_de_semana' => $d->isWeekend(),
                ])->values(),
            'tipos' => $this->tarifas->calendario($tenantId, $mes, $filtros['tipo'] ?? null),
        ]);
    }

    /**
     * QUANTO CUSTA ESTA ESTADA — a pergunta que a recepção faz.
     *
     * É esta porta que põe a conta a servir para alguma coisa: o formulário da
     * reserva propõe a taxa por noite que sai daqui, em vez do preço base do
     * tipo, que ignorava a época e o fim-de-semana.
     */
    public function preco(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $dados = $request->validate([
            'tipo' => ['required', 'integer'],
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after:de'],
        ]);

        $tenantId = activeTenantId();

        abort_unless(
            RoomType::where('tenant_id', $tenantId)->whereKey($dados['tipo'])->exists(),
            422, __('Esse tipo de quarto não é desta casa.')
        );

        return response()->json(
            $this->tarifas->precoDaEstada($tenantId, (int) $dados['tipo'], $dados['de'], $dados['ate'])
        );
    }

    /* ─── As tarifas por dia da semana ────────────────────────────────── */

    public function porDia(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.view');

        $tenantId = activeTenantId();

        $tipos = RoomType::where('tenant_id', $tenantId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'base_price']);

        $gravadas = DB::table('hotel_weekday_rates')
            ->where('tenant_id', $tenantId)
            ->get()->groupBy('room_type_id');

        return response()->json([
            'data' => $tipos->map(function (RoomType $t) use ($gravadas) {
                $doTipo = ($gravadas->get($t->id) ?? collect())->keyBy('day_of_week');

                return [
                    'id' => $t->id,
                    'nome' => $t->name,
                    'base' => (float) $t->base_price,
                    'dias' => collect(Tarifas::DIAS)->map(fn ($rotulo, $n) => [
                        'dia' => $n,
                        'rotulo' => __($rotulo),
                        // Sem regra gravada, o dia vale um: o preço é o da época.
                        'modificador' => (float) ($doTipo->get($n)->price_modifier ?? 1),
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function guardarPorDia(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.edit');

        $dados = $request->validate([
            'tipo' => ['required', 'integer'],
            'dias' => ['required', 'array', 'size:7'],
            'dias.*' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        $tenantId = activeTenantId();

        abort_unless(
            RoomType::where('tenant_id', $tenantId)->whereKey($dados['tipo'])->exists(),
            422, __('Esse tipo de quarto não é desta casa.')
        );

        DB::transaction(function () use ($dados, $tenantId) {
            DB::table('hotel_weekday_rates')
                ->where('tenant_id', $tenantId)
                ->where('room_type_id', $dados['tipo'])
                ->delete();

            $linhas = [];

            foreach ($dados['dias'] as $dia => $modificador) {
                // UM DIA QUE VALE UM NÃO É REGRA NENHUMA: não se grava, para a
                // tabela ter só o que muda alguma coisa.
                if ((float) $modificador == 1.0) {
                    continue;
                }

                $linhas[] = [
                    'tenant_id' => $tenantId,
                    'room_type_id' => $dados['tipo'],
                    'day_of_week' => (int) $dia,
                    'price_modifier' => $modificador,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($linhas) {
                DB::table('hotel_weekday_rates')->insert($linhas);
            }
        });

        return response()->json(['message' => __('Tarifas por dia da semana guardadas.')]);
    }

    /* ─── As tarifas de um dia concreto ───────────────────────────────── */

    public function especiais(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.view');

        $tenantId = activeTenantId();

        $tipos = RoomType::where('tenant_id', $tenantId)->pluck('name', 'id');

        return response()->json([
            'data' => DB::table('hotel_special_rates')
                ->where('tenant_id', $tenantId)
                ->whereDate('date', '>=', today()->subMonth())
                ->orderBy('date')
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'dia' => Carbon::parse($l->date)->toDateString(),
                    'room_type_id' => $l->room_type_id,
                    // Sem tipo, vale para a casa toda.
                    'tipo' => $l->room_type_id ? ($tipos[$l->room_type_id] ?? null) : null,
                    'preco' => $l->price === null ? null : (float) $l->price,
                    'modificador' => $l->price_modifier === null ? null : (float) $l->price_modifier,
                    'motivo' => $l->reason,
                    'activa' => (bool) $l->is_active,
                    'passada' => Carbon::parse($l->date)->isPast() && ! Carbon::parse($l->date)->isToday(),
                ])->values(),
        ]);
    }

    public function guardarEspecial(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.edit');

        $dados = $request->validate([
            'dia' => ['required', 'date'],
            'tipo' => ['nullable', 'integer'],
            'preco' => ['required', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        $tenantId = activeTenantId();

        if (! empty($dados['tipo'])) {
            abort_unless(
                RoomType::where('tenant_id', $tenantId)->whereKey($dados['tipo'])->exists(),
                422, __('Esse tipo de quarto não é desta casa.')
            );
        }

        DB::table('hotel_special_rates')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'room_type_id' => $dados['tipo'] ?: null,
                'date' => Carbon::parse($dados['dia'])->toDateString(),
            ],
            [
                'price' => $dados['preco'],
                'reason' => $dados['motivo'] ?: null,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => __('Tarifa do dia guardada.')]);
    }

    public function apagarEspecial(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.delete');

        // O `where` da empresa está aqui de propósito: o id vem do browser.
        $apagadas = DB::table('hotel_special_rates')
            ->where('tenant_id', activeTenantId())
            ->where('id', $id)
            ->delete();

        abort_if($apagadas === 0, 404, __('Essa tarifa não é desta casa.'));

        return response()->json(['message' => __('Tarifa removida.')]);
    }

    /** As épocas — a lista vive no catálogo, aqui só se contam. */
    public function epocas(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.rates.view');

        $hoje = today();

        return response()->json([
            'data' => \App\Models\Hotel\RateSeason::where('tenant_id', activeTenantId())
                ->orderBy('start_date')->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'nome' => $e->name,
                    'cor' => $e->color ?: '#6366f1',
                    'de' => $e->start_date?->toDateString(),
                    'ate' => $e->end_date?->toDateString(),
                    'modificador' => (float) $e->price_modifier,
                    'tipo' => $e->modifier_type,
                    'tipo_rotulo' => __(Tarifas::MODIFICADORES[$e->modifier_type] ?? (string) $e->modifier_type),
                    'efeito' => $e->modifier_percentage,
                    'prioridade' => (int) $e->priority,
                    'activa' => (bool) $e->is_active,
                    // A que está a valer HOJE — é a que explica o preço de agora.
                    'a_correr' => $e->is_active && $e->start_date?->lte($hoje) && $e->end_date?->gte($hoje),
                ])->values(),
        ]);
    }

}
