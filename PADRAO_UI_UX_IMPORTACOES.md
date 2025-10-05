# 🎨 PADRÃO UI/UX - IMPORTAÇÕES (REPLICAR EM TODOS OS MÓDULOS)

## 📋 ÍNDICE
1. [Header com Ícone Animado](#header)
2. [Filtros com Ícones e Emojis](#filtros)
3. [Ações na Tabela (4 Botões)](#acoes)
4. [Modal Create/Edit Premium](#modal-create)
5. [Modal Delete Premium](#modal-delete)
6. [Animações CSS](#animacoes)
7. [Regras Importantes](#regras)

---

## 🎨 HEADER COM ÍCONE ANIMADO {#header}

**ELEMENTO CHAVE:** Ícone flutuando suavemente (animate-bounce-slow)

```blade
<div class="mb-6 flex items-center justify-between animate-fade-in">
    <div>
        <h2 class="text-3xl font-bold text-gray-800 flex items-center">
            <!-- ⭐ ÍCONE ANIMADO COM BOUNCE - SEMPRE INCLUIR -->
            <div class="bg-gradient-to-br from-cyan-500 to-blue-600 p-3 rounded-xl mr-3 shadow-lg animate-bounce-slow">
                <i class="fas fa-ship text-white"></i>
            </div>
            Importações de Mercadorias
        </h2>
        <p class="text-gray-600 mt-1 flex items-center">
            <i class="fas fa-globe-africa text-cyan-600 mr-2"></i>
            Gestão completa do processo
        </p>
    </div>
    @can('invoicing.imports.create')
    <button wire:click="openCreateModal" 
            class="px-6 py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-2xl hover:scale-105 transform">
        <i class="fas fa-plus-circle mr-2"></i>Nova Importação
    </button>
    @endcan
</div>
```

**Características:**
- ✅ Ícone principal com `animate-bounce-slow` (flutuando)
- ✅ Gradiente no fundo do ícone
- ✅ Shadow lg
- ✅ Ícone secundário na descrição
- ✅ Botão com hover scale 1.05
- ✅ Proteção @can

---

## 🔍 FILTROS COM ÍCONES E EMOJIS {#filtros}

**ELEMENTO CHAVE:** Emojis nos selects + ícones FontAwesome nos inputs

```blade
<div class="bg-white rounded-xl shadow-lg p-4 mb-6 border border-gray-100">
    <div class="flex items-center mb-3">
        <i class="fas fa-filter text-cyan-600 mr-2"></i>
        <h3 class="font-semibold text-gray-700">Filtros</h3>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- PESQUISA COM ÍCONE -->
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live="search" placeholder="Pesquisar..." 
                   class="pl-10 w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500 transition-all">
        </div>
        
        <!-- SELECT COM ÍCONE E EMOJIS -->
        <div class="relative">
            <i class="fas fa-flag absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <select wire:model.live="filterStatus" class="pl-10 w-full rounded-lg border-gray-300 focus:border-cyan-500">
                <option value="">📊 Todos os Status</option>
                <option value="quotation">📋 Cotação</option>
                <option value="order_placed">✅ Pedido Realizado</option>
                <option value="payment_pending">⏳ Pagamento Pendente</option>
                <option value="in_transit">🚢 Em Trânsito</option>
                <option value="customs_pending">🏛️ Desembaraço Pendente</option>
                <option value="completed">🎉 Concluído</option>
            </select>
        </div>
        
        <!-- FORNECEDOR COM ÍCONE -->
        <div class="relative">
            <i class="fas fa-building absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <select wire:model.live="filterSupplier" class="pl-10 w-full rounded-lg border-gray-300">
                <option value="">🌐 Todos os Fornecedores</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        
        <!-- BOTÃO ATUALIZAR -->
        <button wire:click="$refresh" 
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-all duration-200 hover:scale-105 transform">
            <i class="fas fa-sync-alt mr-2"></i>Atualizar
        </button>
    </div>
</div>
```

**Características:**
- ✅ Ícones FontAwesome em TODOS os campos (absolute left-3)
- ✅ Emojis em TODAS as options
- ✅ pl-10 para dar espaço ao ícone
- ✅ Botão atualizar com hover scale

---

## 🎬 AÇÕES NA TABELA (4 BOTÕES) {#acoes}

**ELEMENTO CHAVE:** 4 botões com cores diferentes + hover scale 1.10

```blade
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <div class="flex items-center justify-end gap-2">
        <!-- VISUALIZAR (AZUL) -->
        <button wire:click="viewImport({{ $import->id }})" 
                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                title="Visualizar">
            <i class="fas fa-eye"></i>
        </button>
        
        <!-- EDITAR (CYAN) - COM PERMISSÃO -->
        @can('invoicing.imports.edit')
        <button wire:click="openEditModal({{ $import->id }})" 
                class="p-2 text-cyan-600 hover:bg-cyan-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                title="Editar">
            <i class="fas fa-edit"></i>
        </button>
        @endcan
        
        <!-- IMPRIMIR (VERDE) -->
        <button wire:click="printImport({{ $import->id }})" 
                class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                title="Imprimir">
            <i class="fas fa-print"></i>
        </button>
        
        <!-- ELIMINAR (VERMELHO) - COM PERMISSÃO -->
        @can('invoicing.imports.delete')
        <button wire:click="confirmDelete({{ $import->id }})" 
                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200 hover:scale-110 transform" 
                title="Eliminar">
            <i class="fas fa-trash-alt"></i>
        </button>
        @endcan
    </div>
</td>
```

**Características:**
- ✅ SEMPRE 4 botões: Visualizar, Editar, Imprimir, Eliminar
- ✅ Cores específicas: azul, cyan, verde, vermelho
- ✅ hover:bg-{cor}-50 (fundo suave)
- ✅ hover:scale-110 (aumenta no hover)
- ✅ @can no Editar e Eliminar
- ✅ p-2 (padding uniforme)
- ✅ rounded-lg
- ✅ title para acessibilidade

---

## 🎨 MODAL CREATE/EDIT PREMIUM {#modal-create}

**ELEMENTO CHAVE:** Gradiente triplo + ícone animado + backdrop blur

```blade
@if($showModal)
<div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl animate-scale-in">
        {{-- Header com gradiente TRIPLO e ícone animado --}}
        <div class="bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 px-6 py-5 rounded-t-2xl relative overflow-hidden">
            <!-- Background animado -->
            <div class="absolute inset-0 bg-gradient-to-r from-cyan-400/20 to-blue-400/20 animate-pulse"></div>
            
            <div class="relative flex items-center justify-between">
                <div class="flex items-center">
                    <!-- ÍCONE ANIMADO -->
                    <div class="bg-white/20 backdrop-blur-sm p-3 rounded-xl mr-3 animate-bounce-slow">
                        <i class="fas fa-{{ $isEditing ? 'edit' : 'plus-circle' }} text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">
                            {{ $isEditing ? 'Editar' : 'Nova' }} Importação
                        </h3>
                        <p class="text-cyan-100 text-xs mt-0.5">
                            <i class="fas fa-info-circle mr-1"></i>
                            {{ $isEditing ? 'Atualizar dados' : 'Registar nova importação' }}
                        </p>
                    </div>
                </div>
                <!-- Botão X para fechar -->
                <button wire:click="closeModal" 
                        class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-lg transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        {{-- Conteúdo --}}
        <div class="p-6 space-y-4">
            <!-- Campos do formulário -->
        </div>
        
        {{-- Footer com botões --}}
        <div class="px-6 pb-6 flex gap-3">
            <button wire:click="closeModal" 
                    class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                Cancelar
            </button>
            <button wire:click="save" 
                    class="flex-1 px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg font-semibold transition">
                <i class="fas fa-save mr-2"></i>Salvar
            </button>
        </div>
    </div>
</div>
@endif
```

**Características:**
- ✅ backdrop-blur-sm
- ✅ animate-fade-in no overlay
- ✅ animate-scale-in na modal
- ✅ Gradiente TRIPLO: from-cyan via-blue to-indigo
- ✅ Background animado com pulse
- ✅ Ícone com animate-bounce-slow
- ✅ Botão X no header
- ✅ Subtítulo explicativo
- ✅ shadow-2xl

---

## 🚨 MODAL DELETE PREMIUM {#modal-delete}

**ELEMENTO CHAVE:** Gradiente vermelho + avisos coloridos

```blade
@if($showDeleteModal && $deletingImport)
<div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl animate-scale-in">
        {{-- Header vermelho animado --}}
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 rounded-t-2xl relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-red-400/20 to-red-600/20 animate-pulse"></div>
            <div class="relative flex items-center">
                <div class="bg-white/20 backdrop-blur-sm p-3 rounded-xl mr-3">
                    <i class="fas fa-exclamation-triangle text-white text-xl animate-bounce-slow"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Confirmar Exclusão</h3>
                    <p class="text-red-100 text-xs mt-0.5">
                        <i class="fas fa-info-circle mr-1"></i>Ação irreversível
                    </p>
                </div>
            </div>
        </div>
        
        {{-- Conteúdo com avisos --}}
        <div class="p-6">
            <!-- Card vermelho com dados -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <p class="text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-question-circle text-red-600 mr-2"></i>
                    Tem certeza que deseja eliminar:
                </p>
                <div class="bg-white border-2 border-red-300 rounded-lg p-3 mt-3">
                    <p class="font-mono text-lg font-bold text-gray-900 text-center">
                        {{ $deletingImport->import_number }}
                    </p>
                </div>
            </div>
            
            <!-- Aviso amarelo -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p class="text-sm text-yellow-800 flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-0.5"></i>
                    <span><strong>Aviso:</strong> Esta ação não pode ser desfeita!</span>
                </p>
            </div>
        </div>
        
        {{-- Botões --}}
        <div class="px-6 pb-6 flex gap-3">
            <button wire:click="closeDeleteModal" 
                    class="flex-1 px-4 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition-all duration-200 hover:scale-105 transform">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button wire:click="deleteImport" 
                    class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-all duration-200 hover:scale-105 transform">
                <i class="fas fa-trash-alt mr-2"></i>Sim, Eliminar
            </button>
        </div>
    </div>
</div>
@endif
```

**Características:**
- ✅ Gradiente vermelho (from-red-600 to-red-700)
- ✅ Ícone triangulo com bounce
- ✅ Card vermelho com borda
- ✅ Aviso amarelo destacado
- ✅ Botões com hover scale 1.05
- ✅ Font-mono no código/número

---

## ⚡ ANIMAÇÕES CSS {#animacoes}

**SEMPRE INCLUIR NO FINAL DO BLADE (DENTRO DA DIV ROOT)**

```css
<style>
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes scale-in {
        from { 
            opacity: 0;
            transform: scale(0.95);
        }
        to { 
            opacity: 1;
            transform: scale(1);
        }
    }
    
    @keyframes bounce-slow {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }
    
    .animate-scale-in {
        animation: scale-in 0.3s ease-out;
    }
    
    .animate-bounce-slow {
        animation: bounce-slow 3s ease-in-out infinite;
    }
    
    .hover\:scale-105:hover {
        transform: scale(1.05);
    }
    
    .hover\:scale-110:hover {
        transform: scale(1.10);
    }
    
    .transition-all {
        transition: all 0.3s ease;
    }
</style>
```

**Uso:**
- `animate-fade-in` → Overlay de modais
- `animate-scale-in` → Corpo da modal
- `animate-bounce-slow` → Ícones principais (header, modais)
- `hover:scale-105` → Botões grandes
- `hover:scale-110` → Botões pequenos (ações)

---

## 🎯 REGRAS IMPORTANTES {#regras}

### ✅ SEMPRE FAZER:

1. **Ícone Animado no Header**
   - ✅ `animate-bounce-slow` no ícone principal
   - ✅ Gradiente no fundo
   - ✅ shadow-lg

2. **Emojis nos Selects**
   - ✅ Emoji no início de cada option
   - ✅ Ícones FontAwesome nos inputs

3. **4 Botões de Ação**
   - ✅ Visualizar (azul)
   - ✅ Editar (cyan) + @can
   - ✅ Imprimir (verde)
   - ✅ Eliminar (vermelho) + @can

4. **Modais Premium**
   - ✅ backdrop-blur-sm
   - ✅ Gradientes triplos
   - ✅ Ícone animado
   - ✅ Botão X no header

5. **Hover Effects**
   - ✅ scale-105 ou scale-110
   - ✅ Backgrounds suaves (bg-{cor}-50)

6. **Permissões**
   - ✅ @can('permission.create') no botão criar
   - ✅ @can('permission.edit') no botão editar
   - ✅ @can('permission.delete') no botão eliminar

7. **Animações**
   - ✅ Incluir CSS no final (dentro da div root)
   - ✅ animate-fade-in nos overlays
   - ✅ animate-scale-in nas modais

### ❌ NUNCA FAZER:

- ❌ Botões sem ícones
- ❌ Selects sem emojis
- ❌ Modais sem blur
- ❌ Ações sem hover effects
- ❌ Ícone principal sem animação
- ❌ Esquecer @can nas ações críticas

---

## 📦 CORES POR AÇÃO (PADRÃO)

```
🔵 Azul (blue-600)     → Visualizar, Informações
🔷 Cyan (cyan-600)     → Editar, Atualizar
🟢 Verde (green-600)   → Imprimir, Sucesso, Aprovar
🔴 Vermelho (red-600)  → Eliminar, Erro, Cancelar
🟡 Amarelo (yellow-600)→ Avisos, Pendentes
🟣 Roxo (purple-600)   → Especiais, Premium
⚫ Cinza (gray-500)    → Cancelar, Desabilitado
```

---

## 🎨 ESTRUTURA COMPLETA DE UM BLADE

```
📁 Module.blade.php
├── 1. Header Animado (ícone bounce)
├── 2. Stats Cards
├── 3. Filtros (ícones + emojis)
├── 4. Tabela
│   └── Ações (4 botões)
├── 5. Modal Create/Edit (gradiente triplo)
├── 6. Modal Delete (vermelho premium)
└── 7. CSS Animations (no final)
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

Ao criar/atualizar um módulo:

- [ ] Ícone com animate-bounce-slow no header
- [ ] Emojis em todos os selects
- [ ] Ícones FontAwesome em todos os inputs
- [ ] 4 botões de ação com cores certas
- [ ] Modal create com gradiente triplo
- [ ] Modal delete com avisos coloridos
- [ ] backdrop-blur-sm nas modais
- [ ] Animações CSS incluídas
- [ ] @can em ações críticas
- [ ] hover:scale em todos os botões
- [ ] Botão atualizar nos filtros
- [ ] Ícone secundário na descrição do header

---

**ESTE É O PADRÃO OFICIAL A SER REPLICADO EM TODOS OS MÓDULOS DO SISTEMA!** 🎨✨
