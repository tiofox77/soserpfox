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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Identificação
            $table->string('name'); // Nome do template (ex: "Lembrete de Evento")
            $table->string('slug')->unique(); // Identificador único (ex: "event_reminder")
            $table->string('module'); // Módulo/Área (hr, events, finance, etc)
            $table->text('description')->nullable();
            
            // Canais de Notificação
            $table->boolean('email_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            
            // Templates por Canal
            $table->string('email_template_id')->nullable(); // ID do template de email
            $table->string('sms_template_sid')->nullable(); // SID do template SMS/WhatsApp
            $table->string('whatsapp_template_sid')->nullable(); // SID do template WhatsApp
            
            // Configuração de Timing
            $table->string('trigger_event'); // Evento que dispara (created, updated, date_approaching)
            $table->integer('notify_before_minutes')->nullable(); // Minutos antes do evento
            $table->string('notify_at_time')->nullable(); // Hora específica (HH:MM)
            
            // Mapeamento de Variáveis
            $table->json('variable_mappings')->nullable(); // {"date": "events.start_date", "event": "events.name"}
            
            // Condições
            $table->json('conditions')->nullable(); // Condições para enviar
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'module']);
            $table->index(['slug', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
