<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_quarterly',
        'price_semiannual',
        'price_yearly',
        'max_users',
        'max_companies',
        'max_storage_mb',
        'features',
        'included_modules',
        'is_active',
        'is_public',
        'is_featured',
        'is_promotional',
        'trial_days',
        'auto_activate',
        'order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_quarterly' => 'decimal:2',
        'price_semiannual' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'included_modules' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'is_promotional' => 'boolean',
        'auto_activate' => 'boolean',
        'trial_days' => 'integer',
        'max_users' => 'integer',
        'max_companies' => 'integer',
        'max_storage_mb' => 'integer',
        'order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    // Relacionamentos
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'plan_module')
            ->withTimestamps();
    }

    /**
     * Slugs dos módulos do plano JÁ COM as dependências resolvidas.
     *
     * É esta a lista que qualquer sincronização deve usar: há caminhos
     * (superadmin, comandos de resync) que sincronizam o tenant directamente a
     * partir do plano e, como os planos Business/Enterprise não listavam a
     * Tesouraria, apagavam-na aos clientes — deixando a Faturação sem formas de
     * pagamento. Resolver aqui garante a regra em TODOS os caminhos.
     *
     * @return string[]
     */
    public function moduleSlugsWithDependencies(): array
    {
        $slugs = $this->modules()->pluck('modules.slug')->toArray();

        $resolved = [];
        foreach ($slugs as $slug) {
            foreach (\App\Services\Tenant\TenantModuleSyncService::withDependencies($slug) as $s) {
                if (!in_array($s, $resolved, true)) {
                    $resolved[] = $s;
                }
            }
        }

        // Só módulos que existem mesmo no catálogo
        return Module::whereIn('slug', $resolved)->pluck('slug')->toArray();
    }

    /** Ids dos módulos do plano já com dependências (para syncs por id). */
    public function moduleIdsWithDependencies(): array
    {
        return Module::whereIn('slug', $this->moduleSlugsWithDependencies())
            ->pluck('id')
            ->toArray();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Os planos da MONTRA: activos e públicos.
     *
     * Um plano feito à medida de um cliente fica activo (para a subscrição
     * funcionar e continuar visível na gestão) mas fora daqui — não tem de
     * aparecer a quem não o comprou.
     */
    public function scopePublico($query)
    {
        return $query->where('is_active', true)->where('is_public', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Accessors - Calcular preços automaticamente se não definidos
    public function getPriceQuarterlyAttribute($value)
    {
        // Se não definido ou zero, calcula 3x o mensal
        $monthly = $this->attributes['price_monthly'] ?? 0;
        return $value > 0 ? $value : ($monthly * 3);
    }

    public function getPriceSemiannualAttribute($value)
    {
        // Se não definido ou zero, calcula 6x o mensal
        $monthly = $this->attributes['price_monthly'] ?? 0;
        return $value > 0 ? $value : ($monthly * 6);
    }

    public function getPriceYearlyAttribute($value)
    {
        // Se não definido ou zero, calcula 12x o mensal
        $monthly = $this->attributes['price_monthly'] ?? 0;
        return $value > 0 ? $value : ($monthly * 12);
    }

    // Métodos auxiliares
    public function getPrice($billingCycle = 'monthly')
    {
        return match($billingCycle) {
            'quarterly' => $this->price_quarterly,
            'semiannual' => $this->price_semiannual,
            'yearly' => $this->price_yearly,
            default => $this->price_monthly,
        };
    }

    public function hasModule($moduleSlug)
    {
        // BUG-06 FIX: Usar pivot table plan_module (fonte de verdade) em vez de JSON
        return $this->modules()->where('slug', $moduleSlug)->exists();
    }

    public function getYearlySavings()
    {
        $monthlyYearly = $this->price_monthly * 12;
        return $monthlyYearly - $this->price_yearly;
    }

    public function getYearlySavingsPercentage()
    {
        $monthlyYearly = $this->price_monthly * 12;
        if ($monthlyYearly == 0) return 0;
        
        return round((($monthlyYearly - $this->price_yearly) / $monthlyYearly) * 100);
    }

    /**
     * Sincronizar módulos do plano com todos os tenants que têm subscription ativa
     */
    public function syncModulesToTenants()
    {
        try {
            // Pegar IDs dos módulos vinculados ao plano
            $moduleIds = $this->modules()->pluck('modules.id')->toArray();

            if (empty($moduleIds)) {
                \Log::info("Plano {$this->name} não tem módulos vinculados.");
                return 0;
            }

            // Buscar todos os tenants com subscription ativa deste plano
            $tenants = $this->subscriptions()
                ->where('status', 'active')
                ->with('tenant')
                ->get()
                ->pluck('tenant')
                ->filter(); // Remove nulls

            if ($tenants->isEmpty()) {
                \Log::info("Plano {$this->name} não tem tenants com subscription ativa.");
                return 0;
            }

            $syncedCount = 0;

            // Para cada tenant, vincular os módulos do plano
            foreach ($tenants as $tenant) {
                if (!$tenant) continue;

                // Preparar dados para sincronização
                $modulesToSync = [];
                foreach ($moduleIds as $moduleId) {
                    $modulesToSync[$moduleId] = [
                        'is_active' => true,
                        'activated_at' => now(),
                        // Activar por direito de plano encerra qualquer teste
                        // anterior — senao um trial expirado bloqueava o
                        // modulo mesmo depois de o cliente pagar o plano.
                        'trial_ends_at' => null,
                    ];
                }

                // Sincronizar sem remover módulos existentes
                $tenant->modules()->syncWithoutDetaching($modulesToSync);
                $syncedCount++;

                \Log::info("Módulos do plano '{$this->name}' sincronizados com tenant '{$tenant->name}' (ID: {$tenant->id})");
            }

            \Log::info("Total de {$syncedCount} tenant(s) sincronizados com os módulos do plano '{$this->name}'");

            return $syncedCount;

        } catch (\Exception $e) {
            \Log::error("Erro ao sincronizar módulos do plano {$this->name}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Sincronizar um módulo específico com todos os tenants do plano
     */
    public function syncModuleToTenants($moduleId)
    {
        try {
            // Verificar se o módulo está vinculado ao plano
            if (!$this->modules()->where('modules.id', $moduleId)->exists()) {
                \Log::warning("Módulo ID {$moduleId} não está vinculado ao plano {$this->name}");
                return 0;
            }

            // Buscar todos os tenants com subscription ativa
            $tenants = $this->subscriptions()
                ->where('status', 'active')
                ->with('tenant')
                ->get()
                ->pluck('tenant')
                ->filter();

            $syncedCount = 0;

            foreach ($tenants as $tenant) {
                if (!$tenant) continue;

                $tenant->modules()->syncWithoutDetaching([
                    $moduleId => [
                        'is_active' => true,
                        'activated_at' => now(),
                        // Ver acima: activar por direito de plano encerra
                        // qualquer período de teste anterior do módulo.
                        'trial_ends_at' => null,
                    ]
                ]);

                $syncedCount++;
            }

            return $syncedCount;

        } catch (\Exception $e) {
            \Log::error("Erro ao sincronizar módulo {$moduleId} do plano {$this->name}: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Adicionar um módulo aos tenants que têm subscription ativa deste plano
     */
    public function addModuleToTenants($moduleId)
    {
        try {
            $module = Module::find($moduleId);
            if (!$module) {
                \Log::error("Módulo ID {$moduleId} não encontrado");
                return 0;
            }

            // Buscar todos os tenants com subscription ativa
            $tenants = $this->subscriptions()
                ->where('status', 'active')
                ->with('tenant')
                ->get()
                ->pluck('tenant')
                ->filter();

            if ($tenants->isEmpty()) {
                return 0;
            }

            $addedCount = 0;

            foreach ($tenants as $tenant) {
                if (!$tenant) continue;

                // Adicionar módulo ao tenant (sem remover os existentes)
                $tenant->modules()->syncWithoutDetaching([
                    $moduleId => [
                        'is_active' => true,
                        'activated_at' => now(),
                        // Ver acima: activar por direito de plano encerra
                        // qualquer período de teste anterior do módulo.
                        'trial_ends_at' => null,
                    ]
                ]);

                $addedCount++;

                \Log::info("✅ Módulo '{$module->name}' adicionado ao tenant '{$tenant->name}' (Plano: {$this->name})");
            }

            return $addedCount;

        } catch (\Exception $e) {
            \Log::error("Erro ao adicionar módulo {$moduleId} aos tenants do plano {$this->name}: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Remover um módulo dos tenants que têm subscription ativa deste plano
     */
    public function removeModuleFromTenants($moduleId)
    {
        try {
            $module = Module::find($moduleId);
            if (!$module) {
                \Log::error("Módulo ID {$moduleId} não encontrado");
                return 0;
            }

            // Buscar todos os tenants com subscription ativa
            $tenants = $this->subscriptions()
                ->where('status', 'active')
                ->with('tenant')
                ->get()
                ->pluck('tenant')
                ->filter();

            if ($tenants->isEmpty()) {
                return 0;
            }

            $removedCount = 0;

            foreach ($tenants as $tenant) {
                if (!$tenant) continue;

                // Remover módulo do tenant
                $tenant->modules()->detach($moduleId);

                $removedCount++;

                \Log::info("❌ Módulo '{$module->name}' removido do tenant '{$tenant->name}' (Plano: {$this->name})");
            }

            return $removedCount;

        } catch (\Exception $e) {
            \Log::error("Erro ao remover módulo {$moduleId} dos tenants do plano {$this->name}: " . $e->getMessage());
            return 0;
        }
    }
}
