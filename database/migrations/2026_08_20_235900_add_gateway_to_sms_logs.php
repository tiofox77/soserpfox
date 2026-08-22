<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sms_logs', 'gateway')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->string('gateway', 30)->nullable()->after('sender_id')->index();
            });
        }

        DB::table('sms_logs')->whereNull('gateway')->where('sender_id', 'SOSERP')->update(['gateway' => 'telcosms']);
        DB::table('sms_logs')->whereNull('gateway')->update(['gateway' => 'd7networks']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('sms_logs', 'gateway')) {
            Schema::table('sms_logs', fn (Blueprint $table) => $table->dropColumn('gateway'));
        }
    }
};
