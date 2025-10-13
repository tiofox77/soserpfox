<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            // Adicionar coluna para relacionar com licenças
            $table->foreignId('leave_id')->nullable()->after('employee_id')->constrained('hr_leaves')->onDelete('set null');
            
            // Adicionar campos para controle de atrasos
            $table->boolean('is_late')->default(false)->after('hours_worked');
            $table->integer('late_minutes')->default(0)->after('is_late');
        });
        
        // Atualizar enum de status para incluir novos tipos de licença
        DB::statement("ALTER TABLE hr_attendances MODIFY COLUMN status ENUM(
            'present',
            'absent', 
            'late',
            'half_day',
            'sick',
            'vacation',
            'sick_leave',
            'on_leave',
            'maternity_leave',
            'paternity_leave'
        ) DEFAULT 'present'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropForeign(['leave_id']);
            $table->dropColumn(['leave_id', 'is_late', 'late_minutes']);
        });
        
        // Reverter enum de status
        DB::statement("ALTER TABLE hr_attendances MODIFY COLUMN status ENUM(
            'present',
            'absent',
            'late',
            'half_day',
            'sick',
            'vacation'
        ) DEFAULT 'present'");
    }
};
