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
        // Loyalty fields on hotel_guests
        if (Schema::hasTable('hotel_guests')) {
            Schema::table('hotel_guests', function (Blueprint $table) {
                if (!Schema::hasColumn('hotel_guests', 'loyalty_points')) {
                    $table->integer('loyalty_points')->default(0)->after('is_blacklisted');
                }
                if (!Schema::hasColumn('hotel_guests', 'loyalty_tier')) {
                    $table->string('loyalty_tier', 20)->default('bronze')->after('loyalty_points');
                }
                if (!Schema::hasColumn('hotel_guests', 'total_spent')) {
                    $table->decimal('total_spent', 15, 2)->default(0)->after('loyalty_tier');
                }
            });
        }

        // Extra category on reservation items (minibar/room_service/laundry/transfer/spa/other)
        if (Schema::hasTable('hotel_reservation_items')) {
            Schema::table('hotel_reservation_items', function (Blueprint $table) {
                if (!Schema::hasColumn('hotel_reservation_items', 'category')) {
                    $table->string('category', 30)->nullable()->after('type');
                }
                if (!Schema::hasColumn('hotel_reservation_items', 'charged_at')) {
                    $table->timestamp('charged_at')->nullable()->after('date');
                }
                if (!Schema::hasColumn('hotel_reservation_items', 'charged_by')) {
                    $table->foreignId('charged_by')->nullable()->after('charged_at')->constrained('users')->nullOnDelete();
                }
            });
        }

        // Overbooking + loyalty columns added to hotel_settings
        if (Schema::hasTable('hotel_settings')) {
            Schema::table('hotel_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('hotel_settings', 'overbooking_enabled')) {
                    $table->boolean('overbooking_enabled')->default(false);
                }
                if (!Schema::hasColumn('hotel_settings', 'overbooking_percent')) {
                    $table->integer('overbooking_percent')->default(10);
                }
                if (!Schema::hasColumn('hotel_settings', 'loyalty_enabled')) {
                    $table->boolean('loyalty_enabled')->default(true);
                }
                if (!Schema::hasColumn('hotel_settings', 'loyalty_points_per_kz')) {
                    $table->decimal('loyalty_points_per_kz', 8, 4)->default(0.01);
                }
                if (!Schema::hasColumn('hotel_settings', 'loyalty_tier_silver')) {
                    $table->integer('loyalty_tier_silver')->default(500);
                }
                if (!Schema::hasColumn('hotel_settings', 'loyalty_tier_gold')) {
                    $table->integer('loyalty_tier_gold')->default(2000);
                }
                if (!Schema::hasColumn('hotel_settings', 'loyalty_tier_platinum')) {
                    $table->integer('loyalty_tier_platinum')->default(5000);
                }
                if (!Schema::hasColumn('hotel_settings', 'notify_reservation_confirmed')) {
                    $table->boolean('notify_reservation_confirmed')->default(true);
                }
                if (!Schema::hasColumn('hotel_settings', 'notify_pre_arrival')) {
                    $table->boolean('notify_pre_arrival')->default(true);
                }
                if (!Schema::hasColumn('hotel_settings', 'notify_post_stay')) {
                    $table->boolean('notify_post_stay')->default(true);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hotel_guests')) {
            Schema::table('hotel_guests', function (Blueprint $table) {
                foreach (['loyalty_points', 'loyalty_tier', 'total_spent'] as $col) {
                    if (Schema::hasColumn('hotel_guests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('hotel_reservation_items')) {
            Schema::table('hotel_reservation_items', function (Blueprint $table) {
                foreach (['category', 'charged_at', 'charged_by'] as $col) {
                    if (Schema::hasColumn('hotel_reservation_items', $col)) {
                        if ($col === 'charged_by') {
                            try { $table->dropForeign(['charged_by']); } catch (\Throwable $e) {}
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
