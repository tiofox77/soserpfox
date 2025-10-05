# 🦊 FOX Friendly Easter Eggs - Documentação

## 🎯 Visão Geral

Sistema de Easter Eggs sutis e animados para usuários do plano **FOX Friendly**, criando uma experiência especial sem incomodar a navegação.

---

## 🎨 Locais dos Easter Eggs

### 1. **Plano FOX Friendly** 🦊
**Arquivo:** `app/Console/Commands/CreateFoxFriendlyPlan.php`

```php
'name' => '🦊 FOX Friendly',
'description' => '🦊 Plano promocional com 6 meses grátis!'
```

**Visual:**
- Ícone de raposa no nome do plano
- Aparece em todas as listagens de planos

---

### 2. **Sidebar - Raposa Flutuante** 🦊 (Principal)
**Arquivo:** `resources/views/layouts/app.blade.php`

**Localização:** Rodapé da sidebar, acima do User Menu

**Características:**
```
┌──────────────────────┐
│                      │
│   [Menu Items]       │
│                      │
├──────────────────────┤
│        🦊           │ ← Easter Egg
│   [Flutua suavemente]│
├──────────────────────┤
│   👤 User Menu       │
└──────────────────────┘
```

**Funcionalidades:**
- ✨ Animação de flutuação suave (3s loop)
- 🔍 Ao passar o mouse: cresce 25%
- 💬 Tooltip aparece: "🦊 FOX Friendly Active!"
- 📊 Mostra: "6 meses grátis • Todos os módulos"
- 🎨 Background gradient laranja/vermelho

**Código:**
```blade
<div x-data="{ foxHover: false }">
    <div class="text-3xl" 
         style="animation: foxFloat 3s ease-in-out infinite;">
        🦊
    </div>
</div>
```

---

### 3. **Header - Pegadas da Raposa** 🐾
**Arquivo:** `resources/views/layouts/app.blade.php`

**Localização:** Barra superior, próximo ao Tenant Switcher

**Características:**
```
Header: [...Tenant Switcher] [🐾] [Notificações] [Busca]
```

**Funcionalidades:**
- 🐾 Ícone de pegada que balança (wiggle animation)
- 🔄 Rotação suave: -5° ↔ 5°
- 💬 Tooltip ao hover: "🦊 FOX Power!"
- ⏱️ Animação de 2s em loop

**Código:**
```css
@keyframes foxWiggle {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-5deg); }
    75% { transform: rotate(5deg); }
}
```

---

### 4. **Dashboard - Banner de Boas-Vindas** 🎉
**Arquivo:** `resources/views/home.blade.php`

**Localização:** Topo do dashboard, primeira coisa visível

**Características:**
```
┌──────────────────────────────────────────────────┐
│  🦊  🎉 FOX Friendly Ativo! [6 meses GRÁTIS] [X]│
│                                                  │
│  Você tem acesso completo e ilimitado a todos   │
│  os módulos do sistema! 🚀                       │
│                                                  │
│  ✓ 999 utilizadores  ✓ 100GB  ✓ Todos módulos  │
│                                          🦊      │
└──────────────────────────────────────────────────┘
```

**Funcionalidades:**
- 🦊 Raposa grande saltando (bounce animation, 2s)
- 🌈 Gradient laranja → vermelho → rosa
- 🦊 Raposa grande em fundo (opacidade 20%, flutuando)
- ❌ Botão X para fechar (com Alpine.js)
- ✨ Fade in/out suave ao abrir/fechar
- 📱 Responsivo (raposa de fundo oculta em mobile)

---

## 🎬 Animações CSS

### 1. **foxFloat** - Flutuação Suave
```css
@keyframes foxFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}
```
**Uso:** Sidebar (raposa) e Dashboard (raposa de fundo)

### 2. **foxWiggle** - Balanço Rotativo
```css
@keyframes foxWiggle {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-5deg); }
    75% { transform: rotate(5deg); }
}
```
**Uso:** Header (pegadas)

### 3. **bounce** - Salto (Tailwind nativo)
```html
<div class="animate-bounce" style="animation-duration: 2s;">🦊</div>
```
**Uso:** Dashboard (raposa grande)

---

## 🎛️ Controle de Exibição

### Condição de Ativação:
```php
@php
    $tenant = auth()->user()->activeTenant();
    $subscription = $tenant ? $tenant->activeSubscription : null;
    $plan = $subscription ? $subscription->plan : null;
    $isFoxFriendly = $plan && str_contains(strtolower($plan->slug), 'fox');
@endphp

@if($isFoxFriendly)
    <!-- Easter Eggs aparecem aqui -->
@endif
```

**Verificação:**
- ✅ Usuário está logado
- ✅ Tem tenant ativo
- ✅ Tenant tem subscription ativa
- ✅ Plano contém "fox" no slug

---

## 📍 Mapa Visual

```
┌─────────────────────────────────────────────────────────┐
│  HEADER                                                 │
│  [Logo] [Tenant] [🐾 FOX] [🔔] [🔍]                    │
├───────────┬─────────────────────────────────────────────┤
│ SIDEBAR   │  DASHBOARD (home.blade.php)                 │
│           │                                             │
│  Home     │  ┌──────────────────────────────────────┐  │
│  Fatura   │  │ 🦊 FOX Friendly Banner [GRÁTIS] [X] │  │
│  Tesoura  │  └──────────────────────────────────────┘  │
│  POS      │                                             │
│  ...      │  [Cards do Dashboard]                      │
│           │  [Estatísticas]                            │
│ ───────── │  [Gráficos]                                │
│    🦊     │  [...]                                     │
│ [Flutua]  │                                             │
│ ───────── │                                             │
│ 👤 User   │                                             │
└───────────┴─────────────────────────────────────────────┘
```

---

## 🎨 Paleta de Cores FOX

```css
- Laranja: #f97316 (orange-500)
- Vermelho: #ef4444 (red-500)
- Rosa: #ec4899 (pink-500)
- Gradientes: from-orange-400 via-red-400 to-pink-400
```

---

## 🧪 Como Testar

### 1. Criar Plano FOX Friendly:
```bash
php artisan create:fox-friendly-plan
```

### 2. Atribuir Plano a Tenant:
```bash
php artisan tinker
>>> $tenant = Tenant::find(1);
>>> $plan = Plan::where('slug', 'fox-friendly')->first();
>>> $tenant->subscriptions()->create([
    'plan_id' => $plan->id,
    'status' => 'trial',
    'billing_cycle' => 'monthly',
    'trial_ends_at' => now()->addMonths(6),
]);
```

### 3. Acessar como Usuário:
```
http://soserp.test/home
```

### 4. Verificar Easter Eggs:
- ✅ Banner no topo do dashboard
- ✅ Raposa flutuando na sidebar
- ✅ Pegadas no header
- ✅ Todas animações funcionando

---

## 📦 Arquivos Modificados

```
✅ app/Console/Commands/CreateFoxFriendlyPlan.php
   - Nome do plano com ícone 🦊

✅ resources/views/layouts/app.blade.php
   - Raposa flutuante na sidebar (linha ~636)
   - Pegadas no header (linha ~747)
   - Animações CSS (linha ~180-204)

✅ resources/views/home.blade.php
   - Banner de boas-vindas (linha ~39-92)
```

---

## 🎯 Princípios de Design

### ✨ Não-Intrusivo
- Easter eggs são **sutis** e **discretos**
- Não bloqueiam conteúdo importante
- Não interferem na navegação
- Podem ser fechados (banner)

### 🎨 Visualmente Agradável
- Animações **suaves** (3s, ease-in-out)
- Cores **vibrantes** mas não agressivas
- Tooltips aparecem apenas ao hover
- Transições fade-in/out

### ⚡ Performance
- Animações CSS puras (não JavaScript)
- Alpine.js para interatividade mínima
- Sem impacto na velocidade

### 📱 Responsivo
- Raposa de fundo oculta em mobile
- Tooltips adaptam posição
- Banner ajusta em telas pequenas

---

## 🚀 Expansões Futuras

### Ideias para Mais Easter Eggs:
- 🦊 Raposa correndo no footer
- 🌙 Easter egg à meia-noite (fox dormindo)
- 🎂 Easter egg no aniversário de conta
- 🏆 Conquistas "Fox Hunter"
- 🎲 Easter egg aleatório (1% chance por dia)
- 🔊 Som de raposa ao clicar (opcional)

---

## 🎉 Resumo

Sistema completo de Easter Eggs da Raposa para o plano **FOX Friendly**:

- 🦊 **4 localizações** estratégicas
- 🎨 **3 animações** CSS customizadas
- ✨ **100% não-intrusivo**
- 🚀 **Performance otimizada**
- 📱 **Totalmente responsivo**

**Easter Eggs são uma forma divertida de criar conexão emocional com usuários especiais!** 🦊✨
