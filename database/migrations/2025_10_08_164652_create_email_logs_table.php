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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('smtp_setting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Quem enviou
            
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject');
            $table->text('body_preview')->nullable(); // Primeiras linhas do email
            
            $table->string('template_slug')->nullable();
            $table->json('template_data')->nullable(); // Dados usados no template
            
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            
            $table->string('message_id')->nullable(); // ID da mensagem do servidor SMTP
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['to_email', 'created_at']);
            $table->index('template_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
