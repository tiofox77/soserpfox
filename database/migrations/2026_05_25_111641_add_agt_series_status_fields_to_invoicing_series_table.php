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
        Schema::table('invoicing_series', function (Blueprint $table) {
            // DS.120 §4.6 — Estado AGT da série: A=Aberta, U=Em utilização, F=Fechada
            if (!Schema::hasColumn('invoicing_series', 'agt_series_status')) {
                $table->char('agt_series_status', 1)->nullable()->after('agt_status')
                    ->comment('A=Aberta, U=Em utilização, F=Fechada (DS.120 §4.6)');
            }
            // Método de facturação (FEPC=Pré-comunicada, FESF=Sem facturação, SF=Sem fatura)
            if (!Schema::hasColumn('invoicing_series', 'invoicing_method')) {
                $table->string('invoicing_method', 8)->nullable()->after('agt_series_status');
            }
            // Timestamps de início/fim da série na AGT
            if (!Schema::hasColumn('invoicing_series', 'agt_series_start_ts')) {
                $table->timestamp('agt_series_start_ts')->nullable()->after('invoicing_method');
            }
            if (!Schema::hasColumn('invoicing_series', 'agt_series_end_ts')) {
                $table->timestamp('agt_series_end_ts')->nullable()->after('agt_series_start_ts');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_series', function (Blueprint $table) {
            $table->dropColumn(['agt_series_status', 'invoicing_method', 'agt_series_start_ts', 'agt_series_end_ts']);
        });
    }
};
