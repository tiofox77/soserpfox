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
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nome do template
            $table->string('slug')->unique(); // new_account, payment_approved, etc
            $table->text('content'); // Conteúdo da mensagem
            $table->json('variables')->nullable(); // Variáveis disponíveis
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
