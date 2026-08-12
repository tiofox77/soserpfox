<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termo pesquisado e tempo de permanência na trilha de visitas.
 *
 * O `meta` já é um JSON e daria para lá meter estas coisas, mas não se agrupa
 * um JSON: "os termos mais pesquisados" é um GROUP BY, e em MySQL isso obriga
 * a extrair a chave em cada linha da tabela inteira, sem índice possível. Uma
 * coluna própria com índice resolve a pergunta em milissegundos.
 *
 * `duration_seconds` é o tempo que a página esteve aberta, enviado quando o
 * visitante sai. Sem ele não se distingue quem leu a página de quem fechou o
 * separador ao segundo — que é a diferença entre uma visita e um engano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            if (!Schema::hasColumn('analytics_events', 'search_term')) {
                $table->string('search_term', 150)->nullable()->after('event_name');
                $table->index('search_term');
            }

            if (!Schema::hasColumn('analytics_events', 'duration_seconds')) {
                $table->unsignedInteger('duration_seconds')->nullable()->after('search_term');
            }

            if (!Schema::hasColumn('analytics_events', 'tenant_id')) {
                // Quem pesquisou, de que empresa. A tabela é da PLATAFORMA e
                // não tem BelongsToTenant de propósito — o visitante anónimo
                // não pertence a empresa nenhuma. Mas quando a pesquisa vem de
                // dentro da aplicação, saber de que empresa veio é metade do
                // valor da informação.
                $table->unsignedBigInteger('tenant_id')->nullable()->after('user_id');
                $table->index('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            foreach (['search_term', 'duration_seconds', 'tenant_id'] as $coluna) {
                if (Schema::hasColumn('analytics_events', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
