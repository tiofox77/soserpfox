<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * AS DEFINIÇÕES DO HOTEL — a casa como ela se apresenta.
 *
 * O nome, a morada, as estrelas, as horas de entrada e de saída, as regras de
 * reserva, as políticas, as comodidades e a página pública de reservas. É o
 * ecrã que decide como o hotel aparece a quem o procura de fora.
 *
 * VER NÃO É ALTERAR. O ecrã de sempre pedia `hotel.settings.view` na morada e
 * mais nada: chegar lá era poder mudar o preço do check-in tardio, a política
 * de cancelamento e o endereço público da casa. As duas permissões existem —
 * `view` e `edit` — e passam a valer as duas.
 */
class DefinicoesApiController extends Controller
{
    /**
     * AS COMODIDADES QUE A CASA PODE ANUNCIAR.
     *
     * Uma lista só, aqui — no ecrã de sempre estava escrita dentro do
     * `render()` do componente, e a página pública tinha a sua própria cópia.
     */
    public const COMODIDADES = [
        'wifi' => ['rotulo' => 'Wi-Fi Gratuito', 'icone' => 'fa-wifi'],
        'parking' => ['rotulo' => 'Estacionamento', 'icone' => 'fa-square-parking'],
        'pool' => ['rotulo' => 'Piscina', 'icone' => 'fa-water-ladder'],
        'gym' => ['rotulo' => 'Ginásio', 'icone' => 'fa-dumbbell'],
        'restaurant' => ['rotulo' => 'Restaurante', 'icone' => 'fa-utensils'],
        'bar' => ['rotulo' => 'Bar', 'icone' => 'fa-martini-glass'],
        'spa' => ['rotulo' => 'Spa', 'icone' => 'fa-spa'],
        'room_service' => ['rotulo' => 'Serviço de Quarto', 'icone' => 'fa-concierge-bell'],
        'laundry' => ['rotulo' => 'Lavandaria', 'icone' => 'fa-shirt'],
        'airport_shuttle' => ['rotulo' => 'Transfer do Aeroporto', 'icone' => 'fa-van-shuttle'],
        'ac' => ['rotulo' => 'Ar Condicionado', 'icone' => 'fa-snowflake'],
        'tv' => ['rotulo' => 'TV por Cabo', 'icone' => 'fa-tv'],
        'minibar' => ['rotulo' => 'Minibar', 'icone' => 'fa-wine-bottle'],
        'safe' => ['rotulo' => 'Cofre', 'icone' => 'fa-lock'],
        'breakfast' => ['rotulo' => 'Pequeno-almoço', 'icone' => 'fa-mug-saucer'],
        'pets' => ['rotulo' => 'Aceita Animais', 'icone' => 'fa-paw'],
        'conference' => ['rotulo' => 'Sala de Conferências', 'icone' => 'fa-users'],
        'business' => ['rotulo' => 'Centro de Negócios', 'icone' => 'fa-briefcase'],
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.view');

        $d = HotelSettings::getForTenant();

        return response()->json([
            'definicoes' => $this->linha($d),
            'comodidades' => collect(self::COMODIDADES)
                ->map(fn ($c, $v) => ['valor' => (string) $v, 'rotulo' => __($c['rotulo']), 'icone' => $c['icone']])
                ->values(),
            'tipos_de_quarto' => RoomType::where('tenant_id', activeTenantId())->where('is_active', true)
                ->withCount('rooms')->orderBy('name')->get()
                ->map(fn (RoomType $t) => [
                    'id' => $t->id,
                    'nome' => $t->name,
                    'preco' => (float) $t->base_price,
                    'quartos' => (int) $t->rooms_count,
                ])->values(),
            'permissoes' => [
                'pode_editar' => (bool) $request->user()?->can('hotel.settings.edit'),
            ],
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate([
            'hotel_name' => ['required', 'string', 'min:2', 'max:255'],
            'hotel_description' => ['nullable', 'string', 'max:2000'],
            'hotel_address' => ['nullable', 'string', 'max:255'],
            'hotel_city' => ['nullable', 'string', 'max:100'],
            'hotel_country' => ['nullable', 'string', 'max:100'],
            'hotel_phone' => ['nullable', 'string', 'max:50'],
            'hotel_whatsapp' => ['nullable', 'string', 'max:50'],
            'hotel_email' => ['nullable', 'email', 'max:255'],
            'hotel_website' => ['nullable', 'string', 'max:255'],
            'star_rating' => ['required', 'integer', 'min:1', 'max:5'],

            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'google_maps_url' => ['nullable', 'string', 'max:500'],
            'tripadvisor_url' => ['nullable', 'string', 'max:500'],
            'booking_com_url' => ['nullable', 'string', 'max:500'],

            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],

            'default_check_in_time' => ['required', 'date_format:H:i'],
            'default_check_out_time' => ['required', 'date_format:H:i'],
            'early_check_in_available' => ['nullable', 'boolean'],
            'late_check_out_available' => ['nullable', 'boolean'],
            'early_check_in_fee' => ['nullable', 'numeric', 'min:0'],
            'late_check_out_fee' => ['nullable', 'numeric', 'min:0'],

            'min_advance_booking_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'min_advance_booking_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_advance_booking_days' => ['required', 'integer', 'min:1', 'max:1095'],
            'cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'online_booking_enabled' => ['nullable', 'boolean'],
            'require_deposit' => ['nullable', 'boolean'],
            'deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],

            'booking_policies' => ['nullable', 'string', 'max:5000'],
            'cancellation_policies' => ['nullable', 'string', 'max:5000'],
            'house_rules' => ['nullable', 'string', 'max:5000'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],

            'amenities_list' => ['nullable', 'array'],
            'amenities_list.*' => ['string', 'max:50'],
            'featured_rooms' => ['nullable', 'array'],
            'featured_rooms.*' => ['integer'],

            'overbooking_enabled' => ['nullable', 'boolean'],
            'overbooking_percent' => ['nullable', 'integer', 'min:0', 'max:100'],

            'loyalty_enabled' => ['nullable', 'boolean'],
            'loyalty_points_per_kz' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'loyalty_tier_silver' => ['nullable', 'integer', 'min:0'],
            'loyalty_tier_gold' => ['nullable', 'integer', 'min:0'],
            'loyalty_tier_platinum' => ['nullable', 'integer', 'min:0'],

            'notify_reservation_confirmed' => ['nullable', 'boolean'],
            'notify_pre_arrival' => ['nullable', 'boolean'],
            'notify_post_stay' => ['nullable', 'boolean'],
        ]);

        $tenantId = activeTenantId();

        /*
         * OS QUARTOS EM DESTAQUE TÊM DE SER DESTA CASA.
         *
         * A lista vem do browser e vai para a PÁGINA PÚBLICA: um id de outro
         * hotel punha o quarto do concorrente na montra desta casa.
         */
        if (! empty($dados['featured_rooms'])) {
            $meus = RoomType::where('tenant_id', $tenantId)
                ->whereIn('id', $dados['featured_rooms'])->pluck('id')->all();

            $dados['featured_rooms'] = array_values(array_intersect($dados['featured_rooms'], $meus));
        }

        // As comodidades que a casa não conhece não entram: a página pública
        // desenha-as por esta lista, e uma chave inventada não tinha ícone.
        if (! empty($dados['amenities_list'])) {
            $dados['amenities_list'] = array_values(array_intersect(
                $dados['amenities_list'], array_keys(self::COMODIDADES)
            ));
        }

        $d = HotelSettings::getForTenant();
        $d->fill($dados);

        // O endereço público nasce do nome, e só uma vez: mudá-lo a cada
        // gravação partia as ligações que já andam por aí.
        if (empty($d->booking_slug)) {
            $d->booking_slug = HotelSettings::generateUniqueSlug($dados['hotel_name']);
        }

        $d->save();

        return response()->json([
            'definicoes' => $this->linha($d->fresh()),
            'message' => __('Definições guardadas.'),
        ]);
    }

    /** O LOGÓTIPO E A CAPA vão à parte, em multipart — não cabem em JSON. */
    public function imagem(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate([
            'qual' => ['required', 'in:logo,capa'],
            'ficheiro' => ['required', 'image', 'max:5120'],
        ]);

        $d = HotelSettings::getForTenant();

        $pasta = $dados['qual'] === 'logo' ? 'hotel/logos/' : 'hotel/covers/';
        $caminho = $request->file('ficheiro')->store($pasta . activeTenantId(), 'public');

        // A nova substitui a que lá estava: é um logótipo, não um histórico.
        $antiga = $dados['qual'] === 'logo' ? $d->logo : $d->cover_image;

        if ($antiga && Storage::disk('public')->exists($antiga)) {
            Storage::disk('public')->delete($antiga);
        }

        $d->{$dados['qual'] === 'logo' ? 'logo' : 'cover_image'} = $caminho;
        $d->save();

        return response()->json([
            'definicoes' => $this->linha($d->fresh()),
            'message' => __('Imagem guardada.'),
        ]);
    }

    public function apagarImagem(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $dados = $request->validate(['qual' => ['required', 'in:logo,capa']]);

        $d = HotelSettings::getForTenant();
        $coluna = $dados['qual'] === 'logo' ? 'logo' : 'cover_image';

        if ($d->{$coluna} && Storage::disk('public')->exists($d->{$coluna})) {
            Storage::disk('public')->delete($d->{$coluna});
        }

        $d->{$coluna} = null;
        $d->save();

        return response()->json([
            'definicoes' => $this->linha($d->fresh()),
            'message' => __('Imagem removida.'),
        ]);
    }

    /**
     * UM ENDEREÇO PÚBLICO NOVO — e o que isso custa.
     *
     * Regenerar o slug PARTE todas as ligações que já foram partilhadas: o
     * cartaz, o Instagram, o WhatsApp. É por isso que não acontece sozinho ao
     * gravar, e que o ecrã pergunta antes.
     */
    public function novoEndereco(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.settings.edit');

        $d = HotelSettings::getForTenant();
        $d->regenerateSlug();

        return response()->json([
            'definicoes' => $this->linha($d->fresh()),
            'message' => __('Endereço de reservas trocado. As ligações antigas deixaram de funcionar.'),
        ]);
    }

    private function linha(HotelSettings $d): array
    {
        return [
            'hotel_name' => $d->hotel_name ?? '',
            'hotel_description' => $d->hotel_description ?? '',
            'hotel_address' => $d->hotel_address ?? '',
            'hotel_city' => $d->hotel_city ?? '',
            'hotel_country' => $d->hotel_country ?? 'Angola',
            'hotel_phone' => $d->hotel_phone ?? '',
            'hotel_whatsapp' => $d->hotel_whatsapp ?? '',
            'hotel_email' => $d->hotel_email ?? '',
            'hotel_website' => $d->hotel_website ?? '',
            'star_rating' => (int) ($d->star_rating ?? 3),

            'instagram' => $d->instagram ?? '',
            'facebook' => $d->facebook ?? '',
            'google_maps_url' => $d->google_maps_url ?? '',
            'tripadvisor_url' => $d->tripadvisor_url ?? '',
            'booking_com_url' => $d->booking_com_url ?? '',

            'primary_color' => $d->primary_color ?: '#3b82f6',
            'secondary_color' => $d->secondary_color ?: '#6366f1',
            'logo' => $d->logo_url,
            'capa' => $d->cover_url,

            'default_check_in_time' => $d->default_check_in_time?->format('H:i') ?? '14:00',
            'default_check_out_time' => $d->default_check_out_time?->format('H:i') ?? '12:00',
            'early_check_in_available' => (bool) ($d->early_check_in_available ?? true),
            'late_check_out_available' => (bool) ($d->late_check_out_available ?? true),
            'early_check_in_fee' => (float) ($d->early_check_in_fee ?? 0),
            'late_check_out_fee' => (float) ($d->late_check_out_fee ?? 0),

            'min_advance_booking_hours' => (int) ($d->min_advance_booking_hours ?? 24),
            'min_advance_booking_days' => (int) ($d->min_advance_booking_days ?? 1),
            'max_advance_booking_days' => (int) ($d->max_advance_booking_days ?? 365),
            'cancellation_hours' => (int) ($d->cancellation_hours ?? 48),
            'online_booking_enabled' => (bool) ($d->online_booking_enabled ?? true),
            'require_deposit' => (bool) ($d->require_deposit ?? false),
            'deposit_percent' => (int) ($d->deposit_percent ?? 30),

            'booking_policies' => $d->booking_policies ?? '',
            'cancellation_policies' => $d->cancellation_policies ?? '',
            'house_rules' => $d->house_rules ?? '',

            'booking_slug' => $d->booking_slug ?? '',
            'booking_url' => $d->booking_url ?? '',
            'meta_title' => $d->meta_title ?? '',
            'meta_description' => $d->meta_description ?? '',
            'welcome_message' => $d->welcome_message ?? '',

            'amenities_list' => array_values($d->amenities_list ?? []),
            'featured_rooms' => array_values($d->featured_rooms ?? []),

            'overbooking_enabled' => (bool) ($d->overbooking_enabled ?? false),
            'overbooking_percent' => (int) ($d->overbooking_percent ?? 10),

            'loyalty_enabled' => (bool) ($d->loyalty_enabled ?? true),
            'loyalty_points_per_kz' => (float) ($d->loyalty_points_per_kz ?? 0.01),
            'loyalty_tier_silver' => (int) ($d->loyalty_tier_silver ?? 500),
            'loyalty_tier_gold' => (int) ($d->loyalty_tier_gold ?? 2000),
            'loyalty_tier_platinum' => (int) ($d->loyalty_tier_platinum ?? 5000),

            'notify_reservation_confirmed' => (bool) ($d->notify_reservation_confirmed ?? true),
            'notify_pre_arrival' => (bool) ($d->notify_pre_arrival ?? true),
            'notify_post_stay' => (bool) ($d->notify_post_stay ?? true),
        ];
    }
}
