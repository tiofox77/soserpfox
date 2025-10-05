<?php

namespace App\Helpers;

use App\Models\Invoicing\InvoicingSettings;
use Carbon\Carbon;

class DocumentConfigHelper
{
    /**
     * Obtém as configurações de faturação do tenant atual
     */
    private static function getSettings(): InvoicingSettings
    {
        return InvoicingSettings::forTenant(activeTenantId());
    }

    /**
     * Calcula data de validade da proforma baseada nas configurações
     * 
     * @param Carbon|null $fromDate Data inicial (padrão: hoje)
     * @return Carbon Data de validade
     */
    public static function getProformaValidUntil(?Carbon $fromDate = null): Carbon
    {
        $settings = self::getSettings();
        $days = $settings->proforma_validity_days ?? 30;
        $from = $fromDate ?? now();
        
        return $from->copy()->addDays($days);
    }

    /**
     * Calcula data de vencimento da fatura baseada nas configurações
     * 
     * @param Carbon|null $fromDate Data inicial (padrão: hoje)
     * @return Carbon Data de vencimento
     */
    public static function getInvoiceDueDate(?Carbon $fromDate = null): Carbon
    {
        $settings = self::getSettings();
        $days = $settings->invoice_due_days ?? 30;
        $from = $fromDate ?? now();
        
        return $from->copy()->addDays($days);
    }

    /**
     * Obtém número de dias de validade da proforma
     */
    public static function getProformaValidityDays(): int
    {
        $settings = self::getSettings();
        return $settings->proforma_validity_days ?? 30;
    }

    /**
     * Obtém número de dias para vencimento da fatura
     */
    public static function getInvoiceDueDays(): int
    {
        $settings = self::getSettings();
        return $settings->invoice_due_days ?? 30;
    }

    /**
     * Verifica se deve imprimir automaticamente após salvar
     */
    public static function shouldAutoPrint(): bool
    {
        $settings = self::getSettings();
        return $settings->auto_print_after_save ?? false;
    }

    /**
     * Verifica se deve mostrar logo da empresa nos documentos
     */
    public static function shouldShowLogo(): bool
    {
        $settings = self::getSettings();
        return $settings->show_company_logo ?? true;
    }

    /**
     * Obtém texto do rodapé configurado
     */
    public static function getFooterText(): ?string
    {
        $settings = self::getSettings();
        return $settings->invoice_footer_text;
    }

    /**
     * Obtém todas as configurações de dias
     */
    public static function getDaysSettings(): array
    {
        $settings = self::getSettings();
        
        return [
            'proforma_validity' => $settings->proforma_validity_days ?? 30,
            'invoice_due' => $settings->invoice_due_days ?? 30,
        ];
    }

    /**
     * Obtém todas as configurações de impressão
     */
    public static function getPrintSettings(): array
    {
        $settings = self::getSettings();
        
        return [
            'auto_print' => $settings->auto_print_after_save ?? false,
            'show_logo' => $settings->show_company_logo ?? true,
            'footer_text' => $settings->invoice_footer_text,
        ];
    }

    /**
     * Obtém todas as configurações para uso em PDFs
     */
    public static function getPDFSettings(): array
    {
        $settings = self::getSettings();
        
        return [
            'show_logo' => $settings->show_company_logo ?? true,
            'footer_text' => $settings->invoice_footer_text,
            'auto_print' => $settings->auto_print_after_save ?? false,
        ];
    }
}
