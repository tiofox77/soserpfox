<?php

namespace App\Helpers;

use App\Models\Invoicing\InvoicingSettings;

class DiscountHelper
{
    /**
     * Verifica se o desconto por linha está permitido
     */
    public static function isLineDiscountAllowed(): bool
    {
        $settings = InvoicingSettings::forTenant(activeTenantId());
        return $settings->allow_line_discounts ?? true;
    }

    /**
     * Verifica se o desconto comercial está permitido
     */
    public static function isCommercialDiscountAllowed(): bool
    {
        $settings = InvoicingSettings::forTenant(activeTenantId());
        return $settings->allow_commercial_discount ?? true;
    }

    /**
     * Verifica se o desconto financeiro está permitido
     */
    public static function isFinancialDiscountAllowed(): bool
    {
        $settings = InvoicingSettings::forTenant(activeTenantId());
        return $settings->allow_financial_discount ?? true;
    }

    /**
     * Obtém o desconto máximo permitido
     */
    public static function getMaxDiscountPercent(): float
    {
        $settings = InvoicingSettings::forTenant(activeTenantId());
        return $settings->max_discount_percent ?? 100.00;
    }

    /**
     * Valida se um desconto está dentro do limite permitido
     * 
     * @param float $discountPercent Percentual de desconto a validar
     * @return bool
     */
    public static function isDiscountValid(float $discountPercent): bool
    {
        $maxDiscount = self::getMaxDiscountPercent();
        return $discountPercent <= $maxDiscount;
    }

    /**
     * Valida desconto e retorna mensagem de erro se inválido
     * 
     * @param float $discountPercent
     * @param string $type Tipo: 'line', 'commercial', 'financial'
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateDiscount(float $discountPercent, string $type = 'line'): array
    {
        // Verificar se o tipo de desconto está permitido
        switch ($type) {
            case 'line':
                if (!self::isLineDiscountAllowed()) {
                    return [
                        'valid' => false,
                        'message' => 'Desconto por linha não está permitido nas configurações'
                    ];
                }
                break;
            case 'commercial':
                if (!self::isCommercialDiscountAllowed()) {
                    return [
                        'valid' => false,
                        'message' => 'Desconto comercial não está permitido nas configurações'
                    ];
                }
                break;
            case 'financial':
                if (!self::isFinancialDiscountAllowed()) {
                    return [
                        'valid' => false,
                        'message' => 'Desconto financeiro não está permitido nas configurações'
                    ];
                }
                break;
        }

        // Verificar se está dentro do limite máximo
        $maxDiscount = self::getMaxDiscountPercent();
        if ($discountPercent > $maxDiscount) {
            return [
                'valid' => false,
                'message' => "Desconto de {$discountPercent}% excede o máximo permitido de {$maxDiscount}%"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Desconto válido'
        ];
    }

    /**
     * Formata mensagem de erro para exibição
     */
    public static function getDiscountErrorMessage(float $discountPercent, string $type = 'line'): ?string
    {
        $validation = self::validateDiscount($discountPercent, $type);
        return $validation['valid'] ? null : $validation['message'];
    }
}
