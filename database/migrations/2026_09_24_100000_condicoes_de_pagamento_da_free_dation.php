<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AS CONDIÇÕES DE PAGAMENTO DA FREE DATION (#80), por ordem do dono (24/09/2026).
 *
 * As proformas e os orçamentos passaram a sair com as condições da empresa no
 * rodapé, e a nascer com elas escritas (Definições › Textos por omissão ›
 * Condições de pagamento). Este é o texto que a Free Dation usa; muda-se
 * depois no ecrã das definições, ou em cada documento.
 *
 * Só escreve se a empresa for a Free Dation e se o campo estiver vazio: não
 * apaga o que alguém já lá tenha posto, e correr duas vezes não faz nada.
 */
return new class extends Migration
{
    private const EMPRESA = 80;

    private const CONDICOES = <<<'TXT'
A) A primeira prestação 70% com a adjudicação e antes do início do trabalho, 30% após a conclusão do trabalho.
B) Os orçamentos que não impliquem qualquer encargo imediato para o cliente caducam ao fim de 15 dias após a sua execução.
C) O cliente não poderá suspender, anular, adiar ou modificar o conteúdo do pedido sem consulta prévia e confirmação por escrito da Free Dation Produções.
D) A partir da 3.ª reedição do trabalho, quaisquer alterações adicionais devem ser devidamente orçamentadas pela Free Dation Lda., em conformidade com o cliente.
E) PRAZOS DE ENTREGA:
Após a realização leva 15 dias para a edição do mesmo, dependendo da complexidade do projecto; caso o serviço seja com urgência tem uma taxa de 5% do valor.
As circunstâncias de força maior ou eventos fortuitos de causa insuperável que impeçam o normal desenrolar do trabalho ilibam a Free Dation, Lda. da responsabilidade sobre o incumprimento do prazo de entrega.
F) PRAZOS DE EXECUÇÃO:
Quando não vierem especificados, de forma clara, prazos de filmagem e de entrega dos projectos, estes terão de ser determinados na altura de adjudicação do trabalho, num espaço até 15 dias úteis para a sua execução. Esta determinação de prazos poderá levar a uma alteração do valor do orçamento, que será informada no momento de aprovação do orçamento.
TXT;

    public function up(): void
    {
        $empresa = DB::table('tenants')->where('id', self::EMPRESA)->value('name');

        if (! $empresa || stripos($empresa, 'dation') === false) {
            Log::info('Condições da Free Dation: a empresa #' . self::EMPRESA . ' não é a Free Dation; nada feito.', ['nome' => $empresa]);

            return;
        }

        $escritas = DB::table('invoicing_settings')
            ->where('tenant_id', self::EMPRESA)
            ->where(fn ($q) => $q->whereNull('default_terms')->orWhere('default_terms', ''))
            ->update(['default_terms' => self::CONDICOES, 'updated_at' => now()]);

        Log::info('Condições da Free Dation: ' . ($escritas ? 'gravadas nas definições.' : 'já havia texto (ou não há definições); nada feito.'));
    }

    public function down(): void
    {
        // Só desfaz se o texto for ainda o que aqui se pôs.
        DB::table('invoicing_settings')
            ->where('tenant_id', self::EMPRESA)
            ->where('default_terms', self::CONDICOES)
            ->update(['default_terms' => null, 'updated_at' => now()]);
    }
};
