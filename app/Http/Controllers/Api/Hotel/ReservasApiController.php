<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Models\Treasury\PaymentMethod;
use App\Services\Hotel\Reservas;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS RESERVAS — o centro do módulo do hotel.
 *
 * Tudo o que acontece a uma estada passa por aqui: nasce pendente, confirma-se,
 * dá entrada, e sai pelo ecrã de check-out. Cancelar e «não compareceu» são os
 * dois fins que não passam pela porta.
 *
 * AS TRANSIÇÕES SÃO DO MODELO e não deste controlador: `Reservation::TRANSICOES`
 * é a tabela, e `confirm()`, `checkIn()`, `cancel()` e `marcarNaoCompareceu()`
 * são as portas. Cada uma delas mexe também no QUARTO, e reimplementá-las aqui
 * era ter duas verdades sobre a mesma estada.
 *
 * O CHECK-OUT NÃO ESTÁ AQUI, de propósito: fechar a estada sem facturar deixava
 * o hóspede sair sem documento e, com a reserva já em «checked_out», o caminho
 * fiscal deixava de estar acessível — os consumos por facturar evaporavam. O
 * botão encaminha para o ecrã de check-out, que é onde se factura.
 */
class ReservasApiController extends Controller
{
    public function __construct(private readonly Reservas $reservas) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** @return list<array{valor: string, rotulo: string}> */
    private static function escolhas(array $mapa): array
    {
        return collect($mapa)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values()->all();
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $tenantId = activeTenantId();

        return response()->json([
            'estados' => self::escolhas(Reservation::STATUSES),
            'fontes' => self::escolhas(Reservation::SOURCES),
            'estados_de_pagamento' => self::escolhas(Reservation::PAYMENT_STATUSES),
            'filtros_de_data' => self::escolhas(Reservas::FILTROS_DE_DATA),
            'tipos_de_quarto' => RoomType::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'base_price'])
                ->map(fn (RoomType $t) => [
                    'valor' => (string) $t->id,
                    'rotulo' => $t->name,
                    // O PREÇO BASE VIAJA COM O TIPO: escolher o tipo preenche a
                    // taxa por noite, como o ecrã de sempre fazia — mas sem uma
                    // ida ao servidor a cada mudança da caixa.
                    'preco' => (float) $t->base_price,
                ])->values(),
            'quartos' => Room::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('number')->get(['id', 'number', 'room_type_id', 'status'])
                ->map(fn (Room $q) => [
                    'valor' => (string) $q->id,
                    'rotulo' => trim($q->number . ' — ' . __(Room::STATUSES[$q->status] ?? (string) $q->status)),
                    'tipo' => (string) $q->room_type_id,
                ])->values(),
            'meios_de_pagamento' => PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name])->values(),
            'provincias' => Geografia::provincias(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('hotel.reservations.create'),
                'pode_editar' => (bool) $request->user()?->can('hotel.reservations.edit'),
                'pode_apagar' => (bool) $request->user()?->can('hotel.reservations.delete'),
                /*
                 * CRIAR O HÓSPEDE AQUI MESMO é a permissão da FICHA, e não a de
                 * fazer a reserva: são coisas diferentes. O formulário rápido
                 * do ecrã de sempre criava clientes sem perguntar nada a
                 * ninguém — era uma porta das traseiras para a lista de
                 * clientes da facturação.
                 */
                'pode_criar_hospede' => (bool) $request->user()?->can('hotel.guests.create'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(array_keys(Reservation::STATUSES))],
            'fonte' => ['nullable', Rule::in(array_keys(Reservation::SOURCES))],
            'quando' => ['nullable', Rule::in(array_keys(Reservas::FILTROS_DE_DATA))],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $consulta = Reservation::where('tenant_id', $tenantId)
            // `invoice` incluída: a lista mostra o número da factura em cada
            // linha, e sem isto era uma consulta por reserva.
            ->with(['client:id,name,phone,email', 'room:id,number', 'roomType:id,name', 'invoice:id,invoice_number'])
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w
                ->where('reservation_number', 'like', "%{$p}%")
                ->orWhere('confirmation_code', 'like', "%{$p}%")
                ->orWhereHas('client', fn ($c) => $c
                    ->where('name', 'like', "%{$p}%")
                    ->orWhere('email', 'like', "%{$p}%")
                    ->orWhere('phone', 'like', "%{$p}%"))))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['fonte'] ?? null, fn ($q, $f) => $q->where('source', $f))
            ->when(($filtros['quando'] ?? null) === 'today', fn ($q) => $q->today())
            ->when(($filtros['quando'] ?? null) === 'checkin_today', fn ($q) => $q->checkingInToday())
            ->when(($filtros['quando'] ?? null) === 'checkout_today', fn ($q) => $q->checkingOutToday())
            ->when(($filtros['quando'] ?? null) === 'current', fn ($q) => $q->currentlyStaying())
            ->latest();

        $pagina = $consulta->paginate($filtros['por_pagina'] ?? 15)->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Reservation $r) => $this->linha($r))->all(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
            ],
            /*
             * OS CARTÕES SÃO DA CASA e não da página nem do filtro: «entram
             * hoje» é uma pergunta sobre o hotel, e um número que muda ao virar
             * a página deixa de a responder.
             */
            'resumo' => [
                'entram_hoje' => Reservation::where('tenant_id', $tenantId)->checkingInToday()->count(),
                'saem_hoje' => Reservation::where('tenant_id', $tenantId)->checkingOutToday()->count(),
                'hospedados' => Reservation::where('tenant_id', $tenantId)->currentlyStaying()->count(),
                'pendentes' => Reservation::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            ],
        ]);
    }

    /** A ficha inteira de uma reserva — o modal de ver. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        return response()->json(['data' => $this->linha($this->encontrar($id), completa: true)]);
    }

    /**
     * OS HÓSPEDES, para a caixa de procura do formulário.
     *
     * É a mesma tabela dos clientes da facturação: o hóspede de um hotel é o
     * adquirente da factura, e ter duas fichas para a mesma pessoa era ter de
     * escolher qual delas leva o NIF.
     */
    public function hospedes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $procura = trim((string) $request->query('procura', ''));

        $clientes = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('phone', 'like', "%{$procura}%")
                ->orWhere('email', 'like', "%{$procura}%")
                ->orWhere('nif', 'like', "%{$procura}%")))
            ->orderBy('name')->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'nif', 'hotel_vip', 'hotel_blacklisted']);

        return response()->json([
            'data' => $clientes->map(fn (Client $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'telefone' => $c->phone,
                'email' => $c->email,
                'nif' => $c->nif,
                /*
                 * A LISTA NEGRA VIAJA COM O HÓSPEDE. É uma decisão da casa —
                 * quem lá está não volta a ficar hospedado — e o ecrã de sempre
                 * não a mostrava na altura em que ela importa: a escolher quem
                 * fica no quarto.
                 */
                'vip' => (bool) $c->hotel_vip,
                'lista_negra' => (bool) $c->hotel_blacklisted,
            ])->values(),
        ]);
    }

    /** Os quartos livres do tipo da reserva — a lista do modal de entrada. */
    public function quartosLivres(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $reserva = $this->encontrar($id);

        return response()->json([
            'data' => Room::where('tenant_id', activeTenantId())
                ->where('room_type_id', $reserva->room_type_id)
                ->where('status', 'available')
                ->where('is_active', true)
                ->orderBy('number')
                ->get(['id', 'number', 'floor', 'housekeeping_status'])
                ->map(fn (Room $q) => [
                    'id' => $q->id,
                    'numero' => $q->number,
                    'piso' => $q->floor,
                    'limpeza' => $q->housekeeping_status,
                    'limpeza_rotulo' => __(Room::HOUSEKEEPING_STATUSES[$q->housekeeping_status] ?? (string) $q->housekeeping_status),
                ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.create');

        $dados = $this->validar($request);

        $reserva = Reservation::create($dados + ['tenant_id' => activeTenantId(), 'status' => 'pending']);

        return response()->json([
            'data' => $this->linha($this->recarregar($reserva), completa: true),
            'message' => __('Reserva criada.'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $reserva = $this->encontrar($id);
        $reserva->update($this->validar($request, $reserva));

        return response()->json([
            'data' => $this->linha($this->recarregar($reserva), completa: true),
            'message' => __('Reserva guardada.'),
        ]);
    }

    /**
     * AS TRANSIÇÕES — confirmar, dar entrada, cancelar, não compareceu.
     *
     * Cada uma delas é um método do modelo que valida a transição e mexe no
     * quarto. Uma transição que a tabela não permite responde 422 com o motivo,
     * em vez de gravar um estado impossível.
     */
    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $dados = $request->validate([
            'accao' => ['required', Rule::in(['confirmar', 'entrada', 'nao-compareceu', 'cancelar'])],
            'quarto' => ['nullable', 'integer'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $reserva = $this->encontrar($id);

        try {
            [$mensagem, $aviso] = match ($dados['accao']) {
                'confirmar' => $this->confirmar($reserva),
                'entrada' => $this->darEntrada($reserva, $dados['quarto'] ?? null),
                'nao-compareceu' => $this->naoCompareceu($reserva),
                'cancelar' => $this->cancelar($reserva, $dados['motivo'] ?? null),
            };
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['accao' => $e->getMessage()]);
        }

        return response()->json([
            'data' => $this->linha($this->recarregar($reserva), completa: true),
            'message' => $mensagem,
            'aviso' => $aviso,
        ]);
    }

    /** @return array{0: string, 1: ?string} */
    private function confirmar(Reservation $r): array
    {
        $r->confirm();

        return [__('Reserva confirmada.'), null];
    }

    /** @return array{0: string, 1: ?string} */
    private function darEntrada(Reservation $r, ?int $quarto): array
    {
        $escolhido = $quarto ?: $r->room_id;

        if (! $escolhido) {
            throw new \DomainException(__('Escolha um quarto para dar entrada.'));
        }

        // O QUARTO TEM DE SER DESTA CASA: o id vem do browser, e sem esta
        // verificação dava-se entrada no quarto de outro hotel.
        abort_unless(
            Room::where('tenant_id', activeTenantId())->whereKey($escolhido)->exists(),
            422, __('Esse quarto não é desta casa.')
        );

        $r->checkIn($escolhido);

        return [__('Entrada registada.'), null];
    }

    /** @return array{0: string, 1: ?string} */
    private function naoCompareceu(Reservation $r): array
    {
        $porRegularizar = $r->marcarNaoCompareceu(auth()->id());

        return [
            __('Marcada como «não compareceu». Quarto libertado.'),
            $this->reservas->avisoDeFacturas($porRegularizar),
        ];
    }

    /** @return array{0: string, 1: ?string} */
    private function cancelar(Reservation $r, ?string $motivo): array
    {
        $porRegularizar = $r->cancel($motivo ?: __('Cancelada pelo utilizador'), auth()->id());

        return [__('Reserva cancelada.'), $this->reservas->avisoDeFacturas($porRegularizar)];
    }

    /** RECEBER UM ADIANTAMENTO — e, se for pedido, facturá-lo. */
    public function receber(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0.01'],
            'meio' => ['required', 'integer'],
            'facturar' => ['nullable', 'boolean'],
        ]);

        $reserva = $this->encontrar($id);

        $meio = PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)->find($dados['meio']);

        abort_unless($meio, 422, __('Esse meio de pagamento não é desta empresa.'));

        try {
            $resultado = $this->reservas->receber(
                $reserva, (float) $dados['valor'], $meio, (bool) ($dados['facturar'] ?? false)
            );
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['valor' => $e->getMessage()]);
        }

        $mensagem = __('Recebidos :valor Kz.', ['valor' => number_format((float) $dados['valor'], 2, ',', '.')]);

        if ($resultado['factura']) {
            $mensagem .= ' ' . __('Factura :n emitida.', ['n' => $resultado['factura']->invoice_number]);
        }

        return response()->json([
            'data' => $this->linha($resultado['reserva'], completa: true),
            'message' => $mensagem,
        ]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function encontrar(int $id): Reservation
    {
        return Reservation::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    private function recarregar(Reservation $r): Reservation
    {
        return $r->fresh(['client', 'room:id,number', 'roomType:id,name', 'invoice:id,invoice_number', 'createdBy:id,name']);
    }

    private function validar(Request $request, ?Reservation $reserva = null): array
    {
        $tenantId = activeTenantId();

        $dados = $request->validate([
            'client_id' => ['required', 'integer'],
            'room_type_id' => ['required', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['required', 'integer', 'min:0', 'max:10'],
            'extra_beds' => ['required', 'integer', 'min:0', 'max:5'],
            'source' => ['required', Rule::in(array_keys(Reservation::SOURCES))],
            'room_rate' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);

        /*
         * O HÓSPEDE, O TIPO E O QUARTO TÊM DE SER DESTA CASA.
         *
         * O ecrã de sempre validava com `exists:hotel_rooms,id` e
         * `exists:invoicing_clients,id` — as tabelas inteiras, sem empresa
         * nenhuma —, e os ids vêm do browser: reservar o quarto de outro hotel
         * em nome de um cliente de outra empresa era escrever dois números.
         */
        abort_unless(
            Client::where('tenant_id', $tenantId)->whereKey($dados['client_id'])->exists(),
            422, __('Esse hóspede não é desta empresa.')
        );

        abort_unless(
            RoomType::where('tenant_id', $tenantId)->whereKey($dados['room_type_id'])->exists(),
            422, __('Esse tipo de quarto não é desta casa.')
        );

        $dados['room_id'] = $dados['room_id'] ?: null;

        if ($dados['room_id']) {
            $quarto = Room::where('tenant_id', $tenantId)->find($dados['room_id']);

            abort_unless($quarto, 422, __('Esse quarto não é desta casa.'));

            /*
             * SOBREPOSIÇÃO DE DATAS NO MESMO QUARTO.
             *
             * `Room::isAvailableForDates()` já existia — e até aceita a reserva
             * a excluir, para as edições — mas só o site público a usava. Pela
             * recepção era possível reservar o mesmo quarto duas vezes para as
             * mesmas noites, e o conflito só aparecia com os dois hóspedes ao
             * balcão.
             */
            if (! $quarto->isAvailableForDates($dados['check_in_date'], $dados['check_out_date'], $reserva?->id)) {
                throw ValidationException::withMessages([
                    'room_id' => __('O quarto :n já está reservado nestas datas. Escolha outro quarto ou outras datas.', [
                        'n' => $quarto->number,
                    ]),
                ]);
            }
        }

        foreach (['special_requests', 'internal_notes', 'payment_method'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = $dados[$campo] ?: null;
            }
        }

        return $dados;
    }

    private function linha(Reservation $r, bool $completa = false): array
    {
        $base = [
            'id' => $r->id,
            'numero' => $r->reservation_number,
            'codigo' => $r->confirmation_code,
            'client_id' => $r->client_id,
            // O nome de quem fica, pela ficha certa — a antiga está vazia.
            'hospede' => $r->nome_do_hospede,
            'telefone' => $r->client?->phone,
            'email' => $r->client?->email,
            'room_type_id' => $r->room_type_id,
            'tipo_de_quarto' => $r->roomType?->name,
            'room_id' => $r->room_id,
            'quarto' => $r->room?->number,
            'entrada' => $r->check_in_date?->toDateString(),
            'saida' => $r->check_out_date?->toDateString(),
            'noites' => (int) $r->nights,
            'adultos' => (int) $r->adults,
            'criancas' => (int) $r->children,
            'camas_extra' => (int) $r->extra_beds,
            'fonte' => $r->source,
            'fonte_rotulo' => __(Reservation::SOURCES[$r->source] ?? (string) $r->source),
            'estado' => $r->status,
            'estado_rotulo' => __(Reservation::STATUSES[$r->status] ?? (string) $r->status),
            'taxa' => (float) $r->room_rate,
            'total' => (float) $r->total,
            'pago' => (float) $r->paid_amount,
            // O saldo por receber: o acessor já existia e o folio mostrava-o,
            // mas nesta lista — onde se decide a quem cobrar — não aparecia.
            'por_receber' => (float) $r->balance_due,
            'estado_de_pagamento' => $r->payment_status,
            'estado_de_pagamento_rotulo' => __(Reservation::PAYMENT_STATUSES[$r->payment_status] ?? (string) $r->payment_status),
            'invoice_id' => $r->invoice_id,
            'factura' => $r->invoice?->invoice_number,
            /*
             * O QUE SE PODE FAZER A ESTA RESERVA, decidido no servidor pela
             * tabela de transições. O ecrã de sempre repetia as condições em
             * `@if`s — e quando a tabela mudou, os botões não mudaram com ela.
             */
            'pode' => [
                'confirmar' => $r->podeTransitarPara(Reservation::STATUS_CONFIRMED) && $r->status !== Reservation::STATUS_CONFIRMED,
                'entrada' => $r->podeTransitarPara(Reservation::STATUS_CHECKED_IN) && $r->status !== Reservation::STATUS_CHECKED_IN,
                'saida' => $r->status === Reservation::STATUS_CHECKED_IN,
                'cancelar' => $r->podeTransitarPara(Reservation::STATUS_CANCELLED),
                'nao_compareceu' => $r->podeTransitarPara(Reservation::STATUS_NO_SHOW),
                'editar' => in_array($r->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED], true),
                'receber' => $r->payment_status !== 'paid' && (float) $r->balance_due > 0,
            ],
        ];

        if (! $completa) {
            return $base;
        }

        return $base + [
            'subtotal' => (float) $r->subtotal,
            'desconto' => (float) $r->discount,
            'imposto' => (float) $r->tax,
            'extras' => (float) $r->extras_total,
            'pedidos' => $r->special_requests,
            'notas' => $r->internal_notes,
            'meio_de_pagamento' => $r->payment_method,
            'criada_por' => $r->createdBy?->name,
            'criada_em' => $r->created_at?->toIso8601String(),
            'cancelada_em' => $r->cancelled_at?->toIso8601String(),
            'motivo_do_cancelamento' => $r->cancellation_reason,
        ];
    }
}
