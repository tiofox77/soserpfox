<?php

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderAttachment;
use App\Services\Invoicing\SomasDasFacturas;
use App\Services\Workshop\OrdensDeServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A OFICINA NO PORTAL DO CLIENTE — o carro, o estado, a folha de obra e a factura.
 *
 * Pedido de 15/09/2026: «na oficina o cliente deve ver o estado do carro, o
 * status, a folha de obra, a factura para pagamento e outros dados relevantes».
 *
 * O QUE É DO CLIENTE:
 *  · as viaturas com `client_id` dele, nesta empresa;
 *  · as ordens dessas viaturas — MENOS as que foram facturadas a outra pessoa.
 *    Um carro que mudou de dono não mostra ao dono novo as reparações (e as
 *    facturas) do antigo.
 *
 * O QUE FICA DE FORA, de propósito: as notas internas, o histórico de quem mexeu
 * em quê, o mecânico, o custo das peças. O cliente vê o que o papel da ordem
 * lhe diria.
 */
class PortalDaOficinaApiController extends Controller
{
    /** O caminho de uma ordem, pela ordem em que acontece. */
    private const ETAPAS = ['pending', 'in_progress', 'waiting_parts', 'completed', 'delivered'];

    /** Um ícone para cada estado de raiz do catálogo; os que a oficina criar levam o do carro. */
    private const ICONES = [
        'active' => 'fa-circle-check',
        'in_service' => 'fa-screwdriver-wrench',
        'aguarda_orcamento' => 'fa-file-invoice-dollar',
        'aguarda_pecas' => 'fa-boxes-stacked',
        'pronta_entrega' => 'fa-flag-checkered',
        'completed' => 'fa-circle-check',
        'inactive' => 'fa-circle-pause',
        'abatida' => 'fa-car-burst',
    ];

    public function index(Request $request): JsonResponse
    {
        $cliente = $request->user('client');
        $viaturas = $this->viaturasDe($cliente);
        $ordens = $this->ordensDe($cliente, $viaturas->pluck('id')->all());
        $estados = $this->estadosDe($cliente, $viaturas, $ordens);

        $contas = \App\Models\Treasury\Account::withoutGlobalScopes()->with('bank')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('is_active', true)->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')->limit(4)->get();

        return response()->json([
            'resumo' => self::resumo($cliente),
            'viaturas' => $viaturas->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'matricula' => $v->plate,
                'viatura' => trim("{$v->brand} {$v->model}"),
                'ano' => $v->year,
                'cor' => $v->color,
                'km' => (int) $v->mileage,
                'documentos' => array_values(array_filter([
                    $this->documento(__('Livrete'), $v->registration_expiry),
                    $this->documento(__('Seguro'), $v->insurance_expiry),
                    $this->documento(__('Inspecção'), $v->inspection_expiry),
                ])),
                'na_oficina' => $ordens->where('vehicle_id', $v->id)->whereNotIn('status', ['delivered', 'cancelled'])->count() > 0,
            ] + $estados[$v->id])->values(),
            'ordens' => $ordens->map(fn (WorkOrder $o) => $this->ordem($o, $cliente))->values(),
            'contas' => $contas->map(fn ($c) => [
                'banco' => $c->bank?->name ?? $c->account_name,
                'conta' => $c->account_number,
                'iban' => $c->iban,
            ])->values(),
        ]);
    }

    /** Os números do cartão da oficina no início do portal. */
    public static function resumo(Client $cliente): array
    {
        $eu = new self();
        $viaturas = $eu->viaturasDe($cliente);
        $ordens = $eu->ordensDe($cliente, $viaturas->pluck('id')->all());
        $facturas = $ordens->pluck('invoice')->filter(fn ($f) => $f && $f->status !== 'draft');

        $estados = $eu->estadosDe($cliente, $viaturas, $ordens);

        return [
            'viaturas' => $viaturas->count(),
            'na_oficina' => $ordens->whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'prontas' => $ordens->where('status', 'completed')->count(),
            'por_pagar' => round((float) $facturas->sum(fn ($f) => $eu->falta($f)), 2),
            // O estado de cada carro, para o cartão da oficina no início do portal.
            'estados' => $viaturas->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'matricula' => $v->plate,
                'viatura' => trim("{$v->brand} {$v->model}"),
                'oficina' => $estados[$v->id]['estado_oficina'],
            ] + array_intersect_key($estados[$v->id]['estado'], array_flip(['chave', 'rotulo', 'cor', 'icone'])))->values()->all(),
        ];
    }

    /**
     * O ESTADO DE CADA VIATURA — pedido de 15/09/2026: «no cliente devia puxar o
     * status ou estado da viatura».
     *
     * O dono não quer ler a ordem para saber do carro: o cartão da viatura diz-lhe
     * logo UMA coisa, a que mais lhe importa agora, por esta ordem:
     *
     *  · NA OFICINA — à espera da aprovação dele, pronta a levantar, à espera de
     *    peças, em reparação, deu entrada, marcada (a ordem aberta mais recente;
     *    a aprovação passa à frente porque é ele que tem de agir);
     *  · FORA DELA — revisão em atraso, documentos caducados, revisão a chegar,
     *    ou tudo em dia.
     *
     * E por baixo o resto que ajuda: a próxima revisão (com os km que o carro TERÁ
     * hoje, pelo andamento entre visitas, como nos lembretes), os trabalhos que a
     * oficina recomendou e ficaram para depois, a viatura de cortesia que tem
     * consigo e a última visita.
     *
     * @return array<int, array<string, mixed>> por id da viatura
     */
    private function estadosDe(Client $cliente, $viaturas, $ordens): array
    {
        if ($viaturas->isEmpty()) {
            return [];
        }

        // Ler sem criar: o portal não grava as definições da oficina por ter sido aberto.
        $definicoes = \App\Models\Workshop\WorkshopSetting::withoutGlobalScopes()->where('tenant_id', $cliente->tenant_id)->first()
            ?? new \App\Models\Workshop\WorkshopSetting();
        $adiadas = \App\Models\Workshop\DeferredItem::withoutGlobalScopes()
            ->where('tenant_id', $cliente->tenant_id)->whereIn('vehicle_id', $viaturas->pluck('id'))->where('status', 'pendente')
            ->orderByRaw("CASE severity WHEN 'urgente' THEN 0 WHEN 'atencao' THEN 1 ELSE 2 END")->orderBy('follow_up_on')
            ->get()->groupBy('vehicle_id');
        $emprestimos = \App\Models\Workshop\CourtesyLoan::withoutGlobalScopes()->with(['car' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('tenant_id', $cliente->tenant_id)->whereIn('work_order_id', $ordens->pluck('id'))->whereNull('returned_at')
            ->get()->keyBy('work_order_id');

        // O ESTADO QUE A OFICINA PÔS NA VIATURA (o catálogo da empresa) — é o que
        // manda no cartão; o que se apura das ordens fica por baixo, em detalhe.
        $catalogo = collect(\App\Models\Workshop\VehicleStatus::todosDe((int) $cliente->tenant_id))->keyBy('valor');

        $hoje = today();
        $estados = [];

        foreach ($viaturas as $v) {
            $minhas = $ordens->where('vehicle_id', $v->id);
            $abertas = $minhas->whereNotIn('status', ['delivered', 'cancelled']);
            $aprovar = $abertas->first(fn (WorkOrder $o) => $o->items->contains('approval', 'pending')
                && \App\Http\Controllers\Api\Workshop\AprovacaoDoOrcamentoApiController::linkActivo($o));
            $aberta = $aprovar ?? $abertas->first();
            $ultima = $minhas->whereIn('status', ['completed', 'delivered'])->first();

            // A REVISÃO — a mesma conta dos lembretes (OF-11).
            $revisao = null;
            if ($v->next_service_date || $v->next_service_km) {
                $pontos = $minhas->filter(fn (WorkOrder $o) => $o->received_at && (int) $o->mileage_in > 0)
                    ->sortBy('received_at')->map(fn (WorkOrder $o) => [\Illuminate\Support\Carbon::parse($o->received_at)->startOfDay(), (int) $o->mileage_in])->values();
                $estimados = \App\Services\Workshop\LembretesDaOficina::kmEstimados($v, $pontos, \App\Services\Workshop\LembretesDaOficina::kmPorDia($pontos));
                $faltamKm = $v->next_service_km ? (int) $v->next_service_km - $estimados : null;

                $revisao = [
                    'data' => $v->next_service_date?->toDateString(),
                    'km' => $v->next_service_km,
                    'km_estimados' => $estimados,
                    'faltam_km' => $faltamKm,
                    'dias' => $v->next_service_date ? (int) $hoje->diffInDays($v->next_service_date, false) : null,
                    'atrasada' => ($v->next_service_date && $v->next_service_date->lt($hoje)) || ($faltamKm !== null && $faltamKm <= 0),
                    'a_chegar' => ($v->next_service_date && $v->next_service_date->lte($hoje->copy()->addDays((int) $definicoes->remind_days_before)))
                        || ($faltamKm !== null && $faltamKm <= (int) $definicoes->remind_km_before),
                    'quando' => \App\Services\Workshop\LembretesDaOficina::quando($v->next_service_km, $v->next_service_date),
                ];
            }

            $caducados = collect([
                __('Livrete') => $v->registration_expiry,
                __('Seguro') => $v->insurance_expiry,
                __('Inspecção') => $v->inspection_expiry,
            ])->filter(fn ($data) => $data && $data->lt($hoje))->keys();

            $emprestimo = $aberta ? $emprestimos->get($aberta->id) : null;

            $doCatalogo = $v->status ? $catalogo->get($v->status) : null;

            $estados[$v->id] = [
                'estado_oficina' => $doCatalogo ? [
                    'codigo' => $v->status,
                    'rotulo' => __($doCatalogo['rotulo']),
                    'cor' => $doCatalogo['cor'],
                    'icone' => self::ICONES[$v->status] ?? 'fa-car-side',
                ] : null,
                'estado' => $this->estado($aberta, $aprovar !== null, $revisao, $caducados->all(), $ultima),
                'ordem' => $aberta ? ['id' => $aberta->id, 'numero' => $aberta->order_number] : null,
                'revisao' => $revisao,
                'recomendadas' => ($adiadas[$v->id] ?? collect())->take(5)->map(fn ($r) => [
                    'nome' => $r->name,
                    'gravidade' => $r->severity,
                    'voltar_em' => $r->follow_up_on?->toDateString(),
                ])->values(),
                'recomendadas_total' => ($adiadas[$v->id] ?? collect())->count(),
                'cortesia' => $emprestimo ? [
                    'matricula' => $emprestimo->car?->plate,
                    'viatura' => $emprestimo->car ? trim("{$emprestimo->car->brand} {$emprestimo->car->model}") : null,
                    'devolver_ate' => $emprestimo->expected_return_at?->toIso8601String(),
                    'atrasada' => $emprestimo->atrasado(),
                ] : null,
                'ultima_visita' => ($ultima?->delivered_at ?? $ultima?->completed_at ?? $ultima?->received_at)?->toDateString(),
            ];
        }

        return $estados;
    }

    /**
     * A UMA FRASE do cartão: a chave, o rótulo, a cor (a das etiquetas), o ícone,
     * quanto do caminho já andou (só na oficina) e a data que a acompanha.
     *
     * @param  list<string>  $caducados
     */
    private function estado(?WorkOrder $o, bool $aprovar, ?array $revisao, array $caducados, ?WorkOrder $ultima): array
    {
        $dia = fn ($data) => $data ? \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') : null;

        if ($o) {
            [$chave, $rotulo, $cor, $icone, $progresso, $frase] = match (true) {
                $aprovar => ['aprovar', __('À espera da sua aprovação'), 'aviso', 'fa-hand', null, __('A oficina propôs trabalhos. Veja-os e decida na folha de obra.')],
                $o->status === 'completed' => ['pronta', __('Pronta a levantar'), 'bom', 'fa-flag-checkered', 100, __('Concluída a :data. Pode vir buscar o carro.', ['data' => $dia($o->completed_at) ?? '—'])],
                $o->status === 'waiting_parts' => ['pecas', __('À espera de peças'), 'aviso', 'fa-boxes-stacked', 65, __('O trabalho continua assim que as peças chegarem.')],
                $o->status === 'in_progress' => ['em_curso', __('Em reparação'), 'primaria', 'fa-screwdriver-wrench', 50, $o->started_at ? __('Os trabalhos começaram a :data.', ['data' => $dia($o->started_at)]) : __('Os trabalhos já começaram.')],
                $o->status === 'scheduled' => ['marcada', __('Entrada marcada'), 'primaria', 'fa-calendar-check', 5, $o->scheduled_for ? __('Esperamos o carro a :data.', ['data' => $dia($o->scheduled_for)]) : __('A oficina marcou a entrada do carro.')],
                default => ['recebida', __('Deu entrada na oficina'), 'aviso', 'fa-car-on', 20, __('Entrou a :data. Está à espera do diagnóstico.', ['data' => $dia($o->received_at) ?? '—'])],
            };

            return ['chave' => $chave, 'rotulo' => $rotulo, 'cor' => $cor, 'icone' => $icone, 'na_oficina' => true, 'progresso' => $progresso, 'frase' => $frase];
        }

        $fora = fn (string $chave, string $rotulo, string $cor, string $icone, string $frase) => ['chave' => $chave, 'rotulo' => $rotulo, 'cor' => $cor, 'icone' => $icone, 'na_oficina' => false, 'progresso' => null, 'frase' => $frase];

        if ($revisao && $revisao['atrasada']) {
            return $fora('revisao_atrasada', __('Revisão em atraso'), 'perigo', 'fa-oil-can', __('A revisão era :quando. Marque a entrada com a oficina.', ['quando' => $revisao['quando']]));
        }
        if ($caducados) {
            return $fora('documentos', __('Documentos caducados'), 'perigo', 'fa-triangle-exclamation', __('Caducou: :lista.', ['lista' => implode(', ', $caducados)]));
        }
        if ($revisao && $revisao['a_chegar']) {
            return $fora('revisao_a_chegar', __('Revisão a chegar'), 'aviso', 'fa-oil-can', __('A próxima revisão é :quando.', ['quando' => $revisao['quando']]));
        }

        return $fora('em_dia', __('Tudo em dia'), 'bom', 'fa-circle-check', $ultima
            ? __('Última visita a :data.', ['data' => $dia($ultima->delivered_at ?? $ultima->completed_at ?? $ultima->received_at)])
            : __('Sem visitas registadas nesta oficina.'));
    }

    private function viaturasDe(Client $cliente)
    {
        return Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $cliente->tenant_id)
            ->where('client_id', $cliente->id)
            ->orderBy('plate')
            ->get();
    }

    private function ordensDe(Client $cliente, array $viaturas)
    {
        if (! $viaturas) {
            return collect();
        }

        return WorkOrder::withoutGlobalScopes()
            ->with(['invoice' => fn ($q) => $q->withoutGlobalScopes(), 'vehicle' => fn ($q) => $q->withoutGlobalScopes(), 'items', 'attachments', 'vehiclePhotos' => fn ($q) => $q->withoutGlobalScopes()->orderBy('created_at'),
                // As inspecções CONCLUÍDAS (OF-02): uma a meio não se mostra ao cliente.
                'inspections' => fn ($q) => $q->withoutGlobalScopes()->whereNotNull('completed_at')->orderByDesc('completed_at'),
                // A avaliação do serviço (OF-14).
                'survey' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('tenant_id', $cliente->tenant_id)
            ->whereIn('vehicle_id', $viaturas)
            ->orderByDesc('received_at')->orderByDesc('id')
            ->limit(60)
            ->get()
            // A ordem facturada a OUTRA pessoa não é deste cliente (carro que mudou de dono).
            ->filter(fn (WorkOrder $o) => ! $o->invoice || (int) $o->invoice->client_id === (int) $cliente->id)
            ->values();
    }

    private function ordem(WorkOrder $o, Client $cliente): array
    {
        $f = $o->invoice && $o->invoice->status !== 'draft' ? $o->invoice : null;
        $falta = $f ? $this->falta($f) : 0.0;
        $datas = [
            'pending' => $o->received_at,
            'in_progress' => $o->started_at,
            'waiting_parts' => null,
            'completed' => $o->completed_at,
            'delivered' => $o->delivered_at,
        ];
        $aqui = array_search($o->status, self::ETAPAS, true);

        return [
            'id' => $o->id,
            'numero' => $o->order_number,
            'matricula' => $o->vehicle?->plate,
            'viatura' => $o->vehicle ? trim("{$o->vehicle->brand} {$o->vehicle->model}") : null,
            'estado' => $o->status,
            'estado_rotulo' => __(OrdensDeServico::ESTADOS[$o->status] ?? $o->status),
            'cancelada' => $o->status === 'cancelled',
            // A LINHA DO TEMPO: cada etapa, feita ou não, com a data quando a há.
            // «À espera de peças» só aparece se a ordem lá estiver ou lá tiver passado.
            'etapas' => collect(self::ETAPAS)
                ->reject(fn ($e) => $e === 'waiting_parts' && $o->status !== 'waiting_parts')
                ->map(fn ($e) => [
                    'chave' => $e,
                    'rotulo' => __(OrdensDeServico::ESTADOS[$e]),
                    'feita' => $aqui !== false && array_search($e, self::ETAPAS, true) <= $aqui,
                    'actual' => $e === $o->status,
                    'quando' => $datas[$e]?->toIso8601String(),
                ])->values(),
            'entrada' => $o->received_at?->toIso8601String(),
            'agendada' => $o->scheduled_for?->toIso8601String(),
            'km' => (int) $o->mileage_in,
            'problema' => $o->problem_description,
            'diagnostico' => $o->diagnosis,
            'trabalho' => $o->work_performed,
            'recomendacoes' => $o->recommendations,
            'linhas' => $o->items->map(fn ($l) => [
                'tipo' => $l->type,
                'nome' => $l->name,
                'quantidade' => (float) $l->quantity,
                'total' => round((float) $l->subtotal, 2),
                'aprovacao' => $l->approval ?? 'approved',
            ])->values(),
            // OF-03: o orçamento à espera da decisão do cliente.
            'aprovar' => \App\Http\Controllers\Api\Workshop\AprovacaoDoOrcamentoApiController::linkActivo($o) && $o->items->contains('approval', 'pending')
                ? route('oficina.aprovar-orcamento', $o->approval_token) : null,
            // OF-14: o link para avaliar (por responder) ou a nota que o cliente deu.
            'avaliar' => $o->survey && ! $o->survey->answered_at ? route('oficina.avaliar', $o->survey->token) : null,
            'avaliacao' => $o->survey?->answered_at ? $o->survey->score : null,
            'total' => round((float) $o->total, 2),
            'garantia_ate' => $o->warranty_expires?->toDateString(),
            'inspeccoes' => $o->inspections->map(fn ($i) => \App\Http\Controllers\Api\Workshop\InspeccoesDaOrdemApiController::paraEcra($i))->values(),
            'fotos' => $o->attachments
                ->filter(fn (WorkOrderAttachment $a) => in_array($a->category, ['photo_before', 'photo_after', 'photo_damage'], true))
                ->map(fn (WorkOrderAttachment $a) => [
                    'url' => $a->file_url,
                    'tipo' => $a->category,
                    'descricao' => $a->description,
                    'servico' => null,
                    'zona' => null,
                ])
                /*
                 * E AS FOTOGRAFIAS DA VIATURA POSTAS NESTA FOLHA DE OBRA — o antes
                 * e o depois do bate-chapa e da pintura. Só as ligadas a uma
                 * folha: as outras são registo interno da oficina.
                 */
                ->concat($o->vehiclePhotos->map(fn (\App\Models\Workshop\VehiclePhoto $v) => [
                    'url' => $v->url,
                    'tipo' => ['antes' => 'photo_before', 'depois' => 'photo_after', 'dano' => 'photo_damage'][$v->phase] ?? 'photo_during',
                    'descricao' => $v->description,
                    'servico' => __(\App\Models\Workshop\VehiclePhoto::SERVICOS[$v->service] ?? 'Outros'),
                    'zona' => $v->zone ? __(\App\Models\Workshop\VehiclePhoto::ZONAS[$v->zone] ?? $v->zone) : null,
                ]))->values(),
            'factura' => $f ? [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'data' => $f->invoice_date?->toDateString(),
                'vencimento' => $f->due_date?->toDateString(),
                'estado_rotulo' => $f->status_label,
                'total' => round((float) $f->total, 2),
                'falta' => $falta,
                'vencida' => $falta > 0 && $f->due_date && $f->due_date->lt(today()),
                'pdf' => route('client.invoices.pdf', $f->id),
            ] : null,
        ];
    }

    /** O que falta receber de uma factura — a regra única das facturas. */
    private function falta($f): float
    {
        if (($f->invoice_type ?? 'FT') === 'FR' || in_array($f->status, SomasDasFacturas::SEM_NADA_A_RECEBER, true)) {
            return 0.0;
        }

        return max(0.0, round((float) $f->total - (float) $f->paid_amount, 2));
    }

    /** Uma validade da viatura, com os dias que faltam (negativos = caducada). */
    private function documento(string $nome, $data): ?array
    {
        if (! $data) {
            return null;
        }

        return ['nome' => $nome, 'ate' => $data->toDateString(), 'dias' => (int) today()->diffInDays($data, false)];
    }
}
