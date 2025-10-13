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
        Schema::create('accounting_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->enum('type', ['vat', 'withholding', 'other']);
            $table->decimal('rate', 5, 2); // 14.00 = 14%
            $table->foreignId('account_collected_id')->nullable()->constrained('accounting_accounts');
            $table->foreignId('account_paid_id')->nullable()->constrained('accounting_accounts');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_taxes');
    }
};
