<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfis de cosmética e de mercearia: interruptores nas definições e campos
 * próprios no catálogo.
 *
 * O catálogo já sabia identificar medicamentos e roupa, mas quem vende cremes ou
 * bens alimentares distingue os artigos por outras coisas. Duas embalagens do
 * mesmo champô só se distinguem pelo conteúdo líquido — sem esse campo, "Champô
 * Suave" aparece duas vezes na lista e ninguém sabe qual é o de 200ml.
 *
 * Porquê os campos que aqui estão, e não outros:
 *
 *  · net_content é PARTILHADO pelos dois ramos. É a única coisa que separa duas
 *    embalagens do mesmo produto, tanto num creme como num pacote de arroz.
 *
 *  · pao_months é o símbolo do frasco aberto com "12M" no rótulo: quanto tempo
 *    o produto dura DEPOIS de aberto. É informação diferente do prazo de
 *    validade por abrir (que vive no lote), e uma loja precisa dos dois números
 *    ao mesmo tempo — por isso é coluna nova e não reaproveita a validade.
 *
 *  · inci_ingredients é a lista normalizada de ingredientes. É o que responde a
 *    "isto tem parabenos?" sem ir buscar a caixa ao armazém. Texto e não string:
 *    uma lista INCI real passa facilmente dos mil caracteres.
 *
 *  · storage_conditions diz a quem arruma se o artigo vai ao frio. Aparece no
 *    stock, não só na ficha — quem recebe uma palete precisa de o saber antes de
 *    abrir artigo a artigo.
 *
 *  · allergens e origin_country são informação obrigatória de rótulo alimentar e
 *    pergunta de balcão diária.
 *
 * A cor já existe (veio do vestuário) e serve de tom na cosmética: não há coluna
 * nova para a mesma coisa.
 *
 * Nada disto é obrigatório. A esmagadora maioria dos artigos não é nem cosmética
 * nem mercearia, e uma coluna NOT NULL partia o catálogo de toda a gente.
 *
 * Guardas coluna a coluna porque esta base já levou migrações corridas à mão —
 * se uma rebentar a meio, o que já foi aplicado não volta a ser tentado.
 */
return new class extends Migration
{
    /** Interruptores de perfil, nas definições de faturação. */
    private const PERFIS = ['profile_cosmetics', 'profile_grocery'];

    /**
     * Colunas novas do catálogo por ordem de inserção. A chave é o nome da
     * coluna e o valor a coluna que a precede, para o esquema sair legível
     * num `describe`.
     */
    private const CAMPOS = [
        // Partilhado pelos dois ramos
        'net_content'        => 'material',
        // Cosmética
        'pao_months'         => 'net_content',
        'inci_ingredients'   => 'pao_months',
        // Mercearia
        'storage_conditions' => 'inci_ingredients',
        'allergens'          => 'storage_conditions',
        'origin_country'     => 'allergens',
    ];

    private const INDICE_CONSERVACAO = 'invoicing_products_tenant_storage_index';

    public function up(): void
    {
        $this->criarPerfis();
        $this->criarCampos();
    }

    /**
     * Dois booleanos e não uma escolha única, pela mesma razão dos perfis que já
     * existem: uma mercearia com prateleira de cosmética é as duas coisas ao
     * mesmo tempo, e uma escolha única obrigava-a a mentir. "Normal" continua a
     * ser nenhum ligado — não é um terceiro valor a guardar.
     */
    private function criarPerfis(): void
    {
        if (!Schema::hasTable('invoicing_settings')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            // O `after` só entra se a âncora existir mesmo: noutra base o
            // esquema pode estar noutro estado, e um `after` para uma coluna
            // inexistente é erro de SQL, não um aviso.
            $ancora = Schema::hasColumn('invoicing_settings', 'profile_clothing')
                ? 'profile_clothing'
                : null;

            if (!Schema::hasColumn('invoicing_settings', 'profile_cosmetics')) {
                $coluna = $table->boolean('profile_cosmetics')
                    ->default(false)
                    ->comment('Trabalha com cosmética');

                if ($ancora !== null) {
                    $coluna->after($ancora);
                }
            }

            if (!Schema::hasColumn('invoicing_settings', 'profile_grocery')) {
                // Encosta ao perfil de cosmética, que ou já lá estava, ou é
                // acrescentado nesta mesma instrução.
                $table->boolean('profile_grocery')
                    ->default(false)
                    ->after('profile_cosmetics')
                    ->comment('Trabalha com mercearia');
            }
        });
    }

    private function criarCampos(): void
    {
        if (!Schema::hasTable('invoicing_products')) {
            return;
        }

        Schema::table('invoicing_products', function (Blueprint $table) {
            // A âncora da primeira coluna pode não existir se a migração do
            // vestuário nunca correu nesta base; as restantes apontam sempre
            // para uma coluna criada nesta mesma instrução.
            $depois = fn (string $coluna) => self::CAMPOS[$coluna];

            if (!Schema::hasColumn('invoicing_products', 'net_content')) {
                $coluna = $table->string('net_content', 40)->nullable()
                    ->comment('Conteúdo líquido: 50ml, 200g, 1kg');

                if (Schema::hasColumn('invoicing_products', $depois('net_content'))) {
                    $coluna->after($depois('net_content'));
                }
            }

            if (!Schema::hasColumn('invoicing_products', 'pao_months')) {
                $table->unsignedSmallInteger('pao_months')->nullable()
                    ->after($depois('pao_months'))
                    ->comment('Meses após abertura (PAO)');
            }

            if (!Schema::hasColumn('invoicing_products', 'inci_ingredients')) {
                // Texto: uma lista INCI completa não cabe num varchar.
                $table->text('inci_ingredients')->nullable()
                    ->after($depois('inci_ingredients'))
                    ->comment('Lista INCI');
            }

            if (!Schema::hasColumn('invoicing_products', 'storage_conditions')) {
                $table->string('storage_conditions', 30)->nullable()
                    ->after($depois('storage_conditions'))
                    ->comment('ambiente | refrigerado | congelado');
            }

            if (!Schema::hasColumn('invoicing_products', 'allergens')) {
                $table->string('allergens', 255)->nullable()
                    ->after($depois('allergens'))
                    ->comment('Alergénios');
            }

            if (!Schema::hasColumn('invoicing_products', 'origin_country')) {
                $table->string('origin_country', 60)->nullable()
                    ->after($depois('origin_country'))
                    ->comment('País de origem');
            }
        });

        // Índice só depois da coluna existir — e com o tenant_id à cabeça, que é
        // o filtro que nunca falta. O stock lista "o que vai ao frio" a cada
        // recepção de mercadoria; num catálogo de vinte mil artigos isso sem
        // índice é varrimento completo da tabela.
        Schema::table('invoicing_products', function (Blueprint $table) {
            if (
                Schema::hasColumn('invoicing_products', 'storage_conditions')
                && !Schema::hasIndex('invoicing_products', self::INDICE_CONSERVACAO)
            ) {
                $table->index(['tenant_id', 'storage_conditions'], self::INDICE_CONSERVACAO);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoicing_products')) {
            // O índice cai primeiro: o MySQL recusa largar uma coluna que ainda
            // faz parte de um índice composto.
            Schema::table('invoicing_products', function (Blueprint $table) {
                if (Schema::hasIndex('invoicing_products', self::INDICE_CONSERVACAO)) {
                    $table->dropIndex(self::INDICE_CONSERVACAO);
                }
            });

            $campos = array_values(array_filter(
                array_keys(self::CAMPOS),
                fn (string $coluna) => Schema::hasColumn('invoicing_products', $coluna)
            ));

            if ($campos !== []) {
                Schema::table('invoicing_products', function (Blueprint $table) use ($campos) {
                    $table->dropColumn($campos);
                });
            }
        }

        if (!Schema::hasTable('invoicing_settings')) {
            return;
        }

        $perfis = array_values(array_filter(
            self::PERFIS,
            fn (string $coluna) => Schema::hasColumn('invoicing_settings', $coluna)
        ));

        if ($perfis === []) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) use ($perfis) {
            $table->dropColumn($perfis);
        });
    }
};
