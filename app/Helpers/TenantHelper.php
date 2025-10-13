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
        
        $user = auth()->user();
        
        // Verificar se é um User (não Client)
        if (!method_exists($user, 'activeTenantId')) {
            return null;
        }
        
        return $user->activeTenantId();
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
        
        $user = auth()->user();
        
        // Verificar se é um User (não Client)
        if (!method_exists($user, 'activeTenant')) {
            return null;
        }
        
        return $user->activeTenant();
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
        
        // Verificar se é um User (não Client)
        if (!method_exists($user, 'tenants') || !property_exists($user, 'is_super_admin')) {
            return false;
        }
        
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
        
        // Verificar se é um User (não Client)
        if (!method_exists($user, 'tenants') || !property_exists($user, 'is_super_admin')) {
            return false;
        }
        
        // Super Admin nunca excede
        if ($user->is_super_admin) {
            return false;
        }
        
        $currentCount = $user->tenants()->count();
        $maxAllowed = $user->getMaxCompaniesLimit();
        
        return $currentCount > $maxAllowed;
    }
}
