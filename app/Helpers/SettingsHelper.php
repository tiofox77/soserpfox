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
        return $logo ? \Storage::url($logo) : asset('brand/soserp-logo.png');
    }
}

if (!function_exists('app_icone')) {
    /**
     * O ícone da marca — a raposa, sem o lettering.
     *
     * Um ícone não é um logótipo em pequeno. O logótipo da SOSERP é largo
     * (822x412: emblema à esquerda, "SOS ERP" à direita) e num quadrado de
     * 16px não sobra nada dele — nem se lê, nem se reconhece. O ícone é só o
     * emblema, e é este que vai para o separador do browser, para o ecrã do
     * telemóvel e para os atalhos.
     */
    function app_icone(int $tamanho = 192): string
    {
        return asset($tamanho <= 256 ? 'brand/soserp-icone-192.png' : 'brand/soserp-icone-512.png');
    }
}

if (!function_exists('app_favicon')) {
    /**
     * O ícone para o separador do browser.
     *
     * Um favicon configurado à mão só vale se for aproximadamente quadrado.
     * Estava aqui a origem do favicon esmagado: nas Definições do Sistema o
     * campo do favicon e o do logótipo são dois, mas foi carregado o mesmo
     * ficheiro nos dois — o banner largo. O browser recebia uma imagem 2:1
     * para meter num quadrado, e o que aparecia no separador era uma mancha.
     *
     * Uma imagem que não é quadrada não é um ícone, seja qual for o campo
     * onde foi parar. Nesse caso ignora-se e usa-se o ícone da marca.
     */
    function app_favicon()
    {
        $favicon = \App\Models\SystemSetting::get('app_favicon');

        if (!$favicon) {
            return asset('favicon.ico');
        }

        $ficheiro = storage_path('app/public/' . ltrim($favicon, '/'));
        $medidas  = is_file($ficheiro) ? @getimagesize($ficheiro) : false;

        if ($medidas && $medidas[1] > 0) {
            $proporcao = $medidas[0] / $medidas[1];

            // Fora de 4:5–5:4 já não é um ícone, é um logótipo.
            if ($proporcao < 0.8 || $proporcao > 1.25) {
                return asset('favicon.ico');
            }
        }

        return \Storage::url($favicon);
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

if (!function_exists('moeda_config')) {
    /**
     * Configuração da máscara de dinheiro do tenant activo:
     * ['on'=>bool, 'milhar'=>'.', 'decimal'=>',', 'casas'=>2].
     */
    function moeda_config(): array
    {
        return \App\Helpers\MoneyHelper::config();
    }
}

if (!function_exists('moeda_fmt')) {
    /**
     * Formata um número segundo a máscara do tenant, ex.: 10000.23 → "10.000,23".
     */
    function moeda_fmt($valor): string
    {
        return \App\Helpers\MoneyHelper::format($valor);
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
