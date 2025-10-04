# Configuração SAFT-AO (Angola)

## 📋 Visão Geral

Este módulo implementa a configuração de chaves criptográficas para assinatura digital de documentos fiscais conforme o regulamento SAFT-AO (Standard Audit File for Tax - Angola) da AGT.

## 🔐 Componentes

### 1. **Geração de Chaves**
- **Algoritmo**: RSA-2048 bits
- **Hash**: SHA-256
- **Formato**: PEM (Privacy Enhanced Mail)
- **Conformidade**: SAFT-AO Angola

### 2. **Armazenamento**
As chaves são armazenadas em:
```
storage/app/saft/
├── public_key.pem      # Chave pública
├── private_key.pem     # Chave privada (confidencial)
├── metadata.json       # Metadados da geração
└── backups/            # Backups de chaves antigas
    └── YYYY-MM-DD_HHmmss/
```

## 🚀 Como Usar

### Acessar Configuração
```
URL: http://soserp.test/superadmin/saft-configuration
```
> **Nota**: Apenas Super Admins têm acesso

### Gerar Novas Chaves

1. Acesse a página de configuração SAFT-AO
2. Clique em **"Gerar Chaves"**
3. As chaves serão criadas automaticamente:
   - `public_key.pem` - Chave pública
   - `private_key.pem` - Chave privada
   - `metadata.json` - Informações de geração

### Regenerar Chaves

⚠️ **ATENÇÃO**: Regenerar as chaves invalidará todos os documentos assinados anteriormente!

1. Clique em **"Regenerar Chaves"**
2. Confirme a ação
3. As chaves antigas serão automaticamente arquivadas em `backups/`
4. Novas chaves serão geradas

### Download de Chaves

- **Chave Pública**: Pode ser compartilhada com AGT para validação
- **Chave Privada**: ⚠️ DEVE ser mantida em segurança absoluta

## 💻 Uso Programático

### Gerar Hash para Documento

```php
use App\Helpers\SAFTHelper;

// Formatar dados do documento
$documentData = SAFTHelper::formatDocumentData(
    date: '2025-10-04',
    documentNumber: 'FT 2025/00001',
    total: 1250.50
);

// Obter hash do documento anterior
$previousHash = $previousProforma->saft_hash ?? '';

// Gerar hash assinado
$hash = SAFTHelper::generateHash($documentData, $previousHash);

// Salvar no documento
$proforma->saft_hash = $hash;
$proforma->save();
```

### Exemplo Completo (Proforma)

```php
// No método save() do ProformaCreate.php

use App\Helpers\SAFTHelper;

// Após salvar a proforma
$proforma->save();

// Buscar hash do documento anterior
$previousProforma = SalesProforma::where('tenant_id', activeTenantId())
    ->where('id', '<', $proforma->id)
    ->orderBy('id', 'desc')
    ->first();

// Formatar dados conforme SAFT-AO
$documentData = SAFTHelper::formatDocumentData(
    $proforma->proforma_date->format('Y-m-d'),
    $proforma->proforma_number,
    $proforma->total
);

// Gerar hash
$hash = SAFTHelper::generateHash(
    $documentData, 
    $previousProforma->saft_hash ?? ''
);

// Atualizar proforma com hash
$proforma->saft_hash = $hash;
$proforma->save();
```

### Verificar Hash

```php
use App\Helpers\SAFTHelper;

$isValid = SAFTHelper::verifyHash(
    $documentData,
    $previousHash,
    $proforma->saft_hash
);

if ($isValid) {
    echo "Hash válido!";
} else {
    echo "Hash inválido!";
}
```

## 📊 Formato do Hash no Documento

O hash aparece no rodapé dos documentos fiscais:

```
Processado por sistema certificado AGT | Regime: Regime Geral
HASH e SAFT-AO: "e|sC"
```

Os últimos 4 caracteres do hash são exibidos entre aspas conforme regulamento.

## 🔄 Fluxo de Assinatura

```
1. Documento é criado
   ↓
2. Buscar hash do documento anterior
   ↓
3. Formatar dados: Data;Número;Total
   ↓
4. Concatenar: DadosDocumento;HashAnterior
   ↓
5. Gerar SHA-256
   ↓
6. Assinar com chave privada RSA
   ↓
7. Encode Base64
   ↓
8. Salvar no campo saft_hash
```

## 🛡️ Segurança

### Chave Privada
- ⚠️ **NUNCA** compartilhe a chave privada
- ⚠️ **NUNCA** commit no Git (já está no .gitignore)
- ✅ Faça backup em local seguro
- ✅ Controle acesso ao servidor

### Chave Pública
- ✅ Pode ser compartilhada
- ✅ Enviada à AGT para validação
- ✅ Usada para verificar assinaturas

## 📁 Estrutura de Arquivos

```php
app/
├── Helpers/
│   └── SAFTHelper.php                    # Helper para geração de hash
├── Livewire/
│   └── SuperAdmin/
│       └── SaftConfiguration.php         # Componente de configuração

resources/
└── views/
    └── livewire/
        └── super-admin/
            └── saft-configuration.blade.php

storage/
└── app/
    └── saft/                             # Chaves e metadados
        ├── public_key.pem
        ├── private_key.pem
        ├── metadata.json
        └── backups/
```

## 🔧 Troubleshooting

### Erro: "Erro ao gerar par de chaves"
- Verifique se a extensão OpenSSL está habilitada no PHP
- Verifique permissões da pasta `storage/app`

### Erro: "Chave não encontrada"
- Execute a geração de chaves na interface
- Verifique se a pasta `storage/app/saft` existe

### Hash não aparece no documento
- Verifique se `$proforma->saft_hash` foi salvo
- Limpe o cache: `php artisan view:clear`

## 📚 Referências

- **SAFT-AO**: Standard Audit File for Tax - Angola
- **AGT**: Administração Geral Tributária de Angola
- **RSA-2048**: Algoritmo de criptografia assimétrica
- **SHA-256**: Secure Hash Algorithm 256 bits

## ✅ Checklist de Implementação

- [x] Criar migração para campo `hash` e `saft_hash`
- [x] Criar componente de configuração SAFT
- [x] Implementar geração de chaves RSA-2048
- [x] Criar helper para assinatura de documentos
- [x] Adicionar exibição de hash nos documentos
- [ ] Integrar geração automática de hash ao salvar documentos
- [ ] Implementar validação de hash
- [ ] Criar job para reassinar documentos após regeneração de chaves
- [ ] Adicionar logs de auditoria

## 🎯 Próximos Passos

1. **Integrar com Proformas**: Gerar hash automaticamente ao salvar
2. **Integrar com Faturas**: Gerar hash automaticamente ao salvar
3. **Validação**: Criar endpoint para validar hash de documento
4. **Relatórios**: Criar relatório de documentos assinados
5. **Auditoria**: Log de todas as operações com chaves
