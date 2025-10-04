# Estrutura de Hash SAFT-AO

## 📊 Estrutura da Tabela

### Tabela: `invoicing_sales_proformas`

**Campo de Hash:**
- ✅ **saft_hash** - TEXT (suporta até 65,535 caracteres)
- ❌ ~~hash~~ - REMOVIDO (duplicado, desnecessário)

## 🔐 Tipo de Dados

### Por que TEXT e não VARCHAR?

**Hash RSA-2048 em Base64:**
- Tamanho: ~344 caracteres
- VARCHAR(172): ❌ Muito pequeno (causa erro "Data too long")
- VARCHAR(512): ⚠️ Funciona, mas é limitado
- **TEXT**: ✅ Ideal (suporta até 65KB)

### Exemplo de Hash Real:

```
qBTAITm0g0b60gKyeHo0xYvM5MmXQ9KUTA+nleqhIAqHYB83eohegIH1RanZegSqEzEhq7TuK9cXEfwdJwW2OLKoy2WOMdescuEH2fWNDVg61cDuRJaY3eBvOgZ+WrW69pOKceaEXqzy9tQ5Nnx4lV157Hu4wVW0pDUex0ME6ujNLV5kg/GiHxYzaepzLEGR11JJJcd9mcJ6M54fhKVt5RGqMkCz9TLPMLx/Lcc0N/dlXCspihi0vCwNFjQ3taTctr9GUCT4h6PXZp9628eltVFLzzl07HswsCAH8hMSlG9LrfJZr6kqt7lLPDg7mjwyoLvxGpn2G1Uz0UyEah8oPw==
```

**Comprimento:** 344 caracteres

## 🔧 Migration Correta

### Estrutura Final:

```php
Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
    // Apenas um campo necessário
    $table->text('saft_hash')
          ->nullable()
          ->after('notes')
          ->comment('Hash SAFT-AO assinado (RSA-2048 Base64)');
});
```

### ❌ Estrutura Antiga (ERRADA):

```php
// PROBLEMA: Dois campos + tamanho insuficiente
$table->string('hash', 172)->nullable();           // ❌ Duplicado
$table->string('saft_hash', 172)->nullable();      // ❌ Muito pequeno
```

## 📋 Comparação de Tamanhos

| Tipo de Hash | Algoritmo | Tamanho Base64 | Tipo de Coluna Necessário |
|--------------|-----------|----------------|---------------------------|
| SHA-1 | Hash simples | 28 caracteres | VARCHAR(50) |
| SHA-256 | Hash simples | 44 caracteres | VARCHAR(100) |
| RSA-1024 | Assinado | ~172 caracteres | VARCHAR(200) |
| **RSA-2048** | **Assinado** | **~344 caracteres** | **TEXT** ✅ |
| RSA-4096 | Assinado | ~688 caracteres | TEXT |

## 🔍 Verificar Estrutura Atual

### SQL:

```sql
DESCRIBE invoicing_sales_proformas;

-- Ou ver apenas saft_hash
SHOW COLUMNS FROM invoicing_sales_proformas LIKE 'saft_hash';
```

### Resultado Esperado:

```
Field       | Type | Null | Key | Default | Extra
------------|------|------|-----|---------|-------
saft_hash   | text | YES  |     | NULL    |
```

## 📝 Modelo Laravel

### Fillable:

```php
protected $fillable = [
    // ... outros campos
    'saft_hash',  // ✅ Apenas este
    // 'hash',    // ❌ Removido
];
```

## 🚨 Erros Comuns

### 1. "Data too long for column 'saft_hash'"

**Causa:** Coluna VARCHAR muito pequena

**Solução:** Mudar para TEXT
```bash
php artisan migrate
```

### 2. "Unknown column 'hash'"

**Causa:** Modelo ainda referencia campo removido

**Solução:** Remover 'hash' do $fillable

### 3. "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'hash'"

**Causa:** Código ainda tenta usar campo 'hash'

**Solução:** Usar apenas 'saft_hash'

## ✅ Checklist de Validação

- [ ] Coluna `saft_hash` é do tipo TEXT
- [ ] Coluna `hash` foi removida (duplicada)
- [ ] Modelo só tem `saft_hash` no fillable
- [ ] Hash está sendo gerado corretamente
- [ ] Hash é salvo sem erros
- [ ] Preview exibe últimos 4 caracteres

## 🧪 Testar

### 1. Criar Proforma:

```
http://soserp.test/invoicing/sales/proformas/create
```

### 2. Verificar Hash no Banco:

```sql
SELECT 
    id,
    proforma_number,
    LENGTH(saft_hash) as hash_length,
    SUBSTRING(saft_hash, -4) as hash_preview,
    created_at
FROM invoicing_sales_proformas
ORDER BY id DESC
LIMIT 5;
```

### 3. Resultado Esperado:

```
id | proforma_number | hash_length | hash_preview | created_at
---|-----------------|-------------|--------------|------------
13 | FT 2025/00001  | 344         | oPw==        | 2025-10-04 12:16:38
```

## 📊 Tamanho do Banco de Dados

### Impacto do TEXT:

- **TEXT:** Armazena até 65KB (65,535 caracteres)
- **Hash atual:** ~344 bytes (0.34 KB)
- **Overhead:** Mínimo

### Comparação:

| Tipo | Espaço Fixo | Espaço Hash | Overhead |
|------|-------------|-------------|----------|
| VARCHAR(512) | 512 bytes | 344 bytes | 168 bytes desperdiçados |
| TEXT | Variável | 344 bytes | 2 bytes (ponteiro) |

**Vantagem TEXT:** Usa apenas o espaço necessário + 2 bytes

## 🔐 Segurança

### Hash Completo:

```
✅ Armazenado: Inteiro (344 caracteres)
✅ Exibido: Últimos 4 caracteres
✅ Validável: Com chave pública
```

### Formato de Exibição:

```
Processado por sistema certificado AGT | Regime: Regime Geral
HASH e SAFT-AO: "oPw=="
                 ^^^^^^
            (últimos 4 chars)
```

## 📚 Referências

- [MySQL TEXT Types](https://dev.mysql.com/doc/refman/8.0/en/string-type-syntax.html)
- [Laravel Schema Builder](https://laravel.com/docs/11.x/migrations#columns)
- [Base64 Encoding](https://en.wikipedia.org/wiki/Base64)
- [RSA Key Sizes](https://www.keylength.com/)

## 🔄 Migrations Aplicadas

1. ✅ `2025_10_04_112804_add_hash_to_sales_proformas_table.php`
   - Estrutura inicial

2. ✅ `2025_10_04_121750_fix_saft_hash_column_in_sales_proformas_table.php`
   - Remove campo `hash` duplicado
   - Muda `saft_hash` para TEXT

## 💡 Conclusão

**Uma única coluna TEXT é suficiente e adequada para armazenar hashes SAFT-AO conforme regulamento Angola.**
