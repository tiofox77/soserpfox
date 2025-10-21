<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Inserir novas configurações de horário de trabalho
        $settings = [
            [
                'key' => 'work_start_time',
                'category' => 'worktime',
                'label' => 'Horário de Início',
                'description' => 'Hora em que o expediente inicia (formato 24h)',
                'value' => '08:00',
                'default_value' => '08:00',
                'value_type' => 'time',
                'validation_rules' => 'required',
                'display_order' => 1,
            ],
            [
                'key' => 'work_end_time',
                'category' => 'worktime',
                'label' => 'Horário de Término',
                'description' => 'Hora em que o expediente termina (formato 24h)',
                'value' => '17:00',
                'default_value' => '17:00',
                'value_type' => 'time',
                'validation_rules' => 'required',
                'display_order' => 2,
            ],
            [
                'key' => 'daily_work_hours',
                'category' => 'worktime',
                'label' => 'Horas de Trabalho por Dia',
                'description' => 'Quantas horas de trabalho por dia (ex: 8)',
                'value' => '8',
                'default_value' => '8',
                'value_type' => 'number',
                'validation_rules' => 'required|numeric|min:1|max:24',
                'display_order' => 3,
            ],
            [
                'key' => 'uses_shifts',
                'category' => 'worktime',
                'label' => 'Trabalha por Turnos?',
                'description' => 'Ativar se a empresa opera com sistema de turnos',
                'value' => '0',
                'default_value' => '0',
                'value_type' => 'boolean',
                'validation_rules' => '',
                'display_order' => 4,
            ],
        ];

        foreach ($settings as $setting) {
            // Verificar se a chave já existe globalmente (unique constraint)
            $exists = \DB::table('hr_settings')
                ->where('key', $setting['key'])
                ->exists();
            
            if ($exists) {
                // Se já existe, apenas atualizar para garantir que está correto
                \DB::table('hr_settings')
                    ->where('key', $setting['key'])
                    ->update([
                        'category' => $setting['category'],
                        'label' => $setting['label'],
                        'description' => $setting['description'],
                        'value_type' => $setting['value_type'],
                        'validation_rules' => $setting['validation_rules'],
                        'display_order' => $setting['display_order'],
                        'updated_at' => now(),
                    ]);
            } else {
                // Se não existe, inserir para o primeiro tenant
                $firstTenant = \DB::table('tenants')->first();
                if ($firstTenant) {
                    \DB::table('hr_settings')->insert(array_merge($setting, [
                        'tenant_id' => $firstTenant->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::table('hr_settings')->whereIn('key', [
            'work_start_time',
            'work_end_time',
            'daily_work_hours',
            'uses_shifts',
        ])->delete();
    }
};
