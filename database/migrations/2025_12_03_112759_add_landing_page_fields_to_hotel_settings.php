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
        Schema::table('hotel_settings', function (Blueprint $table) {
            // Cidade e país
            if (!Schema::hasColumn('hotel_settings', 'hotel_city')) {
                $table->string('hotel_city')->nullable()->after('hotel_address');
            }
            if (!Schema::hasColumn('hotel_settings', 'hotel_country')) {
                $table->string('hotel_country')->default('Angola')->after('hotel_city');
            }
            
            // WhatsApp
            if (!Schema::hasColumn('hotel_settings', 'hotel_whatsapp')) {
                $table->string('hotel_whatsapp')->nullable()->after('hotel_phone');
            }
            
            // Classificação
            if (!Schema::hasColumn('hotel_settings', 'star_rating')) {
                $table->integer('star_rating')->default(3)->after('hotel_email');
            }
            
            // Redes sociais
            if (!Schema::hasColumn('hotel_settings', 'instagram')) {
                $table->string('instagram')->nullable()->after('star_rating');
            }
            if (!Schema::hasColumn('hotel_settings', 'facebook')) {
                $table->string('facebook')->nullable()->after('instagram');
            }
            if (!Schema::hasColumn('hotel_settings', 'google_maps_url')) {
                $table->string('google_maps_url')->nullable()->after('facebook');
            }
            if (!Schema::hasColumn('hotel_settings', 'tripadvisor_url')) {
                $table->string('tripadvisor_url')->nullable()->after('google_maps_url');
            }
            if (!Schema::hasColumn('hotel_settings', 'booking_com_url')) {
                $table->string('booking_com_url')->nullable()->after('tripadvisor_url');
            }
            
            // Branding
            if (!Schema::hasColumn('hotel_settings', 'primary_color')) {
                $table->string('primary_color')->default('#3b82f6')->after('booking_com_url');
            }
            if (!Schema::hasColumn('hotel_settings', 'secondary_color')) {
                $table->string('secondary_color')->default('#6366f1')->after('primary_color');
            }
            if (!Schema::hasColumn('hotel_settings', 'logo')) {
                $table->string('logo')->nullable()->after('secondary_color');
            }
            if (!Schema::hasColumn('hotel_settings', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('hotel_settings', 'gallery_images')) {
                $table->json('gallery_images')->nullable()->after('cover_image');
            }
            
            // Horários extras
            if (!Schema::hasColumn('hotel_settings', 'early_check_in_available')) {
                $table->boolean('early_check_in_available')->default(true)->after('default_check_out_time');
            }
            if (!Schema::hasColumn('hotel_settings', 'late_check_out_available')) {
                $table->boolean('late_check_out_available')->default(true)->after('early_check_in_available');
            }
            if (!Schema::hasColumn('hotel_settings', 'early_check_in_fee')) {
                $table->decimal('early_check_in_fee', 10, 2)->default(0)->after('late_check_out_available');
            }
            if (!Schema::hasColumn('hotel_settings', 'late_check_out_fee')) {
                $table->decimal('late_check_out_fee', 10, 2)->default(0)->after('early_check_in_fee');
            }
            
            // Reservas
            if (!Schema::hasColumn('hotel_settings', 'min_advance_booking_hours')) {
                $table->integer('min_advance_booking_hours')->default(24)->after('late_check_out_fee');
            }
            if (!Schema::hasColumn('hotel_settings', 'cancellation_hours')) {
                $table->integer('cancellation_hours')->default(48)->after('min_advance_booking_hours');
            }
            if (!Schema::hasColumn('hotel_settings', 'require_deposit')) {
                $table->boolean('require_deposit')->default(false)->after('online_booking_enabled');
            }
            if (!Schema::hasColumn('hotel_settings', 'deposit_percent')) {
                $table->integer('deposit_percent')->default(30)->after('require_deposit');
            }
            
            // Políticas
            if (!Schema::hasColumn('hotel_settings', 'house_rules')) {
                $table->text('house_rules')->nullable()->after('cancellation_policies');
            }
            
            // Landing Page
            if (!Schema::hasColumn('hotel_settings', 'booking_slug')) {
                $table->string('booking_slug')->nullable()->unique()->after('house_rules');
            }
            if (!Schema::hasColumn('hotel_settings', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('booking_slug');
            }
            if (!Schema::hasColumn('hotel_settings', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('hotel_settings', 'welcome_message')) {
                $table->text('welcome_message')->nullable()->after('meta_description');
            }
            
            // Quartos em destaque
            if (!Schema::hasColumn('hotel_settings', 'featured_rooms')) {
                $table->json('featured_rooms')->nullable()->after('amenities_list');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_settings', function (Blueprint $table) {
            //
        });
    }
};
