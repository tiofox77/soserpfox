<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria: `accounting_logs` → `audit_trail`.
 *
 * A tabela `accounting_logs` foi criada em 2025-10 e nunca chegou a ser usada
 * (0 linhas, nenhuma referência no código além de um model vazio). O esquema já
 * era genérico, por isso reaproveita-se em vez de criar uma quinta tabela de
 * registo — já há quatro dispersas (product_activity_logs,
 * restaurant_order_events, workshop_work_order_history, invoicing_import_history).
 *
 * Três decisões do esquema original que TÊM de cair:
 *
 *  1. `tenant_id` com FK e cascadeOnDelete — apagar uma empresa levaria consigo
 *     a auditoria que justifica os dados apagados. Fica sem FK, de propósito.
 *  2. `user_id` NOT NULL — impede registar o que vem da consola, das filas ou de
 *     um visitante anónimo (portal público, reserva online).
 *  3. `morphs()` NOT NULL — impede registar actos sem modelo associado: login,
 *     exportação, impressão, execução de comando.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Renomear preserva os índices e evita uma tabela a mais.
        if (Schema::hasTable('accounting_logs') && !Schema::hasTable('audit_trail')) {
            Schema::rename('accounting_logs', 'audit_trail');
        }

        // As FK e a nulidade herdadas têm de ser desfeitas antes de tudo o resto.
        $this->largarChavesEstrangeiras();

        Schema::table('audit_trail', function (Blueprint $table) {
            // ── Acto ──
            // `action` passa a `event`; o tipo/id do alvo passam a poder ser
            // nulos (login e exportação não têm modelo).
            $table->renameColumn('action', 'event');

            // Rótulo CONGELADO no momento do acto (nº da factura, nome do
            // artigo). Sem ele, um documento apagado deixa a linha ilegível.
            $table->string('auditable_label')->nullable()->after('auditable_id');

            // ── Actor ──
            $table->string('actor_type', 20)->default('user')->after('event');
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('channel', 20)->default('web')->after('actor_name');

            // Personificação: guarda-se o par real+efectivo, senão o acto fica
            // atribuído a quem não o praticou.
            $table->unsignedBigInteger('impersonator_id')->nullable()->after('channel');

            // ── Contexto ──
            // Empresa ACTIVA na sessão. Diferente de tenant_id (que vem do
            // REGISTO): divergirem é sinal de contaminação entre empresas.
            $table->unsignedBigInteger('context_tenant_id')->nullable()->after('tenant_id');
            $table->string('route')->nullable()->after('user_agent');
            $table->char('request_id', 26)->nullable()->after('route');
            $table->json('metadata')->nullable()->after('new_values');

            // ── Selagem ──
            // Sequência por empresa + hash encadeado: torna a reescrita
            // DETECTÁVEL. Não a impede — quem tem FTP e acesso à base reescreve
            // na mesma; o que não consegue é fazê-lo sem partir a cadeia.
            $table->unsignedBigInteger('sequence')->nullable()->after('request_id');
            $table->char('hash', 64)->nullable()->after('sequence');
            $table->char('hash_previous', 64)->nullable()->after('hash');

            // Índices para as consultas reais: por empresa/data, por documento,
            // por actor, e para colar as N linhas de um mesmo pedido.
            $table->index(['tenant_id', 'auditable_type', 'auditable_id'], 'idx_audit_alvo');
            $table->index(['tenant_id', 'user_id', 'created_at'], 'idx_audit_actor');
            $table->index('request_id', 'idx_audit_pedido');
            $table->unique(['tenant_id', 'sequence'], 'idx_audit_sequencia');
        });

        // Nulidade: o Laravel sem doctrine/dbal não altera colunas existentes.
        DB::statement('ALTER TABLE audit_trail MODIFY user_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE audit_trail MODIFY auditable_type VARCHAR(255) NULL');
        DB::statement('ALTER TABLE audit_trail MODIFY auditable_id BIGINT UNSIGNED NULL');
    }

    /**
     * Larga as FK herdadas. Os nomes variam consoante o que o MySQL gerou, por
     * isso lêem-se do information_schema em vez de se adivinharem.
     */
    private function largarChavesEstrangeiras(): void
    {
        $chaves = DB::select(
            'SELECT CONSTRAINT_NAME AS nome
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "audit_trail"
                AND CONSTRAINT_TYPE = "FOREIGN KEY"'
        );

        foreach ($chaves as $chave) {
            DB::statement("ALTER TABLE audit_trail DROP FOREIGN KEY `{$chave->nome}`");
        }
    }

    public function down(): void
    {
        Schema::table('audit_trail', function (Blueprint $table) {
            $table->dropIndex('idx_audit_alvo');
            $table->dropIndex('idx_audit_actor');
            $table->dropIndex('idx_audit_pedido');
            $table->dropUnique('idx_audit_sequencia');

            $table->dropColumn([
                'auditable_label', 'actor_type', 'actor_name', 'channel',
                'impersonator_id', 'context_tenant_id', 'route', 'request_id',
                'metadata', 'sequence', 'hash', 'hash_previous',
            ]);

            $table->renameColumn('event', 'action');
        });

        if (Schema::hasTable('audit_trail') && !Schema::hasTable('accounting_logs')) {
            Schema::rename('audit_trail', 'accounting_logs');
        }
    }
};
