<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \App\Models\SystemSetting::set('facebook_pixel_id', '1020506307494204');
    }

    public function down(): void
    {
        if (\App\Models\SystemSetting::get('facebook_pixel_id') === '1020506307494204') {
            \App\Models\SystemSetting::set('facebook_pixel_id', null);
        }
    }
};
