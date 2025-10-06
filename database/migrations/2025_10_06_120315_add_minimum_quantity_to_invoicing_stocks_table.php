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
        Schema::table('invoicing_stocks', function (Blueprint $table) {
            $table->decimal('minimum_quantity', 15, 3)->default(0)->after('available_quantity')
                ->comment('Quantidade mínima de stock (alerta de baixo stock)');
            $table->decimal('maximum_quantity', 15, 3)->nullable()->after('minimum_quantity')
                ->comment('Quantidade máxima de stock (alerta de excesso)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_stocks', function (Blueprint $table) {
            $table->dropColumn(['minimum_quantity', 'maximum_quantity']);
        });
    }
};
