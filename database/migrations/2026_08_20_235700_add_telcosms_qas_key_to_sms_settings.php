<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sms_settings', 'telco_api_key_qas')) {
            Schema::table('sms_settings', function (Blueprint $table): void {
                $table->text('telco_api_key_qas')->nullable()->after('api_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sms_settings', 'telco_api_key_qas')) {
            Schema::table('sms_settings', fn (Blueprint $table) => $table->dropColumn('telco_api_key_qas'));
        }
    }
};
