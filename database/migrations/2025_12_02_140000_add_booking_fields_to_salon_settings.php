<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_settings', function (Blueprint $table) {
            // Booking Slug único para landing page
            $table->string('booking_slug', 100)->nullable()->after('tenant_id');
            
            // Branding da Landing Page
            $table->string('logo')->nullable()->after('salon_email');
            $table->string('cover_image')->nullable()->after('logo');
            $table->string('primary_color', 7)->default('#ec4899')->after('cover_image');
            $table->string('secondary_color', 7)->default('#8b5cf6')->after('primary_color');
            
            // Informações adicionais
            $table->string('salon_facebook')->nullable()->after('salon_instagram');
            $table->string('salon_tiktok')->nullable()->after('salon_facebook');
            $table->string('salon_website')->nullable()->after('salon_tiktok');
            $table->string('salon_google_maps_url')->nullable()->after('salon_website');
            
            // SEO e Meta
            $table->string('meta_title')->nullable()->after('cancellation_policy');
            $table->text('meta_description')->nullable()->after('meta_title');
            
            // Mensagens customizáveis
            $table->text('welcome_message')->nullable()->after('meta_description');
            $table->text('confirmation_message')->nullable()->after('welcome_message');
            $table->text('sms_template')->nullable()->after('confirmation_message');
            $table->text('email_template')->nullable()->after('sms_template');
            
            // Configurações de pagamento online
            $table->boolean('require_deposit')->default(false)->after('no_show_fee_percent');
            $table->decimal('deposit_percent', 5, 2)->default(0)->after('require_deposit');
            $table->boolean('allow_online_payment')->default(false)->after('deposit_percent');
            
            // Galeria de fotos
            $table->json('gallery_images')->nullable()->after('email_template');
            
            // Serviços em destaque
            $table->json('featured_services')->nullable()->after('gallery_images');
            
            // Índice único para slug
            $table->unique('booking_slug');
        });
    }

    public function down(): void
    {
        Schema::table('salon_settings', function (Blueprint $table) {
            $table->dropUnique(['booking_slug']);
            $table->dropColumn([
                'booking_slug',
                'logo',
                'cover_image',
                'primary_color',
                'secondary_color',
                'salon_facebook',
                'salon_tiktok',
                'salon_website',
                'salon_google_maps_url',
                'meta_title',
                'meta_description',
                'welcome_message',
                'confirmation_message',
                'sms_template',
                'email_template',
                'require_deposit',
                'deposit_percent',
                'allow_online_payment',
                'gallery_images',
                'featured_services',
            ]);
        });
    }
};
