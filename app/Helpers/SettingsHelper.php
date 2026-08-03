<?php

if (!function_exists('setting')) {
    /**
     * Get system setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    function setting($key, $default = null)
    {
        return \App\Models\SystemSetting::get($key, $default);
    }
}

if (!function_exists('app_logo')) {
    /**
     * Get application logo URL
     * 
     * @return string|null
     */
    function app_logo()
    {
        $logo = \App\Models\SystemSetting::get('app_logo');
        return $logo ? \Storage::url($logo) : null;
    }
}

if (!function_exists('app_favicon')) {
    /**
     * Get application favicon URL
     * 
     * @return string|null
     */
    function app_favicon()
    {
        $favicon = \App\Models\SystemSetting::get('app_favicon');
        return $favicon ? \Storage::url($favicon) : null;
    }
}

if (!function_exists('app_logo_path')) {
    /**
     * Path absoluto local do logo da app — adequado para DomPDF.
     * Devolve null se não existir.
     */
    function app_logo_path()
    {
        $logo = \App\Models\SystemSetting::get('app_logo');
        if (!$logo) {
            return null;
        }
        $full = storage_path('app/public/' . ltrim($logo, '/'));
        return file_exists($full) ? $full : null;
    }
}

if (!function_exists('app_logo_data_uri')) {
    /**
     * Devolve o logo em data-uri base64 (PNG/JPG/SVG) — útil para HTML embed.
     */
    function app_logo_data_uri()
    {
        $path = app_logo_path();
        if (!$path) {
            return null;
        }
        $mime = mime_content_type($path) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}

if (!function_exists('posPaymentMethodLabel')) {
    /**
     * Tradução de métodos de pagamento POS para etiqueta legível.
     */
    function posPaymentMethodLabel(?string $method): string
    {
        $map = [
            'cash'        => 'DINHEIRO',
            'transfer'    => 'TRANSFERÊNCIA',
            'multicaixa'  => 'MULTICAIXA',
            'tpa'         => 'TPA / CARTÃO',
            'card'        => 'CARTÃO',
            'mbway'       => 'MB WAY',
            'cheque'      => 'CHEQUE',
            'mixed'       => 'MISTO',
            'other'       => 'OUTROS',
        ];
        $key = strtolower((string) $method);
        return $map[$key] ?? ($method ? strtoupper($method) : '—');
    }
}

if (!function_exists('app_name')) {
    /**
     * Get application name
     * 
     * @return string
     */
    function app_name()
    {
        return \App\Models\SystemSetting::get('app_name', config('app.name', 'SOS ERP'));
    }
}
