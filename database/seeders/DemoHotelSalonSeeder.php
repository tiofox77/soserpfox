<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\Hotel\HotelSettings;
use App\Models\Hotel\RoomType;
use App\Models\Hotel\Room;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\ServiceCategory;
use App\Models\Salon\Service;
use App\Models\Salon\Professional;

/**
 * Cria um HOTEL e um SALÃO de DEMONSTRAÇÃO no tenant softecangola.
 * Idempotente (updateOrCreate / guardas por nome). Ativa os módulos e publica
 * os sites online em /hotel/booking/softec-hotel e /agendar/softec-salao.
 */
class DemoHotelSalonSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('email', 'softecangola@gmail.com')->first();
        if (!$tenant) {
            $this->command?->error("Tenant softecangola@gmail.com não encontrado.");
            return;
        }
        $tid = $tenant->id;
        $this->command?->info("Tenant #{$tid} — {$tenant->name}");

        // ── Ativar módulos hotel (11) e salon (12) ──
        foreach ([11 => 'hotel', 12 => 'salon'] as $moduleId => $name) {
            DB::table('tenant_module')->updateOrInsert(
                ['tenant_id' => $tid, 'module_id' => $moduleId],
                ['is_active' => 1, 'activated_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->seedHotel($tid);
        $this->seedSalon($tid);

        $base = rtrim(config('app.url'), '/');
        $this->command?->info("✅ Hotel: {$base}/hotel/booking/softec-hotel");
        $this->command?->info("✅ Salão: {$base}/agendar/softec-salao");
    }

    private function seedHotel(int $tid): void
    {
        HotelSettings::updateOrCreate(
            ['tenant_id' => $tid],
            [
                'hotel_name' => 'Softec Hotel Demo',
                'hotel_description' => 'Hotel de demonstração — conforto e tecnologia no coração de Luanda.',
                'hotel_city' => 'Luanda',
                'hotel_country' => 'Angola',
                'hotel_phone' => '+244 923 000 000',
                'hotel_whatsapp' => '+244 923 000 000',
                'hotel_email' => 'softecangola@gmail.com',
                'star_rating' => 4,
                'primary_color' => '#0ea5e9',
                'secondary_color' => '#6366f1',
                'booking_slug' => 'softec-hotel',
                'online_booking_enabled' => true,
                'require_deposit' => false,
                'deposit_percent' => 0,
                'min_advance_booking_days' => 0,
                'max_advance_booking_days' => 60,
                'currency' => 'AOA',
                'welcome_message' => 'Bem-vindo ao Softec Hotel Demo!',
            ]
        );

        $types = [
            ['name' => 'Quarto Standard', 'code' => 'STD', 'base_price' => 35000, 'capacity' => 2,
             'description' => 'Quarto acolhedor com todo o essencial para uma estadia confortável.',
             'amenities' => ['wifi', 'ac', 'tv']],
            ['name' => 'Quarto Deluxe', 'code' => 'DLX', 'base_price' => 55000, 'capacity' => 3,
             'description' => 'Mais espaço e comodidades, ideal para famílias.',
             'amenities' => ['wifi', 'ac', 'tv', 'minibar']],
            ['name' => 'Suite Executiva', 'code' => 'SUI', 'base_price' => 90000, 'capacity' => 4,
             'description' => 'A nossa melhor acomodação, com sala de estar e varanda.',
             'amenities' => ['wifi', 'ac', 'tv', 'minibar', 'safe', 'balcony']],
        ];

        foreach ($types as $i => $t) {
            $rt = RoomType::updateOrCreate(
                ['tenant_id' => $tid, 'name' => $t['name']],
                [
                    'code' => $t['code'],
                    'description' => $t['description'],
                    'base_price' => $t['base_price'],
                    'capacity' => $t['capacity'],
                    'amenities' => $t['amenities'],
                    'is_active' => true,
                ]
            );
            // 3 quartos por tipo
            for ($n = 1; $n <= 3; $n++) {
                $number = ($i + 1) . '0' . $n; // 101,102,103,201...
                Room::updateOrCreate(
                    ['tenant_id' => $tid, 'number' => $number],
                    [
                        'room_type_id' => $rt->id,
                        'floor' => (string) ($i + 1),
                        'status' => 'available',
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedSalon(int $tid): void
    {
        SalonSettings::updateOrCreate(
            ['tenant_id' => $tid],
            [
                'salon_name' => 'Softec Salão Demo',
                'salon_description' => 'Salão de beleza de demonstração — cabelo, estética e bem-estar.',
                'salon_address' => 'Luanda, Angola',
                'salon_phone' => '+244 923 000 001',
                'salon_whatsapp' => '+244 923 000 001',
                'salon_email' => 'softecangola@gmail.com',
                'primary_color' => '#ec4899',
                'secondary_color' => '#8b5cf6',
                'booking_slug' => 'softec-salao',
                'opening_time' => '09:00:00',
                'closing_time' => '19:00:00',
                'working_days' => [1, 2, 3, 4, 5, 6], // Seg a Sáb
                'slot_interval' => 30,
                'min_advance_booking_hours' => 2,
                'max_advance_booking_days' => 30,
                'online_booking_enabled' => true,
                'require_confirmation' => false,
                'welcome_message' => 'Agende o seu momento de beleza no Softec Salão Demo!',
            ]
        );

        $catCabelo = ServiceCategory::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'cabelo'],
            ['name' => 'Cabelo', 'icon' => 'fa-scissors', 'color' => '#ec4899', 'order' => 1, 'is_active' => true]
        );
        $catEstetica = ServiceCategory::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'estetica'],
            ['name' => 'Estética', 'icon' => 'fa-spa', 'color' => '#8b5cf6', 'order' => 2, 'is_active' => true]
        );

        $services = [
            ['name' => 'Corte de Cabelo', 'price' => 3000, 'duration' => 30, 'cat' => $catCabelo->id],
            ['name' => 'Coloração', 'price' => 8000, 'duration' => 90, 'cat' => $catCabelo->id],
            ['name' => 'Manicure', 'price' => 2500, 'duration' => 45, 'cat' => $catEstetica->id],
            ['name' => 'Limpeza de Pele', 'price' => 6000, 'duration' => 60, 'cat' => $catEstetica->id],
        ];
        foreach ($services as $s) {
            $exists = Service::where('tenant_id', $tid)->where('name', $s['name'])->first();
            if ($exists) {
                $exists->update(['price' => $s['price'], 'is_active' => true]);
                $exists->updateSalonData(['duration' => $s['duration'], 'online_booking' => true, 'category_id' => $s['cat']]);
                continue;
            }
            Service::create([
                'tenant_id' => $tid,
                'code' => 'SVC-' . Str::upper(Str::random(6)),
                'name' => $s['name'],
                'price' => $s['price'],
                'is_active' => true,
                'description' => json_encode([
                    'salon' => [
                        'duration' => $s['duration'],
                        'commission_percent' => 0,
                        'commission_fixed' => 0,
                        'online_booking' => true,
                        'category_id' => $s['cat'],
                    ],
                    'text' => '',
                ]),
            ]);
        }

        $pros = [
            ['name' => 'Ana Silva', 'specialization' => 'Cabeleireira'],
            ['name' => 'João Mendes', 'specialization' => 'Esteticista'],
        ];
        foreach ($pros as $p) {
            Professional::updateOrCreate(
                ['tenant_id' => $tid, 'name' => $p['name']],
                [
                    'specialization' => $p['specialization'],
                    'working_days' => [1, 2, 3, 4, 5, 6],
                    'work_start' => '09:00:00',
                    'work_end' => '18:00:00',
                    'lunch_start' => '13:00:00',
                    'lunch_end' => '14:00:00',
                    'accepts_online_booking' => true,
                    'is_active' => true,
                    'is_available' => true,
                ]
            );
        }
    }
}
