<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O CIRCUITO DAS COMPRAS COM SEPARAÇÃO DE FUNÇÕES (27/09/2026).
 *
 * Pedido de um cliente: «Requisição → Encomenda → Solicitação de Pagamento →
 * Pagamento → Recepção → Fatura», cada etapa com o seu responsável e com o
 * dinheiro a sair da tesouraria. Faltavam três coisas:
 *
 *  · APROVAR A ENCOMENDA, por uma ou mais pessoas (a empresa escolhe quantas);
 *    `compras_aprovacoes` guarda quem aprovou ou recusou, quando e porquê.
 *  · PEDIR O PAGAMENTO À TESOURARIA (`compras_pagamentos`): quem compra pede,
 *    o tesoureiro paga — e o pagamento é um movimento de tesouraria de verdade,
 *    com recibo de compra, sem o tesoureiro mexer na encomenda.
 *  · AS REGRAS DA EMPRESA (`compras_definicoes`): quantas aprovações cada passo
 *    exige e quem é o tesoureiro por omissão. Zero aprovações = como até hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A encomenda ganha dois estados: à espera de aprovação, e aprovada
        // (pronta a enviar ao fornecedor).
        DB::statement("ALTER TABLE compras_encomendas MODIFY estado ENUM('rascunho','em_aprovacao','aprovada','enviada','confirmada','parcial','recebida','cancelada') NOT NULL DEFAULT 'rascunho'");

        Schema::table('compras_encomendas', function (Blueprint $table) {
            if (! Schema::hasColumn('compras_encomendas', 'ronda_aprovacao')) {
                // Cada envio para aprovação é uma ronda nova: recusada e
                // corrigida, a encomenda volta a precisar de todos os «sim».
                $table->unsignedInteger('ronda_aprovacao')->default(0)->after('estado');
                $table->timestamp('aprovada_em')->nullable()->after('ronda_aprovacao');
                $table->text('motivo_recusa')->nullable()->after('aprovada_em');
            }
        });

        Schema::create('compras_aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            // A encomenda ou o pedido de pagamento — o mesmo registo serve aos dois.
            $table->string('aprovavel_type', 80);
            $table->unsignedBigInteger('aprovavel_id');
            $table->unsignedInteger('ronda')->default(1);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('decisao', ['aprovado', 'recusado']);
            $table->text('comentario')->nullable();
            $table->timestamp('created_at')->nullable();

            // Uma pessoa, um voto por ronda.
            $table->unique(['aprovavel_type', 'aprovavel_id', 'ronda', 'user_id'], 'compras_aprovacoes_voto_unico');
            $table->index(['tenant_id', 'aprovavel_type', 'aprovavel_id'], 'compras_aprovacoes_do_documento');
        });

        Schema::create('compras_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('numero');
            $table->foreignId('encomenda_id')->constrained('compras_encomendas')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('invoicing_suppliers')->onDelete('cascade');
            $table->decimal('valor', 15, 2);
            $table->string('forma_sugerida', 30)->nullable();
            $table->date('data_limite')->nullable();
            $table->text('notas')->nullable();
            $table->enum('estado', ['em_aprovacao', 'por_pagar', 'pago', 'recusado', 'cancelado'])->default('por_pagar');
            $table->unsignedInteger('ronda')->default(1);
            $table->foreignId('pedido_por')->constrained('users')->onDelete('cascade');
            // O tesoureiro a quem o pedido foi entregue (opcional): é ele que
            // o sino avisa. Sem ninguém, avisa quem tem a permissão de pagar.
            $table->foreignId('tesoureiro_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('decidido_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('decidido_em')->nullable();
            // O pagamento feito.
            $table->foreignId('pago_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('pago_em')->nullable();
            $table->string('forma_paga', 30)->nullable();
            $table->string('referencia')->nullable();
            // O rasto do dinheiro: o recibo de compra e o movimento de tesouraria.
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
            $table->index(['tenant_id', 'estado']);
            $table->index('encomenda_id');
        });

        Schema::create('compras_definicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->onDelete('cascade');
            // 0 = o passo não precisa de aprovação (como até 27/09/2026).
            $table->unsignedTinyInteger('aprovacoes_encomenda')->default(0);
            $table->unsignedTinyInteger('aprovacoes_pagamento')->default(0);
            $table->foreignId('tesoureiro_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_definicoes');
        Schema::dropIfExists('compras_pagamentos');
        Schema::dropIfExists('compras_aprovacoes');

        Schema::table('compras_encomendas', function (Blueprint $table) {
            $table->dropColumn(['ronda_aprovacao', 'aprovada_em', 'motivo_recusa']);
        });

        DB::statement("UPDATE compras_encomendas SET estado = 'rascunho' WHERE estado IN ('em_aprovacao','aprovada')");
        DB::statement("ALTER TABLE compras_encomendas MODIFY estado ENUM('rascunho','enviada','confirmada','parcial','recebida','cancelada') NOT NULL DEFAULT 'rascunho'");
    }
};
