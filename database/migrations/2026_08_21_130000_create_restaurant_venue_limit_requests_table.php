<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'restaurant_venue_limit')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedSmallInteger('restaurant_venue_limit')->default(1)->after('max_storage_mb');
            });
        }

        if (! Schema::hasTable('restaurant_venue_limit_requests')) {
            Schema::create('restaurant_venue_limit_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('current_limit')->default(1);
                $table->unsignedSmallInteger('requested_limit');
                $table->string('status', 20)->default('pending');
                $table->text('reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_venue_limit_requests');
        if (Schema::hasColumn('tenants', 'restaurant_venue_limit')) {
            Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('restaurant_venue_limit'));
        }
    }
};
