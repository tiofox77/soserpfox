<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Projetos: o projeto, as suas tarefas, e as horas que lá foram.
 *
 * O projeto não é documento fiscal — não tem série nem hash. O que o torna
 * diferente de uma lista de tarefas qualquer é o dinheiro: um projeto tem
 * orçamento, e as horas lançadas contra ele consomem-no. Quando se factura,
 * são essas horas que viram linhas de factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projetos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('codigo');
            $table->string('nome');
            // Um projeto interno não tem cliente — e continua a ser um projeto.
            $table->foreignId('client_id')->nullable()->constrained('invoicing_clients')->onDelete('set null');
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('estado', ['rascunho', 'activo', 'em_pausa', 'concluido', 'cancelado'])->default('rascunho');
            $table->date('data_inicio')->nullable();
            $table->date('data_fim_prevista')->nullable();
            $table->date('data_fim_real')->nullable();
            $table->decimal('orcamento', 15, 2)->nullable();
            // Preço/hora por omissão do projeto. Cada lançamento CONGELA o seu,
            // para uma actualização de tabela não reescrever o passado.
            $table->decimal('valor_hora', 15, 2)->nullable();
            $table->text('descricao')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Número gerado por empresa (PRJ-AAAA-NNNNNN) — unicidade por
            // tenant, não global. Ver tenant-scoped-unique-indexes.
            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'estado']);
        });

        Schema::create('projeto_tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('projeto_id')->constrained('projetos')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('estado', ['por_fazer', 'em_curso', 'bloqueada', 'concluida', 'cancelada'])->default('por_fazer');
            $table->enum('prioridade', ['baixa', 'normal', 'alta', 'urgente'])->default('normal');
            $table->date('prazo')->nullable();
            $table->decimal('horas_estimadas', 8, 2)->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->integer('ordem')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'projeto_id', 'estado']);
            $table->index(['tenant_id', 'responsavel_id', 'estado']);
        });

        Schema::create('projeto_horas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('projeto_id')->constrained('projetos')->onDelete('cascade');
            // As horas podem ser do projeto sem serem de uma tarefa concreta
            // (uma reunião, uma deslocação). Se a tarefa desaparecer, as horas
            // ficam — foram trabalhadas na mesma.
            $table->foreignId('tarefa_id')->nullable()->constrained('projeto_tarefas')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('data');
            $table->decimal('horas', 6, 2);
            $table->string('descricao')->nullable();
            $table->boolean('facturavel')->default(true);
            // Congelado no lançamento: facturar mais tarde não pode mudar o
            // que a hora valia no dia em que foi trabalhada.
            $table->decimal('valor_hora', 15, 2)->nullable();
            // Depois de facturada, a linha é história: não se edita nem apaga.
            $table->timestamp('facturado_em')->nullable();
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'projeto_id', 'data']);
            $table->index(['tenant_id', 'user_id', 'data']);
            $table->index(['tenant_id', 'facturado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projeto_horas');
        Schema::dropIfExists('projeto_tarefas');
        Schema::dropIfExists('projetos');
    }
};
