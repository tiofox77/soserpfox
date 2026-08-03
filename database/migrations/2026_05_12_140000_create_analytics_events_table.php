<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('visitor_id')->index();           // cookie persistente (lead tracking)
            $table->uuid('session_id')->index();           // sessão (sessionStorage)
            $table->string('type', 30)->index();           // pageview, click, cta_click, form_submit
            $table->string('event_name', 80)->nullable();  // ex: 'click_register', 'view_module_hotel'
            $table->string('url', 500)->nullable();
            $table->string('path', 255)->nullable()->index();
            $table->string('referrer', 500)->nullable();

            // UTM tracking
            $table->string('utm_source', 100)->nullable()->index();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            $table->string('utm_content', 100)->nullable();

            // Geo + Device
            $table->string('ip', 45)->nullable()->index();
            $table->string('country', 2)->nullable()->index();   // ISO 2-letter
            $table->string('city', 80)->nullable();
            $table->string('device_type', 20)->nullable()->index(); // mobile, tablet, desktop, bot
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('language', 10)->nullable();

            // Utilizador (se logged)
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Payload flexível
            $table->json('meta')->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->index();

            $table->index(['type', 'created_at']);
            $table->index(['visitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
