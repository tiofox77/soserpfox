<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIN de turno para login offline no POS.
 *
 * O PIN é uma credencial de chão de loja, separada da password da conta:
 * é o verificador (bcrypt do PIN) que vai para o tablet na sincronização,
 * para qualquer funcionário activo poder abrir turno offline. Assim, um
 * tablet perdido expõe no máximo PINs de caixa daquele aparelho — nunca a
 * password real do funcionário, que costuma ser reutilizada noutros sítios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // bcrypt do PIN. Só o hash — o PIN em claro nunca se guarda.
            $t->string('pos_pin_hash')->nullable()->after('password');
            $t->timestamp('pos_pin_set_at')->nullable()->after('pos_pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['pos_pin_hash', 'pos_pin_set_at']);
        });
    }
};
