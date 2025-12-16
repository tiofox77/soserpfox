<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para adicionar campos AGT às tabelas EXISTENTES
 * Não duplica funcionalidades - apenas extende o sistema atual
 * Decreto Presidencial n.º 71/25 - Angola
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // 1. CAMPOS AGT NA TABELA DE SÉRIES EXISTENTE
        // =====================================================
        if (!Schema::hasColumn('invoicing_series', 'agt_series_id')) {
            Schema::table('invoicing_series', function (Blueprint $table) {
                $table->string('agt_series_id')->nullable()->after('description')
                    ->comment('ID da série retornado pela AGT após SolicitarSerie');
                $table->string('atcud_validation_code')->nullable()->after('agt_series_id')
                    ->comment('Código de validação ATCUD da série');
                $table->enum('agt_status', ['pending', 'active', 'blocked', 'closed'])
                    ->default('pending')->after('atcud_validation_code')
                    ->comment('Estado da série na AGT');
                $table->string('agt_environment')->default('sandbox')->after('agt_status')
                    ->comment('sandbox ou production');
                $table->timestamp('agt_registered_at')->nullable()->after('agt_environment')
                    ->comment('Data/hora de registo na AGT');
                $table->json('agt_response')->nullable()->after('agt_registered_at')
                    ->comment('Resposta completa da AGT ao criar série');
            });
        }

        // =====================================================
        // 2. CAMPOS AGT NAS CONFIGURAÇÕES EXISTENTES
        // =====================================================
        if (!Schema::hasColumn('invoicing_settings', 'agt_environment')) {
            Schema::table('invoicing_settings', function (Blueprint $table) {
                $table->string('agt_environment')->default('sandbox')->after('saft_version')
                    ->comment('Ambiente AGT: sandbox ou production');
                $table->string('agt_api_base_url')->nullable()->after('agt_environment')
                    ->comment('URL base da API AGT');
                $table->text('agt_client_id')->nullable()->after('agt_api_base_url')
                    ->comment('OAuth Client ID (encrypted)');
                $table->text('agt_client_secret')->nullable()->after('agt_client_id')
                    ->comment('OAuth Client Secret (encrypted)');
                $table->text('agt_access_token')->nullable()->after('agt_client_secret')
                    ->comment('Token de acesso atual (encrypted)');
                $table->timestamp('agt_token_expires_at')->nullable()->after('agt_access_token')
                    ->comment('Expiração do token');
                $table->boolean('agt_auto_submit')->default(false)->after('agt_token_expires_at')
                    ->comment('Submeter automaticamente à AGT');
                $table->boolean('agt_require_validation')->default(true)->after('agt_auto_submit')
                    ->comment('Exigir validação AGT antes de finalizar');
                $table->string('agt_software_certificate')->nullable()->after('agt_require_validation')
                    ->comment('Número do certificado de software AGT');
            });
        }

        // =====================================================
        // 3. CAMPOS JWS NAS FATURAS EXISTENTES
        // =====================================================
        if (!Schema::hasColumn('invoicing_sales_invoices', 'jws_signature')) {
            Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
                $table->text('jws_signature')->nullable()->after('saft_hash')
                    ->comment('Assinatura JWS do documento');
                $table->string('agt_status')->nullable()->after('jws_signature')
                    ->comment('Estado na AGT: pending, submitted, validated, rejected');
                $table->string('agt_reference')->nullable()->after('agt_status')
                    ->comment('Referência única da AGT');
                $table->timestamp('agt_submitted_at')->nullable()->after('agt_reference')
                    ->comment('Data/hora de submissão');
                $table->timestamp('agt_validated_at')->nullable()->after('agt_submitted_at')
                    ->comment('Data/hora de validação');
            });
        }

        // =====================================================
        // 4. CAMPOS JWS NAS NOTAS DE CRÉDITO
        // =====================================================
        if (!Schema::hasColumn('invoicing_credit_notes', 'jws_signature')) {
            Schema::table('invoicing_credit_notes', function (Blueprint $table) {
                $table->text('jws_signature')->nullable()->after('saft_hash')
                    ->comment('Assinatura JWS do documento');
                $table->string('agt_status')->nullable()->after('jws_signature')
                    ->comment('Estado na AGT');
                $table->string('agt_reference')->nullable()->after('agt_status')
                    ->comment('Referência única da AGT');
            });
        }

        // =====================================================
        // 5. CAMPOS JWS NAS NOTAS DE DÉBITO
        // =====================================================
        if (!Schema::hasColumn('invoicing_debit_notes', 'jws_signature')) {
            Schema::table('invoicing_debit_notes', function (Blueprint $table) {
                $table->text('jws_signature')->nullable()->after('saft_hash')
                    ->comment('Assinatura JWS do documento');
                $table->string('agt_status')->nullable()->after('jws_signature')
                    ->comment('Estado na AGT');
                $table->string('agt_reference')->nullable()->after('agt_status')
                    ->comment('Referência única da AGT');
            });
        }

        // =====================================================
        // 6. TABELA DE SUBMISSÕES AGT (NOVA)
        // =====================================================
        if (!Schema::hasTable('agt_submissions')) {
            Schema::create('agt_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->morphs('document'); // document_type, document_id
            $table->string('document_number');
            $table->string('document_type_code')->comment('FT, FR, NC, ND, RC, etc.');
            $table->string('agt_reference')->nullable()->comment('Referência única da AGT');
            $table->string('atcud')->nullable()->comment('Código único do documento');
            $table->enum('status', ['pending', 'submitted', 'validated', 'rejected', 'cancelled'])
                ->default('pending');
            $table->text('jws_signature')->nullable();
            $table->string('hash')->nullable();
            $table->json('request_payload')->nullable()->comment('Dados enviados à AGT');
            $table->json('response_payload')->nullable()->comment('Resposta da AGT');
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            
                $table->index(['tenant_id', 'status']);
                $table->index('agt_reference');
            });
        }

        // =====================================================
        // 7. TABELA DE LOGS DE COMUNICAÇÃO AGT (NOVA)
        // =====================================================
        if (!Schema::hasTable('agt_communication_logs')) {
            Schema::create('agt_communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('submission_id')->nullable()
                ->constrained('agt_submissions')->onDelete('set null');
            $table->string('service')->comment('SolicitarSerie, RegistarFactura, etc.');
            $table->string('method')->default('POST');
            $table->string('endpoint');
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->float('response_time')->nullable()->comment('Tempo de resposta em ms');
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
                $table->index(['tenant_id', 'service']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        // Remover tabelas novas
        Schema::dropIfExists('agt_communication_logs');
        Schema::dropIfExists('agt_submissions');

        // Remover campos das notas de débito
        Schema::table('invoicing_debit_notes', function (Blueprint $table) {
            $table->dropColumn(['jws_signature', 'agt_status', 'agt_reference']);
        });

        // Remover campos das notas de crédito
        Schema::table('invoicing_credit_notes', function (Blueprint $table) {
            $table->dropColumn(['jws_signature', 'agt_status', 'agt_reference']);
        });

        // Remover campos das faturas
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'jws_signature', 'agt_status', 'agt_reference', 
                'agt_submitted_at', 'agt_validated_at'
            ]);
        });

        // Remover campos das configurações
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'agt_environment', 'agt_api_base_url', 'agt_client_id', 
                'agt_client_secret', 'agt_access_token', 'agt_token_expires_at',
                'agt_auto_submit', 'agt_require_validation', 'agt_software_certificate'
            ]);
        });

        // Remover campos das séries
        Schema::table('invoicing_series', function (Blueprint $table) {
            $table->dropColumn([
                'agt_series_id', 'atcud_validation_code', 'agt_status',
                'agt_environment', 'agt_registered_at', 'agt_response'
            ]);
        });
    }
};
