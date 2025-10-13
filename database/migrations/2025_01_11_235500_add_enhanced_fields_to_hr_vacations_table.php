<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_vacations', function (Blueprint $table) {
            // Tipo de férias
            if (!Schema::hasColumn('hr_vacations', 'vacation_type')) {
                $table->enum('vacation_type', ['normal', 'accumulated', 'advance', 'collective'])
                      ->default('normal')->after('vacation_number');
            }
            
            // Divisão de férias
            if (!Schema::hasColumn('hr_vacations', 'can_split')) {
                $table->boolean('can_split')->default(true)->after('vacation_type');
            }
            if (!Schema::hasColumn('hr_vacations', 'split_number')) {
                $table->integer('split_number')->nullable()->after('can_split')->comment('1ª, 2ª ou 3ª parcela');
            }
            if (!Schema::hasColumn('hr_vacations', 'total_splits')) {
                $table->integer('total_splits')->default(1)->after('split_number')->comment('Total de divisões');
            }
            if (!Schema::hasColumn('hr_vacations', 'parent_vacation_id')) {
                $table->foreignId('parent_vacation_id')->nullable()
                      ->after('total_splits')
                      ->constrained('hr_vacations')
                      ->onDelete('cascade')
                      ->comment('Se for divisão, referência à férias original');
            }
            
            // Saldo de férias
            if (!Schema::hasColumn('hr_vacations', 'previous_balance')) {
                $table->integer('previous_balance')->default(0)->after('calculated_days')->comment('Saldo anterior não gozado');
            }
            if (!Schema::hasColumn('hr_vacations', 'accumulated_days')) {
                $table->integer('accumulated_days')->default(0)->after('previous_balance')->comment('Dias acumulados de anos anteriores');
            }
            if (!Schema::hasColumn('hr_vacations', 'days_remaining')) {
                $table->integer('days_remaining')->default(0)->after('accumulated_days')->comment('Dias restantes após estas férias');
            }
            
            // Controle de retorno
            if (!Schema::hasColumn('hr_vacations', 'expected_return_date')) {
                $table->date('expected_return_date')->nullable()->after('end_date')->comment('Data prevista de retorno ao trabalho');
            }
            if (!Schema::hasColumn('hr_vacations', 'actual_return_date')) {
                $table->date('actual_return_date')->nullable()->after('expected_return_date')->comment('Data real de retorno');
            }
            if (!Schema::hasColumn('hr_vacations', 'returned_on_time')) {
                $table->boolean('returned_on_time')->nullable()->after('actual_return_date');
            }
            if (!Schema::hasColumn('hr_vacations', 'return_notes')) {
                $table->text('return_notes')->nullable()->after('returned_on_time');
            }
            
            // Adiantamento de férias
            if (!Schema::hasColumn('hr_vacations', 'advance_payment_date')) {
                $table->date('advance_payment_date')->nullable()->after('total_amount')->comment('Data limite para pagar adiantamento');
            }
            if (!Schema::hasColumn('hr_vacations', 'advance_paid')) {
                $table->boolean('advance_paid')->default(false)->after('advance_payment_date');
            }
            if (!Schema::hasColumn('hr_vacations', 'advance_paid_date')) {
                $table->date('advance_paid_date')->nullable()->after('advance_paid');
            }
            if (!Schema::hasColumn('hr_vacations', 'advance_paid_by')) {
                $table->foreignId('advance_paid_by')->nullable()->after('advance_paid_date')->constrained('users');
            }
            
            // Integração com folha
            if (!Schema::hasColumn('hr_vacations', 'payroll_id')) {
                $table->foreignId('payroll_id')->nullable()
                      ->after('paid_by')
                      ->constrained('hr_payrolls')
                      ->onDelete('set null')
                      ->comment('Folha que processou o pagamento');
            }
            if (!Schema::hasColumn('hr_vacations', 'processed_in_payroll')) {
                $table->boolean('processed_in_payroll')->default(false)->after('payroll_id');
            }
            
            // Anexos
            if (!Schema::hasColumn('hr_vacations', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('notes')->comment('Caminho para documentos anexos');
            }
            
            // Controle adicional
            if (!Schema::hasColumn('hr_vacations', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status')->comment('Se o registro está ativo');
            }
            if (!Schema::hasColumn('hr_vacations', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('hr_vacations', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users');
            }
            if (!Schema::hasColumn('hr_vacations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            
            // Férias coletivas
            if (!Schema::hasColumn('hr_vacations', 'is_collective')) {
                $table->boolean('is_collective')->default(false)->after('is_active')->comment('Se é férias coletivas da empresa');
            }
            if (!Schema::hasColumn('hr_vacations', 'collective_group')) {
                $table->string('collective_group')->nullable()->after('is_collective')->comment('Grupo/departamento das férias coletivas');
            }
        });
        
        // Adicionar índices (silenciosamente ignora se já existem)
        try {
            Schema::table('hr_vacations', function (Blueprint $table) {
                $table->index(['vacation_type', 'status'], 'hr_vacations_vac_type_status_idx');
            });
        } catch (\Exception $e) {
            // Índice já existe
        }
        
        try {
            Schema::table('hr_vacations', function (Blueprint $table) {
                $table->index(['parent_vacation_id'], 'hr_vacations_parent_id_idx');
            });
        } catch (\Exception $e) {
            // Índice já existe
        }
        
        try {
            Schema::table('hr_vacations', function (Blueprint $table) {
                $table->index(['is_collective', 'collective_group'], 'hr_vacations_collective_idx');
            });
        } catch (\Exception $e) {
            // Índice já existe
        }
    }

    public function down(): void
    {
        Schema::table('hr_vacations', function (Blueprint $table) {
            // Remover foreign keys primeiro
            if (Schema::hasColumn('hr_vacations', 'parent_vacation_id')) {
                $table->dropForeign(['parent_vacation_id']);
            }
            if (Schema::hasColumn('hr_vacations', 'advance_paid_by')) {
                $table->dropForeign(['advance_paid_by']);
            }
            if (Schema::hasColumn('hr_vacations', 'payroll_id')) {
                $table->dropForeign(['payroll_id']);
            }
            if (Schema::hasColumn('hr_vacations', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
            }
            
            // Remover índices
            try {
                $table->dropIndex('hr_vacations_vac_type_status_idx');
            } catch (\Exception $e) {}
            
            try {
                $table->dropIndex('hr_vacations_parent_id_idx');
            } catch (\Exception $e) {}
            
            try {
                $table->dropIndex('hr_vacations_collective_idx');
            } catch (\Exception $e) {}
            
            // Remover colunas
            $columns = [
                'vacation_type', 'can_split', 'split_number', 'total_splits', 'parent_vacation_id',
                'previous_balance', 'accumulated_days', 'days_remaining',
                'expected_return_date', 'actual_return_date', 'returned_on_time', 'return_notes',
                'advance_payment_date', 'advance_paid', 'advance_paid_date', 'advance_paid_by',
                'payroll_id', 'processed_in_payroll', 'attachment_path',
                'is_collective', 'collective_group', 'is_active',
                'cancellation_reason', 'cancelled_by', 'cancelled_at',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('hr_vacations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
