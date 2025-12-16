<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Room;

#[Layout('layouts.app')]
#[Title('Configurações - Hotel')]
class HotelSettingsManagement extends Component
{
    use WithFileUploads;

    public $activeTab = 'general';

    // Dados gerais
    public $hotel_name = '';
    public $hotel_description = '';
    public $hotel_address = '';
    public $hotel_city = '';
    public $hotel_country = 'Angola';
    public $hotel_phone = '';
    public $hotel_whatsapp = '';
    public $hotel_email = '';
    public $hotel_website = '';
    public $star_rating = 3;

    // Redes sociais
    public $instagram = '';
    public $facebook = '';
    public $google_maps_url = '';
    public $tripadvisor_url = '';
    public $booking_com_url = '';

    // Branding
    public $primary_color = '#3b82f6';
    public $secondary_color = '#6366f1';
    public $newLogo;
    public $newCoverImage;
    public $currentLogo;
    public $currentCoverImage;

    // Horários
    public $check_in_time = '14:00';
    public $check_out_time = '12:00';
    public $early_check_in_available = true;
    public $late_check_out_available = true;
    public $early_check_in_fee = 0;
    public $late_check_out_fee = 0;

    // Configurações de reserva
    public $min_advance_booking_hours = 24;
    public $min_advance_booking_days = 1;
    public $max_advance_booking_days = 365;
    public $cancellation_hours = 48;
    public $online_booking_enabled = true;
    public $require_deposit = false;
    public $deposit_percent = 30;

    // Políticas
    public $booking_policies = '';
    public $cancellation_policies = '';
    public $house_rules = '';

    // Landing Page
    public $booking_slug = '';
    public $bookingUrl = '';
    public $meta_title = '';
    public $meta_description = '';
    public $welcome_message = '';

    // Amenidades e quartos
    public $amenities = [];
    public $featured_rooms = [];

    protected function rules()
    {
        return [
            'hotel_name' => 'required|string|min:2|max:255',
            'hotel_email' => 'nullable|email|max:255',
            'hotel_phone' => 'nullable|string|max:50',
            'star_rating' => 'required|integer|min:1|max:5',
            'check_in_time' => 'required|string',
            'check_out_time' => 'required|string',
            'min_advance_booking_hours' => 'required|integer|min:0',
            'max_advance_booking_days' => 'required|integer|min:1',
            'newLogo' => 'nullable|image|max:2048',
            'newCoverImage' => 'nullable|image|max:5120',
        ];
    }

    public function mount()
    {
        $settings = HotelSettings::getForTenant();
        
        $this->hotel_name = $settings->hotel_name ?? '';
        $this->hotel_description = $settings->hotel_description ?? '';
        $this->hotel_address = $settings->hotel_address ?? '';
        $this->hotel_city = $settings->hotel_city ?? '';
        $this->hotel_country = $settings->hotel_country ?? 'Angola';
        $this->hotel_phone = $settings->hotel_phone ?? '';
        $this->hotel_whatsapp = $settings->hotel_whatsapp ?? '';
        $this->hotel_email = $settings->hotel_email ?? '';
        $this->hotel_website = $settings->hotel_website ?? '';
        $this->star_rating = $settings->star_rating ?? 3;
        $this->instagram = $settings->instagram ?? '';
        $this->facebook = $settings->facebook ?? '';
        $this->google_maps_url = $settings->google_maps_url ?? '';
        $this->tripadvisor_url = $settings->tripadvisor_url ?? '';
        $this->booking_com_url = $settings->booking_com_url ?? '';
        $this->primary_color = $settings->primary_color ?? '#3b82f6';
        $this->secondary_color = $settings->secondary_color ?? '#6366f1';
        $this->currentLogo = $settings->logo_url;
        $this->currentCoverImage = $settings->cover_url;
        $this->check_in_time = $settings->default_check_in_time ? $settings->default_check_in_time->format('H:i') : '14:00';
        $this->check_out_time = $settings->default_check_out_time ? $settings->default_check_out_time->format('H:i') : '12:00';
        $this->early_check_in_available = $settings->early_check_in_available ?? true;
        $this->late_check_out_available = $settings->late_check_out_available ?? true;
        $this->early_check_in_fee = $settings->early_check_in_fee ?? 0;
        $this->late_check_out_fee = $settings->late_check_out_fee ?? 0;
        $this->min_advance_booking_hours = $settings->min_advance_booking_hours ?? 24;
        $this->min_advance_booking_days = $settings->min_advance_booking_days ?? 1;
        $this->max_advance_booking_days = $settings->max_advance_booking_days ?? 365;
        $this->cancellation_hours = $settings->cancellation_hours ?? 48;
        $this->online_booking_enabled = $settings->online_booking_enabled ?? true;
        $this->require_deposit = $settings->require_deposit ?? false;
        $this->deposit_percent = $settings->deposit_percent ?? 30;
        $this->booking_policies = $settings->booking_policies ?? '';
        $this->cancellation_policies = $settings->cancellation_policies ?? '';
        $this->house_rules = $settings->house_rules ?? '';
        $this->booking_slug = $settings->booking_slug ?? '';
        $this->bookingUrl = $settings->booking_url ?? '';
        $this->meta_title = $settings->meta_title ?? '';
        $this->meta_description = $settings->meta_description ?? '';
        $this->welcome_message = $settings->welcome_message ?? '';
        $this->amenities = $settings->amenities_list ?? [];
        $this->featured_rooms = $settings->featured_rooms ?? [];
        
        // Atualizar slug e URL (já gerado no modelo)
        $this->booking_slug = $settings->booking_slug;
        $this->bookingUrl = $settings->booking_url;
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function save()
    {
        $this->validate();

        $settings = HotelSettings::getForTenant();

        // Upload logo
        if ($this->newLogo) {
            $logoPath = $this->newLogo->store('hotel/logos/' . activeTenantId(), 'public');
            $settings->logo = $logoPath;
            $this->currentLogo = \Storage::url($logoPath);
        }

        // Upload cover
        if ($this->newCoverImage) {
            $coverPath = $this->newCoverImage->store('hotel/covers/' . activeTenantId(), 'public');
            $settings->cover_image = $coverPath;
            $this->currentCoverImage = \Storage::url($coverPath);
        }

        $settings->fill([
            'hotel_name' => $this->hotel_name,
            'hotel_description' => $this->hotel_description,
            'hotel_address' => $this->hotel_address,
            'hotel_city' => $this->hotel_city,
            'hotel_country' => $this->hotel_country,
            'hotel_phone' => $this->hotel_phone,
            'hotel_whatsapp' => $this->hotel_whatsapp,
            'hotel_email' => $this->hotel_email,
            'hotel_website' => $this->hotel_website,
            'star_rating' => $this->star_rating,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'google_maps_url' => $this->google_maps_url,
            'tripadvisor_url' => $this->tripadvisor_url,
            'booking_com_url' => $this->booking_com_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'default_check_in_time' => $this->check_in_time,
            'default_check_out_time' => $this->check_out_time,
            'early_check_in_available' => $this->early_check_in_available,
            'late_check_out_available' => $this->late_check_out_available,
            'early_check_in_fee' => $this->early_check_in_fee,
            'late_check_out_fee' => $this->late_check_out_fee,
            'min_advance_booking_hours' => $this->min_advance_booking_hours,
            'min_advance_booking_days' => $this->min_advance_booking_days,
            'max_advance_booking_days' => $this->max_advance_booking_days,
            'cancellation_hours' => $this->cancellation_hours,
            'online_booking_enabled' => $this->online_booking_enabled,
            'require_deposit' => $this->require_deposit,
            'deposit_percent' => $this->deposit_percent,
            'booking_policies' => $this->booking_policies,
            'cancellation_policies' => $this->cancellation_policies,
            'house_rules' => $this->house_rules,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'welcome_message' => $this->welcome_message,
            'amenities_list' => $this->amenities,
            'featured_rooms' => $this->featured_rooms,
        ]);

        // Gerar slug se não existir
        if (empty($settings->booking_slug)) {
            $settings->booking_slug = HotelSettings::generateUniqueSlug($this->hotel_name);
        }

        $settings->save();

        $this->booking_slug = $settings->booking_slug;
        $this->bookingUrl = $settings->booking_url;

        $this->newLogo = null;
        $this->newCoverImage = null;

        $this->dispatch('notify', type: 'success', message: 'Configurações guardadas com sucesso!');
    }

    public function regenerateSlug()
    {
        $settings = HotelSettings::getForTenant();
        $this->booking_slug = $settings->regenerateSlug();
        $this->bookingUrl = $settings->booking_url;
        
        $this->dispatch('notify', type: 'success', message: 'Link de reserva regenerado!');
    }

    public function copyBookingUrl()
    {
        $this->dispatch('copyToClipboard', url: $this->bookingUrl);
        $this->dispatch('notify', type: 'success', message: 'Link copiado!');
    }

    public function toggleFeaturedRoom($roomTypeId)
    {
        if (in_array($roomTypeId, $this->featured_rooms)) {
            $this->featured_rooms = array_values(array_diff($this->featured_rooms, [$roomTypeId]));
        } else {
            $this->featured_rooms[] = $roomTypeId;
        }
    }

    public function toggleAmenity($amenity)
    {
        if (in_array($amenity, $this->amenities)) {
            $this->amenities = array_values(array_diff($this->amenities, [$amenity]));
        } else {
            $this->amenities[] = $amenity;
        }
    }

    public function render()
    {
        $roomTypes = RoomType::forTenant()->active()->with('rooms')->orderBy('name')->get();
        $rooms = Room::forTenant()->active()->with('roomType')->orderBy('number')->get();
        
        $availableAmenities = [
            'wifi' => ['icon' => 'wifi', 'label' => 'Wi-Fi Gratuito'],
            'parking' => ['icon' => 'parking', 'label' => 'Estacionamento'],
            'pool' => ['icon' => 'swimming-pool', 'label' => 'Piscina'],
            'gym' => ['icon' => 'dumbbell', 'label' => 'Ginásio'],
            'restaurant' => ['icon' => 'utensils', 'label' => 'Restaurante'],
            'bar' => ['icon' => 'cocktail', 'label' => 'Bar'],
            'spa' => ['icon' => 'spa', 'label' => 'Spa'],
            'room_service' => ['icon' => 'concierge-bell', 'label' => 'Serviço de Quarto'],
            'laundry' => ['icon' => 'tshirt', 'label' => 'Lavandaria'],
            'airport_shuttle' => ['icon' => 'shuttle-van', 'label' => 'Transfer Aeroporto'],
            'ac' => ['icon' => 'snowflake', 'label' => 'Ar Condicionado'],
            'tv' => ['icon' => 'tv', 'label' => 'TV a Cabo'],
            'minibar' => ['icon' => 'wine-bottle', 'label' => 'Minibar'],
            'safe' => ['icon' => 'lock', 'label' => 'Cofre'],
            'breakfast' => ['icon' => 'coffee', 'label' => 'Pequeno Almoço'],
            'pets' => ['icon' => 'paw', 'label' => 'Aceita Animais'],
            'conference' => ['icon' => 'users', 'label' => 'Sala de Conferências'],
            'business' => ['icon' => 'briefcase', 'label' => 'Centro de Negócios'],
        ];

        return view('livewire.hotel.settings.settings', compact('roomTypes', 'rooms', 'availableAmenities'));
    }
}
