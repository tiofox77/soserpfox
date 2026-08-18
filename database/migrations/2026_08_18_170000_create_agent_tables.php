<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas da API do agente externo.
 *
 * Ficam fora do modelo multi-tenant de propósito: o agente é da PLATAFORMA,
 * não de uma empresa, e as suas credenciais não podem ser alcançadas por
 * nenhum tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Credenciais ──────────────────────────────────────────────
        Schema::create('agent_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);

            // O prefixo fica em claro só para localizar a linha; o segredo
            // guarda-se em sha256 e compara-se com hash_equals.
            $t->string('prefix', 32)->unique();
            $t->string('token_hash', 64);

            // O humano que RESPONDE pelo agente. É ele que aparece na
            // trilha de auditoria — um agente não é responsável por nada.
            $t->foreignId('owner_user_id')->constrained('users');

            $t->json('scopes');
            $t->json('allowed_ips')->nullable();

            // Obrigatório: um token de agente não vive para sempre.
            $t->timestamp('expires_at');

            $t->timestamp('last_used_at')->nullable();
            $t->string('last_ip', 45)->nullable();

            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users');
            $t->string('revoked_reason', 255)->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();

            $t->index(['revoked_at', 'expires_at']);
        });

        // ── Idempotência ─────────────────────────────────────────────
        // Mesmo padrão do checkout do restaurante: chave + índice único,
        // porque verificar antes de escrever tem janela de corrida.
        Schema::create('agent_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_token_id')->constrained('agent_tokens')->cascadeOnDelete();
            $t->uuid('idempotency_key');
            $t->string('rota', 120);
            $t->string('corpo_hash', 64);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->json('resposta')->nullable();
            $t->timestamps();

            $t->unique(['agent_token_id', 'idempotency_key'], 'agent_idempotency_unique');
        });

        // ── Registo de mensagens enviadas ────────────────────────────
        // A linha é criada ANTES da chamada ao fornecedor. Se não se
        // conseguir registar, não se envia.
        Schema::create('agent_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_token_id')->constrained('agent_tokens');
            $t->foreignId('tenant_id')->constrained('tenants');

            $t->string('canal', 10);              // email | sms
            $t->string('template_slug', 60);
            $t->string('destinatario_handle', 60); // responsavel | empresa | cliente:{id}
            $t->string('destinatario_mascarado', 120);

            $t->string('estado', 20)->default('reservado'); // reservado|enviado|falhou
            $t->text('erro')->nullable();
            $t->string('motivo', 500)->nullable();
            $t->uuid('idempotency_key')->nullable();

            $t->date('dia');
            $t->timestamps();

            // Um envio do mesmo modelo, para a mesma empresa, no mesmo
            // canal e no mesmo dia — uma vez só.
            $t->unique(
                ['agent_token_id', 'tenant_id', 'canal', 'template_slug', 'dia'],
                'agent_envio_unico_dia'
            );
            $t->index(['tenant_id', 'created_at']);
        });

        // ── Recomendações do agente nos pedidos ──────────────────────
        Schema::create('agent_order_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_token_id')->constrained('agent_tokens');
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('recomendacao', 20);   // aprovar | recusar | rever
            $t->text('nota');
            $t->timestamps();

            $t->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_order_notes');
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_requests');
        Schema::dropIfExists('agent_tokens');
    }
};
