<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lista de espera: quem chega sem reserva numa casa cheia.
 *
 * As reservas tratam de quem avisou; isto trata da fila à porta. É uma fila
 * simples de propósito — nome, quantos são, telefone, quando chegou — porque é
 * isso que o empregado da porta consegue apontar com a casa cheia.
 *
 * Estados: waiting → seated | left. O `left` importa tanto como o `seated`:
 * a diferença entre os dois é a medida de quantos clientes a casa perde por
 * falta de mesa, que é o número que justifica (ou não) pôr mais mesas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_waitlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('guest_name', 150);
            $table->string('phone', 30)->nullable();
            $table->unsignedSmallInteger('guest_count')->default(2);
            $table->string('status', 20)->default('waiting'); // waiting|seated|left
            $table->text('notes')->nullable();

            // A mesa e a comanda em que acabou sentado, quando acabou.
            $table->unsignedBigInteger('table_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();

            $table->timestamp('arrived_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // A fila lê-se sempre assim: os desta casa, à espera, por ordem
            // de chegada.
            $table->index(['tenant_id', 'venue_id', 'status', 'arrived_at'], 'waitlist_fila_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_waitlist');
    }
};
