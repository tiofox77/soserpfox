<?php

namespace App\Services\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehicleReminder;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkshopSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * OS LEMBRETES DE MANUTENÇÃO (15/09/2026, OF-11).
 *
 * TRÊS COISAS:
 *
 * 1. A PRÓXIMA REVISÃO marca-se sozinha quando a ordem fica concluída — km de
 *    entrada + intervalo, hoje + meses —, com o intervalo da viatura ou, se não
 *    o tem, o da oficina. Só se a viatura não tinha revisão marcada ou se ela
 *    já estava perto: uma chapa a meio do ciclo não empurra a revisão.
 *
 * 2. A LISTA DE QUEM CHAMAR: revisões a chegar (por data ou pelos km que a
 *    viatura TERÁ hoje, estimados pelo andamento entre as visitas) e seguro,
 *    inspecção e livrete a caducar.
 *
 * 3. O CONTACTO fica registado contra o vencimento (`due_key`): quem já foi
 *    chamado para a revisão dos 60 000 km não volta a sê-lo para essa — e o
 *    envio automático (se a oficina o ligar) sai uma vez por dia no máximo.
 */
class LembretesDaOficina
{
    /** Estados em que o carro está na oficina — não se chama quem já cá está. */
    private const NA_OFICINA = ['pending', 'scheduled', 'in_progress', 'waiting_parts'];

    private const DOCUMENTOS = ['seguro' => 'insurance_expiry', 'inspeccao' => 'inspection_expiry', 'livrete' => 'registration_expiry'];

    /** @return array{0: int, 1: int} km e meses (0 = não conta) */
    public static function intervalos(Vehicle $v, WorkshopSetting $d): array
    {
        return [
            (int) ($v->service_interval_km ?? $d->service_interval_km),
            (int) ($v->service_interval_months ?? $d->service_interval_months),
        ];
    }

    /**
     * Quantos km a viatura anda por dia, pelas entradas na oficina.
     *
     * @param  Collection<int, array{0: Carbon, 1: int}>  $pontos  (data, km) por ordem de data
     */
    public static function kmPorDia(Collection $pontos): ?float
    {
        $pontos = $pontos->filter(fn ($p) => $p[1] > 0)->values();
        if ($pontos->count() < 2) {
            return null;
        }

        [$d0, $k0] = $pontos->first();
        [$d1, $k1] = $pontos->last();
        $dias = $d0->diffInDays($d1);

        // Duas visitas na mesma quinzena dizem pouco do andamento, e km a descer é erro de escrita.
        if ($dias < 14 || $k1 <= $k0) {
            return null;
        }

        return min(500.0, ($k1 - $k0) / $dias);
    }

    /** @param  Collection<int, array{0: Carbon, 1: int}>  $pontos */
    public static function kmEstimados(Vehicle $v, Collection $pontos, ?float $porDia): int
    {
        $ultimo = $pontos->filter(fn ($p) => $p[1] > 0)->last();
        $km = max((int) $v->mileage, (int) ($ultimo[1] ?? 0));

        if ($porDia && $ultimo) {
            $km += (int) round($porDia * max(0, $ultimo[0]->diffInDays(today())));
        }

        return $km;
    }

    /** «aos 60 000 km ou a 20/10/2026» */
    public static function quando(?int $km, ?Carbon $data): string
    {
        $partes = [];
        if ($km) {
            $partes[] = __('aos :km km', ['km' => number_format($km, 0, ',', '.')]);
        }
        if ($data) {
            $partes[] = __('a :data', ['data' => $data->format('d/m/Y')]);
        }

        return implode(' ' . __('ou') . ' ', $partes);
    }

    /** A chave do vencimento a que um contacto responde; nulo se não há. */
    public static function vencimento(Vehicle $v, string $tipo): ?string
    {
        if ($tipo === 'recomendacao') {
            $lista = self::adiadasDevidas($v);

            return $lista->isEmpty() ? null : 'rec|' . $lista->first()->follow_up_on->toDateString() . '|' . $lista->pluck('id')->sort()->implode(',');
        }

        if ($tipo === 'revisao') {
            return $v->next_service_date || $v->next_service_km
                ? ($v->next_service_date?->toDateString() ?? '-') . '|' . ($v->next_service_km ?? '-')
                : null;
        }

        $coluna = self::DOCUMENTOS[$tipo] ?? null;

        return $coluna && $v->{$coluna} ? $v->{$coluna}->toDateString() : null;
    }

    /** «Pastilhas, Discos e mais 2» — cabe num SMS. */
    public static function trabalhos(array $nomes): string
    {
        $primeiros = array_slice($nomes, 0, 2);
        $resto = count($nomes) - count($primeiros);

        return implode(', ', $primeiros) . ($resto > 0 ? ' ' . trans_choice('e mais :n|e mais :n', $resto, ['n' => $resto]) : '');
    }

    /** As recomendações por propor de uma viatura que já chegaram à data. */
    private static function adiadasDevidas(Vehicle $v): Collection
    {
        $limite = today()->addDays(WorkshopSetting::getForTenant((int) $v->tenant_id)->remind_days_before);

        return \App\Models\Workshop\DeferredItem::withoutGlobalScope('tenant')->where('vehicle_id', $v->id)->where('status', 'pendente')
            ->whereDate('follow_up_on', '<=', $limite)->orderBy('follow_up_on')->get();
    }

    /** As variáveis próprias do lembrete: `quando` na revisão, `data` nos documentos. */
    public static function variaveis(Vehicle $v, string $tipo): array
    {
        if ($tipo === 'recomendacao') {
            return ['trabalhos' => self::trabalhos(self::adiadasDevidas($v)->pluck('name')->all())];
        }

        if ($tipo === 'revisao') {
            return ['quando' => self::quando($v->next_service_km, $v->next_service_date)];
        }

        $coluna = self::DOCUMENTOS[$tipo] ?? null;

        return ['data' => $coluna ? $v->{$coluna}?->format('d/m/Y') : null];
    }

    /**
     * A ORDEM FICOU CONCLUÍDA (ou entregue) — marca a próxima revisão.
     */
    public static function revisaoFeita(WorkOrder $ordem): void
    {
        try {
            $v = Vehicle::withoutGlobalScope('tenant')->find($ordem->vehicle_id);
            if (! $v) {
                return;
            }

            $d = WorkshopSetting::getForTenant((int) $ordem->tenant_id);
            $km = max((int) $ordem->mileage_in, (int) $v->mileage);
            $semMarcacao = ! $v->next_service_date && ! $v->next_service_km;

            if (! $semMarcacao && ! self::revisaoPerto($v, $d, $km)) {
                return;
            }

            [$porKm, $porMeses] = self::intervalos($v, $d);
            if (! $porKm && ! $porMeses) {
                return;
            }

            $proximoKm = $porKm && $km ? $km + $porKm : null;
            $proximaData = $porMeses ? today()->addMonthsNoOverflow($porMeses) : null;

            $v->update([
                'last_service_date' => today(),
                'last_service_km' => $km ?: null,
                'next_service_km' => $proximoKm,
                'next_service_date' => $proximaData,
                'reminders_paused_until' => null,
            ]);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __('Próxima revisão da viatura marcada :quando.', ['quando' => self::quando($proximoKm, $proximaData)]));
        } catch (\Throwable $e) {
            Log::warning('Próxima revisão não marcada', ['ordem' => $ordem->id, 'erro' => $e->getMessage()]);
        }
    }

    private static function revisaoPerto(Vehicle $v, WorkshopSetting $d, int $km): bool
    {
        return ($v->next_service_date && $v->next_service_date->lte(today()->addDays($d->remind_days_before)))
            || ($v->next_service_km && $km >= (int) $v->next_service_km - $d->remind_km_before);
    }

    /**
     * QUEM CHAMAR, dos mais atrasados para os mais folgados.
     *
     * @return list<array<string, mixed>>
     */
    public static function devidos(int $tenantId): array
    {
        $d = WorkshopSetting::getForTenant($tenantId);
        $hoje = today();
        $limiteRevisao = $hoje->copy()->addDays($d->remind_days_before);
        $limiteDocumentos = $hoje->copy()->addDays($d->documents_days_before);

        $viaturas = Vehicle::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->where(function ($q) use ($limiteRevisao, $limiteDocumentos) {
                $q->whereNotNull('next_service_km')
                    ->orWhereDate('next_service_date', '<=', $limiteRevisao)
                    ->orWhereDate('insurance_expiry', '<=', $limiteDocumentos)
                    ->orWhereDate('inspection_expiry', '<=', $limiteDocumentos)
                    ->orWhereDate('registration_expiry', '<=', $limiteDocumentos)
                    // OF-12: as recomendações adiadas que chegam à data de voltar a propor.
                    ->orWhereIn('id', \App\Models\Workshop\DeferredItem::withoutGlobalScope('tenant')->where('status', 'pendente')
                        ->whereDate('follow_up_on', '<=', $limiteRevisao)->select('vehicle_id'));
            })->get();

        if ($viaturas->isEmpty()) {
            return [];
        }

        $ids = $viaturas->pluck('id')->all();
        $naOficina = WorkOrder::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereIn('vehicle_id', $ids)
            ->whereIn('status', self::NA_OFICINA)->pluck('vehicle_id')->flip();
        $entradas = WorkOrder::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereIn('vehicle_id', $ids)
            ->where('mileage_in', '>', 0)->whereNotNull('received_at')->orderBy('received_at')
            ->get(['vehicle_id', 'received_at', 'mileage_in'])->groupBy('vehicle_id');
        $contactos = VehicleReminder::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('vehicle_id', $ids)
            ->with('user:id,name')->orderByDesc('id')->get()
            ->groupBy(fn (VehicleReminder $r) => "{$r->vehicle_id}|{$r->kind}|{$r->due_key}");
        $clientes = \App\Models\Client::withoutGlobalScopes()->whereIn('id', $viaturas->pluck('client_id')->filter()->unique())
            ->get(['id', 'name', 'phone', 'mobile', 'email'])->keyBy('id');
        $modelos = \App\Models\NotificationTemplate::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('slug', 'like', 'oficina-%')->pluck('sms_body', 'slug');
        $empresa = Tenant::find($tenantId)?->name;
        $adiadas = \App\Models\Workshop\DeferredItem::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereIn('vehicle_id', $ids)
            ->where('status', 'pendente')->whereDate('follow_up_on', '<=', $limiteRevisao)->orderBy('follow_up_on')->get()->groupBy('vehicle_id');

        $itens = [];
        foreach ($viaturas as $v) {
            $pontos = ($entradas[$v->id] ?? collect())->map(fn ($o) => [Carbon::parse($o->received_at)->startOfDay(), (int) $o->mileage_in])->values();
            $porDia = self::kmPorDia($pontos);
            $cliente = $v->client_id ? $clientes[$v->client_id] ?? null : null;
            $base = [
                'viatura_id' => $v->id,
                'matricula' => $v->plate,
                'marca_modelo' => trim(($v->brand ?? '') . ' ' . ($v->model ?? '')),
                'dono' => $v->owner_name ?: $cliente?->name,
                'telefone' => $v->owner_phone ?: ($cliente?->phone ?: $cliente?->mobile),
                'email' => $v->owner_email ?: $cliente?->email,
                'na_oficina' => isset($naOficina[$v->id]),
                'pausado_ate' => $v->reminders_paused_until && $v->reminders_paused_until->gte($hoje) ? $v->reminders_paused_until->toDateString() : null,
            ];
            $variaveis = ['cliente' => $base['dono'], 'matricula' => $v->plate, 'viatura' => $base['marca_modelo'], 'empresa' => $empresa];

            $juntar = function (string $tipo, string $vencimento, bool $vencido, ?int $dias, array $extra, array $mais) use (&$itens, $v, $base, $variaveis, $contactos, $modelos) {
                $lista = $contactos["{$v->id}|{$tipo}|{$vencimento}"] ?? collect();
                $ultimo = $lista->first();
                $texto = ($modelos["oficina-{$tipo}-{$v->tenant_id}"] ?? null) ?: __(AvisosDaOficina::MODELOS[$tipo]['sms_body']);
                $todas = $variaveis + $extra;

                $itens[] = $base + $mais + [
                    'chave' => "{$v->id}-{$tipo}",
                    'tipo' => $tipo,
                    'tipo_rotulo' => __(VehicleReminder::TIPOS[$tipo]),
                    'vencimento' => $vencimento,
                    'vencido' => $vencido,
                    'dias' => $dias,
                    'variaveis' => $extra,
                    'mensagem' => (string) preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($todas[$m[1]] ?? ''), $texto),
                    'contactos' => $lista->count(),
                    'ultimo_contacto' => $ultimo ? [
                        'canal' => $ultimo->channel,
                        'canal_rotulo' => __(VehicleReminder::CANAIS[$ultimo->channel] ?? $ultimo->channel),
                        'quando' => $ultimo->created_at?->toIso8601String(),
                        'por' => $ultimo->user?->name,
                        'nota' => $ultimo->note,
                    ] : null,
                ];
            };

            // A REVISÃO — pela data ou pelos km de hoje, estimados.
            if ($v->next_service_date || $v->next_service_km) {
                $estimados = self::kmEstimados($v, $pontos, $porDia);
                $porData = $v->next_service_date && $v->next_service_date->lte($limiteRevisao);
                $porKm = $v->next_service_km && $estimados >= (int) $v->next_service_km - $d->remind_km_before;

                if ($porData || $porKm) {
                    $faltamKm = $v->next_service_km ? (int) $v->next_service_km - $estimados : null;
                    $juntar('revisao',
                        ($v->next_service_date?->toDateString() ?? '-') . '|' . ($v->next_service_km ?? '-'),
                        ($v->next_service_date && $v->next_service_date->lt($hoje)) || ($faltamKm !== null && $faltamKm <= 0),
                        $v->next_service_date ? (int) $hoje->diffInDays($v->next_service_date, false) : null,
                        ['quando' => self::quando($v->next_service_km, $v->next_service_date)],
                        [
                            'data' => $v->next_service_date?->toDateString(),
                            'km_previstos' => $v->next_service_km,
                            'km_estimados' => $estimados,
                            'km_por_dia' => $porDia !== null ? round($porDia, 1) : null,
                            'faltam_km' => $faltamKm,
                            'ultima_revisao' => $v->last_service_date?->toDateString(),
                            // Pelo andamento, quantos dias faltam para os km (serve para ordenar).
                            'dias_pelos_km' => $faltamKm !== null && $porDia ? (int) floor($faltamKm / $porDia) : null,
                        ]);
                }
            }

            // OS DOCUMENTOS a caducar.
            foreach (self::DOCUMENTOS as $tipo => $coluna) {
                $data = $v->{$coluna};
                if ($data && $data->lte($limiteDocumentos)) {
                    $juntar($tipo, $data->toDateString(), $data->lt($hoje), (int) $hoje->diffInDays($data, false),
                        ['data' => $data->format('d/m/Y')],
                        ['data' => $data->toDateString(), 'km_previstos' => null, 'km_estimados' => null, 'km_por_dia' => null, 'faltam_km' => null, 'ultima_revisao' => null, 'dias_pelos_km' => null]);
                }
            }

            // AS RECOMENDAÇÕES ADIADAS (OF-12): uma linha por viatura, com os trabalhos que esperam.
            if (isset($adiadas[$v->id])) {
                $lista = $adiadas[$v->id];
                $primeira = $lista->first()->follow_up_on;
                $juntar('recomendacao', 'rec|' . $primeira->toDateString() . '|' . $lista->pluck('id')->sort()->implode(','),
                    $primeira->lt($hoje), (int) $hoje->diffInDays($primeira, false),
                    ['trabalhos' => self::trabalhos($lista->pluck('name')->all())],
                    ['data' => $primeira->toDateString(), 'km_previstos' => null, 'km_estimados' => null, 'km_por_dia' => null, 'faltam_km' => null, 'ultima_revisao' => null, 'dias_pelos_km' => null,
                        'trabalhos' => $lista->pluck('name')->values()->all(), 'valor' => round($lista->sum(fn ($r) => $r->valor()), 2)]);
            }
        }

        usort($itens, function (array $a, array $b) {
            $urgencia = fn (array $i) => min($i['dias'] ?? PHP_INT_MAX, $i['dias_pelos_km'] ?? ($i['faltam_km'] !== null && $i['faltam_km'] <= 0 ? -1 : PHP_INT_MAX));

            return [$b['vencido'], $urgencia($a)] <=> [$a['vencido'], $urgencia($b)];
        });

        return $itens;
    }

    public static function registar(Vehicle $v, string $tipo, string $vencimento, string $canal, ?string $nota = null, ?int $userId = null): VehicleReminder
    {
        return VehicleReminder::create([
            'tenant_id' => $v->tenant_id,
            'vehicle_id' => $v->id,
            'kind' => $tipo,
            'due_key' => $vencimento,
            'channel' => $canal,
            'note' => $nota ? mb_substr($nota, 0, 500) : null,
            'user_id' => $userId,
        ]);
    }

    /**
     * O ENVIO AUTOMÁTICO — só com a opção ligada na oficina e o módulo
     * Notificações configurado; no máximo uma volta por dia por empresa.
     *
     * @return int quantas viaturas foram avisadas
     */
    public static function despachar(int $tenantId, int $limite = 30): int
    {
        $d = WorkshopSetting::where('tenant_id', $tenantId)->first();
        if (! $d?->auto_reminders || ! Tenant::find($tenantId)?->hasModule('oficina') || ! AvisosDaOficina::activos($tenantId)) {
            return 0;
        }

        if (! Cache::add("oficina:lembretes:{$tenantId}:" . today()->toDateString(), 1, now()->addHours(26))) {
            return 0;
        }

        $avisadas = 0;
        $porAvisar = collect(self::devidos($tenantId))
            ->filter(fn ($i) => ! $i['na_oficina'] && ! $i['pausado_ate'] && ! $i['ultimo_contacto'])
            ->take($limite);

        foreach ($porAvisar as $i) {
            $v = Vehicle::withoutGlobalScope('tenant')->find($i['viatura_id']);
            $canais = AvisosDaOficina::avisarViatura($v, $i['tipo'], $i['variaveis']);

            foreach ($canais as $canal) {
                self::registar($v, $i['tipo'], $i['vencimento'], mb_strtolower($canal), __('Enviado automaticamente.'));
            }
            $avisadas += $canais ? 1 : 0;
        }

        return $avisadas;
    }
}
