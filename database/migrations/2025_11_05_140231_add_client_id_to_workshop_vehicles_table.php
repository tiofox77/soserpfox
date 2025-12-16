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
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            // Adicionar campo client_id para vincular diretamente ao cliente
            $table->foreignId('client_id')->nullable()->after('tenant_id')->constrained('invoicing_clients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            //
        });
    }
};
