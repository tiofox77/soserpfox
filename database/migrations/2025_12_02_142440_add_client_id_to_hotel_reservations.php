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
        Schema::table('hotel_reservations', function (Blueprint $table) {
            // Adicionar client_id para vincular ao cliente de faturação
            $table->foreignId('client_id')->nullable()->after('guest_id')
                  ->constrained('invoicing_clients')->onDelete('set null');
            
            // Tornar guest_id nullable (mantém para compatibilidade)
            $table->foreignId('guest_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_reservations', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
