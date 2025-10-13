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
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->enum('type', ['sale', 'purchase', 'cash', 'bank', 'payroll', 'adjustment']);
            $table->string('sequence_prefix', 20);
            $table->integer('last_number')->default(0);
            $table->foreignId('default_debit_account_id')->nullable()->constrained('accounting_accounts');
            $table->foreignId('default_credit_account_id')->nullable()->constrained('accounting_accounts');
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
        Schema::dropIfExists('accounting_journals');
    }
};
