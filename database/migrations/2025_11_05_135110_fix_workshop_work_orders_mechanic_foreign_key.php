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
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            // Remover foreign key antiga que referencia hr_employees
            $table->dropForeign(['mechanic_id']);
            
            // Adicionar nova foreign key que referencia workshop_mechanics
            $table->foreign('mechanic_id')
                  ->references('id')
                  ->on('workshop_mechanics')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            // Reverter: remover foreign key de workshop_mechanics
            $table->dropForeign(['mechanic_id']);
            
            // Recriar foreign key antiga para hr_employees
            $table->foreign('mechanic_id')
                  ->references('id')
                  ->on('hr_employees')
                  ->onDelete('set null');
        });
    }
};
