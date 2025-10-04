<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'avatar',
        'is_active',
        'last_login_at',
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
    public function activeTenant()
    {
        // Verifica se há uma sessão explícita de troca de empresa
        $sessionTenantId = session('active_tenant_id');
        $userTenantId = $this->tenant_id;
        
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

        $hasModule = $activeTenant->modules()
            ->where('slug', $moduleSlug)
            ->wherePivot('is_active', true)
            ->exists();

        \Log::info("Check module '{$moduleSlug}' for user {$this->id} ({$this->email}), tenant {$activeTenant->id}: " . ($hasModule ? 'YES' : 'NO'));

        return $hasModule;
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
        
        // Pegar o plano de qualquer tenant ativo do usuário
        $activeTenant = $this->activeTenant();
        if (!$activeTenant) {
            return 1; // Padrão
        }
        
        $subscription = $activeTenant->activeSubscription;
        if (!$subscription) {
            return 1; // Padrão se não tiver subscription
        }
        
        return $subscription->plan->max_companies ?? 1;
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
}
