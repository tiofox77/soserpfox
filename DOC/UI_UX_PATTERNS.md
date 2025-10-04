# Padrões de UI/UX - Animações e Ícones

## 🎨 Sistema de Design Implementado

### **1. Estrutura de Modals Modernas**

**Padrão de Modal:**
```blade
<!-- Modal Container -->
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-[tamanho] w-full max-h-[90vh] overflow-y-auto transform transition-all animate-scale-in">
        
        <!-- Header com Gradiente -->
        <div class="sticky top-0 bg-gradient-to-r from-[cor1] to-[cor2] px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-[icone] mr-2"></i>
                Título
            </h3>
            <button wire:click="$set('showModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <!-- Body -->
        <div class="p-6">
            <!-- Conteúdo -->
        </div>
        
        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end gap-3">
            <!-- Botões -->
        </div>
    </div>
</div>
```

### **2. Sistema de Cores e Gradientes**

**Gradientes por Tipo:**
- **Transferência**: `from-blue-600 to-cyan-600` / `from-purple-600 to-indigo-600`
- **Ajuste**: `from-yellow-500 to-orange-500`
- **Stock/Inventário**: `from-purple-600 to-indigo-600`
- **Movimentos/Histórico**: `from-green-600 to-emerald-600`

**Cards Informativos:**
```blade
<div class="bg-gradient-to-r from-[cor]-50 to-[cor2]-50 border-2 border-[cor]-200 rounded-xl p-4">
    <!-- Conteúdo -->
</div>
```

### **3. Sistema de Ícones FontAwesome**

**Ícones Padrão por Contexto:**
- 📦 `fa-box` - Produto
- 🏭 `fa-warehouse` - Armazém
- 📊 `fa-cubes` - Quantidade
- ✅ `fa-check-circle` - Disponível
- 🔒 `fa-lock` - Reservado
- 💰 `fa-dollar-sign` - Custo
- ⚙️ `fa-cog` - Configurações
- 🔄 `fa-exchange-alt` - Transferência
- ✏️ `fa-edit` - Editar/Ajustar
- 📜 `fa-history` - Histórico/Movimentos
- 🔍 `fa-search` - Pesquisa
- ➕ `fa-plus` - Adicionar
- 🗑️ `fa-trash-alt` - Remover
- #️⃣ `fa-hashtag` - Quantidade
- 📝 `fa-sticky-note` - Observações
- 📋 `fa-barcode` - Código

### **4. Animações CSS**

**Animações Tailwind:**
```css
animate-pulse         /* Para alertas */
animate-bounce        /* Para chamadas de atenção */
animate-fade-in       /* Entrada de modals */
animate-scale-in      /* Zoom de modals */
```

**Transições Hover:**
```blade
<!-- Linhas de Tabela -->
class="hover:bg-purple-50 transition-all duration-200 ease-in-out transform hover:scale-[1.01] hover:shadow-md"

<!-- Botões de Ação -->
class="transition-all duration-200 transform hover:scale-110"

<!-- Ícones -->
class="transition-colors"
```

### **5. Botões de Ação com Tooltips**

**Padrão de Botão Interativo:**
```blade
<button 
    wire:click="metodo()" 
    class="group relative p-2 bg-[cor]-100 hover:bg-[cor]-600 rounded-lg transition-all duration-200 transform hover:scale-110"
    title="Título">
    <i class="fas fa-[icone] text-[cor]-600 group-hover:text-white transition-colors"></i>
    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
        Texto Tooltip
    </span>
</button>
```

**Cores por Ação:**
- 🔵 **Editar/Ajustar**: `blue-100` / `blue-600`
- 🟣 **Transferir**: `purple-100` / `purple-600`
- 🟢 **Histórico/Ver**: `green-100` / `green-600`
- 🔴 **Remover/Deletar**: `red-100` / `red-600`

### **6. Sistema de Steps (Wizard)**

```blade
<!-- Step Header -->
<div class="flex items-center mb-6">
    <div class="w-10 h-10 bg-[cor]-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
        1
    </div>
    <div>
        <h4 class="font-bold text-[cor]-900 text-lg">Título do Step</h4>
        <p class="text-sm text-[cor]-700">Descrição</p>
    </div>
</div>
```

### **7. Badges e Tags**

**Badge Padrão:**
```blade
<span class="inline-flex items-center px-3 py-1.5 text-xs font-bold bg-[cor]-100 text-[cor]-800 rounded-full">
    <i class="fas fa-[icone] mr-1.5"></i>
    Texto
</span>
```

**Por Tipo de Movimento:**
- ✅ **Entrada**: `bg-green-100 text-green-800`
- ❌ **Saída**: `bg-red-100 text-red-800`
- 🔄 **Transferência**: `bg-blue-100 text-blue-800`
- ⚙️ **Ajuste**: `bg-yellow-100 text-yellow-800`

### **8. Cards de Produto em Grid**

```blade
<div wire:click="selectProduct({{ $id }})" 
     class="p-4 border-2 rounded-xl cursor-pointer transition border-gray-200 hover:border-[cor]-400 hover:shadow-lg bg-white hover:scale-105">
    <div class="flex items-start justify-between mb-2">
        <div class="flex-1">
            <p class="font-bold text-sm text-gray-900">{{ $nome }}</p>
            <p class="text-xs text-gray-600">{{ $codigo }}</p>
        </div>
    </div>
    <div class="mt-2 pt-2 border-t border-gray-200">
        <p class="text-xs text-gray-600">Stock:</p>
        <p class="text-sm font-bold text-[cor]-600">{{ $quantidade }}</p>
    </div>
</div>
```

### **9. Inputs Estilizados**

**Input de Quantidade Grande:**
```blade
<input 
    type="number" 
    step="1" 
    min="0"
    wire:model="quantidade" 
    class="w-full px-6 py-4 rounded-xl border-2 border-gray-300 focus:border-[cor]-500 focus:ring-4 focus:ring-[cor]-200 transition text-3xl font-bold text-center"
    placeholder="0"
    autofocus>
```

**Botões Rápidos de Quantidade:**
```blade
<div class="grid grid-cols-4 gap-2 mt-4">
    <button type="button" wire:click="$set('quantidade', 10)"
            class="px-3 py-2 bg-gray-100 hover:bg-[cor]-100 border-2 border-gray-300 hover:border-[cor]-400 rounded-lg font-semibold text-sm transition">
        10
    </button>
    <!-- ... mais botões ... -->
</div>
```

### **10. Alertas e Indicadores Visuais**

**Alerta de Stock Baixo:**
```blade
@if($isLowStock)
    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded-full animate-bounce">
        <i class="fas fa-exclamation-triangle mr-1"></i>Baixo
    </span>
@endif
```

**Barra de Progresso:**
```blade
<div class="w-full bg-gray-200 rounded-full h-2 mt-2">
    <div class="bg-green-500 h-2 rounded-full transition-all duration-300" 
         style="width: {{ $percent }}%"></div>
</div>
```

### **11. Empty States**

```blade
<div class="flex flex-col items-center justify-center animate-pulse py-16">
    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
        <i class="fas fa-[icone] text-gray-300 text-4xl"></i>
    </div>
    <p class="text-gray-500 text-lg font-semibold">Nenhum item encontrado</p>
    <p class="text-gray-400 text-sm mt-2">Mensagem secundária</p>
</div>
```

### **12. Imagens de Produto**

**Com Imagem:**
```blade
<img src="{{ Storage::url($image) }}" 
     class="h-12 w-12 rounded-xl object-cover shadow-md ring-2 ring-purple-200">
```

**Placeholder:**
```blade
<div class="h-12 w-12 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-md">
    <i class="fas fa-box text-white text-lg"></i>
</div>
```

## 🎯 Princípios de Uso

1. **Consistência**: Usar sempre os mesmos ícones para as mesmas ações
2. **Feedback Visual**: Toda ação tem hover/focus/active states
3. **Hierarquia**: Gradientes em headers, backgrounds suaves em body
4. **Animações Sutis**: 200-300ms para transições, evitar exageros
5. **Acessibilidade**: Tooltips em botões, labels claros, contraste adequado
6. **Responsividade**: Grid responsivo, modals adaptáveis, max-h-[90vh]

## 📦 Exemplos Implementados

- **Stock Management**: Listagem com animações hover, tooltips em botões
- **Warehouse Transfer**: Modal multi-step com gradientes e ícones
- **Transfer Details**: Modal específica com visual de origem/destino
- **Adjust Stock**: Modal com indicador de diferença em tempo real
- **Movements History**: Modal com tabela, badges coloridos e resumo
