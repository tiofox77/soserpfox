<?php

use App\Models\SoftwareSetting;

if (!function_exists('canDeleteDocument')) {
    /**
     * Verificar se um tipo de documento pode ser eliminado
     * 
     * @param string $documentType (sales_invoice, proforma, receipt, credit_note, invoice_receipt, pos_invoice)
     * @return bool
     */
    function canDeleteDocument(string $documentType): bool
    {
        return !SoftwareSetting::isDeleteBlocked($documentType);
    }
}

if (!function_exists('isDeleteBlocked')) {
    /**
     * Verificar se eliminação está bloqueada para um tipo de documento
     * 
     * @param string $documentType
     * @return bool
     */
    function isDeleteBlocked(string $documentType): bool
    {
        return SoftwareSetting::isDeleteBlocked($documentType);
    }
}

if (!function_exists('getBlockedDocuments')) {
    /**
     * Obter lista de documentos bloqueados
     * 
     * @return array
     */
    function getBlockedDocuments(): array
    {
        $documents = [
            'sales_invoice' => 'Faturas de Venda',
            'proforma' => 'Proformas',
            'receipt' => 'Recibos',
            'credit_note' => 'Notas de Crédito',
            'invoice_receipt' => 'Faturas Recibo',
            'pos_invoice' => 'Faturas POS',
        ];
        
        $blocked = [];
        
        foreach ($documents as $key => $name) {
            if (isDeleteBlocked($key)) {
                $blocked[$key] = $name;
            }
        }
        
        return $blocked;
    }
}

if (!function_exists('softwareSetting')) {
    /**
     * Obter valor de uma configuração de software
     * 
     * @param string $module
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function softwareSetting(string $module, string $key, $default = null)
    {
        return SoftwareSetting::get($module, $key, $default);
    }
}
