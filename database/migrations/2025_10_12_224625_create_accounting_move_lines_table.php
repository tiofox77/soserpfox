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
        Schema::create('accounting_move_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('move_id')->constrained('accounting_moves')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->unsignedBigInteger('partner_id')->nullable(); // FK será adicionada depois que partners existir
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained('accounting_taxes');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->string('document_ref', 100)->nullable()->index();
            $table->text('narration')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'account_id']);
            $table->index(['tenant_id', 'partner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_move_lines');
    }
};
