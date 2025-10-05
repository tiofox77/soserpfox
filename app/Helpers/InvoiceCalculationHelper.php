<?php

namespace App\Helpers;

/**
 * Helper para Cálculos de Faturação conforme AGT Angola
 * Decreto Presidencial 312/18
 * 
 * Este helper centraliza todos os cálculos de documentos de faturação
 * (Proformas e Faturas de Venda/Compra) para garantir consistência.
 */
class InvoiceCalculationHelper
{
    /**
     * Calcula totais de um documento conforme modelo AGT Angola
     * 
     * @param \Illuminate\Support\Collection $cartItems Items do carrinho
     * @param float $discountCommercial Desconto comercial global
     * @param float $discountAmount Desconto adicional
     * @param float $discountFinancial Desconto financeiro
     * @param bool $isService Se é prestação de serviço (para IRT)
     * @return array Array com todos os valores calculados
     */
    public static function calculateTotals($cartItems, $discountCommercial = 0, $discountAmount = 0, $discountFinancial = 0, $isService = false)
    {
        // PASSO 1: TOTAL BRUTO (Valor Ilíquido - Σ Quantidade × Preço)
        $totalBruto = 0;
        $descontoComercialLinhas = 0;
        
        foreach ($cartItems as $item) {
            $valorBrutoLinha = $item->price * $item->quantity;
            $totalBruto += $valorBrutoLinha;
            
            // Desconto comercial da linha (aplicado PRIMEIRO)
            $attributes = is_array($item->attributes) ? $item->attributes : (array)$item->attributes;
            $descontoPercent = $attributes['discount_percent'] ?? 0;
            $descontoLinha = $valorBrutoLinha * ($descontoPercent / 100);
            $descontoComercialLinhas += $descontoLinha;
        }
        
        // PASSO 2: VALOR LÍQUIDO (após desconto comercial por linha)
        $valorLiquido = $totalBruto - $descontoComercialLinhas;
        
        // PASSO 3: DESCONTO COMERCIAL ADICIONAL (global)
        $descontoComercialTotal = $descontoComercialLinhas + $discountCommercial + $discountAmount;
        $valorAposDescComercial = $totalBruto - $descontoComercialTotal;
        
        // PASSO 4: DESCONTO FINANCEIRO (aplicado APÓS desconto comercial)
        // Desconto financeiro já é passado como parâmetro
        
        // PASSO 5: INCIDÊNCIA IVA (Base de IVA)
        $incidenciaIva = $valorAposDescComercial - $discountFinancial;
        
        // PASSO 6: CÁLCULO DO IVA (sobre Incidência IVA, distribuído proporcionalmente)
        $taxAmount = 0;
        $irtAmount = 0;
        
        foreach ($cartItems as $item) {
            // Valor bruto da linha
            $valorBrutoLinha = $item->price * $item->quantity;
            
            // Desconto comercial da linha
            $attributes = is_array($item->attributes) ? $item->attributes : (array)$item->attributes;
            $descontoPercent = $attributes['discount_percent'] ?? 0;
            $descontoLinha = $valorBrutoLinha * ($descontoPercent / 100);
            $valorLiquidoLinha = $valorBrutoLinha - $descontoLinha;
            
            // Proporção da linha no valor líquido
            $proporcao = $valorLiquido > 0 ? $valorLiquidoLinha / $valorLiquido : 0;
            
            // Desconto comercial adicional proporcional
            $descComercialAdicionalLinha = ($discountCommercial + $discountAmount) * $proporcao;
            
            // Desconto financeiro proporcional
            $descFinanceiroLinha = $discountFinancial * $proporcao;
            
            // Base IVA da linha (incidência)
            $baseIvaLinha = $valorLiquidoLinha - $descComercialAdicionalLinha - $descFinanceiroLinha;
            
            // IVA da linha
            $taxRate = $attributes['tax_rate'] ?? 14;
            $taxAmountLinha = $baseIvaLinha * ($taxRate / 100);
            $taxAmount += $taxAmountLinha;
        }
        
        // PASSO 7: RETENÇÃO IRT 6.5% (apenas para serviços)
        if ($isService) {
            $irtAmount = $incidenciaIva * 0.065;
        }
        
        // PASSO 8: TOTAL A PAGAR
        $total = $incidenciaIva + $taxAmount - $irtAmount;
        
        return [
            'subtotal_original' => round($totalBruto, 2),              // Total Bruto
            'total_discount_items' => round($descontoComercialLinhas, 2), // Desc. por linha
            'subtotal' => round($valorLiquido, 2),                     // Valor Líquido
            'desconto_comercial_total' => round($descontoComercialTotal, 2), // Total Desc. Comercial
            'incidencia_iva' => round($incidenciaIva, 2),             // Base IVA (INCIDÊNCIA)
            'tax_amount' => round($taxAmount, 2),                      // IVA
            'irt_amount' => round($irtAmount, 2),                      // Retenção IRT
            'total' => round($total, 2),                               // Total a Pagar
        ];
    }
    
    /**
     * Calcula valores de um item individual
     * 
     * @param float $price Preço unitário
     * @param int $quantity Quantidade
     * @param float $discountPercent Desconto percentual
     * @param float $taxRate Taxa de IVA
     * @param float $proportionalCommercialDiscount Desconto comercial proporcional
     * @param float $proportionalFinancialDiscount Desconto financeiro proporcional
     * @return array Array com valores do item
     */
    public static function calculateItemTotals($price, $quantity, $discountPercent = 0, $taxRate = 14, $proportionalCommercialDiscount = 0, $proportionalFinancialDiscount = 0)
    {
        // Valor bruto da linha
        $valorBrutoLinha = $price * $quantity;
        
        // Desconto comercial da linha
        $descontoLinha = $valorBrutoLinha * ($discountPercent / 100);
        $valorLiquidoLinha = $valorBrutoLinha - $descontoLinha;
        
        // Subtrair descontos proporcionais
        $baseIva = $valorLiquidoLinha - $proportionalCommercialDiscount - $proportionalFinancialDiscount;
        
        // IVA
        $taxAmount = $baseIva * ($taxRate / 100);
        
        // Total da linha
        $total = $baseIva + $taxAmount;
        
        return [
            'unit_price' => round($price, 2),
            'quantity' => $quantity,
            'discount_percent' => round($discountPercent, 2),
            'discount_amount' => round($descontoLinha, 2),
            'subtotal' => round($valorLiquidoLinha, 2),
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }
    
    /**
     * Calcula descontos proporcionais para distribuição entre itens
     * 
     * @param \Illuminate\Support\Collection $cartItems Items do carrinho
     * @param float $globalCommercialDiscount Desconto comercial global
     * @param float $globalFinancialDiscount Desconto financeiro global
     * @return array Array com proporções por item
     */
    public static function calculateProportionalDiscounts($cartItems, $globalCommercialDiscount = 0, $globalFinancialDiscount = 0)
    {
        // Calcular valor líquido total (após descontos por linha)
        $valorLiquidoTotal = 0;
        $itemValues = [];
        
        foreach ($cartItems as $item) {
            $valorBruto = $item->price * $item->quantity;
            $attributes = is_array($item->attributes) ? $item->attributes : (array)$item->attributes;
            $descontoPercent = $attributes['discount_percent'] ?? 0;
            $desconto = $valorBruto * ($descontoPercent / 100);
            $valorLiquido = $valorBruto - $desconto;
            
            $valorLiquidoTotal += $valorLiquido;
            $itemValues[$item->id] = $valorLiquido;
        }
        
        // Calcular proporção para cada item
        $proportions = [];
        foreach ($itemValues as $itemId => $valorLiquido) {
            $proporcao = $valorLiquidoTotal > 0 ? $valorLiquido / $valorLiquidoTotal : 0;
            
            $proportions[$itemId] = [
                'proportion' => $proporcao,
                'commercial_discount' => $globalCommercialDiscount * $proporcao,
                'financial_discount' => $globalFinancialDiscount * $proporcao,
            ];
        }
        
        return $proportions;
    }
    
    /**
     * Calcula apenas o IVA de um valor
     * 
     * @param float $amount Valor base
     * @param float $taxRate Taxa de IVA (padrão 14%)
     * @return float Valor do IVA
     */
    public static function calculateTax($amount, $taxRate = 14)
    {
        return round($amount * ($taxRate / 100), 2);
    }
    
    /**
     * Calcula IRT (Retenção) para serviços
     * 
     * @param float $amount Valor base
     * @param bool $isService Se é serviço
     * @return float Valor da retenção (6.5% para serviços)
     */
    public static function calculateIRT($amount, $isService = false)
    {
        if (!$isService) {
            return 0;
        }
        
        return round($amount * 0.065, 2); // 6.5% para serviços
    }
    
    /**
     * Formata valor monetário para exibição
     * 
     * @param float $value Valor
     * @param string $currency Moeda (padrão AOA)
     * @return string Valor formatado
     */
    public static function formatCurrency($value, $currency = 'AOA')
    {
        return number_format($value, 2, ',', '.') . ' ' . $currency;
    }
    
    /**
     * Valida se os cálculos estão corretos (para testes)
     * 
     * @param array $calculated Valores calculados
     * @return bool True se válido
     */
    public static function validateCalculations($calculated)
    {
        // Total deve ser igual a: Incidência + IVA - IRT
        $expectedTotal = $calculated['incidencia_iva'] + $calculated['tax_amount'] - $calculated['irt_amount'];
        $difference = abs($expectedTotal - $calculated['total']);
        
        // Aceitar diferença de até 0.01 por arredondamentos
        return $difference < 0.02;
    }
}
