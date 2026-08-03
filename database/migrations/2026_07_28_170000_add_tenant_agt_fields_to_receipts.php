<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_receipts', 'series_id')) {
                $table->unsignedBigInteger('series_id')->nullable()->index()->after('tenant_id');
            }
            if (!Schema::hasColumn('invoicing_receipts', 'agt_status')) {
                $table->string('agt_status', 30)->nullable();
                $table->string('agt_reference')->nullable();
                $table->timestamp('agt_submitted_at')->nullable();
                $table->timestamp('agt_validated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_receipts', function (Blueprint $table) {
            foreach (['series_id', 'agt_status', 'agt_reference', 'agt_submitted_at', 'agt_validated_at'] as $column) {
                if (Schema::hasColumn('invoicing_receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
