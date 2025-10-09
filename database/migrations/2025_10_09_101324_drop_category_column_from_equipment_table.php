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
        // Verificar qual nome de tabela usar
        $tableName = Schema::hasTable('events_equipments_manager') 
            ? 'events_equipments_manager' 
            : 'equipment';
        
        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'category')) {
            Schema::table($tableName, function (Blueprint $table) {
                // Remover o índice antes de remover a coluna
                $table->dropIndex(['tenant_id', 'category']);
                
                // Remover a coluna category (antiga)
                $table->dropColumn('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar qual nome de tabela usar
        $tableName = Schema::hasTable('events_equipments_manager') 
            ? 'events_equipments_manager' 
            : 'equipment';
        
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                // Recriar a coluna category
                $table->string('category')->nullable()->after('name');
                
                // Recriar o índice
                $table->index(['tenant_id', 'category']);
            });
        }
    }
};
