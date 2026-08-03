<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planos promocionais só podem ser ativados UMA VEZ por empresa (tenant).
 * Adiciona a flag is_promotional e marca o FOX Friendly como promocional.
 * A regra "1x por tenant" é aplicada no fluxo de upgrade (MyAccount::processUpgrade).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        if (!Schema::hasColumn('plans', 'is_promotional')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->boolean('is_promotional')->default(false)->after('is_featured');
            });
        }

        // FOX Friendly é promocional (6 meses grátis, uma vez por empresa)
        DB::table('plans')->where('slug', 'fox-friendly')->update(['is_promotional' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'is_promotional')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('is_promotional');
            });
        }
    }
};
