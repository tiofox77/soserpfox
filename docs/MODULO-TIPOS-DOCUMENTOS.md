# Módulo: Tipos de Documentos Contabilísticos

**Data:** 28/10/2025  
**Baseado em:** `Tipos de Documentos Contabilísticos (1).xlsx`

## 📋 Resumo

Módulo completo para gestão dos **Tipos de Documentos Contabilísticos** que relaciona documentos com diários contabilísticos. Importa automaticamente dados do Excel fornecido.

---

## 🗂️ Estrutura Criada

### 1. **Database**

#### Migration
- `database/migrations/2025_10_28_142754_create_accounting_document_types_table.php`

**Campos:**
- `id`, `tenant_id`
- `code` (Código do documento: 101, 211, 541...)
- `description` (Descrição do tipo)
- `journal_code`, `journal_id` (Relacionamento com Diário)
- **Flags Booleanas:**
  - `recapitulativos`
  - `retencao_fonte`
  - `bal_financeira`
  - `bal_analitica`
- **Campos Numéricos:**
  - `rec_informacao`
  - `tipo_doc_imo`
  - `calculo_fluxo_caixa`
- `is_active`, `display_order`
- `timestamps`, `softDeletes`

**Índices:**
- UNIQUE: `tenant_id`, `code`
- INDEX: `tenant_id`, `journal_id`
- INDEX: `tenant_id`, `is_active`

---

### 2. **Model**

**Arquivo:** `app/Models/Accounting/DocumentType.php`

**Relacionamentos:**
- `tenant()` → BelongsTo Tenant
- `journal()` → BelongsTo Journal

**Scopes:**
- `active()` - Documentos ativos
- `forTenant($tenantId)` - Por tenant
- `recapitulativos()` - Recapitulativos
- `withRetencao()` - Com retenção
- `balFinanceira()` - Balancete financeira
- `ordered()` - Ordenado por display_order e code

**Attributes:**
- `full_name` → "{code} - {description}"
- `status_color` → "green" ou "gray"
- `status_label` → "Ativo" ou "Inativo"

---

### 3. **Seeder**

**Arquivo:** `database/seeders/Accounting/DocumentTypeSeeder.php`

**Funcionalidades:**
- ✅ Lê Excel: `database/seeders/Accounting/Tipos de Documentos Contabilísticos (1).xlsx`
- ✅ Importa 63 tipos de documentos
- ✅ Relaciona automaticamente com Journals pelo `journal_code`
- ✅ Método `runForTenant($tenantId)` para tenants específicos

**Como usar:**
```bash
php artisan db:seed --class=Database\\Seeders\\Accounting\\DocumentTypeSeeder
```

Ou via interface:
- Clicar em **"Importar do Excel"** na página do módulo

---

### 4. **Componente Livewire**

**Arquivo:** `app/Livewire/Accounting/DocumentTypeManagement.php`

**Funcionalidades:**
- ✅ Listagem com paginação (20 por página)
- ✅ Busca por código ou descrição
- ✅ Filtros:
  - Por diário
  - Recapitulativos (Sim/Não)
  - Retenção Fonte (Sim/Não)
  - Mostrar inativos
- ✅ CRUD completo:
  - Criar
  - Editar
  - Visualizar
  - Excluir (soft delete)
- ✅ Importação do Excel
- ✅ Modais modernos com Alpine.js

---

### 5. **Views**

#### View Principal
**Arquivo:** `resources/views/livewire/accounting/document-type-management.blade.php`

**Elementos:**
- Header com título e botões de ação
- Card de filtros
- Tabela responsiva com badges coloridos
- Paginação
- Estados vazios elegantes

#### Modais (Partials)
**Diretório:** `resources/views/livewire/accounting/document-types/partials/`

1. **form-modal.blade.php**
   - Formulário completo de criação/edição
   - Validação em tempo real
   - Checkboxes para flags booleanas
   - Campos numéricos
   - Status e ordem

2. **view-modal.blade.php**
   - Visualização detalhada
   - Cards informativos
   - Badges coloridos para flags

3. **delete-modal.blade.php**
   - Confirmação de exclusão
   - Aviso de ação irreversível
   - Loading states

---

### 6. **Rota**

**Arquivo:** `routes/web.php`

```php
Route::get('/accounting/document-types', 
    \App\Livewire\Accounting\DocumentTypeManagement::class)
    ->name('accounting.document-types');
```

**URL:** `http://soserp.test/accounting/document-types`

---

## 🎨 Design

**Padrão SOS:**
- Cards com `rounded-2xl` e `shadow-lg`
- Botões com gradientes e hover effects
- Badges coloridos para status
- Filtros em grid responsivo
- Tabela moderna com hover states
- Modais com transições suaves

**Cores:**
- **Roxo** (#7c3aed) → Importar Excel
- **Azul** (#2563eb) → Criar/Editar
- **Cyan** (#0891b2) → Visualizar
- **Vermelho** (#dc2626) → Excluir
- **Verde** (#16a34a) → Status Ativo
- **Cinza** → Inativos/Desabilitados

---

## 📊 Dados do Excel

**Estrutura Original:**
- **Coluna A:** Documento (código)
- **Coluna B:** Descrição
- **Coluna C:** Diário (código)
- **Coluna D:** Recapitulativos (TRUE/FALSE)
- **Coluna E:** Retenção Fonte (TRUE/FALSE)
- **Coluna F:** Bal. Financeira (TRUE/FALSE)
- **Coluna G:** Bal. Analítica (TRUE/FALSE)
- **Coluna H:** Rec. Informação (número)
- **Coluna I:** Tipo Doc. Imo. (número)
- **Coluna J:** Cálculo Fluxo Caixa (número)

**Total de Registros:** 63 tipos de documentos

**Exemplos:**
- `101` - Abertura
- `211` - Caixa AKZ - Pagamentos
- `311` - Fatura - n/Factura
- `541` - Imo. MN - n/Factura
- `621` - Apuramento do IVA

---

## 🔗 Relacionamentos

### Com Journals (Diários)

Cada tipo de documento pode estar associado a um diário:
- `journal_id` → FK para `accounting_journals`
- `journal_code` → Código do diário (10, 21, 31, 54, etc.)

**Busca Automática:**
O seeder busca automaticamente o `journal_id` baseado no `journal_code` do Excel.

### Com Tenant

Multi-tenancy completo:
- Cada registro pertence a um tenant
- Filtragem automática por `tenant_id`
- Unique constraint: `tenant_id` + `code`

---

## 🚀 Como Usar

### 1. Acessar o Módulo

```
http://soserp.test/accounting/document-types
```

### 2. Importar Dados do Excel

1. Clicar em **"Importar do Excel"** (botão roxo)
2. Sistema importa automaticamente os 63 tipos
3. Relaciona com diários existentes
4. Exibe mensagem de sucesso

### 3. Criar Manualmente

1. Clicar em **"Novo Tipo de Documento"**
2. Preencher:
   - Código *
   - Descrição *
   - Diário (opcional)
   - Flags booleanas
   - Campos numéricos
   - Status e ordem
3. Salvar

### 4. Filtrar

- **Busca:** Digitar código ou descrição
- **Diário:** Selecionar diário específico
- **Recapitulativos:** Sim/Não
- **Retenção Fonte:** Sim/Não
- **Checkbox:** Mostrar inativos

### 5. Ações

- **Visualizar (Cyan):** Ver detalhes completos
- **Editar (Azul):** Modificar registro
- **Excluir (Vermelho):** Soft delete com confirmação

---

## 🧪 Testes

### Verificar Migration

```bash
php artisan migrate:status
```

### Popular Dados

```bash
php artisan db:seed --class=Database\\Seeders\\Accounting\\DocumentTypeSeeder
```

### Verificar Dados

```bash
php artisan tinker
>>> \App\Models\Accounting\DocumentType::count()
>>> \App\Models\Accounting\DocumentType::with('journal')->first()
```

---

## 📝 Validações

**Campos Obrigatórios:**
- `code` (max: 10 caracteres)
- `description` (max: 255 caracteres)

**Opcionais:**
- `journal_id` (deve existir em `accounting_journals`)
- Todos os campos booleanos (default: false)
- Todos os campos numéricos (default: 0)

**Regras:**
- `code` único por tenant
- Soft delete preserva registros

---

## 🔄 Integrações

### Com Módulo de Journals

- Relacionamento direto via `journal_id`
- Importação automática busca diários pelo `code`
- Filtro por diário na listagem

### Com Lançamentos Contabilísticos (Moves)

**Uso futuro:**
- Cada lançamento poderá ter um `document_type_id`
- Facilita classificação e relatórios
- Automatiza fluxos de caixa
- Identifica documentos recapitulativos

---

## 📦 Arquivos Criados

```
app/
├── Models/Accounting/DocumentType.php
└── Livewire/Accounting/DocumentTypeManagement.php

database/
├── migrations/2025_10_28_142754_create_accounting_document_types_table.php
└── seeders/Accounting/
    ├── DocumentTypeSeeder.php
    └── Tipos de Documentos Contabilísticos (1).xlsx

resources/views/livewire/accounting/
├── document-type-management.blade.php
└── document-types/partials/
    ├── form-modal.blade.php
    ├── view-modal.blade.php
    └── delete-modal.blade.php

routes/
└── web.php (atualizado)

docs/
└── MODULO-TIPOS-DOCUMENTOS.md (este arquivo)
```

---

## ✅ Checklist de Implementação

- [x] Migration criada e executada
- [x] Model com relacionamentos
- [x] Seeder com importação do Excel
- [x] Componente Livewire completo
- [x] View principal responsiva
- [x] 3 modais funcionais
- [x] Rota registrada
- [x] Filtros e busca
- [x] Paginação
- [x] CRUD completo
- [x] Soft deletes
- [x] Multi-tenancy
- [x] Documentação completa

---

## 🎯 Próximos Passos

1. **Adicionar ao Menu:**
   - Inserir link no menu lateral de Accounting
   - Ícone sugerido: 📄 ou documento

2. **Testar Importação:**
   - Executar importação do Excel
   - Verificar relacionamentos com Journals
   - Validar dados importados

3. **Integrar com Moves:**
   - Adicionar `document_type_id` em `accounting_moves`
   - Atualizar formulário de lançamentos
   - Criar relatórios por tipo de documento

4. **Melhorias Futuras:**
   - Export para Excel
   - Duplicar tipo de documento
   - Histórico de alterações
   - Estatísticas de uso

---

## 📚 Referências

**Excel Original:**
- `Tipos de Documentos Contabilísticos (1).xlsx`
- 63 registros
- 10 colunas de dados

**Padrões Seguidos:**
- Multi-tenancy SOS
- Design System SOS
- Convenções Laravel
- Blade Components
- Livewire 3.x
- Alpine.js

---

**Implementado por:** Cascade AI  
**Data:** 28 de Outubro de 2025  
**Versão:** 1.0
