<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'is_super_admin',
        'phone',
        'bio',
        'avatar',
        'is_active',
        'last_login_at',
        'last_password_changed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'last_password_changed' => 'datetime',
        ];
    }

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role_id', 'is_active', 'invited_at', 'joined_at')
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    /**
     * Retorna o tenant ativo no momento
     */
    /**
     * Memória do pedido, com a empresa da sessão na chave.
     *
     * Cada chamada fazia uma query a tenant_user, e activeTenantId() é chamado
     * dezenas de vezes por pedido (27 vezes só no POS). Trocar de empresa muda
     * o valor da sessão, logo muda a chave e força nova consulta — a troca
     * continua a funcionar dentro do mesmo pedido.
     */
    protected array $memoriaTenantActivo = [];

    public function activeTenant()
    {
        // Verifica se há uma sessão explícita de troca de empresa
        $sessionTenantId = session('active_tenant_id');
        $userTenantId = $this->tenant_id;

        $chave = (string) ($sessionTenantId ?? '-') . '|' . (string) ($userTenantId ?? '-');
        if (array_key_exists($chave, $this->memoriaTenantActivo)) {
            return $this->memoriaTenantActivo[$chave];
        }

        return $this->memoriaTenantActivo[$chave] = $this->resolverTenantActivo($sessionTenantId, $userTenantId);
    }

    /** Resolução verdadeira, sem memória (ver activeTenant). */
    protected function resolverTenantActivo($sessionTenantId, $userTenantId)
    {

        // Se há sessão E é diferente do tenant padrão do usuário,
        // significa que o usuário trocou manualmente de empresa
        if ($sessionTenantId && $sessionTenantId != $userTenantId) {
            // Usar tenant da sessão (troca manual)
            $tenant = $this->tenants()->find($sessionTenantId);
            if ($tenant) {
                return $tenant;
            }
        }
        
        // Caso contrário, usar tenant padrão do usuário (mais atualizado do banco)
        if ($userTenantId) {
            $tenant = $this->tenants()->find($userTenantId);
            if ($tenant) {
                // Sincroniza sessão com tenant padrão
                session(['active_tenant_id' => $userTenantId]);
                return $tenant;
            }
        }
        
        // Se não tem nada, pega primeiro tenant
        $firstTenant = $this->tenants()->first();
        if ($firstTenant) {
            session(['active_tenant_id' => $firstTenant->id]);
            return $firstTenant;
        }
        
        return null;
    }
    
    /**
     * Retorna o ID do tenant ativo
     */
    public function activeTenantId()
    {
        $tenant = $this->activeTenant();
        return $tenant ? $tenant->id : null;
    }

    /**
     * Troca para outro tenant
     */
    public function switchTenant($tenantId)
    {
        // Verifica se o usuário tem acesso a esse tenant
        if ($this->tenants()->where('tenant_id', $tenantId)->exists()) {
            session(['active_tenant_id' => $tenantId]);
            setPermissionsTeamId($tenantId);
            
            \Log::info("User {$this->id} ({$this->email}) switched to tenant {$tenantId}");
            return true;
        }
        
        \Log::warning("User {$this->id} ({$this->email}) tried to switch to tenant {$tenantId} without permission");
        return false;
    }
    
    /**
     * Compatibilidade com código anterior
     */
    public function currentTenant()
    {
        return $this->activeTenant();
    }

    // Métodos auxiliares
    public function isSuperAdmin()
    {
        return $this->is_super_admin === true;
    }

    /**
     * Super Admin DA PLATAFORMA (acesso a /superadmin: todas as empresas, chaves
     * SAFT, script runner, SMTP…).
     *
     * ATENÇÃO: o papel 'Super Admin' do Spatie é POR EMPRESA (teams), pelo que o
     * dono de cada tenant tem esse papel dentro da sua própria empresa. Usar
     * hasRole('Super Admin') como porta de entrada dava acesso ao painel global
     * a qualquer dono de empresa — escalada de privilégios.
     *
     * Só conta: a flag da plataforma OU um papel 'Super Admin' GLOBAL
     * (roles.tenant_id IS NULL), que não pertence a nenhuma empresa.
     */
    public function isPlatformSuperAdmin(): bool
    {
        if ($this->is_super_admin === true) {
            return true;
        }

        // A role tem de ser global E a ATRIBUIÇÃO também. Verificar apenas
        // roles.tenant_id deixava passar utilizadores de empresa: a role
        // "Super Admin" original ficou com tenant_id NULL e foi atribuída dentro
        // do contexto de um tenant (model_has_roles.tenant_id = 1), o que dava
        // acesso ao /superadmin a quem só devia gerir a sua própria empresa.
        return \Illuminate\Support\Facades\DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_id', $this->id)
            ->where('mhr.model_type', static::class)
            ->where('r.name', 'Super Admin')
            ->whereNull('r.tenant_id')
            ->whereNull('mhr.tenant_id')
            ->exists();
    }

    /**
     * Pode gerir a conta a nível administrativo (empresas, plano, faturação).
     * Apenas o Super Admin global, o dono do tenant (role 'Super Admin') e 'Admin'.
     * Utilizadores de nível baixo (Utilizador, Vendedor, Caixa, Gestor) não podem.
     */
    public function canManageAccount(): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        // Garantir contexto de equipa (tenant) para as roles Spatie
        if (function_exists('setPermissionsTeamId') && function_exists('activeTenantId') && activeTenantId()) {
            setPermissionsTeamId(activeTenantId());
        }

        return $this->hasAnyRole(['Super Admin', 'Admin']);
    }

    public function belongsToTenant($tenantId)
    {
        return $this->tenants()->where('tenants.id', $tenantId)->exists();
    }

    /**
     * Compatibilidade com código anterior
     */
    public function setTenant($tenantId)
    {
        return $this->switchTenant($tenantId);
    }
    
    /**
     * Retorna a role do usuário no tenant ativo
     */
    public function roleInActiveTenant()
    {
        $activeTenant = $this->activeTenant();
        if (!$activeTenant) {
            return null;
        }
        
        $pivot = $this->tenants()
            ->where('tenant_id', $activeTenant->id)
            ->first()
            ->pivot ?? null;
        
        return $pivot ? $pivot->role_id : null;
    }

    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }

    public function hasActiveModule($moduleSlug)
    {
        $activeTenant = $this->activeTenant();
        
        if (!$activeTenant) {
            \Log::warning("User {$this->id} ({$this->email}) não tem tenant ativo");
            return false;
        }

        // Usar o método hasModule do Tenant que já valida is_active
        $hasModule = $activeTenant->hasModule($moduleSlug);

        \Log::info("Check module '{$moduleSlug}' for user {$this->id} ({$this->email}), tenant {$activeTenant->id} (active: {$activeTenant->is_active}): " . ($hasModule ? 'YES' : 'NO'));

        return $hasModule;
    }

    /**
     * Verifica se o utilizador deve VER o menu de um módulo no sidebar.
     * Diferente de hasActiveModule (que só vê o tenant), este helper também
     * verifica se a role/permissões do user têm acesso a alguma rota do módulo.
     *
     * Super Admin sempre vê todos os menus.
     * Admin/Gestor/Utilizador têm permissões em todos os módulos por defeito.
     * Roles especializadas (Caixa, Vendedor, Contabilista, Operador Stock) só vêem
     * menus dos módulos onde têm pelo menos 1 permissão.
     */
    public function canAccessModuleMenu($moduleSlug)
    {
        // Apenas o Super Admin GLOBAL (flag is_super_admin) faz bypass das
        // restrições de módulo. O role "Super Admin" do tenant continua sujeito
        // ao plano contratado — caso contrário um downgrade não esconderia nada.
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Tenant precisa de ter o módulo activo (respeita o plano)
        if (!$this->hasActiveModule($moduleSlug)) {
            return false;
        }

        // Mapear slug do módulo → prefixo(s) de permissões/rotas
        $prefixMap = [
            'invoicing'     => ['invoicing.', 'customers.', 'products.', 'treasury.'],
            'contabilidade' => ['accounting.'],
            'oficina'       => ['workshop.'],
            'eventos'       => ['events.', 'eventos.'],
            'rh'            => ['hr.', 'rh.'],
            'crm'           => ['crm.'],
            'inventario'    => ['inventario.', 'inventory.'],
            'compras'       => ['compras.', 'purchases.'],
            'projetos'      => ['projetos.', 'projects.'],
            'hotel'         => ['hotel.'],
            'salon'         => ['salon.'],
            'restaurant'    => ['restaurant.'],
            'notifications' => ['notifications.'],
            'treasury'      => ['treasury.'],
        ];

        $prefixes = $prefixMap[$moduleSlug] ?? [$moduleSlug . '.'];

        // Garantir contexto de team correcto para Spatie
        $tenant = $this->activeTenant();
        if ($tenant) {
            setPermissionsTeamId($tenant->id);
        }

        // Verificar se o user tem PELO MENOS UMA permissão com algum dos prefixos
        $userPerms = $this->getAllPermissions()->pluck('name');
        foreach ($userPerms as $perm) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($perm, $prefix)) {
                    return true;
                }
            }
        }

        // Salvaguarda: se o SISTEMA não tem nenhuma permissão registada com estes
        // prefixos, exigi-las esconde o módulo a toda a gente — foi o que aconteceu
        // com a Contabilidade (0 permissões 'accounting.*' e as rotas nem as usam).
        // Sem permissões definidas, o módulo activo no plano é o único critério.
        $existemPermissoes = cache()->remember(
            'module_perms_exist:' . $moduleSlug,
            300,
            function () use ($prefixes) {
                return \Spatie\Permission\Models\Permission::where(function ($q) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $q->orWhere('name', 'like', $prefix . '%');
                    }
                })->exists();
            }
        );

        return !$existemPermissoes;
    }
    
    /**
     * Retorna o limite de empresas do usuário baseado no plano
     */
    public function getMaxCompaniesLimit()
    {
        // Super Admin não tem limite
        if ($this->is_super_admin) {
            return PHP_INT_MAX;
        }
        
        // BUG-04 FIX: Procurar subscription activa em QUALQUER tenant do user
        $maxCompanies = 1; // Default
        
        foreach ($this->tenants as $tenant) {
            $subscription = $tenant->subscriptions()
                ->with('plan')
                ->whereIn('status', ['active', 'trial'])
                ->where(function($q) {
                    $q->whereNull('current_period_end')
                      ->orWhere('current_period_end', '>=', now());
                })
                ->latest()
                ->first();
            
            if ($subscription && $subscription->plan) {
                $planMax = $subscription->plan->max_companies ?? 1;
                if ($planMax > $maxCompanies) {
                    $maxCompanies = $planMax;
                }
            }
        }
        
        return $maxCompanies;
    }
    
    /**
     * Verifica se o usuário pode adicionar mais empresas
     */
    public function canAddMoreCompanies()
    {
        $currentCount = $this->tenants()->count();
        $maxAllowed = $this->getMaxCompaniesLimit();
        
        return $currentCount < $maxAllowed;
    }
    
    /**
     * Enviar notificação de reset de senha com SMTP e template do banco
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
