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

// ====== DATE HELPERS (Formato Português) ======

if (!function_exists('formatDate')) {
    /**
     * Formatar data para exibição PT (dd/mm/yyyy)
     */
    function formatDate($date, $format = 'd/m/Y')
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->format($format);
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Formatar data e hora PT
     */
    function formatDateTime($date, $format = 'd/m/Y H:i')
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->format($format);
    }
}

if (!function_exists('formatDateLong')) {
    /**
     * Formatar data por extenso
     */
    function formatDateLong($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->locale('pt')->isoFormat('D [de] MMMM [de] YYYY');
    }
}

if (!function_exists('dateToDb')) {
    /**
     * Converter de formato PT para Y-m-d (para DB)
     */
    function dateToDb($date)
    {
        if (!$date) return null;
        
        // Se já está no formato correto
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // Converter de dd/mm/yyyy para yyyy-mm-dd
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    }
}

if (!function_exists('dateFromDb')) {
    /**
     * Converter de Y-m-d para formato PT
     */
    function dateFromDb($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->format('d/m/Y');
    }
}

if (!function_exists('dateDiff')) {
    /**
     * Data relativa (há X dias, etc)
     */
    function dateDiff($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->locale('pt')->diffForHumans();
    }
}
