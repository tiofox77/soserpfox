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
        Schema::create('treasury_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Dinheiro, Transferência, Multicaixa, etc
            $table->string('code')->unique(); // CASH, TRANSFER, MULTICAIXA, etc
            $table->string('type')->default('manual'); // manual, automatic, online
            $table->text('description')->nullable();
            $table->string('icon')->default('fa-money-bill')->nullable();
            $table->string('color')->default('green')->nullable();
            $table->decimal('fee_percentage', 5, 2)->default(0); // Taxa %
            $table->decimal('fee_fixed', 10, 2)->default(0); // Taxa fixa
            $table->boolean('requires_account')->default(false); // Requer conta bancária
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_payment_methods');
    }
};
