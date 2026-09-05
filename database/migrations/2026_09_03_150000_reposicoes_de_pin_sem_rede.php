<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O registo de cada PIN de turno reposto num aparelho sem rede.
 *
 * Uma reposição feita ao balcão, autorizada por um gestor com o seu PIN,
 * chega ao servidor mais tarde, pela fila. Fica aqui quem pediu, quem
 * autorizou, que conta tinha sessão no aparelho, e se o servidor a aceitou —
 * ou porque não. O `local_uuid` é o que impede a mesma reposição de entrar
 * duas vezes quando a fila repete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reposicoes_de_pin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('local_uuid', 80);
            $table->unsignedBigInteger('user_id')->comment('quem ficou com PIN novo');
            $table->unsignedBigInteger('autorizado_por')->comment('o gestor que pôs o seu PIN no aparelho');
            $table->unsignedBigInteger('sessao_user_id')->comment('a conta com sessão no aparelho quando a fila subiu');
            $table->string('aparelho', 64)->nullable();
            $table->string('estado', 12)->comment('aceite | recusada');
            $table->string('motivo')->nullable();
            $table->timestamp('reposto_no_aparelho_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'local_uuid'], 'reposicoes_pin_uuid_unq');
            $table->index(['tenant_id', 'user_id'], 'reposicoes_pin_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reposicoes_de_pin');
    }
};
