<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_transport_guides', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_transport_guides', 'series_id')) {
                $table->unsignedBigInteger('series_id')->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'system_entry_date')) {
                $table->dateTime('system_entry_date')->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'gross_total')) {
                $table->decimal('gross_total', 15, 2)->default(0);
                $table->decimal('net_total', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'atcud')) {
                $table->string('atcud', 70)->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'saft_hash')) {
                $table->text('saft_hash')->nullable();
                $table->text('hash')->nullable();
                $table->text('hash_previous')->nullable();
                $table->string('hash_control', 100)->nullable();
                $table->text('jws_signature')->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'agt_status')) {
                $table->string('agt_status', 20)->nullable();
                $table->string('agt_reference', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_transport_guides', function (Blueprint $table) {
            foreach ([
                'series_id', 'system_entry_date', 'gross_total', 'net_total', 'tax_amount',
                'atcud', 'saft_hash', 'hash', 'hash_previous', 'hash_control', 'jws_signature',
                'agt_status', 'agt_reference',
            ] as $col) {
                if (Schema::hasColumn('invoicing_transport_guides', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
