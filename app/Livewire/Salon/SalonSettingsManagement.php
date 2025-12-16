<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\Service;

#[Layout('layouts.app')]
#[Title('Configurações - Salão de Beleza')]
class SalonSettingsManagement extends Component
{
    use WithFileUploads;

    // Tab ativa
    public $activeTab = 'general';

    // Dados gerais
    public $salon_name = '';
    public $salon_description = '';
    public $salon_address = '';
    public $salon_phone = '';
    public $salon_whatsapp = '';
    public $salon_email = '';

    // Redes sociais
    public $salon_instagram = '';
    public $salon_facebook = '';
    public $salon_tiktok = '';
    public $salon_website = '';
    public $salon_google_maps_url = '';

    // Branding
    public $primary_color = '#ec4899';
    public $secondary_color = '#8b5cf6';
    public $newLogo;
    public $newCoverImage;
    public $currentLogo;
    public $currentCoverImage;

    // Horários
    public $opening_time = '09:00';
    public $closing_time = '19:00';
    public $working_days = [1, 2, 3, 4, 5, 6];

    // Configurações de agendamento
    public $slot_interval = 30;
    public $min_advance_booking_hours = 2;
    public $max_advance_booking_days = 30;
    public $cancellation_hours = 24;
    public $reminder_hours = 24;
    public $online_booking_enabled = true;
    public $require_confirmation = true;

    // Taxas e depósitos
    public $no_show_fee_percent = 0;
    public $require_deposit = false;
    public $deposit_percent = 0;
    public $allow_online_payment = false;

    // Políticas
    public $booking_terms = '';
    public $cancellation_policy = '';

    // Booking link
    public $booking_slug = '';
    public $bookingUrl = '';

    // SEO
    public $meta_title = '';
    public $meta_description = '';

    // Mensagens
    public $welcome_message = '';
    public $confirmation_message = '';

    // Serviços em destaque
    public $featured_services = [];

    protected function rules()
    {
        return [
            'salon_name' => 'required|string|min:2|max:255',
            'salon_email' => 'nullable|email|max:255',
            'salon_phone' => 'nullable|string|max:50',
            'salon_whatsapp' => 'nullable|string|max:50',
            'opening_time' => 'required|string',
            'closing_time' => 'required|string',
            'slot_interval' => 'required|integer|min:5|max:120',
            'min_advance_booking_hours' => 'required|integer|min:0',
            'max_advance_booking_days' => 'required|integer|min:1',
            'newLogo' => 'nullable|image|max:2048',
            'newCoverImage' => 'nullable|image|max:5120',
        ];
    }

    public function mount()
    {
        $settings = SalonSettings::getForTenant();
        
        $this->salon_name = $settings->salon_name ?? '';
        $this->salon_description = $settings->salon_description ?? '';
        $this->salon_address = $settings->salon_address ?? '';
        $this->salon_phone = $settings->salon_phone ?? '';
        $this->salon_whatsapp = $settings->salon_whatsapp ?? '';
        $this->salon_email = $settings->salon_email ?? '';
        $this->salon_instagram = $settings->salon_instagram ?? '';
        $this->salon_facebook = $settings->salon_facebook ?? '';
        $this->salon_tiktok = $settings->salon_tiktok ?? '';
        $this->salon_website = $settings->salon_website ?? '';
        $this->salon_google_maps_url = $settings->salon_google_maps_url ?? '';
        $this->primary_color = $settings->primary_color ?? '#ec4899';
        $this->secondary_color = $settings->secondary_color ?? '#8b5cf6';
        $this->currentLogo = $settings->logo_url;
        $this->currentCoverImage = $settings->cover_url;
        $this->opening_time = $settings->opening_time ? $settings->opening_time->format('H:i') : '09:00';
        $this->closing_time = $settings->closing_time ? $settings->closing_time->format('H:i') : '19:00';
        $this->working_days = $settings->working_days ?? [1, 2, 3, 4, 5, 6];
        $this->slot_interval = $settings->slot_interval ?? 30;
        $this->min_advance_booking_hours = $settings->min_advance_booking_hours ?? 2;
        $this->max_advance_booking_days = $settings->max_advance_booking_days ?? 30;
        $this->cancellation_hours = $settings->cancellation_hours ?? 24;
        $this->reminder_hours = $settings->reminder_hours ?? 24;
        $this->online_booking_enabled = $settings->online_booking_enabled ?? true;
        $this->require_confirmation = $settings->require_confirmation ?? true;
        $this->no_show_fee_percent = $settings->no_show_fee_percent ?? 0;
        $this->require_deposit = $settings->require_deposit ?? false;
        $this->deposit_percent = $settings->deposit_percent ?? 0;
        $this->allow_online_payment = $settings->allow_online_payment ?? false;
        $this->booking_terms = $settings->booking_terms ?? '';
        $this->cancellation_policy = $settings->cancellation_policy ?? '';
        $this->booking_slug = $settings->booking_slug ?? '';
        $this->bookingUrl = $settings->booking_url ?? '';
        $this->meta_title = $settings->meta_title ?? '';
        $this->meta_description = $settings->meta_description ?? '';
        $this->welcome_message = $settings->welcome_message ?? '';
        $this->confirmation_message = $settings->confirmation_message ?? '';
        $this->featured_services = $settings->featured_services ?? [];
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function save()
    {
        $this->validate();

        $settings = SalonSettings::getForTenant();

        // Upload logo
        if ($this->newLogo) {
            $logoPath = $this->newLogo->store('salon/logos/' . activeTenantId(), 'public');
            $settings->logo = $logoPath;
            $this->currentLogo = \Storage::url($logoPath);
        }

        // Upload cover
        if ($this->newCoverImage) {
            $coverPath = $this->newCoverImage->store('salon/covers/' . activeTenantId(), 'public');
            $settings->cover_image = $coverPath;
            $this->currentCoverImage = \Storage::url($coverPath);
        }

        $settings->fill([
            'salon_name' => $this->salon_name,
            'salon_description' => $this->salon_description,
            'salon_address' => $this->salon_address,
            'salon_phone' => $this->salon_phone,
            'salon_whatsapp' => $this->salon_whatsapp,
            'salon_email' => $this->salon_email,
            'salon_instagram' => $this->salon_instagram,
            'salon_facebook' => $this->salon_facebook,
            'salon_tiktok' => $this->salon_tiktok,
            'salon_website' => $this->salon_website,
            'salon_google_maps_url' => $this->salon_google_maps_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'working_days' => $this->working_days,
            'slot_interval' => $this->slot_interval,
            'min_advance_booking_hours' => $this->min_advance_booking_hours,
            'max_advance_booking_days' => $this->max_advance_booking_days,
            'cancellation_hours' => $this->cancellation_hours,
            'reminder_hours' => $this->reminder_hours,
            'online_booking_enabled' => $this->online_booking_enabled,
            'require_confirmation' => $this->require_confirmation,
            'no_show_fee_percent' => $this->no_show_fee_percent,
            'require_deposit' => $this->require_deposit,
            'deposit_percent' => $this->deposit_percent,
            'allow_online_payment' => $this->allow_online_payment,
            'booking_terms' => $this->booking_terms,
            'cancellation_policy' => $this->cancellation_policy,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'welcome_message' => $this->welcome_message,
            'confirmation_message' => $this->confirmation_message,
            'featured_services' => $this->featured_services,
        ]);

        // Gerar slug se não existir
        if (empty($settings->booking_slug)) {
            $settings->booking_slug = SalonSettings::generateUniqueSlug($this->salon_name);
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
        $settings = SalonSettings::getForTenant();
        $this->booking_slug = $settings->regenerateSlug();
        $this->bookingUrl = $settings->booking_url;
        
        $this->dispatch('notify', type: 'success', message: 'Link de agendamento regenerado!');
    }

    public function copyBookingUrl()
    {
        $this->dispatch('copyToClipboard', url: $this->bookingUrl);
        $this->dispatch('notify', type: 'success', message: 'Link copiado para a área de transferência!');
    }

    public function toggleFeaturedService($serviceId)
    {
        if (in_array($serviceId, $this->featured_services)) {
            $this->featured_services = array_values(array_diff($this->featured_services, [$serviceId]));
        } else {
            $this->featured_services[] = $serviceId;
        }
    }

    public function render()
    {
        $services = Service::forTenant()->active()->orderBy('name')->get();
        
        return view('livewire.salon.settings.settings', compact('services'));
    }
}
