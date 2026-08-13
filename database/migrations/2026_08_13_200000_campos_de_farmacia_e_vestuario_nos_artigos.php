<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de farmácia e de vestuário no catálogo de artigos.
 *
 * O catálogo servia para tudo, mas quem vende medicamentos e quem vende roupa
 * precisa de identificar o artigo por coisas que aqui não existiam: a substância
 * activa e a dosagem num caso, o tamanho e a cor no outro. Sem isso, distinguir
 * dois artigos ao balcão obriga a ler o nome inteiro — e "Paracetamol" sozinho
 * não diz se são os comprimidos de 500mg ou o xarope.
 *
 * Nada disto é obrigatório. A esmagadora maioria dos artigos do sistema não é
 * nem medicamento nem roupa, e uma coluna NOT NULL partia o catálogo de toda a
 * gente para servir dois ramos.
 *
 * Lotes e validades já existem (track_batches, track_expiry) e não se tocam:
 * a farmácia em produção depende deles.
 *
 * Guardas coluna a coluna porque esta base já levou migrações corridas à mão —
 * se uma delas rebentar a meio, o que já foi aplicado não volta a ser tentado.
 */
return new class extends Migration
{
    /**
     * Colunas novas por ordem de inserção. A chave é o nome da coluna e o valor
     * a coluna que a precede, para o esquema sair legível num `describe`.
     */
    private const COLUNAS = [
        // Farmácia
        'requires_prescription' => 'require_batch_on_sale',
        'is_controlled'         => 'requires_prescription',
        'active_ingredient'     => 'is_controlled',
        'dosage'                => 'active_ingredient',
        'pharmaceutical_form'   => 'dosage',
        'armed_registration'    => 'pharmaceutical_form',
        // Vestuário
        'size'                  => 'armed_registration',
        'color'                 => 'size',
        'gender'                => 'color',
        'material'              => 'gender',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('invoicing_products')) {
            return;
        }

        Schema::table('invoicing_products', function (Blueprint $table) {
            // O `after` aponta sempre para uma coluna que existe: ou já lá estava,
            // ou é acrescentada nesta mesma instrução.
            $depois = fn (string $coluna) => self::COLUNAS[$coluna];

            if (!Schema::hasColumn('invoicing_products', 'requires_prescription')) {
                $table->boolean('requires_prescription')->default(false)
                    ->after($depois('requires_prescription'))
                    ->comment('Exige receita médica');
            }

            if (!Schema::hasColumn('invoicing_products', 'is_controlled')) {
                $table->boolean('is_controlled')->default(false)
                    ->after($depois('is_controlled'))
                    ->comment('Psicotrópico / estupefaciente');
            }

            if (!Schema::hasColumn('invoicing_products', 'active_ingredient')) {
                $table->string('active_ingredient', 255)->nullable()
                    ->after($depois('active_ingredient'))
                    ->comment('Substância activa (DCI)');
            }

            if (!Schema::hasColumn('invoicing_products', 'dosage')) {
                $table->string('dosage', 60)->nullable()
                    ->after($depois('dosage'))
                    ->comment('Dosagem: 500mg, 5mg/ml');
            }

            if (!Schema::hasColumn('invoicing_products', 'pharmaceutical_form')) {
                $table->string('pharmaceutical_form', 40)->nullable()
                    ->after($depois('pharmaceutical_form'))
                    ->comment('Forma: comprimido, xarope, injectável');
            }

            if (!Schema::hasColumn('invoicing_products', 'armed_registration')) {
                $table->string('armed_registration', 60)->nullable()
                    ->after($depois('armed_registration'))
                    ->comment('N.º de registo ARMED (Angola)');
            }

            if (!Schema::hasColumn('invoicing_products', 'size')) {
                $table->string('size', 20)->nullable()
                    ->after($depois('size'))
                    ->comment('Tamanho: S, M, L, 38, 40');
            }

            if (!Schema::hasColumn('invoicing_products', 'color')) {
                $table->string('color', 40)->nullable()
                    ->after($depois('color'))
                    ->comment('Cor');
            }

            if (!Schema::hasColumn('invoicing_products', 'gender')) {
                $table->string('gender', 20)->nullable()
                    ->after($depois('gender'))
                    ->comment('masculino | feminino | unissexo | criança');
            }

            if (!Schema::hasColumn('invoicing_products', 'material')) {
                $table->string('material', 120)->nullable()
                    ->after($depois('material'))
                    ->comment('Composição: 100% algodão');
            }
        });

        // Índices só depois das colunas existirem — e sempre com o tenant_id à
        // cabeça, que é o filtro que nunca falta. Num catálogo de vinte mil
        // artigos, filtrar por receita ou por tamanho sem índice é varrimento
        // completo da tabela a cada ecrã.
        Schema::table('invoicing_products', function (Blueprint $table) {
            if (
                Schema::hasColumn('invoicing_products', 'requires_prescription')
                && !Schema::hasIndex('invoicing_products', 'invoicing_products_tenant_prescription_index')
            ) {
                $table->index(['tenant_id', 'requires_prescription'], 'invoicing_products_tenant_prescription_index');
            }

            if (
                Schema::hasColumn('invoicing_products', 'size')
                && !Schema::hasIndex('invoicing_products', 'invoicing_products_tenant_size_index')
            ) {
                $table->index(['tenant_id', 'size'], 'invoicing_products_tenant_size_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoicing_products')) {
            return;
        }

        // Os índices caem primeiro: o MySQL recusa largar uma coluna que ainda
        // faz parte de um índice composto.
        Schema::table('invoicing_products', function (Blueprint $table) {
            foreach (['invoicing_products_tenant_prescription_index', 'invoicing_products_tenant_size_index'] as $indice) {
                if (Schema::hasIndex('invoicing_products', $indice)) {
                    $table->dropIndex($indice);
                }
            }
        });

        $existentes = array_values(array_filter(
            array_keys(self::COLUNAS),
            fn (string $coluna) => Schema::hasColumn('invoicing_products', $coluna)
        ));

        if ($existentes === []) {
            return;
        }

        Schema::table('invoicing_products', function (Blueprint $table) use ($existentes) {
            $table->dropColumn($existentes);
        });
    }
};
