<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SmsSetting;

class SmsSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SmsSetting::updateOrCreate(
            ['tenant_id' => null], // Configuração global
            [
                'provider' => 'd7networks',
                'api_url' => 'https://api.d7networks.com/messages/v1/send',
                'api_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJhdXRoLWJhY2tlbmQ6YXBwIiwic3ViIjoiY2JkM2ZiOTAtZGVlZi00YWUwLTkwYTctZjI2MzIzNGNhMDNjIn0.eRjhl2ZrPL0qXdlcSpaCfwPSHGIiJKE1gZZEgRGeURI',
                'sender_id' => 'SOS ERP',
                'report_url' => config('app.url') . '/api/sms-delivery-report',
                'is_active' => true,
                'config' => [
                    'timeout' => 30,
                    'max_retries' => 3,
                ],
            ]
        );

        $this->command->info('✅ Configuração SMS global criada com sucesso!');
    }
}
