<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('treasury_transaction_types')) {
            Schema::create('treasury_transaction_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 60);
                $table->enum('nature', ['income', 'expense', 'transfer']);
                $table->text('description')->nullable();
                $table->string('color', 30)->default('blue');
                $table->string('icon', 60)->default('fa-exchange-alt');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['tenant_id', 'code'], 'treasury_tx_types_tenant_code_unique');
            });
        }

        if (!Schema::hasTable('treasury_transaction_categories')) {
            Schema::create('treasury_transaction_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transaction_type_id')->nullable()->constrained('treasury_transaction_types')->nullOnDelete();
                $table->string('name');
                $table->string('code', 80);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['tenant_id', 'code'], 'treasury_tx_categories_tenant_code_unique');
            });
        }

        if (Schema::hasTable('treasury_transactions')) {
            Schema::table('treasury_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('treasury_transactions', 'transaction_type_id')) {
                    $table->foreignId('transaction_type_id')->nullable()->after('type')
                        ->constrained('treasury_transaction_types')->nullOnDelete();
                }
                if (!Schema::hasColumn('treasury_transactions', 'transaction_category_id')) {
                    $table->foreignId('transaction_category_id')->nullable()->after('category')
                        ->constrained('treasury_transaction_categories')->nullOnDelete();
                }
            });
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            \App\Models\Treasury\TransactionCategory::seedDefaultsForTenant((int) $tenantId);
        }

        // Associa os dados históricos sem alterar os códigos já gravados.
        DB::statement("UPDATE treasury_transactions tr JOIN treasury_transaction_types tt ON tt.tenant_id=tr.tenant_id AND tt.nature=tr.type SET tr.transaction_type_id=tt.id WHERE tr.transaction_type_id IS NULL");
        DB::statement("UPDATE treasury_transactions tr JOIN treasury_transaction_categories tc ON tc.tenant_id=tr.tenant_id AND tc.code=tr.category SET tr.transaction_category_id=tc.id WHERE tr.transaction_category_id IS NULL");
    }

    public function down(): void
    {
        if (Schema::hasTable('treasury_transactions')) {
            Schema::table('treasury_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('treasury_transactions', 'transaction_category_id')) $table->dropConstrainedForeignId('transaction_category_id');
                if (Schema::hasColumn('treasury_transactions', 'transaction_type_id')) $table->dropConstrainedForeignId('transaction_type_id');
            });
        }
        Schema::dropIfExists('treasury_transaction_categories');
        Schema::dropIfExists('treasury_transaction_types');
    }
};
