<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Importa dados da BD legada `farmaciadois` (Stock Manager Advance / SMA)
 * para o sistema soserp como novo tenant.
 *
 * Importa: company info (tenant) + admin user, categorias, armazéns,
 * produtos e stock por armazém.
 *
 * Idempotente: pode correr múltiplas vezes — usa code/email como chave natural.
 *
 * Uso:
 *   php artisan farmaciadois:import --dry-run
 *   php artisan farmaciadois:import
 *   php artisan farmaciadois:import --skip-products --skip-stock
 */
class ImportFarmaciaDois extends Command
{
    protected $signature = 'farmaciadois:import
                            {--source-db=farmaciadois : Nome da BD de origem (SMA)}
                            {--source-prefix=sma_ : Prefixo das tabelas SMA}
                            {--tenant-name=Farmácia Neves Bendinha : Nome do tenant}
                            {--tenant-slug=farmacia-neves-bendinha : Slug do tenant}
                            {--tenant-nif=5417289442 : NIF angolano do tenant}
                            {--admin-email=farmacia@luksimoes.com : Email do admin do tenant}
                            {--admin-password=Farmacia@2026 : Password temporária do admin}
                            {--dry-run : Apenas simula, sem gravar}
                            {--skip-tenant : Não criar tenant (assume já existe pelo slug)}
                            {--skip-categories : Não importar categorias}
                            {--skip-warehouses : Não importar armazéns}
                            {--skip-products : Não importar produtos}
                            {--skip-stock : Não importar stock}
                            {--overwrite-stock : PERIGOSO pós-go-live: sobrescreve linhas de stock JÁ existentes com os valores da BD origem (por omissão só insere linhas em falta)}
                            {--with-cashiers : Criar utilizadores caixa (Suzana, Rosa, Engrácia) com role Caixa}
                            {--cashier-password=Caixa@2026 : Password temporária dos caixas}
                            {--default-warehouse-source-id=6 : ID do armazém na BD origem que será marcado is_default no destino}
                            {--admin-name=Administrador Farmácia : Nome do admin user}
                            {--tenant-phone=222 734 171 : Telefone do tenant}
                            {--tenant-address=Bairro Popular Rua: Almada Nogueiro : Morada do tenant}
                            {--tenant-city=Luanda : Cidade do tenant}
                            {--chunk=500 : Tamanho do batch de produtos/stock}';

    protected $description = 'Importa dados (empresa, categorias, produtos, stock) da BD legada farmaciadois (SMA) para um novo tenant soserp.';

    private string $srcDb;
    private string $srcPrefix;
    private bool $dryRun;
    private int $tenantId = 0;

    /** Mapas id_origem => id_destino */
    private array $catMap = [];
    private array $whMap = [];
    private array $prodMap = [];

    /** Métricas de execução */
    private array $stats = [
        'tenant' => ['created' => 0, 'existing' => 0],
        'user' => ['created' => 0, 'existing' => 0],
        'categories' => ['created' => 0, 'existing' => 0, 'skipped' => 0],
        'warehouses' => ['created' => 0, 'existing' => 0],
        'products' => ['created' => 0, 'existing' => 0, 'errors' => 0],
        'stock' => ['created' => 0, 'updated' => 0, 'orphan' => 0],
        'cashiers' => ['created' => 0, 'existing' => 0],
    ];

    public function handle(): int
    {
        $this->srcDb = $this->option('source-db');
        $this->srcPrefix = $this->option('source-prefix');
        $this->dryRun = (bool) $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Importador SMA (farmaciadois) → soserp');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->line("  Source DB    : <fg=cyan>{$this->srcDb}</> (prefix: {$this->srcPrefix})");
        $this->line('  Dry-run      : ' . ($this->dryRun ? '<fg=yellow>SIM</>' : '<fg=green>NÃO</>'));
        $this->line("  Tenant       : <fg=cyan>{$this->option('tenant-name')}</> (slug: {$this->option('tenant-slug')})");
        $this->line("  Tenant NIF   : <fg=cyan>{$this->option('tenant-nif')}</>");
        $this->line('───────────────────────────────────────────────────────────');

        if (!$this->checkSource()) {
            return self::FAILURE;
        }

        try {
            // 1. Tenant + admin
            if (!$this->option('skip-tenant')) {
                $this->createOrFindTenant();
                $this->createOrFindAdmin();
            } else {
                $this->resolveTenantBySlug();
            }

            if (!$this->tenantId) {
                $this->error('Tenant não resolvido — abortando.');
                return self::FAILURE;
            }

            // 2. Categorias
            if (!$this->option('skip-categories')) {
                $this->importCategories();
            } else {
                $this->loadCategoryMap();
            }

            // 3. Armazéns
            if (!$this->option('skip-warehouses')) {
                $this->importWarehouses();
            } else {
                $this->loadWarehouseMap();
            }

            // 4. Produtos
            if (!$this->option('skip-products')) {
                $this->importProducts();
            } else {
                $this->loadProductMap();
            }

            // 5. Stock
            if (!$this->option('skip-stock')) {
                $this->importStock();
            }

            // 6. Caixas (opcional)
            if ($this->option('with-cashiers')) {
                $this->createCashiers();
            }
        } catch (\Throwable $e) {
            $this->error('Erro: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        $this->printSummary();
        return self::SUCCESS;
    }

    private function checkSource(): bool
    {
        try {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '', $this->srcDb);
            $exists = DB::select("SHOW DATABASES LIKE '{$safe}'");
            if (empty($exists)) {
                $this->error("BD origem '{$this->srcDb}' não encontrada.");
                return false;
            }
            $count = DB::table("{$this->srcDb}.{$this->srcPrefix}products")->count();
            $this->info("  ✓ BD origem OK — {$count} produtos disponíveis.");
            return true;
        } catch (\Throwable $e) {
            $this->error('Falha a aceder BD origem: ' . $e->getMessage());
            return false;
        }
    }

    /* ─────────────────────── TENANT ─────────────────────── */

    private function createOrFindTenant(): void
    {
        $this->line("\n<fg=cyan>▸ TENANT</>");
        $slug = $this->option('tenant-slug');

        $existing = DB::table('tenants')->where('slug', $slug)->first();
        if ($existing) {
            $this->tenantId = (int) $existing->id;
            $this->stats['tenant']['existing'] = 1;
            $this->line("  Tenant já existe (id={$this->tenantId}). Reutilizando.");
            return;
        }

        $payload = [
            'name' => $this->option('tenant-name'),
            'slug' => $slug,
            'company_name' => $this->option('tenant-name'),
            'nif' => $this->option('tenant-nif'),
            'regime' => 'regime_geral',
            'email' => $this->option('admin-email'),
            'phone' => $this->option('tenant-phone'),
            'address' => $this->option('tenant-address'),
            'city' => $this->option('tenant-city'),
            'country' => \App\Support\Geografia::PAIS_PADRAO,
            'max_users' => 10,
            'max_storage_mb' => 5000,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->dryRun) {
            $this->line('  [dry-run] criaria tenant: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
            $this->tenantId = -1;
            return;
        }

        $this->tenantId = (int) DB::table('tenants')->insertGetId($payload);
        $this->stats['tenant']['created'] = 1;
        $this->line("  ✓ Tenant criado (id={$this->tenantId}).");

        // Criar roles padrão para o tenant
        if (function_exists('createDefaultRolesForTenant')) {
            createDefaultRolesForTenant($this->tenantId);
            $this->line('  ✓ Roles padrão criadas para o tenant.');
        }

        // Inicializar dados contabilísticos (plano contas, diários, impostos, CC)
        if (function_exists('initializeAccountingDataForTenant')) {
            try {
                initializeAccountingDataForTenant($this->tenantId);
                $this->line('  ✓ Dados contabilísticos padrão inicializados.');
            } catch (\Throwable $e) {
                $this->warn('  ⚠ Falha a inicializar dados contabilísticos: ' . $e->getMessage());
            }
        }
    }

    private function resolveTenantBySlug(): void
    {
        $slug = $this->option('tenant-slug');
        $existing = DB::table('tenants')->where('slug', $slug)->first();
        if (!$existing) {
            $this->error("Tenant slug='{$slug}' não existe e --skip-tenant foi indicado.");
            return;
        }
        $this->tenantId = (int) $existing->id;
        $this->line("  Tenant resolvido por slug: id={$this->tenantId}");
    }

    private function createOrFindAdmin(): void
    {
        $email = $this->option('admin-email');
        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            $this->stats['user']['existing'] = 1;
            $userId = (int) $existing->id;
            $this->line("  Admin user já existe (id={$userId}).");
        } else {
            if ($this->dryRun) {
                $this->line("  [dry-run] criaria admin email={$email}");
                return;
            }
            $userId = (int) DB::table('users')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->option('admin-name'),
                'email' => $email,
                'password' => Hash::make($this->option('admin-password')),
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->stats['user']['created'] = 1;
            $this->line("  ✓ Admin criado (id={$userId}) password='{$this->option('admin-password')}'");
        }

        if ($this->dryRun) {
            return;
        }

        // Vincular ao tenant via tenant_user (idempotente)
        $linked = DB::table('tenant_user')
            ->where('tenant_id', $this->tenantId)
            ->where('user_id', $userId)
            ->exists();

        if (!$linked) {
            // Owner do tenant recebe sempre 'Super Admin' (todas as permissões)
            $ownerRoleId = DB::table('roles')
                ->where('tenant_id', $this->tenantId)
                ->where('name', 'Super Admin')
                ->value('id');

            DB::table('tenant_user')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $userId,
                'role_id' => $ownerRoleId,
                'is_active' => 1,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->line('  ✓ User vinculado ao tenant via tenant_user.');

            // Atribuir role Spatie 'Super Admin' (owner do tenant)
            if ($ownerRoleId) {
                $modelHas = DB::table('model_has_roles')
                    ->where('role_id', $ownerRoleId)
                    ->where('model_id', $userId)
                    ->where('model_type', \App\Models\User::class)
                    ->exists();
                if (!$modelHas) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $ownerRoleId,
                        'model_type' => \App\Models\User::class,
                        'model_id' => $userId,
                        'tenant_id' => $this->tenantId,
                    ]);
                    $this->line('  ✓ Role "Super Admin" (Spatie) atribuída ao dono do tenant.');
                }
            }
        }

        // Propagar subscription do tenant principal do user (se existir) para
        // este novo tenant, e sincronizar módulos com o plano.
        $this->propagateSubscriptionAndModules($userId);
    }

    /**
     * Se o user já é admin/owner noutros tenants, copia a subscription ativa
     * desses para este novo tenant (se ainda não existir) e ativa os módulos
     * do plano via TenantModuleSyncService.
     */
    private function propagateSubscriptionAndModules(int $userId): void
    {
        // Já tem subscription ativa? não fazer nada
        $hasSub = DB::table('subscriptions')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->exists();

        if (!$hasSub) {
            // Procurar subscription ativa noutro tenant do mesmo user
            $otherSub = DB::table('subscriptions as s')
                ->join('tenant_user as tu', 'tu.tenant_id', '=', 's.tenant_id')
                ->where('tu.user_id', $userId)
                ->where('s.tenant_id', '!=', $this->tenantId)
                ->whereIn('s.status', ['active', 'trial'])
                ->where(function ($q) {
                    $q->whereNull('s.current_period_end')
                      ->orWhere('s.current_period_end', '>=', now());
                })
                ->orderByDesc('s.id')
                ->select('s.*')
                ->first();

            if ($otherSub) {
                DB::table('subscriptions')->insert([
                    'tenant_id' => $this->tenantId,
                    'plan_id' => $otherSub->plan_id,
                    'status' => $otherSub->status,
                    'trial_ends_at' => $otherSub->trial_ends_at,
                    'current_period_start' => $otherSub->current_period_start,
                    'current_period_end' => $otherSub->current_period_end,
                    'ends_at' => $otherSub->ends_at ?? $otherSub->current_period_end,
                    'amount' => $otherSub->amount,
                    'billing_cycle' => $otherSub->billing_cycle ?? 'monthly',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("  ✓ Subscription clonada do tenant existente (plan_id={$otherSub->plan_id}).");
            } else {
                $this->warn('  ⚠ Nenhuma subscription ativa encontrada em outros tenants do user. Módulos podem ficar sem acesso.');
            }
        }

        // Reconciliar módulos do tenant com o plano (idempotente)
        try {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            if ($tenant && $tenant->activeSubscription()->exists()) {
                $svc = app(\App\Services\Tenant\TenantModuleSyncService::class);
                $r = $svc->reconcile($tenant);
                $this->line('  ✓ Módulos sincronizados: ativados=' . count($r['activated']) . ', desativados=' . count($r['deactivated']));
            }
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Falha ao reconciliar módulos: ' . $e->getMessage());
        }
    }

    /* ─────────────────────── CATEGORIAS ─────────────────────── */

    private function importCategories(): void
    {
        $this->line("\n<fg=cyan>▸ CATEGORIAS</>");
        $rows = DB::table("{$this->srcDb}.{$this->srcPrefix}categories")->get();
        $this->line('  Origem: ' . $rows->count() . ' categorias.');

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $r) {
            $name = trim($r->name);
            // idempotência: tenant + name
            $existing = DB::table('invoicing_categories')
                ->where('tenant_id', $this->tenantId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $this->catMap[(int) $r->id] = (int) $existing->id;
                $this->stats['categories']['existing']++;
                $bar->advance();
                continue;
            }

            if ($this->dryRun) {
                $this->catMap[(int) $r->id] = -1;
                $bar->advance();
                continue;
            }

            $newId = DB::table('invoicing_categories')->insertGetId([
                'tenant_id' => $this->tenantId,
                'parent_id' => null, // resolvido em 2ª passagem
                'name' => $name,
                'slug' => Str::slug($name . '-' . $r->id),
                'description' => $r->code ? "Código SMA: {$r->code}" : null,
                'color' => '#3B82F6',
                'order' => (int) $r->id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catMap[(int) $r->id] = (int) $newId;
            $this->stats['categories']['created']++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // 2ª passagem: resolver parent_id
        if (!$this->dryRun) {
            foreach ($rows as $r) {
                if ($r->parent_id && $r->parent_id != 0 && isset($this->catMap[(int) $r->parent_id])) {
                    DB::table('invoicing_categories')
                        ->where('id', $this->catMap[(int) $r->id])
                        ->update(['parent_id' => $this->catMap[(int) $r->parent_id]]);
                }
            }
        }
    }

    private function loadCategoryMap(): void
    {
        $rows = DB::table("{$this->srcDb}.{$this->srcPrefix}categories")->get(['id', 'name']);
        foreach ($rows as $r) {
            $dest = DB::table('invoicing_categories')
                ->where('tenant_id', $this->tenantId)
                ->where('name', trim($r->name))
                ->value('id');
            if ($dest) {
                $this->catMap[(int) $r->id] = (int) $dest;
            }
        }
        $this->line('  Mapa categorias carregado: ' . count($this->catMap));
    }

    /* ─────────────────────── ARMAZÉNS ─────────────────────── */

    private function importWarehouses(): void
    {
        $this->line("\n<fg=cyan>▸ ARMAZÉNS</>");
        $rows = DB::table("{$this->srcDb}.{$this->srcPrefix}warehouses")->get();
        $this->line('  Origem: ' . $rows->count() . ' armazéns.');

        $defaultSourceId = (int) $this->option('default-warehouse-source-id');

        foreach ($rows as $r) {
            $code = trim($r->code) ?: ('WH' . $r->id);
            $name = trim($r->name);

            // idempotência: tenant + code
            $existing = DB::table('invoicing_warehouses')
                ->where('tenant_id', $this->tenantId)
                ->where('code', $code)
                ->first();

            if ($existing) {
                $this->whMap[(int) $r->id] = (int) $existing->id;
                $this->stats['warehouses']['existing']++;
                $this->line("    ↷ '{$name}' (code={$code}) já existe.");
                continue;
            }

            if ($this->dryRun) {
                $this->whMap[(int) $r->id] = -1;
                $this->line("    + '{$name}' (code={$code}) [dry-run]");
                continue;
            }

            $newId = DB::table('invoicing_warehouses')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $name,
                'code' => $code,
                'address' => $this->stripHtml($r->address),
                'phone' => $r->phone,
                'email' => $r->email,
                'is_active' => 1,
                'is_default' => $r->id == $defaultSourceId ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->whMap[(int) $r->id] = (int) $newId;
            $this->stats['warehouses']['created']++;
            $this->line("    + '{$name}' (code={$code}) → id={$newId}" . ($r->id == $defaultSourceId ? ' [DEFAULT]' : ''));
        }
    }

    private function loadWarehouseMap(): void
    {
        $rows = DB::table("{$this->srcDb}.{$this->srcPrefix}warehouses")->get(['id', 'code']);
        foreach ($rows as $r) {
            $dest = DB::table('invoicing_warehouses')
                ->where('tenant_id', $this->tenantId)
                ->where('code', trim($r->code))
                ->value('id');
            if ($dest) {
                $this->whMap[(int) $r->id] = (int) $dest;
            }
        }
        $this->line('  Mapa armazéns carregado: ' . count($this->whMap));
    }

    /* ─────────────────────── PRODUTOS ─────────────────────── */

    private function importProducts(): void
    {
        $this->line("\n<fg=cyan>▸ PRODUTOS</>");
        $chunkSize = (int) $this->option('chunk');
        $total = DB::table("{$this->srcDb}.{$this->srcPrefix}products")->count();
        $this->line("  Origem: {$total} produtos. Chunk: {$chunkSize}.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table("{$this->srcDb}.{$this->srcPrefix}products")
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use ($bar) {
                foreach ($rows as $r) {
                    $this->upsertProduct($r);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
    }

    private function upsertProduct(object $r): void
    {
        $code = trim($r->code);
        if (!$code) {
            $this->stats['products']['errors']++;
            return;
        }

        // idempotência: tenant + code
        $existing = DB::table('invoicing_products')
            ->where('tenant_id', $this->tenantId)
            ->where('code', $code)
            ->first();

        if ($existing) {
            $this->prodMap[(int) $r->id] = (int) $existing->id;
            $this->stats['products']['existing']++;
            return;
        }

        if ($this->dryRun) {
            $this->prodMap[(int) $r->id] = -1;
            $this->stats['products']['created']++;
            return;
        }

        $categoryId = $this->catMap[(int) $r->category_id] ?? null;

        try {
            $newId = DB::table('invoicing_products')->insertGetId([
                'tenant_id' => $this->tenantId,
                'category_id' => $categoryId,
                'type' => 'produto',
                'code' => $code,
                'barcode' => $code,
                'name' => trim($r->name),
                'description' => $r->product_details ?: $r->details,
                'price' => (float) $r->price,
                'cost' => (float) $r->cost,
                // Fiscalidade herdada do REGIME do tenant: com 'iva' fixo, uma
                // farmácia em regime de isenção acabou a vender com IVA 14%.
                'tax_type' => $this->taxDefaults()['tax_type'],
                'tax_rate_id' => $this->taxDefaults()['tax_rate_id'],
                'exemption_reason' => $this->taxDefaults()['exemption_reason'],
                'manage_stock' => (int) ($r->track_quantity ?? 1),
                'stock_quantity' => (int) round((float) $r->quantity),
                'stock_min' => (int) round((float) ($r->alert_quantity ?? 0)),
                'minimum_stock' => (int) round((float) ($r->alert_quantity ?? 0)),
                'unit' => 'UN',
                'is_active' => $r->hide ? 0 : 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->prodMap[(int) $r->id] = (int) $newId;
            $this->stats['products']['created']++;
        } catch (\Throwable $e) {
            $this->stats['products']['errors']++;
            if ($this->stats['products']['errors'] <= 5) {
                $this->newLine();
                $this->warn("    ✗ produto id={$r->id} code={$code}: " . $e->getMessage());
            }
        }
    }

    private function loadProductMap(): void
    {
        $this->line('  Carregando mapa produtos (pode demorar)…');
        DB::table("{$this->srcDb}.{$this->srcPrefix}products")
            ->orderBy('id')
            ->chunk(2000, function ($rows) {
                foreach ($rows as $r) {
                    $dest = DB::table('invoicing_products')
                        ->where('tenant_id', $this->tenantId)
                        ->where('code', trim($r->code))
                        ->value('id');
                    if ($dest) {
                        $this->prodMap[(int) $r->id] = (int) $dest;
                    }
                }
            });
        $this->line('  Mapa produtos carregado: ' . count($this->prodMap));
    }

    /* ─────────────────────── STOCK ─────────────────────── */

    private function importStock(): void
    {
        $this->line("\n<fg=cyan>▸ STOCK POR ARMAZÉM</>");
        $chunkSize = (int) $this->option('chunk');
        $total = DB::table("{$this->srcDb}.{$this->srcPrefix}warehouses_products")->count();
        $this->line("  Origem: {$total} linhas. Chunk: {$chunkSize}.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table("{$this->srcDb}.{$this->srcPrefix}warehouses_products")
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use ($bar) {
                foreach ($rows as $r) {
                    $this->upsertStock($r);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        // Ressincronizar o agregado products.stock_quantity = SUM(invoicing_stocks).
        // As escritas acima são via DB::table (query builder) e NÃO disparam o
        // StockObserver — sem este passo o agregado ficava divergente das linhas
        // de armazém (foi a causa das 1604 divergências do tenant 17).
        if (!$this->dryRun) {
            $affected = DB::update('
                UPDATE invoicing_products p
                JOIN (
                    SELECT tenant_id, product_id, SUM(quantity) AS s
                    FROM invoicing_stocks
                    WHERE tenant_id = ?
                    GROUP BY tenant_id, product_id
                ) a ON a.tenant_id = p.tenant_id AND a.product_id = p.id
                SET p.stock_quantity = a.s
                WHERE p.tenant_id = ? AND ROUND(p.stock_quantity, 3) <> ROUND(a.s, 3)
            ', [$this->tenantId, $this->tenantId]);
            $this->line("  Agregado ressincronizado: {$affected} produto(s) atualizado(s).");
        }
    }

    /** Cache da fiscalidade do tenant (regime + imposto por omissão). */
    private ?array $taxDefaultsCache = null;

    /**
     * Estado fiscal a aplicar aos produtos importados, derivado do REGIME do
     * tenant de destino (Tenant::REGIMES) e do seu imposto por omissão.
     */
    private function taxDefaults(): array
    {
        if ($this->taxDefaultsCache !== null) {
            return $this->taxDefaultsCache;
        }

        $tenant  = \App\Models\Tenant::find($this->tenantId);
        $meta    = $tenant?->regimeMeta() ?? \App\Models\Tenant::REGIMES[\App\Models\Tenant::REGIME_GERAL];
        $defTax  = \App\Models\Invoicing\Tax::where('tenant_id', $this->tenantId)
            ->where('is_default', true)
            ->first();

        $this->taxDefaultsCache = $meta['exempt']
            ? [
                'tax_type'         => 'isento',
                'tax_rate_id'      => null,
                'exemption_reason' => $defTax->exemption_code
                    ?? $meta['exemption_code']
                    ?? \App\Services\Tenant\TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE,
            ]
            : [
                'tax_type'         => 'iva',
                'tax_rate_id'      => $defTax?->id,
                'exemption_reason' => null,
            ];

        return $this->taxDefaultsCache;
    }

    private function upsertStock(object $r): void
    {
        $productId = $this->prodMap[(int) $r->product_id] ?? null;
        $warehouseId = $this->whMap[(int) $r->warehouse_id] ?? null;

        if (!$productId || !$warehouseId) {
            $this->stats['stock']['orphan']++;
            return;
        }

        if ($this->dryRun) {
            $this->stats['stock']['created']++;
            return;
        }

        // Clamp: a BD origem (SMA) permite quantidades negativas — nunca importar
        // negativos (no tenant 17 entraram 7 linhas negativas tal-e-qual).
        $qty = max(0.0, (float) $r->quantity);
        $cost = (float) $r->avg_cost;

        $existing = DB::table('invoicing_stocks')
            ->where('tenant_id', $this->tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($existing) {
            // Por omissão NÃO sobrescrever linhas existentes: num re-run pós-go-live
            // isto substituía o stock VIVO do soserp pelo estado da BD legada
            // (26.596 linhas sobrescritas no tenant 17 em 2026-06-30, apagando um
            // mês de vendas/ajustes). Só com --overwrite-stock explícito.
            if (!$this->option('overwrite-stock')) {
                $this->stats['stock']['skipped'] = ($this->stats['stock']['skipped'] ?? 0) + 1;
                return;
            }
            DB::table('invoicing_stocks')->where('id', $existing->id)->update([
                'quantity' => $qty,
                'available_quantity' => $qty,
                'unit_cost' => $cost,
                'updated_at' => now(),
            ]);
            $this->stats['stock']['updated']++;
        } else {
            DB::table('invoicing_stocks')->insert([
                'tenant_id' => $this->tenantId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $qty,
                'reserved_quantity' => 0,
                'available_quantity' => $qty,
                'minimum_quantity' => 0,
                'unit_cost' => $cost,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->stats['stock']['created']++;
        }
    }

    /* ─────────────────────── CAIXAS (POS) ─────────────────────── */

    private function createCashiers(): void
    {
        $this->line("\n<fg=cyan>▸ CAIXAS (role POS)</>");

        $cashiers = [
            ['name' => 'Suzana Matias',     'email' => 'suzana.matias@farmacianevesbendinha.local'],
            ['name' => 'Rosa Sebastião',    'email' => 'rosa.sebastiao@farmacianevesbendinha.local'],
            ['name' => 'Engrácia Fontes',   'email' => 'engracia.fontes@farmacianevesbendinha.local'],
        ];

        $caixaRoleId = DB::table('roles')
            ->where('tenant_id', $this->tenantId)
            ->where('name', 'Caixa')
            ->value('id');

        if (!$caixaRoleId) {
            $this->error('  Role "Caixa" não existe para este tenant.');
            return;
        }

        $userType = \App\Models\User::class;
        $password = $this->option('cashier-password');

        foreach ($cashiers as $c) {
            $existing = DB::table('users')->where('email', $c['email'])->first();
            if ($existing) {
                $userId = (int) $existing->id;
                $this->stats['cashiers']['existing']++;
                $this->line("  ↷ {$c['name']} ({$c['email']}) já existe (id={$userId}).");
            } else {
                if ($this->dryRun) {
                    $this->line("  [dry-run] criaria caixa {$c['name']} <{$c['email']}>");
                    continue;
                }
                $userId = (int) DB::table('users')->insertGetId([
                    'tenant_id' => $this->tenantId,
                    'name' => $c['name'],
                    'email' => $c['email'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->stats['cashiers']['created']++;
                $this->line("  + {$c['name']} <{$c['email']}> id={$userId} (pwd: {$password})");
            }

            if ($this->dryRun) {
                continue;
            }

            // Vincular ao tenant via tenant_user
            $linked = DB::table('tenant_user')
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $userId)
                ->exists();
            if (!$linked) {
                DB::table('tenant_user')->insert([
                    'tenant_id' => $this->tenantId,
                    'user_id' => $userId,
                    'role_id' => $caixaRoleId,
                    'is_active' => 1,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Spatie role
            $hasSpatie = DB::table('model_has_roles')
                ->where('role_id', $caixaRoleId)
                ->where('model_id', $userId)
                ->where('model_type', $userType)
                ->where('tenant_id', $this->tenantId)
                ->exists();
            if (!$hasSpatie) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $caixaRoleId,
                    'model_type' => $userType,
                    'model_id' => $userId,
                    'tenant_id' => $this->tenantId,
                ]);
            }
        }
    }

    /* ─────────────────────── HELPERS / SUMMARY ─────────────────────── */

    private function stripHtml(?string $s): ?string
    {
        if (!$s) {
            return null;
        }
        $clean = trim(strip_tags($s));
        return $clean !== '' ? $clean : null;
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  RESUMO DA IMPORTAÇÃO');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->table(
            ['Entidade', 'Criados', 'Existentes', 'Erros/Órfãos'],
            [
                ['Tenant',     $this->stats['tenant']['created'],     $this->stats['tenant']['existing'],     '-'],
                ['Admin user', $this->stats['user']['created'],       $this->stats['user']['existing'],       '-'],
                ['Categorias', $this->stats['categories']['created'], $this->stats['categories']['existing'], '-'],
                ['Armazéns',   $this->stats['warehouses']['created'], $this->stats['warehouses']['existing'], '-'],
                ['Produtos',   $this->stats['products']['created'],   $this->stats['products']['existing'],   $this->stats['products']['errors']],
                ['Stock (novos/upd)', $this->stats['stock']['created'], $this->stats['stock']['updated'],     $this->stats['stock']['orphan'] . ' órfãos'],
                ['Caixas',     $this->stats['cashiers']['created'],     $this->stats['cashiers']['existing'],   '-'],
            ]
        );
        $this->line("  Tenant ID destino: <fg=green>{$this->tenantId}</>");
        if ($this->dryRun) {
            $this->warn('  ⚠ Dry-run — nenhuma alteração foi persistida.');
        }
    }
}
