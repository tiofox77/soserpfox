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
        // Verificar se a coluna já existe
        if (!Schema::hasColumn('hr_attendances', 'tenant_id')) {
            Schema::table('hr_attendances', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->after('id')->default(1);
            });
            
            // Atualizar registros existentes com tenant_id = 1
            DB::statement('UPDATE hr_attendances SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0');
            
            // Adicionar foreign key
            Schema::table('hr_attendances', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('hr_attendances', 'tenant_id')) {
            Schema::table('hr_attendances', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
