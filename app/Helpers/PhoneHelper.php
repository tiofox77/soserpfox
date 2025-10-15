<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normaliza número de telefone angolano
     * Aceita: 939729902, +244939729902, 244939729902
     * Retorna: +244939729902
     */
    public static function normalizeAngolanPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        
        // Remover espaços, parênteses, hífens
        $phone = preg_replace('/[\s\(\)\-]/', '', $phone);
        
        // Se já começa com +244, retornar
        if (str_starts_with($phone, '+244')) {
            return $phone;
        }
        
        // Se começa com 244 (sem +), adicionar +
        if (str_starts_with($phone, '244')) {
            return '+' . $phone;
        }
        
        // Se tem 9 dígitos (número local angolano)
        if (preg_match('/^[9][0-9]{8}$/', $phone)) {
            return '+244' . $phone;
        }
        
        // Se não corresponder a nenhum padrão, retornar como está
        return $phone;
    }
    
    /**
     * Valida se é um número angolano válido
     */
    public static function isValidAngolanPhone(?string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }
        
        $normalized = self::normalizeAngolanPhone($phone);
        
        // Número angolano deve ter formato +244XXXXXXXXX (9 dígitos após +244)
        return preg_match('/^\+244[9][0-9]{8}$/', $normalized) === 1;
    }
    
    /**
     * Formata número para exibição
     * +244939729902 → +244 939 729 902
     */
    public static function formatAngolanPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        
        $normalized = self::normalizeAngolanPhone($phone);
        
        if (preg_match('/^\+244([9][0-9]{8})$/', $normalized, $matches)) {
            $local = $matches[1];
            return '+244 ' . substr($local, 0, 3) . ' ' . substr($local, 3, 3) . ' ' . substr($local, 6, 3);
        }
        
        return $normalized;
    }
    
    /**
     * Extrai múltiplos números de uma string
     */
    public static function extractPhoneNumbers(string $text): array
    {
        $phones = [];
        
        // Buscar padrões: +244XXXXXXXXX ou XXXXXXXXX
        preg_match_all('/(?:\+244|244)?[9][0-9]{8}/', $text, $matches);
        
        foreach ($matches[0] as $phone) {
            $normalized = self::normalizeAngolanPhone($phone);
            if (self::isValidAngolanPhone($normalized)) {
                $phones[] = $normalized;
            }
        }
        
        return array_unique($phones);
    }
}
