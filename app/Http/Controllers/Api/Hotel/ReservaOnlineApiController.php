<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use App\Services\Hotel\Tarifas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A PÁGINA PÚBLICA DE RESERVAS — a casa vista de fora.
 *
 * Não há sessão nem empresa activa: QUEM MANDA É O SLUG da morada. Todas as
 * consultas levam o `tenant_id` que o slug resolve, e nenhuma confia no escopo
 * global — que aqui não existe, e que, se existisse, podia ser de outra
 * empresa (alguém autenticado no seu próprio hotel a abrir a página de outro).
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • O PREÇO IGNORAVA AS TARIFAS: era `base_price × noites`, e a época alta, o
 *    fim-de-semana e os dias especiais não mexiam no que o hóspede pagava —
 *    justamente na única página onde o preço é uma promessa a um estranho.
 *  • O `deposit_percent` era calculado e nunca chegava a lado nenhum: a
 *    reserva nascia com `paid_amount` a zero e a casa não sabia que tinha
 *    pedido sinal.
 */
class ReservaOnlineApiController extends Controller
{
    public function __construct(private readonly Tarifas $tarifas) {}

    /**
     * A CASA, tal como ela se mostra.
     *
     * Isto responde sem sessão nenhuma: é a página que um hóspede abre de um
     * cartaz. Só sai daqui o que é para ser público.
     */
    public function casa(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);

        $tipos = RoomType::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where('is_active', true)
            ->orderBy('base_price')
            ->get();

        $destaques = ! empty($d->featured_rooms)
            ? $tipos->whereIn('id', $d->featured_rooms)
            : collect();

        return response()->json([
            'casa' => [
                'nome' => $d->hotel_name,
                'descricao' => $d->hotel_description,
                'morada' => $d->hotel_address,
                'cidade' => $d->hotel_city,
                'pais' => $d->hotel_country,
                'telefone' => $d->hotel_phone,
                'whatsapp' => $d->hotel_whatsapp,
                'email' => $d->hotel_email,
                'website' => $d->hotel_website,
                'estrelas' => (int) ($d->star_rating ?? 3),
                'logo' => $d->logo_url,
                'capa' => $d->cover_url,
                'cor' => $d->primary_color ?: '#3b82f6',
                'cor2' => $d->secondary_color ?: '#6366f1',
                'boas_vindas' => $d->welcome_message,
                'instagram' => $d->instagram,
                'facebook' => $d->facebook,
                'mapa' => $d->google_maps_url,
                'tripadvisor' => $d->tripadvisor_url,
                'booking' => $d->booking_com_url,
                'check_in' => $d->default_check_in_time?->format('H:i') ?? '14:00',
                'check_out' => $d->default_check_out_time?->format('H:i') ?? '12:00',
                'politica_de_reserva' => $d->booking_policies,
                'politica_de_cancelamento' => $d->cancellation_policies,
                'regras' => $d->house_rules,
                'comodidades' => collect($d->amenities_list ?? [])
                    ->map(fn ($c) => [
                        'valor' => $c,
                        'rotulo' => __(DefinicoesApiController::COMODIDADES[$c]['rotulo'] ?? $c),
                        'icone' => DefinicoesApiController::COMODIDADES[$c]['icone'] ?? 'fa-check',
                    ])->values(),
                'sinal' => (bool) $d->require_deposit,
                'sinal_percentagem' => (int) ($d->deposit_percent ?? 0),
                'antecedencia_minima_horas' => (int) ($d->min_advance_booking_hours ?? 0),
                'antecedencia_maxima_dias' => (int) ($d->max_advance_booking_days ?? 365),
                'cancelamento_horas' => (int) ($d->cancellation_hours ?? 0),
            ],
            'tipos' => $tipos->map(fn (RoomType $t) => $this->tipo($t))->values(),
            'destaques' => $destaques->pluck('id')->values(),
        ]);
    }

    /**
     * QUE QUARTOS HÁ PARA ESTAS DATAS, e por quanto.
     *
     * O preço sai das TARIFAS da casa — época, dia da semana, dia especial —
     * e não do preço base do tipo, que ignorava tudo isso.
     */
    public function disponibilidade(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);

        $dados = $request->validate([
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after:de'],
        ]);

        $this->garantirJanela($d, $dados['de']);

        $tipos = RoomType::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where('is_active', true)
            ->orderBy('base_price')
            ->get();

        return response()->json([
            'de' => Carbon::parse($dados['de'])->toDateString(),
            'ate' => Carbon::parse($dados['ate'])->toDateString(),
            'tipos' => $tipos->map(function (RoomType $t) use ($d, $dados) {
                $preco = $this->tarifas->precoDaEstada(
                    $d->tenant_id, $t->id, $dados['de'], $dados['ate'], (float) $t->base_price
                );

                return $this->tipo($t) + [
                    'livres' => $this->quantosLivres($d->tenant_id, $t->id, $dados['de'], $dados['ate']),
                    'noites' => $preco['noites'],
                    'preco_por_noite' => $preco['media'],
                    'preco_total' => $preco['total'],
                ];
            })->values(),
        ]);
    }

    /**
     * QUEM JÁ CÁ FICOU — entrar com o telefone.
     *
     * Não é uma sessão: é reconhecer quem já tem ficha, para não voltar a
     * escrever o nome. A senha é opcional porque a maior parte dos hóspedes
     * nunca definiu nenhuma, e obrigá-los a criar conta para reservar uma noite
     * é perder a reserva.
     */
    public function entrar(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);

        $dados = $request->validate([
            'telefone' => ['required', 'string', 'min:6', 'max:50'],
            'senha' => ['nullable', 'string', 'max:100'],
        ]);

        $numero = preg_replace('/[^0-9]/', '', $dados['telefone']);

        $cliente = Client::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where(fn ($q) => $q->where('phone', 'like', "%{$numero}%")->orWhere('mobile', 'like', "%{$numero}%"))
            ->first();

        if (! $cliente) {
            throw ValidationException::withMessages([
                'telefone' => __('Não encontrámos essa ficha. Crie uma conta, ou reserve sem conta.'),
            ]);
        }

        $guardada = $cliente->senha_de_reservas;

        if ($guardada) {
            if (empty($dados['senha'])) {
                throw ValidationException::withMessages(['senha' => __('Esta ficha tem senha.')]);
            }

            if (! Hash::check($dados['senha'], $guardada)) {
                throw ValidationException::withMessages(['senha' => __('Senha errada.')]);
            }
        }

        return response()->json(['hospede' => $this->hospede($cliente)]);
    }

    public function registar(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:255'],
            'telefone' => ['required', 'string', 'min:6', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'senha' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        $numero = preg_replace('/[^0-9]/', '', $dados['telefone']);

        $existe = Client::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where(fn ($q) => $q->where('phone', 'like', "%{$numero}%")->orWhere('mobile', 'like', "%{$numero}%"))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'telefone' => __('Já existe uma ficha com este telefone. Entre em vez de criar.'),
            ]);
        }

        $cliente = Client::withoutGlobalScopes()->create([
            'tenant_id' => $d->tenant_id,
            'name' => $dados['nome'],
            'phone' => $dados['telefone'],
            'mobile' => $dados['telefone'],
            'email' => $dados['email'] ?? null,
            // UM HÓSPEDE É UMA PESSOA: o tipo decide a retenção na factura, e
            // sem ele a coluna ficava a nulo.
            'type' => 'pessoa_fisica',
            'country' => \App\Support\Geografia::PAIS_PADRAO,
            'is_active' => true,
        ]);

        if (! empty($dados['senha'])) {
            $cliente->definirSenhaDeReservas($dados['senha']);
        }

        return response()->json(['hospede' => $this->hospede($cliente->fresh())], 201);
    }

    /** RESERVAR. */
    public function reservar(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);

        $dados = $request->validate([
            'tipo' => ['required', 'integer'],
            'de' => ['required', 'date', 'after_or_equal:today'],
            'ate' => ['required', 'date', 'after:de'],
            'adultos' => ['required', 'integer', 'min:1', 'max:10'],
            'criancas' => ['required', 'integer', 'min:0', 'max:10'],
            'hospede_id' => ['nullable', 'integer'],
            'nome' => ['required_without:hospede_id', 'nullable', 'string', 'min:2', 'max:255'],
            'telefone' => ['required_without:hospede_id', 'nullable', 'string', 'min:6', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->garantirJanela($d, $dados['de']);

        $tipo = RoomType::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where('is_active', true)
            ->find($dados['tipo']);

        abort_unless($tipo, 422, __('Esse quarto não existe nesta casa.'));

        /*
         * UM QUARTO REALMENTE LIVRE — e não um criado a martelo.
         *
         * O `status` do quarto é o estado FÍSICO de agora e não serve para
         * datas futuras: quem responde é a sobreposição de reservas.
         */
        $quarto = $this->primeiroLivre($d->tenant_id, $tipo->id, $dados['de'], $dados['ate']);

        if (! $quarto) {
            throw ValidationException::withMessages([
                'tipo' => __('Não há quartos deste tipo livres nessas datas. Escolha outras datas, ou outro quarto.'),
            ]);
        }

        $preco = $this->tarifas->precoDaEstada(
            $d->tenant_id, $tipo->id, $dados['de'], $dados['ate'], (float) $tipo->base_price
        );

        $reserva = DB::transaction(function () use ($d, $dados, $tipo, $quarto, $preco) {
            $cliente = $this->hospedeDaReserva($d->tenant_id, $dados);

            return Reservation::withoutGlobalScopes()->create([
                'tenant_id' => $d->tenant_id,
                'client_id' => $cliente->id,
                'room_id' => $quarto->id,
                'room_type_id' => $tipo->id,
                'check_in_date' => $dados['de'],
                'check_out_date' => $dados['ate'],
                'adults' => $dados['adultos'],
                'children' => $dados['criancas'],
                'extra_beds' => 0,
                'nights' => $preco['noites'],
                // A TAXA É A MÉDIA DAS NOITES: o modelo grava uma taxa só e
                // multiplica-a, e é assim que o total bate certo com a soma
                // das noites que o hóspede viu.
                'room_rate' => $preco['media'],
                'paid_amount' => 0,
                'status' => Reservation::STATUS_PENDING,
                'payment_status' => 'pending',
                // O ENUM não tem 'online': é 'website'.
                'source' => 'website',
                'special_requests' => $dados['notas'] ?? null,
            ]);
        });

        $reserva->refresh();

        /*
         * O SINAL, se a casa o exige.
         *
         * O ecrã de sempre calculava-o e não o dizia a ninguém: a reserva
         * nascia a zero e a casa não sabia que tinha pedido sinal. Aqui vai na
         * resposta, que é onde o hóspede o lê.
         */
        $sinal = $d->require_deposit
            ? round((float) $reserva->total * (int) ($d->deposit_percent ?? 0) / 100, 2)
            : 0.0;

        return response()->json([
            'reserva' => [
                'numero' => $reserva->reservation_number,
                'codigo' => $reserva->confirmation_code,
                'tipo' => $tipo->name,
                'entrada' => $reserva->check_in_date?->toDateString(),
                'saida' => $reserva->check_out_date?->toDateString(),
                'noites' => (int) $reserva->nights,
                'adultos' => (int) $reserva->adults,
                'criancas' => (int) $reserva->children,
                'preco_por_noite' => (float) $reserva->room_rate,
                'total' => (float) $reserva->total,
                'sinal' => $sinal,
                'sinal_percentagem' => $sinal > 0 ? (int) $d->deposit_percent : 0,
            ],
        ], 201);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    /**
     * QUEM MANDA É O SLUG.
     *
     * Numa página pública não há empresa activa; e se houver — alguém
     * autenticado no seu hotel a abrir a página de outro — é a errada. Todas as
     * consultas desta classe levam o `tenant_id` que sai daqui.
     */
    private function definicoes(string $slug): HotelSettings
    {
        $d = HotelSettings::findBySlug($slug);

        // Uma empresa desactivada, ou sem o módulo, não recebe reservas.
        abort_unless($d && \App\Support\CasaPublica::aberta((int) $d->tenant_id, 'hotel'), 404, __('Hotel não encontrado.'));
        abort_unless($d->online_booking_enabled, 403, __('Esta casa não aceita reservas por aqui.'));

        return $d;
    }

    /** A janela em que a casa aceita reservas — a antecedência mínima e máxima. */
    private function garantirJanela(HotelSettings $d, string $de): void
    {
        $entrada = Carbon::parse($de)->startOfDay();

        $minimo = now()->addHours((int) ($d->min_advance_booking_hours ?? 0));

        if ($entrada->copy()->endOfDay()->lt($minimo)) {
            throw ValidationException::withMessages([
                'de' => __('Esta casa pede pelo menos :h hora(s) de antecedência.', [
                    'h' => (int) ($d->min_advance_booking_hours ?? 0),
                ]),
            ]);
        }

        $maximo = today()->addDays((int) ($d->max_advance_booking_days ?? 365));

        if ($entrada->gt($maximo)) {
            throw ValidationException::withMessages([
                'de' => __('Esta casa aceita reservas até :d dia(s) de antecedência.', [
                    'd' => (int) ($d->max_advance_booking_days ?? 365),
                ]),
            ]);
        }
    }

    private function quartosDoTipo(int $tenantId, int $tipoId)
    {
        return Room::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('room_type_id', $tipoId)
            ->where('is_active', true)
            ->where('status', '!=', Room::STATUS_MAINTENANCE)
            ->orderBy('number')
            ->get();
    }

    private function quantosLivres(int $tenantId, int $tipoId, string $de, string $ate): int
    {
        return $this->quartosDoTipo($tenantId, $tipoId)
            ->filter(fn (Room $q) => $q->isAvailableForDates($de, $ate))
            ->count();
    }

    private function primeiroLivre(int $tenantId, int $tipoId, string $de, string $ate): ?Room
    {
        return $this->quartosDoTipo($tenantId, $tipoId)
            ->first(fn (Room $q) => $q->isAvailableForDates($de, $ate));
    }

    private function hospedeDaReserva(int $tenantId, array $dados): Client
    {
        if (! empty($dados['hospede_id'])) {
            $cliente = Client::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->find($dados['hospede_id']);

            abort_unless($cliente, 422, __('Essa ficha não é desta casa.'));

            if (! empty($dados['email'] ?? null) && $dados['email'] !== $cliente->email) {
                $cliente->update(['email' => $dados['email']]);
            }

            return $cliente;
        }

        $cliente = Client::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'phone' => $dados['telefone']],
            [
                'name' => $dados['nome'],
                'email' => $dados['email'] ?? null,
                'type' => 'pessoa_fisica',
                'country' => \App\Support\Geografia::PAIS_PADRAO,
                'is_active' => true,
            ]
        );

        $cliente->update([
            'name' => $dados['nome'],
            'email' => ($dados['email'] ?? null) ?: $cliente->email,
        ]);

        return $cliente;
    }

    private function tipo(RoomType $t): array
    {
        return [
            'id' => $t->id,
            'nome' => $t->name,
            'descricao' => $t->description,
            'preco_base' => (float) $t->base_price,
            'capacidade' => (int) $t->capacity,
            'camas_extra' => (int) ($t->extra_bed_capacity ?? 0),
            'comodidades' => collect($t->amenities ?? [])
                ->map(fn ($c) => [
                    'valor' => $c,
                    'rotulo' => __(DefinicoesApiController::COMODIDADES[$c]['rotulo'] ?? $c),
                    'icone' => DefinicoesApiController::COMODIDADES[$c]['icone'] ?? 'fa-check',
                ])->values(),
            'fotos' => collect($t->images ?? [])->map(fn ($f) => \Storage::url($f))->values(),
        ];
    }

    private function hospede(Client $c): array
    {
        return [
            'id' => $c->id,
            'nome' => $c->name,
            'telefone' => $c->phone ?: $c->mobile,
            'email' => $c->email,
        ];
    }
}
