<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('key')->unique();
            $table->string('category'); // general, payroll, vacation, overtime, leave
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('value_type'); // integer, decimal, percentage, boolean, text, json
            $table->text('value');
            $table->text('default_value');
            $table->text('validation_rules')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'category']);
            $table->index(['key', 'is_active']);
        });

        // Inserir configurações padrão da legislação angolana
        $this->insertDefaultSettings();
    }

    private function insertDefaultSettings()
    {
        $tenantId = DB::table('tenants')->first()->id ?? 1;
        
        $settings = [
            // Geral
            [
                'tenant_id' => $tenantId,
                'key' => 'working_days_per_month',
                'category' => 'general',
                'label' => 'Dias Trabalhados por Mês',
                'description' => 'Número de dias úteis considerados para cálculos mensais (22, 26 ou 30)',
                'value_type' => 'integer',
                'value' => '22',
                'default_value' => '22',
                'validation_rules' => 'required|integer|in:22,26,30',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'working_hours_per_day',
                'category' => 'general',
                'label' => 'Horas de Trabalho por Dia',
                'description' => 'Jornada de trabalho diária em horas',
                'value_type' => 'integer',
                'value' => '8',
                'default_value' => '8',
                'validation_rules' => 'required|integer|min:6|max:10',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'monthly_working_hours',
                'category' => 'general',
                'label' => 'Horas de Trabalho Mensais',
                'description' => 'Total de horas de trabalho por mês (22 dias x 8 horas = 176)',
                'value_type' => 'integer',
                'value' => '176',
                'default_value' => '176',
                'validation_rules' => 'required|integer|min:120|max:240',
                'display_order' => 3,
                'is_active' => true,
            ],

            // Férias
            [
                'tenant_id' => $tenantId,
                'key' => 'vacation_days_per_year',
                'category' => 'vacation',
                'label' => 'Dias de Férias por Ano',
                'description' => 'Dias úteis de férias anuais segundo legislação angolana',
                'value_type' => 'integer',
                'value' => '22',
                'default_value' => '22',
                'validation_rules' => 'required|integer|min:20|max:30',
                'display_order' => 10,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'vacation_subsidy_percentage',
                'category' => 'vacation',
                'label' => 'Subsídio de Férias (%)',
                'description' => 'Percentual do salário pago como subsídio de férias (14º mês)',
                'value_type' => 'percentage',
                'value' => '50',
                'default_value' => '50',
                'validation_rules' => 'required|numeric|min:0|max:100',
                'display_order' => 11,
                'is_active' => true,
            ],

            // Horas Extras
            [
                'tenant_id' => $tenantId,
                'key' => 'overtime_weekday_multiplier',
                'category' => 'overtime',
                'label' => 'Multiplicador Hora Extra Dia Útil',
                'description' => 'Acréscimo em horas extras em dias úteis (1.5 = 50% adicional)',
                'value_type' => 'decimal',
                'value' => '1.5',
                'default_value' => '1.5',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 20,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'overtime_weekend_multiplier',
                'category' => 'overtime',
                'label' => 'Multiplicador Hora Extra Fim de Semana',
                'description' => 'Acréscimo em horas extras em fins de semana (2.0 = 100% adicional)',
                'value_type' => 'decimal',
                'value' => '2.0',
                'default_value' => '2.0',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 21,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'overtime_holiday_multiplier',
                'category' => 'overtime',
                'label' => 'Multiplicador Hora Extra Feriado',
                'description' => 'Acréscimo em horas extras em feriados (2.0 = 100% adicional)',
                'value_type' => 'decimal',
                'value' => '2.0',
                'default_value' => '2.0',
                'validation_rules' => 'required|numeric|min:1|max:3',
                'display_order' => 22,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'overtime_night_multiplier',
                'category' => 'overtime',
                'label' => 'Multiplicador Hora Extra Noturna',
                'description' => 'Acréscimo adicional para trabalho noturno (1.25 = 25% adicional)',
                'value_type' => 'decimal',
                'value' => '1.25',
                'default_value' => '1.25',
                'validation_rules' => 'required|numeric|min:1|max:2',
                'display_order' => 23,
                'is_active' => true,
            ],

            // Subsídios e Benefícios
            [
                'tenant_id' => $tenantId,
                'key' => 'christmas_bonus_percentage',
                'category' => 'payroll',
                'label' => 'Subsídio de Natal (%)',
                'description' => 'Percentual do salário pago como subsídio de Natal (13º mês)',
                'value_type' => 'percentage',
                'value' => '100',
                'default_value' => '100',
                'validation_rules' => 'required|numeric|min:0|max:200',
                'display_order' => 30,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'meal_allowance',
                'category' => 'payroll',
                'label' => 'Subsídio de Alimentação (Kz)',
                'description' => 'Valor diário de subsídio de alimentação',
                'value_type' => 'decimal',
                'value' => '0',
                'default_value' => '0',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 31,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'transport_allowance',
                'category' => 'payroll',
                'label' => 'Subsídio de Transporte (Kz)',
                'description' => 'Valor diário de subsídio de transporte',
                'value_type' => 'decimal',
                'value' => '0',
                'default_value' => '0',
                'validation_rules' => 'required|numeric|min:0',
                'display_order' => 32,
                'is_active' => true,
            ],

            // Adiantamentos
            [
                'tenant_id' => $tenantId,
                'key' => 'max_salary_advance_percentage',
                'category' => 'payroll',
                'label' => 'Percentual Máximo de Adiantamento (%)',
                'description' => 'Percentual máximo do salário que pode ser adiantado',
                'value_type' => 'percentage',
                'value' => '50',
                'default_value' => '50',
                'validation_rules' => 'required|numeric|min:0|max:100',
                'display_order' => 40,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'max_advance_installments',
                'category' => 'payroll',
                'label' => 'Máximo de Parcelas para Adiantamento',
                'description' => 'Número máximo de parcelas para desconto de adiantamento',
                'value_type' => 'integer',
                'value' => '6',
                'default_value' => '6',
                'validation_rules' => 'required|integer|min:1|max:12',
                'display_order' => 41,
                'is_active' => true,
            ],

            // Licenças
            [
                'tenant_id' => $tenantId,
                'key' => 'maternity_leave_days',
                'category' => 'leave',
                'label' => 'Dias de Licença Maternidade',
                'description' => 'Dias de licença maternidade segundo legislação angolana',
                'value_type' => 'integer',
                'value' => '90',
                'default_value' => '90',
                'validation_rules' => 'required|integer|min:60|max:180',
                'display_order' => 50,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'paternity_leave_days',
                'category' => 'leave',
                'label' => 'Dias de Licença Paternidade',
                'description' => 'Dias de licença paternidade segundo legislação angolana',
                'value_type' => 'integer',
                'value' => '3',
                'default_value' => '3',
                'validation_rules' => 'required|integer|min:1|max:15',
                'display_order' => 51,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'bereavement_leave_days',
                'category' => 'leave',
                'label' => 'Dias de Licença por Luto',
                'description' => 'Dias de licença por falecimento de familiar',
                'value_type' => 'integer',
                'value' => '5',
                'default_value' => '5',
                'validation_rules' => 'required|integer|min:1|max:10',
                'display_order' => 52,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'key' => 'marriage_leave_days',
                'category' => 'leave',
                'label' => 'Dias de Licença por Casamento',
                'description' => 'Dias de licença por casamento',
                'value_type' => 'integer',
                'value' => '10',
                'default_value' => '10',
                'validation_rules' => 'required|integer|min:1|max:15',
                'display_order' => 53,
                'is_active' => true,
            ],
        ];

        foreach ($settings as $setting) {
            $setting['created_at'] = now();
            $setting['updated_at'] = now();
            DB::table('hr_settings')->insert($setting);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_settings');
    }
};
