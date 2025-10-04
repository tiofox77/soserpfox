# Estrutura de Modais Organizadas

## 📁 Nova Estrutura Implementada

### **Proformas de Venda** ✅

```
resources/views/livewire/invoicing/proformas-venda/
├── proformas.blade.php          # Arquivo principal (lista)
├── create.blade.php             # Formulário de criação/edição
├── delete-modal.blade.php       # Modal de confirmação de eliminação
├── view-modal.blade.php         # Modal de visualização de detalhes
└── history-modal.blade.php      # Modal de histórico de conversões
```

---

## 🎯 Benefícios da Separação

### **1. Manutenibilidade** ✅
- Cada modal em arquivo próprio
- Fácil localizar e editar
- Código mais limpo e organizado

### **2. Reutilização** ✅
- Modais podem ser incluídas em qualquer lugar
- Evita duplicação de código
- Padronização entre seções

### **3. Performance** ✅
- Blade compila separadamente
- Cache mais eficiente
- Carregamento otimizado

---

## 📝 Como Usar as Modais

### **No Arquivo Principal:**

```blade
{{-- No final do arquivo proformas.blade.php --}}
@include('livewire.invoicing.proformas-venda.delete-modal')
@include('livewire.invoicing.proformas-venda.view-modal')
@include('livewire.invoicing.proformas-venda.history-modal')
```

### **Propriedades Necessárias no Componente Livewire:**

```php
// Delete Modal
public $showDeleteModal = false;
public $proformaToDelete = null;

// View Modal
public $showViewModal = false;
public $selectedProforma = null;

// History Modal
public $showHistoryModal = false;
public $proformaHistory = null;
public $relatedInvoices = [];
```

---

## 🔄 Padrão para Outras Áreas

### **Faturas de Venda:**
```
resources/views/livewire/invoicing/faturas-venda/
├── faturas.blade.php            # Lista principal
├── create.blade.php             # Formulário
├── delete-modal.blade.php       # Modal delete
├── view-modal.blade.php         # Modal view
└── history-modal.blade.php      # Modal histórico
```

### **Proformas de Compra:**
```
resources/views/livewire/invoicing/proformas-compra/
├── proformas.blade.php          # Lista principal
├── create.blade.php             # Formulário
├── delete-modal.blade.php       # Modal delete
├── view-modal.blade.php         # Modal view
└── history-modal.blade.php      # Modal histórico
```

### **Faturas de Compra:**
```
resources/views/livewire/invoicing/faturas-compra/
├── faturas.blade.php            # Lista principal
├── create.blade.php             # Formulário
├── delete-modal.blade.php       # Modal delete
├── view-modal.blade.php         # Modal view
└── history-modal.blade.php      # Modal histórico
```

---

## 📋 Estrutura de Cada Modal

### **1. Delete Modal**
```blade
@if($showDeleteModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50...">
    <div class="bg-white rounded-2xl...">
        <!-- Confirmação de eliminação -->
        <button wire:click="$set('showDeleteModal', false)">Cancelar</button>
        <button wire:click="deleteProforma">Eliminar</button>
    </div>
</div>
@endif
```

**Métodos Necessários:**
- `confirmDelete($id)` - Abre modal
- `deleteProforma()` - Executa eliminação
- `$set('showDeleteModal', false)` - Fecha modal

---

### **2. View Modal**
```blade
@if($showViewModal && $selectedProforma)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50...">
    <div class="bg-white rounded-2xl...">
        <!-- Detalhes completos do documento -->
        <button wire:click="closeViewModal">Fechar</button>
    </div>
</div>
@endif
```

**Métodos Necessários:**
- `viewProforma($id)` - Carrega dados e abre modal
- `closeViewModal()` - Fecha modal

**Dados Exibidos:**
- Informações do cliente
- Datas e status
- Lista de produtos
- Totais calculados
- Notas adicionais

---

### **3. History Modal**
```blade
@if($showHistoryModal && $proformaHistory)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50...">
    <div class="bg-white rounded-2xl...">
        <!-- Lista de documentos gerados -->
        <button wire:click="convertToInvoice()">Converter Novamente</button>
        <button wire:click="closeHistoryModal">Fechar</button>
    </div>
</div>
@endif
```

**Métodos Necessários:**
- `showHistory($id)` - Carrega histórico e abre modal
- `closeHistoryModal()` - Fecha modal
- `convertToInvoice($id)` - Converte novamente

**Dados Exibidos:**
- Informações da proforma original
- Lista de faturas geradas
- Status de cada fatura
- Links para visualizar faturas
- Botão para nova conversão

---

## 🎨 Padrão Visual das Modais

### **Delete Modal:**
- 🔴 Cor: Vermelho
- ⚠️ Ícone: fa-trash
- 📏 Tamanho: max-w-md (pequeno)
- ⚡ Animação: animate-scale-in

### **View Modal:**
- 🟣 Cor: Roxo/Indigo
- 👁️ Ícone: fa-file-invoice
- 📏 Tamanho: max-w-4xl (grande)
- 📜 Scroll: max-h-[calc(100vh-200px)]

### **History Modal:**
- 🟣 Cor: Roxo/Indigo
- 🕒 Ícone: fa-history
- 📏 Tamanho: max-w-4xl (grande)
- 📜 Scroll: max-h-[90vh]

---

## 🔧 Como Criar Nova Área

### **Passo 1: Criar Estrutura de Pastas**
```bash
mkdir resources/views/livewire/invoicing/nova-area
```

### **Passo 2: Copiar Modais Base**
```bash
# Copiar de proformas-venda
cp resources/views/livewire/invoicing/proformas-venda/*.blade.php \
   resources/views/livewire/invoicing/nova-area/
```

### **Passo 3: Ajustar Variáveis**
Substituir nas modais:
- `$proforma` → `$novoDocumento`
- `showDeleteModal` → mesmo nome (padrão)
- `selectedProforma` → `selectedDocumento`
- `proformaHistory` → `documentoHistory`

### **Passo 4: Incluir no Arquivo Principal**
```blade
{{-- No final do arquivo principal --}}
@include('livewire.invoicing.nova-area.delete-modal')
@include('livewire.invoicing.nova-area.view-modal')
@include('livewire.invoicing.nova-area.history-modal')
```

### **Passo 5: Adicionar Propriedades no Componente**
```php
class NovaArea extends Component
{
    // Modals
    public $showDeleteModal = false;
    public $showViewModal = false;
    public $showHistoryModal = false;
    
    // Data
    public $documentoToDelete = null;
    public $selectedDocumento = null;
    public $documentoHistory = null;
    public $relatedDocuments = [];
}
```

---

## 📊 Comparação: Antes vs Depois

### **ANTES (Monolítico):**
```
proformas.blade.php (619 linhas)
├── Header (20 linhas)
├── Stats (60 linhas)
├── Filters (30 linhas)
├── Table (160 linhas)
├── Delete Modal (22 linhas)
├── View Modal (167 linhas)
└── History Modal (140 linhas)
```

### **DEPOIS (Modular):**
```
proformas.blade.php (293 linhas)      ✅ 52% menor
├── Header (20 linhas)
├── Stats (60 linhas)
├── Filters (30 linhas)
├── Table (160 linhas)
└── @includes (3 linhas)

delete-modal.blade.php (22 linhas)    ✅ Separado
view-modal.blade.php (167 linhas)     ✅ Separado
history-modal.blade.php (140 linhas)  ✅ Separado
```

---

## ✅ Checklist de Implementação

### **Proformas de Venda:**
- [x] delete-modal.blade.php criado
- [x] view-modal.blade.php criado
- [x] history-modal.blade.php criado
- [x] proformas.blade.php atualizado com @includes
- [x] Cache limpo

### **Faturas de Venda:**
- [ ] Criar estrutura de pastas
- [ ] Copiar modais base
- [ ] Ajustar variáveis
- [ ] Incluir no arquivo principal
- [ ] Testar funcionalidade

### **Proformas de Compra:**
- [ ] Criar estrutura de pastas
- [ ] Copiar modais base
- [ ] Ajustar variáveis (supplier em vez de client)
- [ ] Incluir no arquivo principal
- [ ] Testar funcionalidade

### **Faturas de Compra:**
- [ ] Criar estrutura de pastas
- [ ] Copiar modais base
- [ ] Ajustar variáveis (supplier em vez de client)
- [ ] Incluir no arquivo principal
- [ ] Testar funcionalidade

---

## 🚀 Próximos Passos

1. **Testar** área de proformas de venda
2. **Replicar** estrutura para faturas de venda
3. **Adaptar** para áreas de compras (supplier vs client)
4. **Documentar** customizações específicas de cada área

---

## 📝 Notas Importantes

### **Convenções de Nomenclatura:**
```
delete-modal.blade.php   (sempre em kebab-case)
view-modal.blade.php
history-modal.blade.php
```

### **Ordem dos @includes:**
```blade
{{-- Ordem sugerida (não obrigatória) --}}
@include('...delete-modal')  
@include('...view-modal')
@include('...history-modal')
```

### **Variáveis Wire:**
```blade
{{-- Sempre usar wire:click, wire:model --}}
wire:click="$set('showModal', false)"  ✅
wire:click="closeModal()"              ✅
@click="showModal = false"             ❌ Não usar Alpine direto
```

---

**Estrutura de modais organizadas implementada com sucesso! Código mais limpo, modular e fácil de manter. 📁✨**
