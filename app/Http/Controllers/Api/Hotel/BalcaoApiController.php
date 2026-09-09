<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O BALCÃO — quem chega sem reserva e fica hoje.
 *
 * Três passos: o quarto, o hóspede, a conta. No fim há uma estada já com
 * entrada dada, porque a pessoa está ali de mala na mão.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE, e é grave:
 *
 *  • A ESTADA NASCIA SEM ADQUIRENTE. O ecrã criava um `Hotel\Guest` — a ficha
 *    antiga, que está vazia e que a facturação não conhece — e deixava
 *    `client_id` a nulo. No check-out não havia a quem facturar: o hóspede
 *    saía sem documento. Aqui o hóspede é um CLIENTE, como em todo o resto do
 *    módulo.
 *  • OS QUARTOS «LIVRES» ERAM OS DE ESTADO `available`, e mais nada: um quarto
 *    livre hoje mas reservado para amanhã aparecia na lista, e o walk-in de
 *    três noites entrava por cima da reserva de amanhã. Agora pergunta-se
 *    pelas DATAS.
 *  • A ENTRADA ERA ESCRITA À MÃO (`status` = checked_in, `actual_check_in`,
 *    e um `update` no quarto) em vez de passar por `checkIn()`. Duas escritas
 *    do mesmo facto que podiam divergir — e divergiam: a fidelidade do
 *    hóspede não contava a estada.
 */
class BalcaoApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.walk-in.create');

        return response()->json([
            'tipos_de_quarto' => RoomType::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'base_price', 'capacity', 'description'])
                ->map(fn (RoomType $t) => [
                    'id' => $t->id,
                    'nome' => $t->name,
                    'descricao' => $t->description,
                    'preco' => (float) $t->base_price,
                    'capacidade' => (int) $t->capacity,
                ])->values(),
            'permissoes' => [
                // Criar o hóspede aqui é a permissão da FICHA, e não a de
                // registar a entrada: são coisas diferentes.
                'pode_criar_hospede' => (bool) $request->user()?->can('hotel.guests.create'),
            ],
        ]);
    }

    /**
     * OS QUARTOS QUE ESTÃO MESMO LIVRES para estas datas.
     *
     * O ecrã de sempre listava os de estado `available` e mais nada — um
     * quarto livre hoje mas reservado para amanhã aparecia na lista, e o
     * walk-in de três noites entrava por cima da reserva de amanhã.
     */
    public function quartos(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.walk-in.create');

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

        $quartos = Room::where('tenant_id', $tenantId)
            ->where('room_type_id', $dados['tipo'])
            ->where('is_active', true)
            ->orderBy('floor')->orderBy('number')
            ->get();

        return response()->json([
            'data' => $quartos
                ->filter(fn (Room $q) => $q->isAvailableForDates($dados['de'], $dados['ate']))
                ->map(fn (Room $q) => [
                    'id' => $q->id,
                    'numero' => $q->number,
                    'piso' => $q->floor,
                    'estado' => $q->status,
                    'estado_rotulo' => __(Room::STATUSES[$q->status] ?? (string) $q->status),
                    'limpeza' => $q->housekeeping_status,
                    'limpeza_rotulo' => __(Room::HOUSEKEEPING_STATUSES[$q->housekeeping_status] ?? (string) $q->housekeeping_status),
                    /*
                     * UM QUARTO SUJO PODE ESTAR LIVRE — e é preciso dizê-lo.
                     * Dar a chave de um quarto por limpar a quem acabou de
                     * chegar é o pior primeiro minuto possível.
                     */
                    'precisa_de_limpeza' => in_array($q->housekeeping_status, ['dirty', 'in_progress'], true),
                ])->values(),
        ]);
    }

    /** Quem já cá esteve — a mesma procura das reservas. */
    public function hospedes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.walk-in.create');

        $procura = trim((string) $request->query('procura', ''));

        $clientes = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('phone', 'like', "%{$procura}%")
                ->orWhere('email', 'like', "%{$procura}%")
                ->orWhere('document_number', 'like', "%{$procura}%")
                ->orWhere('nif', 'like', "%{$procura}%")))
            ->orderBy('name')->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'nif', 'document_number', 'nationality', 'hotel_vip', 'hotel_blacklisted']);

        return response()->json([
            'data' => $clientes->map(fn (Client $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'telefone' => $c->phone,
                'email' => $c->email,
                'nif' => $c->nif,
                'documento' => $c->document_number,
                'nacionalidade' => $c->nationality,
                'vip' => (bool) $c->hotel_vip,
                'lista_negra' => (bool) $c->hotel_blacklisted,
            ])->values(),
        ]);
    }

    /**
     * REGISTAR A ENTRADA — o hóspede fica com a chave na mão.
     *
     * Numa transacção só: a estada nasce e dá entrada. Se a entrada falhar não
     * pode ficar uma reserva pendente no sistema por uma pessoa que já está no
     * quarto.
     */
    public function registar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.walk-in.create');

        $dados = $request->validate([
            'client_id' => ['required', 'integer'],
            'room_type_id' => ['required', 'integer'],
            'room_id' => ['required', 'integer'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['required', 'integer', 'min:0', 'max:10'],
            'room_rate' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenantId = activeTenantId();

        abort_unless(
            Client::where('tenant_id', $tenantId)->whereKey($dados['client_id'])->exists(),
            422, __('Esse hóspede não é desta empresa.')
        );

        $quarto = Room::where('tenant_id', $tenantId)
            ->where('room_type_id', $dados['room_type_id'])
            ->find($dados['room_id']);

        abort_unless($quarto, 422, __('Esse quarto não é desta casa, ou não é deste tipo.'));

        // A ÚLTIMA PALAVRA É AQUI e não na lista: entre escolher o quarto e
        // carregar em «registar» pode ter entrado outra reserva.
        if (! $quarto->isAvailableForDates($dados['check_in_date'], $dados['check_out_date'])) {
            throw ValidationException::withMessages([
                'room_id' => __('O quarto :n já está reservado nestas datas. Escolha outro quarto ou outras datas.', [
                    'n' => $quarto->number,
                ]),
            ]);
        }

        $noites = max(1, Carbon::parse($dados['check_in_date'])->diffInDays(Carbon::parse($dados['check_out_date'])));

        $reserva = DB::transaction(function () use ($dados, $tenantId, $quarto, $noites) {
            $reserva = Reservation::create([
                'tenant_id' => $tenantId,
                'client_id' => $dados['client_id'],
                'room_type_id' => $dados['room_type_id'],
                'check_in_date' => $dados['check_in_date'],
                'check_out_date' => $dados['check_out_date'],
                'adults' => $dados['adults'],
                'children' => $dados['children'],
                'extra_beds' => 0,
                'nights' => $noites,
                'room_rate' => $dados['room_rate'],
                'discount' => $dados['discount'],
                'paid_amount' => $dados['paid_amount'],
                'status' => Reservation::STATUS_PENDING,
                'source' => 'walk_in',
                'special_requests' => $dados['special_requests'] ?? null,
            ]);

            // A ENTRADA PELA PORTA DO MODELO: põe o quarto em ocupado, escreve
            // a hora real e conta a estada na fidelidade do hóspede. O ecrã de
            // sempre escrevia as três coisas à mão, e a fidelidade ficava por
            // contar.
            $reserva->checkIn($quarto->id);

            return $reserva;
        });

        $reserva->refresh();

        return response()->json([
            'data' => [
                'id' => $reserva->id,
                'numero' => $reserva->reservation_number,
                'codigo' => $reserva->confirmation_code,
                'hospede' => $reserva->nome_do_hospede,
                'quarto' => $quarto->number,
                'tipo_de_quarto' => $quarto->roomType?->name,
                'entrada' => $reserva->check_in_date?->toDateString(),
                'saida' => $reserva->check_out_date?->toDateString(),
                'noites' => (int) $reserva->nights,
                'total' => (float) $reserva->total,
                'pago' => (float) $reserva->paid_amount,
                'por_receber' => (float) $reserva->balance_due,
                'estado_de_pagamento' => $reserva->payment_status,
            ],
            'message' => __('Entrada registada no quarto :n.', ['n' => $quarto->number]),
        ], 201);
    }
}
