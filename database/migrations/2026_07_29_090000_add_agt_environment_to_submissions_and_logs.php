<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa a actividade AGT por ambiente.
 *
 * Submissões e logs não guardavam o ambiente, pelo que homologação e produção
 * apareciam misturadas no mesmo ecrã — e um documento validado em sandbox
 * parecia validado em produção. As séries já tinham `agt_environment`.
 *
 * Backfill: toda a actividade existente foi feita em homologação (as únicas
 * séries registadas até hoje têm agt_environment = 'sandbox').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agt_submissions') && !Schema::hasColumn('agt_submissions', 'agt_environment')) {
            Schema::table('agt_submissions', function (Blueprint $table) {
                $table->string('agt_environment', 20)->default('sandbox')->after('tenant_id');
                $table->index(['tenant_id', 'agt_environment'], 'agt_submissions_tenant_env_idx');
            });

            DB::table('agt_submissions')->whereNull('agt_environment')->update(['agt_environment' => 'sandbox']);
        }

        if (Schema::hasTable('agt_communication_logs') && !Schema::hasColumn('agt_communication_logs', 'agt_environment')) {
            Schema::table('agt_communication_logs', function (Blueprint $table) {
                $table->string('agt_environment', 20)->default('sandbox')->after('tenant_id');
                $table->index(['tenant_id', 'agt_environment'], 'agt_logs_tenant_env_idx');
            });

            DB::table('agt_communication_logs')->whereNull('agt_environment')->update(['agt_environment' => 'sandbox']);
        }

        // Séries antigas sem ambiente: só as registadas têm significado; as que
        // têm agt_series_id sem ambiente foram registadas em homologação.
        if (Schema::hasTable('invoicing_series')) {
            DB::table('invoicing_series')
                ->whereNotNull('agt_series_id')
                ->where(function ($q) {
                    $q->whereNull('agt_environment')->orWhere('agt_environment', '');
                })
                ->update(['agt_environment' => 'sandbox']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agt_submissions', 'agt_environment')) {
            Schema::table('agt_submissions', function (Blueprint $table) {
                $table->dropIndex('agt_submissions_tenant_env_idx');
                $table->dropColumn('agt_environment');
            });
        }

        if (Schema::hasColumn('agt_communication_logs', 'agt_environment')) {
            Schema::table('agt_communication_logs', function (Blueprint $table) {
                $table->dropIndex('agt_logs_tenant_env_idx');
                $table->dropColumn('agt_environment');
            });
        }
    }
};
