<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O AVISO DE EMPRESA NOVA DIZIA «SOS ERP» TRÊS VEZES (28/09/2026).
 *
 * O logótipo do cabeçalho já traz o nome escrito, o layout punha-o outra vez
 * por baixo em texto (corrigido na vista) e o modelo `nova-empresa-admin`
 * repetia-o ainda sob o título, num parágrafo só com {app_name}.
 *
 * Tira-se só esse parágrafo do modelo gravado — o resto pode ter sido mexido
 * no painel de emails e fica como está. Sem o parágrafo, nada.
 */
return new class extends Migration
{
    private const SO_O_NOME = '~\s*<p[^>]*>\s*\{\{?\s*app_name\s*\}?\}\s*</p>~';

    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $modelo = DB::table('email_templates')->where('slug', 'nova-empresa-admin')->first();
        if (! $modelo) {
            return;
        }

        $html = (string) $modelo->body_html;
        $limpo = preg_replace(self::SO_O_NOME, '', $html, 1);

        if ($limpo !== null && $limpo !== $html) {
            DB::table('email_templates')->where('id', $modelo->id)
                ->update(['body_html' => $limpo, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // O nome repetido não se repõe.
    }
};
