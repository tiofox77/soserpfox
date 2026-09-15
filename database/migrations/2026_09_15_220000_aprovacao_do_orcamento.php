<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O ORÇAMENTO APROVADO PELO CLIENTE (15/09/2026, OF-03).
 *
 * Uma linha da ordem pode ficar «à espera da aprovação do cliente». Só as
 * APROVADAS contam para os totais, saem do stock e vão à factura; as recusadas
 * ficam à vista, riscadas. As linhas que já existem nascem aprovadas — nada do
 * que está gravado muda de valor.
 *
 * O cliente decide por um LINK (sem precisar de conta no portal) que vale 14
 * dias, e assina. A oficina pode também registar a decisão à mão («aprovou por
 * telefone»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_work_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_work_order_items', 'approval')) {
                $table->string('approval', 12)->default('approved')->after('is_original');
                $table->timestamp('approval_at')->nullable()->after('approval');
                $table->string('approval_by', 150)->nullable()->after('approval_at');
            }
        });

        Schema::table('workshop_work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_work_orders', 'approval_token')) {
                $table->string('approval_token', 64)->nullable()->unique()->after('invoiced_at');
                $table->timestamp('approval_requested_at')->nullable()->after('approval_token');
                $table->timestamp('approval_expires_at')->nullable()->after('approval_requested_at');
                $table->mediumText('approval_signature')->nullable()->after('approval_expires_at');
                $table->string('approval_signed_by', 150)->nullable()->after('approval_signature');
                $table->timestamp('approval_signed_at')->nullable()->after('approval_signed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_work_order_items', function (Blueprint $table) {
            $table->dropColumn(['approval', 'approval_at', 'approval_by']);
        });
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            $table->dropUnique(['approval_token']);
            $table->dropColumn(['approval_token', 'approval_requested_at', 'approval_expires_at', 'approval_signature', 'approval_signed_by', 'approval_signed_at']);
        });
    }
};
