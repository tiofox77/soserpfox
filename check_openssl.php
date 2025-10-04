<?php
/**
 * Script para verificar configuração do OpenSSL
 */

echo "=== Verificação de OpenSSL ===\n\n";

// 1. Verificar se extensão está carregada
echo "1. Extensão OpenSSL carregada: " . (extension_loaded('openssl') ? 'SIM ✓' : 'NÃO ✗') . "\n";

if (extension_loaded('openssl')) {
    echo "   Versão: " . OPENSSL_VERSION_TEXT . "\n";
}

// 2. Verificar arquivo de configuração
echo "\n2. Procurando arquivo openssl.cnf...\n";

$possiblePaths = [
    'C:\laragon\bin\php\php-8.2.23-nts-Win32-vs16-x64\extras\ssl\openssl.cnf',
    'C:\laragon\bin\php\php-8.2\extras\ssl\openssl.cnf',
    'C:\laragon\bin\apache\apache-2.4.62-win64-VS17\conf\openssl.cnf',
    getenv('OPENSSL_CONF'),
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl.cnf',
];

$foundConfig = false;
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        echo "   ✓ Encontrado: $path\n";
        $foundConfig = true;
    }
}

if (!$foundConfig) {
    echo "   ✗ Nenhum arquivo openssl.cnf encontrado\n";
}

// 3. Tentar criar chave de teste
echo "\n3. Testando geração de chave...\n";

// Limpar erros anteriores
while (openssl_error_string() !== false);

// Criar config temporário
$tempConfig = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl_test.cnf';
$configContent = <<<EOD
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
EOD;
file_put_contents($tempConfig, $configContent);

$config = [
    "digest_alg" => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
    "config" => $tempConfig,
];

$res = openssl_pkey_new($config);

if ($res) {
    echo "   ✓ Geração de chave bem-sucedida!\n";
    
    // Exportar para testar
    $export = openssl_pkey_export($res, $privateKey, null, $config);
    
    if ($export) {
        echo "   ✓ Exportação de chave privada bem-sucedida!\n";
        echo "   Tamanho da chave privada: " . strlen($privateKey) . " bytes\n";
        
        // Obter chave pública
        $details = openssl_pkey_get_details($res);
        if ($details) {
            echo "   ✓ Extração de chave pública bem-sucedida!\n";
            echo "   Tamanho da chave pública: " . strlen($details['key']) . " bytes\n";
        } else {
            echo "   ✗ Erro ao extrair chave pública\n";
        }
    } else {
        echo "   ✗ Erro ao exportar chave privada\n";
    }
    
    openssl_free_key($res);
} else {
    echo "   ✗ Erro ao gerar chave\n";
    
    // Mostrar erros
    $errors = [];
    while ($error = openssl_error_string()) {
        $errors[] = $error;
    }
    
    if (!empty($errors)) {
        echo "   Erros:\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
    }
}

// 4. Informações do PHP
echo "\n4. Informações do PHP:\n";
echo "   Versão: " . PHP_VERSION . "\n";
echo "   Sistema: " . PHP_OS . "\n";
echo "   Temp Dir: " . sys_get_temp_dir() . "\n";

// 5. Variáveis de ambiente relacionadas
echo "\n5. Variáveis de ambiente:\n";
$envVars = ['OPENSSL_CONF', 'OPENSSL_ENGINES', 'PATH'];
foreach ($envVars as $var) {
    $value = getenv($var);
    echo "   $var: " . ($value ?: 'não definida') . "\n";
}

echo "\n=== Fim da Verificação ===\n";
