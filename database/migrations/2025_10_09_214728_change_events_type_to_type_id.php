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
        Schema::table('events_events', function (Blueprint $table) {
            // Remover coluna type ENUM
            $table->dropColumn('type');
        });

        Schema::table('events_events', function (Blueprint $table) {
            // Adicionar type_id como foreign key
            $table->unsignedBigInteger('type_id')->nullable()->after('description');
            $table->foreign('type_id')->references('id')->on('events_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            // Remover foreign key e coluna type_id
            $table->dropForeign(['type_id']);
            $table->dropColumn('type_id');
        });

        Schema::table('events_events', function (Blueprint $table) {
            // Restaurar coluna type ENUM
            $table->enum('type', ['corporativo', 'casamento', 'conferencia', 'show', 'streaming', 'outros'])->default('outros')->after('description');
        });
    }
};
