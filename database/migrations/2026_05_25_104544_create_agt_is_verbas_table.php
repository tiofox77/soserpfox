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
        Schema::create('agt_is_verbas', function (Blueprint $table) {
            $table->id();
            // Número da verba (1..24) DS.120 Anexo 9.6
            $table->unsignedTinyInteger('verba_no')->unique();
            // Descrição da verba
            $table->string('description', 500);
            // Taxa aplicável (% ou valor absoluto). Ex: "1%" ou "AOA 100"
            $table->string('rate', 64);
            // Tipo da taxa: PERCENTAGE | FIXED
            $table->string('rate_type', 16)->default('PERCENTAGE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agt_is_verbas');
    }
};
