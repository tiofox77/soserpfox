<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha as últimas lacunas de unicidade por-tenant em TODOS os módulos.
 *
 * Identificadores gerados/introduzidos POR EMPRESA que ainda tinham UNIQUE global
 * (bloqueavam a 2.ª empresa de usar o mesmo código/número — ex.: 2.º tenant não
 * conseguia criar 'CASH', 'PROD000001', reserva Nº 1, etc.):
 *
 *   products.code                         (PROD/SVC — gerado por tenant, lookup por tenant)
 *   clients.nif                           (por tenant)
 *   cost_centers.code                     (contabilidade, por tenant)
 *   fixed_assets.code                     (imobilizado, por tenant)
 *   treasury_cash_registers.code          (CASH001 — por tenant)
 *   treasury_payment_methods.code         (CASH/TRANSFER — seedDefaultsForTenant, por tenant)
 *   hotel_reservations.reservation_number (por tenant)
 *   hotel_packages.code                   (por tenant)
 *   salon_appointments.appointment_number (por tenant)
 *
 * NÃO incluídos (mantêm-se GLOBAIS de propósito): referências AGT/moedas/bancos,
 * templates de email/sms/notificação (lookup global por slug), system_settings,
 * users.email, tenants.slug, modules.slug, planos, tokens, hotel_settings.booking_slug
 * (URL público), equipment.serial_number. E events_reports.report_number (sem tenant_id).
 *
 * Padrão defensivo idêntico às migrações 114500/150000: descobre o nome REAL do índice
 * único de coluna única via information_schema, remove-o, cria o composto. Idempotente.
 */
return new class extends Migration
{
    protected array $map = [
        'products'                => 'code',
        'clients'                 => 'nif',
        'cost_centers'            => 'code',
        'fixed_assets'            => 'code',
        'treasury_cash_registers' => 'code',
        'treasury_payment_methods'=> 'code',
        'hotel_reservations'      => 'reservation_number',
        'hotel_packages'          => 'code',
        'salon_appointments'      => 'appointment_number',
    ];

    public function up(): void
    {
        $db = DB::getDatabaseName();

        foreach ($this->map as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)
                || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $singleColIndexes = DB::select("
                SELECT s.INDEX_NAME
                FROM information_schema.STATISTICS s
                WHERE s.TABLE_SCHEMA = ? AND s.TABLE_NAME = ? AND s.NON_UNIQUE = 0
                  AND s.INDEX_NAME <> 'PRIMARY'
                GROUP BY s.INDEX_NAME
                HAVING COUNT(*) = 1
                   AND MAX(CASE WHEN s.COLUMN_NAME = ? THEN 1 ELSE 0 END) = 1
            ", [$db, $table, $column]);

            foreach ($singleColIndexes as $idx) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$idx->INDEX_NAME}`");
            }

            $compositeName = "{$table}_tenant_{$column}_unique";
            $exists = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
            ", [$db, $table, $compositeName]);

            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$compositeName}` (`tenant_id`, `{$column}`)");
            }
        }
    }

    public function down(): void
    {
        $db = DB::getDatabaseName();

        foreach ($this->map as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $compositeName = "{$table}_tenant_{$column}_unique";
            $exists = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
            ", [$db, $table, $compositeName]);
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$compositeName}`");
            }

            $globalName = "{$table}_{$column}_unique";
            $existsGlobal = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
            ", [$db, $table, $globalName]);
            if (empty($existsGlobal)) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$globalName}` (`{$column}`)");
            }
        }
    }
};
