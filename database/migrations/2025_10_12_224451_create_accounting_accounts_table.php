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
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->enum('nature', ['debit', 'credit']);
            $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts');
            $table->integer('level')->default(1);
            $table->boolean('is_view')->default(false); // Conta resumo
            $table->boolean('blocked')->default(false);
            $table->string('integration_key')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_accounts');
    }
};
