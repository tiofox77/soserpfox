<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos de proposta: o desenho de um orçamento, separado dos seus números.
 *
 * O orçamento já tem tudo o que é facto — cliente, linhas, impostos, totais.
 * O que faltava era a PROPOSTA à volta disso: capa, âmbito do trabalho,
 * metodologia, prazos, equipa, condições. É isso que ganha um concurso, e é
 * isso que uma empresa de informática ou de media escreve de novo a cada
 * cliente, em Word, fora do sistema.
 *
 * Os blocos ficam em JSON e não em tabelas próprias de propósito: são uma
 * lista ordenada de peças heterogéneas, cada uma com as suas opções, e o que
 * se faz com elas é sempre ler tudo de uma vez para desenhar a página. Uma
 * tabela por tipo de bloco daria dez junções para montar um PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('nome');
            $table->string('descricao')->nullable();
            // Sector a que o modelo se destina (informatica, media, consultoria…).
            // Serve para arrumar a lista, não para restringir o uso.
            $table->string('sector', 40)->nullable();

            $table->json('blocos');    // a lista ordenada de secções
            $table->json('estilos');   // cor, tipo de letra, cabeçalho/rodapé

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A lista é sempre "os modelos activos desta empresa".
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('invoicing_sales_quotes', function (Blueprint $table) {
            // Nulo = sai o desenho antigo. Nenhum orçamento já feito muda de
            // aspecto por causa desta migração.
            $table->foreignId('quote_template_id')->nullable()->after('warehouse_id')
                ->constrained('quote_templates')->nullOnDelete();

            // Os textos que o utilizador escreveu PARA ESTE orçamento e que
            // preenchem os campos livres do modelo ({{proposta.ambito}}, …).
            $table->json('campos_proposta')->nullable()->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_quotes', function (Blueprint $table) {
            $table->dropForeign(['quote_template_id']);
            $table->dropColumn(['quote_template_id', 'campos_proposta']);
        });

        Schema::dropIfExists('quote_templates');
    }
};
