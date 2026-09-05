<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A origem «Sistema» passa a caber na coluna.
 *
 * O código todo conhece esta origem: está em `Appointment::SOURCES`, tem cor e
 * ícone próprios, entra no `SYSTEM_SOURCES` do `scopeSystemBooking()`, aparece
 * no filtro da listagem e num cartão do painel — e é o valor POR OMISSÃO do
 * ecrã de nova marcação. Só a coluna é que nunca soube dela:
 *
 *   enum('walk_in','phone','whatsapp','website','app','instagram','other')
 *
 * Resultado medido: criar uma marcação pelo salão rebentava com «Data
 * truncated for column 'source'». Marcar era o que o módulo faz, e não fazia.
 * E o cartão «SISTEMA — agendados internamente» só podia mesmo dizer zero.
 *
 * Alternativa descartada: tirar 'system' do código. Seria mais fácil e mentia
 * na mesma — deixaria de haver forma de distinguir o que a casa marcou por si
 * do que entrou por telefone.
 */
return new class extends Migration
{
    /** Os valores por que a coluna passa a aceitar, pela ordem do modelo. */
    private const ORIGENS = [
        'walk_in', 'phone', 'whatsapp', 'website', 'app', 'instagram', 'system', 'other',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('salon_appointments') || ! Schema::hasColumn('salon_appointments', 'source')) {
            return;
        }

        $this->definirEnum(self::ORIGENS);
    }

    public function down(): void
    {
        if (! Schema::hasTable('salon_appointments') || ! Schema::hasColumn('salon_appointments', 'source')) {
            return;
        }

        // Ninguém fica sem origem ao voltar atrás: o que estiver em 'system'
        // passa a 'other', que é o mais próximo, antes de a coluna a recusar.
        DB::table('salon_appointments')->where('source', 'system')->update(['source' => 'other']);

        $this->definirEnum(array_values(array_diff(self::ORIGENS, ['system'])));
    }

    private function definirEnum(array $valores): void
    {
        $lista = implode(',', array_map(fn ($v) => "'" . $v . "'", $valores));

        DB::statement(
            "ALTER TABLE salon_appointments MODIFY COLUMN source ENUM({$lista}) NOT NULL DEFAULT 'phone'"
        );
    }
};
