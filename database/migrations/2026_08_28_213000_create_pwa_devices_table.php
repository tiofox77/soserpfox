<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os aparelhos que correm o PWA — quem, onde, e em que versão.
 *
 * PORQUE ISTO PASSOU A SER PRECISO. Descobriu-se, a testar num Android, que um
 * deploy do motor podia NÃO CHEGAR aos aparelhos: a versão do service worker
 * vinha da data do ficheiro e o `?v=` do script era escrito à mão. Produção
 * tinha a correcção, o telemóvel corria a versão de antes, e não havia forma
 * nenhuma de saber isso — nem por cliente, nem por aparelho.
 *
 * A causa está corrigida. O que ficava por resolver era a CEGUEIRA: sem saber
 * que versão cada aparelho corre, a próxima vez que acontecer volta a ser
 * descoberta por acaso.
 *
 * O QUE SE GUARDA, E O QUE NÃO SE GUARDA. Guarda-se o mínimo para responder a
 * "que empresas usam o PWA e estão actualizadas": um identificador do
 * aparelho, a versão, se está instalado, e quando falou connosco pela última
 * vez. NÃO se guarda localização, nem histórico de navegação, nem nada que
 * siga uma pessoa — isto é inventário de instalações, não vigilância de
 * operadores.
 *
 * O `device_uuid` nasce no aparelho e vive no IndexedDB. Se o operador limpar
 * os dados do browser, aparece um aparelho novo — e isso é honesto: do ponto
 * de vista da aplicação offline, é mesmo uma instalação nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwa_devices', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->unsignedBigInteger('tenant_id')->index();

            // Nasce no aparelho. Único por empresa: o mesmo telemóvel a servir
            // duas empresas conta como dois — porque tem duas bases locais,
            // dois catálogos e duas filas.
            $tabela->string('device_uuid', 64);

            // O último operador a sincronizar deste aparelho. Serve para saber
            // a quem ligar quando um aparelho está preso numa versão antiga.
            $tabela->unsignedBigInteger('user_id')->nullable();

            // A VERSÃO QUE ELE ESTÁ MESMO A CORRER, dita por ele. Não é o que
            // o servidor serve — é o que o aparelho tem carregado, que é
            // precisamente onde estava a diferença.
            $tabela->string('app_version', 40)->nullable();

            // Instalado no ecrã principal, ou só um separador do browser? A
            // diferença importa: um separador fecha-se e volta actualizado; uma
            // aplicação instalada pode ficar semanas com a mesma versão.
            $tabela->boolean('standalone')->default(false);

            $tabela->string('platform', 60)->nullable();
            $tabela->string('user_agent', 255)->nullable();

            $tabela->unsignedInteger('syncs')->default(0);
            $tabela->timestamp('first_seen_at')->nullable();
            $tabela->timestamp('last_seen_at')->nullable();

            $tabela->timestamps();

            // Um aparelho, uma linha, por empresa.
            $tabela->unique(['tenant_id', 'device_uuid']);

            // A pergunta do painel: quem falou connosco há pouco, por empresa.
            $tabela->index(['tenant_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_devices');
    }
};
