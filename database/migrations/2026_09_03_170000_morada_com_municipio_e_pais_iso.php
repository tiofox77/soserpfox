<?php

use App\Support\Geografia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A morada ganha município e bairro, e o país passa a código ISO.
 *
 * O país do cliente viaja para a AGT como `customerCountry`, que a DS.120
 * exige em ISO 3166-1 alfa-2. Estava a ser escrito à mão: um «Portugal»
 * chegava lá com oito caracteres num campo de dois.
 *
 * O QUE ESTA MIGRAÇÃO CONVERTE, e o que deixa em paz: converte só o que é
 * inequívoco — o nome exacto de um país, ou um código que já estava certo.
 * O que não reconhece FICA COMO ESTÁ e é listado pelo `geografia:diagnostico`.
 * Adivinhar o país de um documento fiscal é pior do que admitir que não se
 * sabe, e uma migração não é sítio para adivinhar.
 *
 * Aproveita ainda o que já lá está: uma `city` que seja o nome de um município
 * angolano passa também para a coluna do município.
 */
return new class extends Migration
{
    /** As três tabelas cuja morada sai em documentos fiscais. */
    private const TABELAS = ['tenants', 'invoicing_clients', 'invoicing_suppliers'];

    public function up(): void
    {
        // O DEFAULT DA COLUNA DIZIA «Portugal».
        //
        // Não é um detalhe: 67 empresas em produção têm esse país sem ninguém
        // o ter escolhido — o registo nem sequer pergunta o país. É a mesma
        // avaria do `agt_schema_version`, onde um DEFAULT na coluna anulava a
        // constante do código. Um valor por omissão errado espalha-se em
        // silêncio e passa a parecer um facto.
        //
        // Muda-se o default para o código do país da casa. O que já lá está
        // NÃO se toca aqui — ver o comando `geografia:pais-da-empresa`.
        if (Schema::hasColumn('tenants', 'country')) {
            DB::statement("ALTER TABLE `tenants` MODIFY `country` VARCHAR(255) NULL DEFAULT '" . Geografia::PAIS_PADRAO . "'");
        }

        foreach (self::TABELAS as $tabela) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $t) use ($tabela) {
                if (!Schema::hasColumn($tabela, 'province')) {
                    $t->string('province', 100)->nullable()->after('city');
                }
                if (!Schema::hasColumn($tabela, 'municipality')) {
                    $t->string('municipality', 100)->nullable()->after('province');
                }
                if (!Schema::hasColumn($tabela, 'neighbourhood')) {
                    $t->string('neighbourhood', 100)->nullable()->after('municipality');
                }
            });

            $this->arrumar($tabela);
        }
    }

    private function arrumar(string $tabela): void
    {
        $municipios = Geografia::todosOsMunicipios();

        DB::table($tabela)
            ->select('id', 'country', 'city', 'province', 'municipality')
            ->orderBy('id')
            ->chunkById(500, function ($linhas) use ($tabela, $municipios) {
                foreach ($linhas as $linha) {
                    $mudancas = [];

                    // 1) O país, só quando não há dúvida.
                    $iso = Geografia::normalizarPais($linha->country);
                    if ($iso !== null && $iso !== $linha->country) {
                        $mudancas['country'] = $iso;
                    }

                    $paisFinal = $mudancas['country'] ?? $linha->country;
                    $ehAngola  = $paisFinal === null || $paisFinal === '' || strtoupper((string) $paisFinal) === Geografia::PAIS_PADRAO;

                    if ($ehAngola) {
                        // 2) A província escrita de outra maneira — «Kwanza
                        //    Norte», «Huila» — passa à forma actual.
                        if (!empty($linha->province)) {
                            $p = Geografia::normalizarProvincia($linha->province);
                            if ($p !== $linha->province) {
                                $mudancas['province'] = $p;
                            }
                        }

                        // 3) A cidade que é um município passa também para lá,
                        //    e traz a província consigo quando ela falta.
                        $cidade = trim((string) $linha->city);
                        if (empty($linha->municipality) && $cidade !== '' && isset($municipios[$cidade])) {
                            $mudancas['municipality'] = $cidade;

                            if (empty($linha->province) && !isset($mudancas['province'])) {
                                $mudancas['province'] = $municipios[$cidade];
                            }
                        }
                    }

                    if ($mudancas) {
                        DB::table($tabela)->where('id', $linha->id)->update($mudancas);
                    }
                }
            });
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            // O país NÃO se desconverte: «AO» é mais correcto do que «Angola»
            // e desfazê-lo seria estragar de propósito. Só saem as colunas
            // novas, e só as que esta migração criou.
            Schema::table($tabela, function (Blueprint $t) use ($tabela) {
                foreach (['neighbourhood', 'municipality'] as $coluna) {
                    if (Schema::hasColumn($tabela, $coluna)) {
                        $t->dropColumn($coluna);
                    }
                }
            });
        }
    }
};
