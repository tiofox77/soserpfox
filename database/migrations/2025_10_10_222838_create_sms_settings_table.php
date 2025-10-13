<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('d7networks'); // d7networks, twilio, etc
            $table->string('api_url')->default('https://api.d7networks.com/messages/v1/send');
            $table->text('api_token'); // Token da API
            $table->string('sender_id')->default('SOS ERP'); // Sender ID / Originator
            $table->string('report_url')->nullable(); // URL para receber delivery reports
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable(); // Configurações extras
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('is_active');
        });
        
        // Tabela de histórico de SMS
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient'); // Número do destinatário
            $table->text('message'); // Mensagem enviada
            $table->string('sender_id')->nullable(); // Sender usado
            $table->string('type')->nullable(); // Tipo: new_user, payment_approved, plan_expiring, etc
            $table->string('status')->default('sent'); // sent, delivered, failed
            $table->string('request_id')->nullable(); // ID da requisição na API
            $table->text('api_response')->nullable(); // Resposta da API
            $table->text('error_message')->nullable(); // Mensagem de erro se houver
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            $table->index('recipient');
            $table->index('type');
            $table->index('status');
            $table->index('tenant_id');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_settings');
    }
};
