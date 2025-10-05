<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;

class AGTHelper
{
    /**
     * Gera mensagem AGT para rodapé do documento usando hash existente
     * 
     * @param Model $document Documento (Invoice, Proforma, etc)
     * @return string
     */
    public static function getFooterMessage($document): string
    {
        if (empty($document->hash)) {
            return '';
        }
        
        $hashDisplay = substr($document->hash, 0, 4);
        $year = now()->year;
        
        return "{$hashDisplay} - Processado por programa válido n31.1/AGT{$year}";
    }
    
    /**
     * Validar conformidade AGT usando campos existentes
     * 
     * @param Model $document
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public static function validateAGT($document): array
    {
        $errors = [];
        $warnings = [];
        
        // Validar Hash
        if (empty($document->hash)) {
            $errors[] = "Hash SAFT ausente - executar generateHash()";
        } elseif (strlen($document->hash) < 64) {
            $warnings[] = "Hash parece incompleto (< 64 caracteres)";
        }
        
        // Validar Hash Control
        if (empty($document->hash_control)) {
            $errors[] = "HashControl ausente";
        } elseif ($document->hash_control !== '1') {
            $warnings[] = "HashControl diferente de '1'";
        }
        
        // Validar Tipo de Documento
        if (empty($document->invoice_type)) {
            $errors[] = "Tipo de documento não definido";
        } elseif (!in_array($document->invoice_type, ['FT', 'FR', 'FS', 'NC', 'ND', 'GR', 'PR'])) {
            $warnings[] = "Tipo de documento desconhecido: {$document->invoice_type}";
        }
        
        // Validar Status
        if (empty($document->invoice_status)) {
            $errors[] = "Status do documento ausente";
        } elseif (!in_array($document->invoice_status, ['N', 'A', 'F'])) {
            $warnings[] = "Status inválido: {$document->invoice_status}";
        }
        
        // Validar System Entry Date
        if (empty($document->system_entry_date)) {
            $errors[] = "Data de entrada no sistema ausente";
        }
        
        // Validar Totais
        if (isset($document->gross_total, $document->net_total, $document->tax_payable)) {
            $calculated = round($document->net_total + $document->tax_payable - ($document->irt_amount ?? 0), 2);
            if (abs($document->gross_total - $calculated) > 0.02) {
                $errors[] = "Totais inconsistentes: GrossTotal={$document->gross_total} vs Calculado={$calculated}";
            }
        }
        
        // Validar Source ID
        if (empty($document->source_id)) {
            $warnings[] = "SourceID não definido";
        }
        
        // Validar ATCUD
        if (empty($document->atcud)) {
            $warnings[] = "ATCUD não definido";
        }
        
        // Validar Data
        if (empty($document->invoice_date)) {
            $errors[] = "Data do documento ausente";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'compliant' => empty($errors) && empty($warnings),
        ];
    }
    
    /**
     * Marcar documento como anulado conforme AGT
     * 
     * @param Model $document
     * @param string|null $reason
     * @return bool
     */
    public static function cancelDocument($document, string $reason = null): bool
    {
        return $document->update([
            'invoice_status' => 'A',
            'invoice_status_date' => now(),
            'notes' => ($document->notes ? $document->notes . "\n\n" : '') . 
                       "ANULADO EM " . now()->format('d/m/Y H:i') . ": " . 
                       ($reason ?? 'Sem motivo especificado'),
        ]);
    }
    
    /**
     * Período contabilístico (YYYY-MM) para SAFT
     * 
     * @param Model $document
     * @return string
     */
    public static function getPeriod($document): string
    {
        return $document->invoice_date->format('Y-m');
    }
    
    /**
     * Categorias de teste AGT conforme Decreto 312/18
     * 
     * @return array
     */
    public static function getTestCategories(): array
    {
        return [
            '1' => 'Fatura com NIF do cliente',
            '2' => 'Fatura anulada',
            '3' => 'Proforma',
            '4' => 'Fatura baseada em proforma',
            '5' => 'Nota de crédito',
            '6' => 'Fatura com IVA e isento',
            '7' => 'Fatura com descontos',
            '8' => 'Documento em moeda estrangeira',
            '9' => 'Fatura sem NIF (< 50 AOA, antes 10h)',
            '10' => 'Fatura sem NIF (normal)',
            '11' => 'Guia de remessa',
            '12' => 'Orçamento/Proforma',
            '13' => 'Fatura genérica/Auto-facturação',
            '14' => 'Fatura global',
            '15' => 'Outros documentos',
        ];
    }
    
    /**
     * Códigos de isenção IVA Angola (CIVA)
     * 
     * @return array
     */
    public static function getExemptionCodes(): array
    {
        return [
            'M00' => 'Regime Transitório',
            'M02' => 'Transmissão de bens e serviço não sujeita',
            'M04' => 'Iva - Regime de não Sujeição',
            'M11' => 'Isento Artigo 12.º b) do CIVA',
            'M12' => 'Isento Artigo 12.º c) do CIVA',
            'M13' => 'Isento Artigo 12.º d) do CIVA',
            'M14' => 'Isento Artigo 12.º e) do CIVA',
            'M15' => 'Isento Artigo 12.º f) do CIVA',
            'M17' => 'Isento Artigo 12.º h) do CIVA',
            'M18' => 'Isento Artigo 12.º i) do CIVA',
            'M19' => 'Isento Artigo 12.º j) do CIVA',
            'M20' => 'Isento Artigo 12.º k) do CIVA',
            'M30' => 'Isento Artigo 15.º 1 a) do CIVA',
            'M31' => 'Isento Artigo 15.º 1 b) do CIVA',
            'M32' => 'Isento Artigo 15.º 1 c) do CIVA',
            'M33' => 'Isento Artigo 15.º 1 d) do CIVA',
            'M34' => 'Isento Artigo 15.º 1 e) do CIVA',
            'M35' => 'Isento Artigo 15.º 1 f) do CIVA',
            'M36' => 'Isento Artigo 15.º 1 g) do CIVA',
            'M37' => 'Isento Artigo 15.º 1 h) do CIVA',
            'M38' => 'Isento Artigo 15.º 1 i) do CIVA',
        ];
    }
    
    /**
     * Tipos de documento AGT
     * 
     * @return array
     */
    public static function getDocumentTypes(): array
    {
        return [
            'FT' => 'Fatura',
            'FR' => 'Fatura-Recibo',
            'FS' => 'Fatura Simplificada',
            'NC' => 'Nota de Crédito',
            'ND' => 'Nota de Débito',
            'GR' => 'Guia de Remessa',
            'PR' => 'Proforma/Orçamento',
        ];
    }
    
    /**
     * Verificar se documento está conforme para submissão AGT
     * 
     * @param Model $document
     * @return bool
     */
    public static function isReadyForAGT($document): bool
    {
        $validation = self::validateAGT($document);
        return $validation['valid'] && empty($validation['warnings']);
    }
    
    /**
     * Gerar relatório de conformidade AGT
     * 
     * @param Model $document
     * @return array
     */
    public static function getConformityReport($document): array
    {
        $validation = self::validateAGT($document);
        
        return [
            'document_number' => $document->invoice_number ?? 'N/A',
            'document_type' => $document->invoice_type ?? 'N/A',
            'hash_display' => substr($document->hash ?? '', 0, 4),
            'period' => self::getPeriod($document),
            'status' => $document->invoice_status ?? 'N/A',
            'system_entry_date' => $document->system_entry_date?->format('Y-m-d H:i:s') ?? 'N/A',
            'gross_total' => $document->gross_total ?? 0,
            'is_valid' => $validation['valid'],
            'is_compliant' => $validation['compliant'],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'footer_message' => self::getFooterMessage($document),
        ];
    }
}
