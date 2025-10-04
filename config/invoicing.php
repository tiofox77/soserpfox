<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações de Faturação Angola
    |--------------------------------------------------------------------------
    |
    | Configurações específicas para faturação segundo as normas angolanas
    |
    */

    'country' => 'Angola',
    'currency' => 'KZ',
    'currency_symbol' => 'Kz',
    'currency_name' => 'Kwanza',
    
    // Taxa de IVA em Angola
    'tax_rate' => 14, // 14% IVA
    
    // Configurações de numeração de faturas
    'invoice_prefix' => 'FT',
    'invoice_series' => date('Y'),
    'invoice_start_number' => 1,
    
    // Informações da empresa (AGT - Administração Geral Tributária)
    'company' => [
        'name' => env('COMPANY_NAME', 'SOS ERP'),
        'nif' => env('COMPANY_NIF', ''),
        'address' => env('COMPANY_ADDRESS', 'Luanda, Angola'),
        'phone' => env('COMPANY_PHONE', ''),
        'email' => env('COMPANY_EMAIL', ''),
    ],
    
    // Métodos de pagamento aceites em Angola
    'payment_methods' => [
        'transferencia_bancaria' => 'Transferência Bancária',
        'multicaixa' => 'Multicaixa Express',
        'tpa' => 'TPA (Terminal de Pagamento)',
        'numerario' => 'Numerário (Dinheiro)',
        'cheque' => 'Cheque',
        'referencia_bancaria' => 'Referência Bancária',
    ],
    
    // Tipos de documentos segundo normas angolanas
    'document_types' => [
        'fatura' => 'Fatura',
        'fatura_recibo' => 'Fatura/Recibo',
        'recibo' => 'Recibo',
        'nota_credito' => 'Nota de Crédito',
        'nota_debito' => 'Nota de Débito',
        'fatura_proforma' => 'Fatura Pro-forma',
    ],
    
    // Bancos principais em Angola
    'banks' => [
        'BAI' => 'Banco Angolano de Investimentos',
        'BFA' => 'Banco de Fomento Angola',
        'BCI' => 'Banco de Comércio e Indústria',
        'BPC' => 'Banco de Poupança e Crédito',
        'Atlantico' => 'Banco Atlântico',
        'Millennium' => 'Millennium Atlantico',
        'Standard_Bank' => 'Standard Bank Angola',
        'BNI' => 'Banco de Negócios Internacional',
    ],
    
    // Regimes de IVA
    'tax_regimes' => [
        'geral' => 'Regime Geral',
        'simplificado' => 'Regime Simplificado',
        'isento' => 'Isento',
    ],
];
