<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensagens do dono da plataforma para as empresas.
 *
 * Até aqui não havia forma nenhuma de falar com quem usa o sistema: uma
 * paragem para manutenção, uma mudança de preços, uma obrigação nova da AGT,
 * um aviso a um cliente em particular — tudo isso saía por WhatsApp, à mão,
 * empresa a empresa, e ninguém sabia quem tinha lido.
 *
 * A mensagem escreve-se UMA vez e aponta a um público. Escrever uma linha por
 * utilizador seria mais simples de ler mas impossível de corrigir: com trinta
 * empresas e duzentos utilizadores, mudar uma palavra obrigava a reescrever
 * duzentas linhas — e as que já tivessem sido lidas ficavam com o texto
 * antigo.
 *
 * Quem leu fica na tabela ao lado: uma linha por pessoa que a viu, não por
 * pessoa a quem se destina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_messages', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('body');

            // O tom com que aparece: muda a cor e o ícone, e nada mais.
            $table->string('level', 20)->default('info');       // info | aviso | urgente | sucesso

            // Como aparece. O pop-up interrompe; a barra fica no topo e deixa
            // trabalhar. Escolher errado é a diferença entre avisar e chatear.
            $table->string('display', 20)->default('popup');    // popup | barra

            // A quem se dirige.
            $table->string('audience', 20)->default('todas');   // todas | empresas | planos
            $table->json('tenant_ids')->nullable();
            $table->json('plan_ids')->nullable();

            // Uma mensagem sem fim marcado fica para sempre — e uma mensagem
            // para sempre deixa de ser lida ao fim de dois dias.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Pode ser dispensada? Um aviso de manutenção sim; um que exija
            // resposta, não.
            $table->boolean('dismissible')->default(true);

            $table->string('link_url')->nullable();
            $table->string('link_label', 80)->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // A consulta que corre em cada página de cada utilizador.
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('platform_message_reads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('platform_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable();

            $table->timestamp('seen_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();

            $table->timestamps();

            // Uma pessoa lê uma mensagem uma vez.
            $table->unique(['platform_message_id', 'user_id'], 'msg_utilizador_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_message_reads');
        Schema::dropIfExists('platform_messages');
    }
};
