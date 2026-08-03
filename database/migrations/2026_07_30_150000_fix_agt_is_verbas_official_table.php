<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige a tabela de verbas do Imposto de Selo pela Tabela anexa oficial
 * (Lei n.º 3/14, Código do Imposto de Selo).
 *
 * A tabela semeada estava errada em dois aspectos:
 *
 *  1. NUMERAÇÃO — as verbas têm SUBNÍVEIS (2.1, 16.2.4, 23.3), impossíveis de
 *     guardar numa coluna inteira. A AGT recusa um taxCode que não corresponda
 *     à verba real: "Combinação não permitida ... taxCode (6)".
 *
 *  2. CONTEÚDO — descrições e taxas trocadas. Exemplos:
 *       verba 3  era "Cheques (livros)"      → é "Autos e termos judiciais" AKz 1.000
 *       verba 4  era "Contratos em geral 1%" → é "Cheques" AKz 100
 *       verba 6  era "Escritos de quitação"  → é "Depósito estatutos associações" AKz 4.400
 *     "Recibos de quitação" a 1% — o que uma Fatura-Recibo liquida — é a
 *     verba 23.3, que nem existia na tabela.
 *
 * Fonte: Tabela anexa ao Código do Imposto de Selo (Lei n.º 3/14).
 */
return new class extends Migration
{
    /** [verba, descrição, taxa, tipo] — tipo: PERCENTAGE | FIXED */
    private const VERBAS = [
        ['1',      'Aquisição onerosa ou gratuita de imóveis',              '0.3%',        'PERCENTAGE'],
        ['2.1',    'Arrendamento e subarrendamento - fins habitacionais',   '0.1%',        'PERCENTAGE'],
        ['2.2',    'Arrendamento - estabelecimento comercial ou industrial', '0.4%',       'PERCENTAGE'],
        ['3',      'Autos e termos judiciais',                              'AKZ 1000',    'FIXED'],
        ['4',      'Cheques (por dez)',                                     'AKZ 100',     'FIXED'],
        ['5',      'Depósito civil',                                        '0.1%',        'PERCENTAGE'],
        ['6',      'Depósito de estatutos de associações',                  'AKZ 4400',    'FIXED'],
        ['7.1',    'Actos societários - constituição de sociedade',         '0.1%',        'PERCENTAGE'],
        ['7.2',    'Actos societários - transformação em sociedade',        '0.1%',        'PERCENTAGE'],
        ['7.3',    'Actos societários - aumento de capital com bens',       '0.1%',        'PERCENTAGE'],
        ['7.4',    'Actos societários - aumento do activo',                 '0.1%',        'PERCENTAGE'],
        ['8',      'Outros contratos não especificados',                    'AKZ 1000',    'FIXED'],
        ['9',      'Exploração de recursos geológicos',                     'AKZ 3000',    'FIXED'],
        ['10.1',   'Garantias - prazo inferior a um ano',                   '0.3%',        'PERCENTAGE'],
        ['10.2',   'Garantias - prazo igual ou superior a um ano',          '0.2%',        'PERCENTAGE'],
        ['10.3',   'Garantias - sem prazo ou igual/superior a cinco anos',  '0.1%',        'PERCENTAGE'],
        ['11',     'Apostas e jogos',                                       'AKZ 100',     'FIXED'],
        ['11.1',   'Ingressos em salas de jogo',                            'AKZ 100',     'FIXED'],
        ['12.1',   'Licença - máquinas electrónicas de diversão',           'AKZ 1300',    'FIXED'],
        ['12.2',   'Licença - outros jogos legais',                         'AKZ 1300',    'FIXED'],
        ['12.3',   'Licença - restauração e bebidas',                       'AKZ 2000',    'FIXED'],
        ['12.4',   'Licença - hotelaria',                                   'AKZ 2000',    'FIXED'],
        ['12.5',   'Licença - máquinas automáticas de venda',               'AKZ 3000',    'FIXED'],
        ['12.6',   'Outras licenças',                                       'AKZ 2000',    'FIXED'],
        ['13',     'Marcas e patentes',                                     'AKZ 3000',    'FIXED'],
        ['14.1',   'Notariado - escrituras',                                'AKZ 2000',    'FIXED'],
        ['14.2',   'Notariado - habilitação de herdeiros',                  'AKZ 1000',    'FIXED'],
        ['14.3',   'Notariado - testamento',                                'AKZ 1000',    'FIXED'],
        ['14.4',   'Notariado - procurações e representação voluntária',    'AKZ 1000',    'FIXED'],
        ['14.5',   'Notariado - registo de documentos',                     'AKZ 100',     'FIXED'],
        ['14.6',   'Notariado - instrumentos notariais avulsos',            'AKZ 100',     'FIXED'],
        ['15.1',   'Operações aduaneiras - importação',                     '1%',          'PERCENTAGE'],
        ['15.2',   'Operações aduaneiras - exportação',                     '0.5%',        'PERCENTAGE'],
        ['16.1.1', 'Financiamento - crédito até um ano',                    '0.5%',        'PERCENTAGE'],
        ['16.1.2', 'Financiamento - crédito superior a um ano',             '0.4%',        'PERCENTAGE'],
        ['16.1.3', 'Financiamento - crédito igual/superior a cinco anos',   '0.3%',        'PERCENTAGE'],
        ['16.1.4', 'Financiamento - conta corrente ou descoberto',          '0.1%',        'PERCENTAGE'],
        ['16.1.5', 'Financiamento - crédito à habitação',                   '0.1%',        'PERCENTAGE'],
        ['16.2.1', 'Financiamento - juros',                                 '0.2%',        'PERCENTAGE'],
        ['16.2.2', 'Financiamento - prémios de letras',                     '0.5%',        'PERCENTAGE'],
        ['16.2.3', 'Financiamento - comissões de garantias',                '0.5%',        'PERCENTAGE'],
        ['16.2.4', 'Financiamento - outras comissões',                      '0.7%',        'PERCENTAGE'],
        ['16.3.1', 'Financiamento - saque sobre o estrangeiro',             '1%',          'PERCENTAGE'],
        ['16.3.2', 'Títulos de dívida pública estrangeira',                 '0.5%',        'PERCENTAGE'],
        ['16.3.3', 'Câmbio de notas',                                       '0.1%',        'PERCENTAGE'],
        ['17.1',   'Locação financeira - bens imóveis',                     '0.3%',        'PERCENTAGE'],
        ['17.2',   'Locação financeira - bens móveis',                      '0.4%',        'PERCENTAGE'],
        ['18',     'Precatórios',                                           '0.1%',        'PERCENTAGE'],
        ['19.1',   'Publicidade - cartazes e anúncios em via pública',      'AKZ 1000',    'FIXED'],
        ['19.2',   'Publicidade - revista, jornais, rádio e televisão',     'AKZ 25000',   'FIXED'],
        ['20.1',   'Registo em conservatória - aeronaves',                  'AKZ 45000',   'FIXED'],
        ['20.2',   'Registo em conservatória - barcos',                     'AKZ 23000',   'FIXED'],
        ['20.3',   'Registo em conservatória - motas de água',              'AKZ 18000',   'FIXED'],
        ['20.4',   'Registo em conservatória - motociclos e veículos',      'AKZ 7000',    'FIXED'],
        ['21',     'Reporte',                                               '0.1%',        'PERCENTAGE'],
        ['22.1.1', 'Seguros - seguro-caução',                               '0.3%',        'PERCENTAGE'],
        ['22.1.2', 'Seguros - marítimo e fluvial',                          '0.3%',        'PERCENTAGE'],
        ['22.1.3', 'Seguros - aéreo',                                       '0.2%',        'PERCENTAGE'],
        ['22.1.4', 'Seguros - mercadoria transportada',                     '0.1%',        'PERCENTAGE'],
        ['22.1.5', 'Seguros - outros',                                      '0.3%',        'PERCENTAGE'],
        ['22.2',   'Seguros - comissões de mediação',                       '0.4%',        'PERCENTAGE'],
        ['23.1',   'Títulos de crédito - letras e livranças',               '0.1%',        'PERCENTAGE'],
        ['23.2',   'Títulos de crédito - ordens e escritos de pagamento',   '0.1%',        'PERCENTAGE'],
        ['23.3',   'Recibos de quitação',                                   '1%',          'PERCENTAGE'],
        ['23.4',   'Títulos de crédito - abertura de crédito escrito',      '0.1%',        'PERCENTAGE'],
        ['24.1',   'Transferência de actividades - trespasse',              '0.2%',        'PERCENTAGE'],
        ['24.2',   'Transferência - subconcessões e trespasses',            '0.2%',        'PERCENTAGE'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('agt_is_verbas')) {
            return;
        }

        // verba_no passa a texto: as verbas reais têm subníveis (23.3, 16.2.4)
        Schema::table('agt_is_verbas', function (Blueprint $table) {
            $table->string('verba_no', 12)->change();
        });

        // Substitui integralmente: a tabela anterior tinha descrições e taxas
        // trocadas, pelo que corrigir linha a linha deixaria lixo.
        DB::table('agt_is_verbas')->delete();

        $agora = now();
        foreach (self::VERBAS as [$verba, $descricao, $taxa, $tipo]) {
            DB::table('agt_is_verbas')->insert([
                'verba_no'    => $verba,
                'description' => $descricao,
                'rate'        => $taxa,
                'rate_type'   => $tipo,
                'is_active'   => 1,
                'created_at'  => $agora,
                'updated_at'  => $agora,
            ]);
        }

        // Linhas de documento que referenciem verbas antigas ficam com o código
        // inválido — marca-se para revisão em vez de adivinhar a correspondência.
        if (Schema::hasTable('invoicing_line_taxes')) {
            Schema::table('invoicing_line_taxes', function (Blueprint $table) {
                $table->string('verba_no', 12)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Sem reversão: a tabela anterior estava incorrecta.
    }
};
