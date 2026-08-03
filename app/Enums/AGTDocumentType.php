<?php

namespace App\Enums;

/**
 * Tipos de documento AGT v1.2 conforme DS.120 §4.1.
 *
 * Os 18 tipos oficiais aceites pelo endpoint /registarFactura.
 */
enum AGTDocumentType: string
{
    // ============== FACTURAS ==============
    case FA = 'FA'; // Factura
    case FT = 'FT'; // Factura
    case FR = 'FR'; // Factura-Recibo
    case FG = 'FG'; // Factura Global (consumo)
    case GF = 'GF'; // Guia de Frete (transporte)

    // ============== AUTO-FACTURAÇÃO / CONFERÊNCIA ==============
    case AC = 'AC'; // Autofactura (compras a não residentes/pequenos comerciantes)
    case AR = 'AR'; // Auto-Recibo
    case AF = 'AF'; // Auto-Factura específica
    case CS = 'CS'; // Conferência de Stock

    // ============== TALÕES / TRANSPORTE ==============
    case TV = 'TV'; // Talão de Venda
    case LD = 'LD'; // Liquidação Diária

    // ============== RECIBOS ==============
    case RC = 'RC'; // Recibo (associado a factura)
    case RG = 'RG'; // Recibo Genérico (sem factura associada)
    case RE = 'RE'; // Recibo Especial
    case RP = 'RP'; // Recibo de Pagamento
    case RA = 'RA'; // Recibo de Adiantamento

    // ============== NOTAS DE CORRECÇÃO ==============
    case ND = 'ND'; // Nota de Débito
    case NC = 'NC'; // Nota de Crédito

    /** Descrição humana do tipo. */
    public function label(): string
    {
        return match ($this) {
            self::FA => 'Factura',
            self::FT => 'Factura',
            self::FR => 'Factura-Recibo',
            self::FG => 'Factura Global',
            self::GF => 'Guia de Frete',
            self::AC => 'Autofactura',
            self::AR => 'Auto-Recibo',
            self::AF => 'Auto-Factura Específica',
            self::CS => 'Conferência de Stock',
            self::TV => 'Talão de Venda',
            self::LD => 'Liquidação Diária',
            self::RC => 'Recibo',
            self::RG => 'Recibo Genérico',
            self::RE => 'Recibo Especial',
            self::RP => 'Recibo de Pagamento',
            self::RA => 'Recibo de Adiantamento',
            self::ND => 'Nota de Débito',
            self::NC => 'Nota de Crédito',
        };
    }

    /** Indica se o tipo é um recibo (DS.120 §4.1 — paymentReceipt obrigatório em AR/RC/RG). */
    public function isReceipt(): bool
    {
        return in_array($this, [self::AR, self::RC, self::RG, self::RE, self::RP, self::RA], true);
    }

    /** Indica se o tipo requer linhas de artigos. */
    public function requiresLines(): bool
    {
        // DS.120 §4.1: lines obrigatórias excepto AR, RC, RG.
        return !in_array($this, [self::AR, self::RC, self::RG], true);
    }

    /** Categoria para agrupamento em UI. */
    public function category(): string
    {
        return match ($this) {
            self::FA, self::FT, self::FR, self::FG, self::GF => 'invoice',
            self::AC, self::AR, self::AF, self::CS           => 'self_billing',
            self::TV, self::LD                                => 'ticket',
            self::RC, self::RG, self::RE, self::RP, self::RA  => 'receipt',
            self::ND, self::NC                                => 'correction',
        };
    }

    /** Todos os códigos como array. */
    public static function codes(): array
    {
        return array_map(fn(self $t) => $t->value, self::cases());
    }

    /** Mapa código => label para UIs e selects. */
    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = "{$case->value} — {$case->label()}";
        }
        return $out;
    }

    /** Resolve de string com fallback seguro. */
    public static function tryFromCode(?string $code): ?self
    {
        if ($code === null) return null;
        return self::tryFrom(strtoupper(trim($code)));
    }
}
