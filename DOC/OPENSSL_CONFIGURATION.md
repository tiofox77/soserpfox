# Configuração OpenSSL para SAFT-AO

## 🐛 Problema Comum

Erro ao gerar chaves SAFT: `error:80000003:system library::No such process`

## 🔍 Causa

Este erro ocorre quando:
- OpenSSL não encontra o arquivo de configuração `openssl.cnf`
- Variável de ambiente `OPENSSL_CONF` não está definida
- Arquivo de configuração está corrompido

## ✅ Solução Implementada

O sistema agora cria automaticamente um arquivo de configuração OpenSSL temporário quando necessário.

## 🔧 Verificar Instalação

Execute no terminal:

```bash
cd c:\laragon2\www\soserp
php check_openssl.php
```

Você deve ver:
```
✓ Extensão OpenSSL carregada: SIM
✓ Geração de chave bem-sucedida!
✓ Exportação de chave privada bem-sucedida!
✓ Extração de chave pública bem-sucedida!
```

## 📁 Localização do openssl.cnf

### Laragon (Windows):

Possíveis localizações:
```
C:\laragon\bin\php\php-8.x.x\extras\ssl\openssl.cnf
C:\laragon\bin\apache\apache-x.x.x\conf\openssl.cnf
```

### XAMPP (Windows):
```
C:\xampp\apache\conf\openssl.cnf
C:\xampp\php\extras\ssl\openssl.cnf
```

### Linux:
```
/etc/ssl/openssl.cnf
/usr/lib/ssl/openssl.cnf
```

## 🔐 Como o Sistema Funciona Agora

### 1. Criação Automática de Configuração:

```php
// O sistema cria arquivo temporário automaticamente
$configFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl.cnf';

$configContent = <<<EOD
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
EOD;

file_put_contents($configFile, $configContent);
```

### 2. Geração de Chaves com Configuração:

```php
$config = [
    "digest_alg" => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
    "config" => $configFile,  // ← Arquivo de configuração
];

$res = openssl_pkey_new($config);
```

## 🚀 Gerar Chaves Manualmente via Script

Se preferir gerar chaves via linha de comando:

### Windows (PowerShell):

```powershell
# Navegar para pasta
cd C:\laragon2\www\soserp

# Gerar chave privada
openssl genrsa -out storage\app\saft\private_key.pem 2048

# Gerar chave pública
openssl rsa -in storage\app\saft\private_key.pem -pubout -out storage\app\saft\public_key.pem
```

### Linux/Mac:

```bash
# Navegar para pasta
cd /var/www/soserp

# Gerar chave privada
openssl genrsa -out storage/app/saft/private_key.pem 2048

# Gerar chave pública
openssl rsa -in storage/app/saft/private_key.pem -pubout -out storage/app/saft/public_key.pem
```

## 🔍 Verificar Chaves Geradas

```bash
# Ver chave privada
cat storage/app/saft/private_key.pem

# Ver chave pública
cat storage/app/saft/public_key.pem

# Verificar validade da chave privada
openssl rsa -in storage/app/saft/private_key.pem -check

# Ver detalhes da chave pública
openssl rsa -in storage/app/saft/public_key.pem -pubin -text -noout
```

## ⚙️ Configurar OPENSSL_CONF (Opcional)

### Windows:

1. Abra Painel de Controle > Sistema > Configurações Avançadas
2. Clique em "Variáveis de Ambiente"
3. Em "Variáveis do Sistema", clique em "Novo"
4. Nome: `OPENSSL_CONF`
5. Valor: `C:\laragon\bin\php\php-8.x.x\extras\ssl\openssl.cnf`

### Linux/Mac (.bashrc ou .zshrc):

```bash
export OPENSSL_CONF=/etc/ssl/openssl.cnf
```

## 🐛 Troubleshooting

### Erro: "error:80000003:system library::No such process"

**Solução:** Já corrigido no código. O sistema cria arquivo de configuração automaticamente.

### Erro: "Extension openssl not loaded"

**Solução:** Habilite OpenSSL no `php.ini`:
```ini
extension=openssl
```

Reinicie o servidor Apache/Nginx.

### Erro: "Permission denied"

**Solução:** Dê permissões à pasta:

**Windows:**
```powershell
icacls "storage\app\saft" /grant Users:F /t
```

**Linux:**
```bash
chmod -R 755 storage/app/saft
chown -R www-data:www-data storage/app/saft
```

### Chaves não aparecem na interface

**Solução:**
1. Verifique se arquivos existem em `storage/app/saft/`
2. Limpe cache: `php artisan optimize:clear`
3. Recarregue a página

## 📊 Formato das Chaves

### Chave Privada (private_key.pem):

```
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC...
...muitas linhas...
-----END PRIVATE KEY-----
```

### Chave Pública (public_key.pem):

```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtx...
...algumas linhas...
-----END PUBLIC KEY-----
```

## ✅ Checklist de Validação

- [ ] Extensão OpenSSL habilitada (`extension=openssl` no php.ini)
- [ ] Arquivo `openssl.cnf` acessível
- [ ] Permissões corretas na pasta `storage/app/saft/`
- [ ] Script `check_openssl.php` executa sem erros
- [ ] Chaves geradas com sucesso na interface
- [ ] Arquivos `private_key.pem` e `public_key.pem` existem
- [ ] Hash está sendo gerado nas proformas

## 📚 Referências

- [PHP OpenSSL](https://www.php.net/manual/en/book.openssl.php)
- [OpenSSL Documentation](https://www.openssl.org/docs/)
- [SAFT-AO Angola](https://www.agt.minfin.gov.ao/)
