<?php

if (!function_exists('activeTenantId')) {
    /**
     * Retorna o ID do tenant ativo do usuário autenticado
     */
    function activeTenantId()
    {
        if (!auth()->check()) {
            return null;
        }
        
        return auth()->user()->activeTenantId();
    }
}

if (!function_exists('activeTenant')) {
    /**
     * Retorna o tenant ativo do usuário autenticado
     */
    function activeTenant()
    {
        if (!auth()->check()) {
            return null;
        }
        
        return auth()->user()->activeTenant();
    }
}

if (!function_exists('canSwitchTenants')) {
    /**
     * Verifica se o usuário pode trocar de tenant (tem mais de 1 E está dentro do limite do plano)
     */
    function canSwitchTenants()
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        // Super Admin sempre pode
        if ($user->is_super_admin) {
            return $user->tenants()->count() > 1;
        }
        
        // Verificar se tem múltiplas empresas
        $tenantCount = $user->tenants()->count();
        if ($tenantCount <= 1) {
            return false;
        }
        
        // Verificar se está dentro do limite do plano
        $maxAllowed = $user->getMaxCompaniesLimit();
        
        // Se tiver mais empresas que o permitido, ainda mostra switcher
        // mas com aviso de upgrade necessário
        return true;
    }
}

if (!function_exists('hasExceededCompanyLimit')) {
    /**
     * Verifica se o usuário excedeu o limite de empresas do plano
     */
    function hasExceededCompanyLimit()
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        // Super Admin nunca excede
        if ($user->is_super_admin) {
            return false;
        }
        
        $currentCount = $user->tenants()->count();
        $maxAllowed = $user->getMaxCompaniesLimit();
        
        return $currentCount > $maxAllowed;
    }
}
