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
        // Desabilitar verificação de foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Renomear todas as tabelas de equipamentos para adicionar prefixo events_
        
        // 1. equipment_categories → events_equipment_categories
        if (Schema::hasTable('equipment_categories')) {
            DB::statement('RENAME TABLE equipment_categories TO events_equipment_categories');
        }
        
        // 2. equipment_history → events_equipment_history
        if (Schema::hasTable('equipment_history')) {
            DB::statement('RENAME TABLE equipment_history TO events_equipment_history');
        }
        
        // 3. equipment_sets → events_equipment_sets
        if (Schema::hasTable('equipment_sets')) {
            DB::statement('RENAME TABLE equipment_sets TO events_equipment_sets');
        }
        
        // 4. equipment_set_items → events_equipment_set_items
        if (Schema::hasTable('equipment_set_items')) {
            DB::statement('RENAME TABLE equipment_set_items TO events_equipment_set_items');
        }
        
        // 5. equipment → events_equipments_manager (tabela principal)
        // Nota: events_equipment já existe como tabela pivot (eventos ↔ equipamentos)
        if (Schema::hasTable('equipment')) {
            DB::statement('RENAME TABLE equipment TO events_equipments_manager');
        }
        
        // Reabilitar verificação de foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter renomeação (ordem inversa)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        if (Schema::hasTable('events_equipments_manager')) {
            DB::statement('RENAME TABLE events_equipments_manager TO equipment');
        }
        
        if (Schema::hasTable('events_equipment_set_items')) {
            DB::statement('RENAME TABLE events_equipment_set_items TO equipment_set_items');
        }
        
        if (Schema::hasTable('events_equipment_sets')) {
            DB::statement('RENAME TABLE events_equipment_sets TO equipment_sets');
        }
        
        if (Schema::hasTable('events_equipment_history')) {
            DB::statement('RENAME TABLE events_equipment_history TO equipment_history');
        }
        
        if (Schema::hasTable('events_equipment_categories')) {
            DB::statement('RENAME TABLE events_equipment_categories TO equipment_categories');
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
