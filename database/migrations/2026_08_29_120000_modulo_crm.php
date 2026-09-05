<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O módulo CRM — do placeholder ao módulo.
 *
 * As rotas /crm/* existiam desde o início a apontar para "em construção", e o
 * módulo está ACTIVO e vendável nos planos: uma empresa podia comprá-lo e
 * receber quatro páginas de obras. Isto dá-lhe o que as rotas prometem:
 * leads, oportunidades e o funil.
 *
 * O desenho segue a espinha do resto do sistema: tenant_id em tudo, o lead
 * converte-se num CLIENTE DA FACTURAÇÃO (invoicing_clients) — não numa
 * segunda lista de clientes que divergia da primeira — e a oportunidade
 * ganha liga-se ao documento que a concretizou.
 */
return new class extends Migration
{
    public function up(): void
    {
        // As etapas do funil. Por empresa e ordenáveis: cada casa vende à sua
        // maneira. Provisionadas por omissão à primeira utilização (o mesmo
        // padrão das condições de pagamento).
        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // A probabilidade por omissão de quem entra nesta etapa — dá o
            // valor ponderado do funil sem obrigar ninguém a estimar à mão.
            $table->unsignedTinyInteger('probability')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'sort_order']);
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 150);
            $table->string('company', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            // De onde veio: é o campo que responde "onde vale a pena gastar
            // em publicidade" — a pergunta que justifica ter CRM.
            $table->string('source', 30)->default('outro');
            $table->string('status', 20)->default('novo'); // novo|contactado|qualificado|convertido|perdido
            $table->text('notes')->nullable();
            $table->string('lost_reason', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            // O cliente em que se tornou. Aponta para invoicing_clients: UM
            // cadastro de clientes, não dois a divergir.
            $table->unsignedBigInteger('converted_client_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title', 200);
            $table->unsignedBigInteger('stage_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(50);
            $table->date('expected_close_date')->nullable();
            $table->string('status', 10)->default('open'); // open|won|lost
            $table->string('lost_reason', 255)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // O funil lê-se sempre assim: as abertas desta empresa, por etapa.
            $table->index(['tenant_id', 'status', 'stage_id']);
            $table->index(['tenant_id', 'expected_close_date']);
        });

        // As interacções e tarefas: chamadas, reuniões, o que ficou de se
        // fazer. É o que separa um CRM de uma lista de nomes — a resposta a
        // "quando foi a última vez que falámos com este cliente?".
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('type', 20)->default('nota'); // chamada|reuniao|email|whatsapp|visita|nota|tarefa
            $table->string('subject', 200);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            // Tarefa por fazer tem prazo; interacção acontecida tem feito=1.
            $table->timestamp('due_at')->nullable();
            $table->boolean('done')->default(false);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'done', 'due_at']);
            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_stages');
    }
};
