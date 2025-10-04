# MyAccount - Estrutura Organizada

Esta pasta contém os componentes parciais da página "Minha Conta" para melhor organização e manutenibilidade.

## Estrutura de Arquivos

```
my-account/
├── README.md                    # Este arquivo
├── companies-tab.blade.php      # Tab de gerenciamento de empresas
├── plan-tab.blade.php          # Tab de visualização de plano
├── profile-tab.blade.php       # Tab de perfil do usuário
└── create-company-modal.blade.php  # Modal de criação de empresa
```

## Componentes

### 1. companies-tab.blade.php
**Propósito:** Exibe e gerencia as empresas do usuário

**Funcionalidades:**
- Status do limite de empresas do plano
- Barra de progresso de uso
- Lista de todas as empresas
- Botão "Criar Nova Empresa" (respeitando limite)
- Indicação de empresa ativa
- Bloqueio de empresas fora do limite

### 2. plan-tab.blade.php
**Propósito:** Visualização do plano ativo

**Funcionalidades:**
- Card do plano atual com detalhes
- Pedidos pendentes de aprovação
- Lista de recursos incluídos
- Botões de upgrade e faturas

### 3. profile-tab.blade.php
**Propósito:** Perfil do usuário

**Funcionalidades:**
- Informações básicas (nome, email)
- Último login
- Botão de edição

### 4. create-company-modal.blade.php
**Propósito:** Modal para criação de nova empresa

**Funcionalidades:**
- Formulário completo de criação
- Validação de campos
- Informações sobre limite do plano
- **Replica automaticamente:**
  - Subscription do tenant atual
  - Módulos ativos
  - Mesmo status e ciclo de cobrança

## Inclusão no Arquivo Principal

No arquivo `my-account.blade.php`, os componentes são incluídos assim:

```blade
@if($activeTab === 'companies')
    @include('livewire.my-account.companies-tab')
@endif

@if($activeTab === 'plan')
    @include('livewire.my-account.plan-tab')
@endif

@if($activeTab === 'profile')
    @include('livewire.my-account.profile-tab')
@endif

@include('livewire.my-account.create-company-modal')
```

## Lógica de Replicação

Quando uma nova empresa é criada:

1. **Tenant Atual**
   - Busca tenant ativo do usuário
   - Busca subscription ativa
   - Lista módulos ativos

2. **Nova Empresa Criada Com:**
   - **Subscription idêntica:**
     - Mesmo plano
     - Mesmo status (active/trial)
     - Mesmo ciclo (monthly/yearly)
     - Mesmo valor
     - Novas datas (início: agora, fim: +1 mês/ano)
   
   - **Módulos ativos:**
     - Todos os módulos da empresa atual
     - Ativados automaticamente

3. **Usuário:**
   - Vinculado como Admin (role_id: 2)
   - Acesso imediato

## Validações

- Limite de empresas do plano
- NIF único
- Campos obrigatórios
- Transação SQL segura (rollback em erro)

## Benefícios da Organização

✅ Código mais limpo e legível
✅ Fácil manutenção individual
✅ Evita quebras de sintaxe em arquivos grandes
✅ Separação clara de responsabilidades
✅ Facilita testes e debugging
