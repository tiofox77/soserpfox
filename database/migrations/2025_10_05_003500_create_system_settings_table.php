<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, textarea, image, file, boolean
            $table->string('group')->default('general'); // general, seo, appearance, email, etc
            $table->timestamps();
        });

        // Inserir configurações padrão
        DB::table('system_settings')->insert([
            // General
            ['key' => 'app_name', 'value' => 'SOS ERP', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_description', 'value' => 'Sistema ERP Multi-tenant para Angola', 'type' => 'textarea', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_version', 'value' => '5.0.0', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_url', 'value' => 'http://soserp.test', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_email', 'value' => 'contato@soserp.com', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_phone', 'value' => '+244 999 999 999', 'type' => 'text', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            
            // Appearance
            ['key' => 'app_logo', 'value' => null, 'type' => 'image', 'group' => 'appearance', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_favicon', 'value' => null, 'type' => 'image', 'group' => 'appearance', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'primary_color', 'value' => '#4F46E5', 'type' => 'color', 'group' => 'appearance', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'secondary_color', 'value' => '#06B6D4', 'type' => 'color', 'group' => 'appearance', 'created_at' => now(), 'updated_at' => now()],
            
            // SEO
            ['key' => 'seo_title', 'value' => 'SOS ERP - Sistema de Gestão Empresarial', 'type' => 'text', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'seo_description', 'value' => 'Sistema ERP completo para gestão empresarial em Angola. Faturação, Tesouraria, RH e muito mais.', 'type' => 'textarea', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'seo_keywords', 'value' => 'ERP, Angola, Faturação, Gestão, Software', 'type' => 'text', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'seo_author', 'value' => 'SOS ERP Team', 'type' => 'text', 'group' => 'seo', 'created_at' => now(), 'updated_at' => now()],
            
            // Features
            ['key' => 'enable_registration', 'value' => 'true', 'type' => 'boolean', 'group' => 'features', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'enable_email_verification', 'value' => 'false', 'type' => 'boolean', 'group' => 'features', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'features', 'created_at' => now(), 'updated_at' => now()],
            
            // Social
            ['key' => 'facebook_url', 'value' => null, 'type' => 'text', 'group' => 'social', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'instagram_url', 'value' => null, 'type' => 'text', 'group' => 'social', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'twitter_url', 'value' => null, 'type' => 'text', 'group' => 'social', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'linkedin_url', 'value' => null, 'type' => 'text', 'group' => 'social', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
