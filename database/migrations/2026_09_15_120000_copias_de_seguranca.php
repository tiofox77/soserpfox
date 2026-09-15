<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CÓPIAS DE SEGURANÇA: a agenda, os destinos, as cópias, os envios e os restauros.
 *
 * `tenant_id` NULO = da plataforma (a base inteira); preenchido = de uma empresa
 * (só os dados dela). As mesmas tabelas para os dois, com o âmbito na coluna.
 *
 * A TABELA É O ÍNDICE, A PASTA É A VERDADE. Cada cópia deixa ao lado um .json
 * com os seus dados (App\Services\Copias\Pasta); restaurar a base inteira
 * volta estas tabelas atrás no tempo, e o índice refaz-se a partir dos ficheiros.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agendas_de_copia')) {
            Schema::create('agendas_de_copia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->unique();
                $table->boolean('activa')->default(true);
                $table->unsignedSmallInteger('intervalo_horas')->default(6);
                $table->unsignedSmallInteger('manter_locais')->default(12);
                $table->boolean('cifrar')->default(false);
                $table->text('frase')->nullable();          // cifrada com a APP_KEY
                $table->timestamp('ultima_em')->nullable();
                $table->timestamp('proxima_em')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('destinos_de_copia')) {
            Schema::create('destinos_de_copia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('nome', 120);
                $table->string('tipo', 30);                 // google_drive, onedrive, dropbox, ftp, ftps, sftp, s3, webdav
                $table->longText('configuracao')->nullable(); // cifrada com a APP_KEY
                $table->string('pasta', 255)->nullable();
                $table->unsignedSmallInteger('manter')->default(10);
                $table->boolean('activo')->default(true);
                $table->boolean('ligado')->default(false);  // OAuth concluído ou teste passado
                $table->timestamp('testado_em')->nullable();
                $table->text('ultimo_erro')->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('copias_de_seguranca')) {
            Schema::create('copias_de_seguranca', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('ficheiro', 190)->unique();
                $table->unsignedBigInteger('tamanho')->default(0);
                $table->string('sha256', 64)->nullable();
                $table->boolean('cifrada')->default(false);
                $table->string('origem', 30)->default('automatica'); // automatica, manual, antes_de_restaurar, carregada
                $table->string('estado', 20)->default('a_correr');   // a_correr, concluida, falhou
                $table->text('erro')->nullable();
                $table->json('resumo')->nullable();                  // tabelas e linhas
                $table->unsignedBigInteger('pedida_por')->nullable();
                $table->timestamp('iniciada_em')->nullable();
                $table->timestamp('concluida_em')->nullable();
                $table->boolean('ficheiro_local')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('envios_de_copia')) {
            Schema::create('envios_de_copia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('copia_id')->index();
                $table->unsignedBigInteger('destino_id')->index();
                $table->string('estado', 20)->default('pendente'); // pendente, enviado, falhou, apagado
                $table->string('remoto', 500)->nullable();         // id ou caminho do lado de lá
                $table->text('erro')->nullable();
                $table->timestamp('enviado_em')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('restauros_de_copia')) {
            Schema::create('restauros_de_copia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('copia_id')->nullable();
                $table->unsignedBigInteger('destino_id')->nullable();
                $table->string('ficheiro', 190)->nullable();
                $table->unsignedBigInteger('copia_previa_id')->nullable();
                $table->string('estado', 20)->default('a_correr');  // a_correr, concluido, falhou, recusado
                $table->text('erro')->nullable();
                $table->json('resumo')->nullable();
                $table->unsignedBigInteger('pedido_por')->nullable();
                $table->timestamp('iniciado_em')->nullable();
                $table->timestamp('concluido_em')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['restauros_de_copia', 'envios_de_copia', 'copias_de_seguranca', 'destinos_de_copia', 'agendas_de_copia'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
