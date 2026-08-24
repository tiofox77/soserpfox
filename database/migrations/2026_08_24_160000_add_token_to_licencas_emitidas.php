<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guardar o token da licença emitida.
 *
 * Sem isto, uma licença emitida saía uma vez no ecrã e desaparecia: se o
 * cliente a perdesse (reinstalação, email apagado), a única saída era emitir
 * outra. O token não é segredo do lado do servidor — é assinado e só vale na
 * máquina a que se destina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licencas_emitidas', function (Blueprint $table) {
            $table->text('token')->nullable()->after('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('licencas_emitidas', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
