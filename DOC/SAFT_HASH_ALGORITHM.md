# Algoritmo de Geração de HASH SAFT-AO (Angola)

## 📋 Visão Geral

Este documento descreve o algoritmo de geração de hash para documentos fiscais conforme o regulamento SAFT-AO (Standard Audit File for Tax - Angola) da AGT.

## 🔐 Estrutura do Hash

### Dados Necessários (em ordem):

1. **InvoiceDate** - Data do documento (formato: YYYY-MM-DD)
2. **SystemEntryDate** - Data/hora de gravação (formato: YYYY-MM-DDTHH:MM:SS)
3. **InvoiceNo** - Número do documento (ex: FT 2025/00001)
4. **GrossTotal** - Total do documento incluindo impostos (formato: 0.00)
5. **Hash** - Hash do documento anterior (encadeamento)

### Formato de Concatenação:

```
Data;DataHoraSistema;NumeroDocumento;ValorTotal;HashAnterior
```

## 📝 Exemplo Prático

### Primeiro Documento (sem hash anterior):
```
2025-10-04;2025-10-04T12:30:45;FT 2025/00001;1250.50;
```

### Segundo Documento (com hash anterior):
```
2025-10-04;2025-10-04T14:22:10;FT 2025/00002;890.00;a1b2c3d4e5f6g7h8...
```

## 🔧 Algoritmo de Assinatura

### Com Chave Privada RSA:

```php
// 1. Concatenar dados
$dataToSign = "{$invoiceDate};{$systemEntryDate};{$invoiceNo};{$grossTotal}";

// 2. Adicionar hash anterior (se existir)
if (!empty($previousHash)) {
    $dataToSign .= ';' . $previousHash;
}

// 3. Assinar com RSA-SHA256
openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

// 4. Encode Base64
$hash = base64_encode($signature);
```

### Sem Chave Privada (SHA-1):

```php
// 1. Concatenar dados
$dataToSign = "{$invoiceDate};{$systemEntryDate};{$invoiceNo};{$grossTotal}";

// 2. Adicionar hash anterior (se existir)
if (!empty($previousHash)) {
    $dataToSign .= ';' . $previousHash;
}

// 3. Gerar SHA-1
$hash = sha1($dataToSign);
```

## 💻 Implementação no Sistema

### Geração Automática:

```php
use App\Helpers\SAFTHelper;

// Buscar hash do documento anterior
$previousProforma = SalesProforma::where('tenant_id', activeTenantId())
    ->where('id', '<', $proforma->id)
    ->whereNotNull('saft_hash')
    ->orderBy('id', 'desc')
    ->first();

// Gerar hash
$hash = SAFTHelper::generateHash(
    $proforma->proforma_date->format('Y-m-d'),           // Data do documento
    $proforma->created_at->format('Y-m-d H:i:s'),        // Data/hora sistema
    $proforma->proforma_number,                          // Número do documento
    $proforma->total,                                    // Total com impostos
    $previousProforma->saft_hash ?? null                 // Hash anterior
);

// Salvar
$proforma->saft_hash = $hash;
$proforma->save();
```

## 🔍 Validação

### Verificar Hash:

```php
// Reconstruir string de dados
$dataToVerify = $invoiceDate . ';' . 
                $systemEntryDate . ';' . 
                $invoiceNo . ';' . 
                $grossTotal;

if (!empty($previousHash)) {
    $dataToVerify .= ';' . $previousHash;
}

// Verificar assinatura com chave pública
$isValid = openssl_verify(
    $dataToVerify, 
    base64_decode($signature), 
    $publicKey, 
    OPENSSL_ALGO_SHA256
);
```

## 📊 Encadeamento de Hashes

```
┌─────────────────────────────────────────────────────┐
│ Documento 1                                         │
│ Hash: a1b2c3d4... (sem hash anterior)              │
└─────────────────┬───────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────┐
│ Documento 2                                         │
│ Hash Anterior: a1b2c3d4...                         │
│ Hash: e5f6g7h8... (com hash anterior)             │
└─────────────────┬───────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────┐
│ Documento 3                                         │
│ Hash Anterior: e5f6g7h8...                         │
│ Hash: i9j0k1l2... (com hash anterior)             │
└─────────────────────────────────────────────────────┘
```

## 📄 Exibição no Documento

Os últimos 4 caracteres do hash são exibidos no documento:

```
Processado por sistema certificado AGT | Regime: Regime Geral
HASH e SAFT-AO: "e|sC"
```

## ⚙️ Formato dos Dados

### InvoiceDate:
```php
date('Y-m-d', strtotime($proforma->proforma_date))
// Exemplo: 2025-10-04
```

### SystemEntryDate:
```php
date('Y-m-d\TH:i:s', strtotime($proforma->created_at))
// Exemplo: 2025-10-04T12:30:45
```

### GrossTotal:
```php
number_format($proforma->total, 2, '.', '')
// Exemplo: 1250.50
```

### InvoiceNo:
```php
$proforma->proforma_number
// Exemplo: FT 2025/00001
```

## 🔐 Segurança

### Chave Privada:
- Usada para ASSINAR o hash
- **NUNCA** deve ser compartilhada
- Armazenada em: `storage/app/saft/private_key.pem`

### Chave Pública:
- Usada para VERIFICAR o hash
- Pode ser compartilhada com AGT
- Armazenada em: `storage/app/saft/public_key.pem`

## 📚 Referências

- **Decreto Presidencial n.º 71/25** - Regime Jurídico das Facturas (Angola)
- **Portaria n.º 363/2010** - Certificação de Software (Portugal - base)
- **SAFT-AO** - Standard Audit File for Tax - Angola
- **AGT** - Administração Geral Tributária de Angola

## ✅ Checklist de Conformidade

- [x] Hash gerado conforme formato SAFT-AO
- [x] Encadeamento com hash anterior
- [x] Assinatura RSA-SHA256 (se chaves existirem)
- [x] Fallback para SHA-1 (sem chaves)
- [x] Data/hora no formato ISO 8601
- [x] Total com 2 casas decimais
- [x] Exibição dos últimos 4 caracteres
- [x] Validação de assinatura implementada

## 🐛 Troubleshooting

### Hash não é gerado:
1. Verificar se método `SAFTHelper::generateHash()` é chamado
2. Verificar logs em `storage/logs/laravel.log`
3. Verificar se campo `saft_hash` existe na tabela

### Hash inválido na validação:
1. Verificar ordem dos dados concatenados
2. Verificar formato de data/hora
3. Verificar se total tem 2 casas decimais
4. Verificar se hash anterior está correto

### Encadeamento quebrado:
1. Regenerar hashes de todos os documentos em ordem
2. Verificar se busca do hash anterior está correta
3. Verificar se não há documentos sem hash no meio da sequência

## 💡 Notas Importantes

1. O hash é gerado **após** salvar o documento (para ter ID)
2. O hash **encadeia** com o documento anterior
3. Se alterar qualquer dado fiscal, o hash **deve ser regenerado**
4. Alterar um hash **invalida** todos os hashes subsequentes
5. O primeiro documento **não tem** hash anterior (string vazia)
