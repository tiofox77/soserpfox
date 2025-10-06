# 📅 Módulo de Gestão de Eventos

## 📋 Visão Geral

O Módulo de Gestão de Eventos foi criado para empresas que prestam serviços de montagem e gestão de eventos, incluindo:
- 🎬 Equipamentos de som, vídeo e iluminação
- 📺 Telas e painéis LED
- 📡 Streaming e transmissão ao vivo
- 🏗️ Montagem e desmontagem de estruturas
- 👥 Gestão de equipe técnica

---

## 🎯 Funcionalidades Principais

### 1. **Dashboard de Eventos**
- 📊 Estatísticas em tempo real
- 📅 Próximos eventos
- ⚡ Status de equipamentos
- 📈 Visão geral mensal

### 2. **Gestão de Eventos**
- ✅ Criar, editar e excluir eventos
- 📝 Tipos: Corporativo, Casamento, Conferência, Show, Streaming
- 🗓️ Datas de montagem, evento e desmontagem
- 👥 Vinculação com clientes
- 📍 Vinculação com locais
- 💰 Controle de valor total
- 📋 Status do evento (Orçamento → Confirmado → Em Montagem → Em Andamento → Concluído)

### 3. **Equipamentos**
- 🎤 Categorias: Áudio, Vídeo, Iluminação, Streaming, LED, Estrutura
- 💵 Preço de diária
- 📦 Controle de quantidade disponível
- ⚙️ Status: Disponível, Em Uso, Manutenção, Danificado
- 🏷️ Código de identificação

### 4. **Locais de Eventos**
- 📍 Cadastro de salas e locais
- 👥 Capacidade de público
- 📱 Contatos responsáveis
- 🗺️ Endereço completo

### 5. **Equipe Técnica**
- 👨‍💼 Atribuição de colaboradores
- 🎭 Funções: Coordenador, Técnico de Áudio, Técnico de Vídeo, Operador de Streaming, Assistente
- ⏰ Horários de trabalho
- 💰 Custos por função

### 6. **Checklist do Evento**
- ✅ Tarefas pendentes
- 📝 Atribuição de responsáveis
- ⏱️ Prazos
- ✔️ Marcação de conclusão

---

## 🗄️ Estrutura do Banco de Dados

### Tabelas Criadas:

1. **`events_venues`** - Locais de eventos
2. **`events_equipment`** - Equipamentos disponíveis
3. **`events_events`** - Eventos cadastrados
4. **`events_event_equipment`** - Equipamentos alocados por evento
5. **`events_event_staff`** - Equipe técnica por evento
6. **`events_checklists`** - Checklists de tarefas

---

## 📂 Estrutura de Arquivos

### **Models:**
```
app/Models/Events/
├── Event.php              # Modelo principal de eventos
├── Equipment.php          # Equipamentos
├── Venue.php             # Locais
├── EventEquipment.php    # Pivot: evento-equipamento
├── EventStaff.php        # Pivot: evento-equipe
└── Checklist.php         # Checklists de tarefas
```

### **Livewire Components:**
```
app/Livewire/Events/
├── Dashboard.php                          # Dashboard principal
├── EventsManager.php                      # Gestão de eventos
├── Equipment/
│   └── EquipmentManager.php              # Gestão de equipamentos
└── Venues/
    └── VenuesManager.php                 # Gestão de locais
```

### **Views:**
```
resources/views/livewire/events/
├── dashboard.blade.php                    # Dashboard
├── events-manager.blade.php               # Lista e form de eventos
├── equipment/
│   └── equipment-manager.blade.php       # Gestão de equipamentos
└── venues/
    └── venues-manager.blade.php          # Gestão de locais
```

---

## 🚀 Como Usar

### **1. Ativar o Módulo**

No **Super Admin** → **Módulos**:
- Ativar o módulo "Gestão de Eventos"
- Vincular aos planos desejados

### **2. Rodar a Migration**

```bash
php artisan migrate
php artisan db:seed --class=ModuleSeeder
```

### **3. Acessar o Módulo**

No menu lateral, clique em:
- **📅 Eventos** → Dashboard

---

## 📊 Fluxo de Trabalho

### **Passo 1: Cadastrar Pré-requisitos**

1. **Cadastrar Locais**
   - Eventos → Locais → Novo Local
   - Preencher nome, endereço, capacidade

2. **Cadastrar Equipamentos**
   - Eventos → Equipamentos → Novo Equipamento
   - Definir categoria, preço diária, quantidade

3. **Cadastrar Clientes**
   - (Usar o módulo de Faturação)

### **Passo 2: Criar um Evento**

1. **Criar Evento**
   - Eventos → Eventos → Novo Evento
   - Preencher informações básicas
   - Selecionar cliente e local
   - Definir datas

2. **Adicionar Equipamentos**
   - Selecionar equipamentos necessários
   - Definir quantidade e dias de uso

3. **Montar Equipe**
   - Atribuir colaboradores
   - Definir funções e horários

4. **Criar Checklist**
   - Adicionar tarefas
   - Atribuir responsáveis
   - Definir prazos

### **Passo 3: Execução**

1. **Montagem**
   - Atualizar status para "Em Montagem"
   - Marcar tarefas do checklist

2. **Evento em Andamento**
   - Atualizar status para "Em Andamento"

3. **Desmontagem e Conclusão**
   - Atualizar status para "Concluído"
   - Verificar equipamentos retornados

---

## 🎨 Cores e Ícones

### **Status dos Eventos:**
- 🟤 **Orçamento** - Cinza
- 🔵 **Confirmado** - Azul
- 🟡 **Em Montagem** - Amarelo
- 🟢 **Em Andamento** - Verde
- ✅ **Concluído** - Verde
- 🔴 **Cancelado** - Vermelho

### **Categorias de Equipamentos:**
- 🎤 **Áudio** - Microfones, caixas de som, mesa de som
- 📹 **Vídeo** - Câmeras, projetores, monitores
- 💡 **Iluminação** - Luzes, spots, moving heads
- 📡 **Streaming** - Encoders, computadores, switchers
- 💠 **LED** - Painéis LED, telas
- 🏗️ **Estrutura** - Treliças, praticáveis, grades

---

## 📈 Relatórios Futuros (Roadmap)

- 📊 Relatório de faturamento por evento
- 📅 Calendário visual de eventos
- 📦 Histórico de uso de equipamentos
- 💰 Análise de lucratividade
- 👥 Performance da equipe
- 📈 Gráficos de ocupação mensal

---

## 🔒 Permissões

O módulo utiliza o sistema de permissões do Laravel. Permissões sugeridas:

- `events.dashboard.view`
- `events.events.view`
- `events.events.create`
- `events.events.edit`
- `events.events.delete`
- `events.equipment.view`
- `events.equipment.manage`
- `events.venues.view`
- `events.venues.manage`

---

## 🛠️ Integrações

### **Com Faturação:**
- Vinculação de clientes
- Geração de orçamentos e faturas
- Controle de pagamentos

### **Com Tesouraria:**
- Registro de despesas
- Controle de custos de equipe

---

## 📝 Notas de Desenvolvimento

### **Padrões Seguidos:**
- ✅ Traits `BelongsToTenant` em todos os models
- ✅ SoftDeletes nos models principais
- ✅ Validação em todos os formulários
- ✅ Paginação nas listagens
- ✅ Filtros de busca
- ✅ Responsividade mobile
- ✅ Animações suaves
- ✅ Ícones Font Awesome
- ✅ Design moderno com Tailwind CSS

### **Segurança:**
- ✅ Multi-tenancy
- ✅ Middleware de autenticação
- ✅ Proteção CSRF
- ✅ Validação server-side

---

## 🆘 Suporte

Para dúvidas ou problemas:
1. Verificar logs em `storage/logs/laravel.log`
2. Verificar permissões de usuário
3. Verificar se o módulo está ativo para o tenant

---

## ✅ Checklist de Implantação

- [x] Migration criada
- [x] Models criados
- [x] Livewire components criados
- [x] Views criadas
- [x] Rotas adicionadas
- [x] Menu lateral adicionado
- [x] Módulo adicionado no seeder
- [ ] Testes funcionais
- [ ] Documentação de API (se necessário)
- [ ] Treinamento de usuários

---

**✨ O módulo está pronto para uso! ✨**

**Para ativar:**
```bash
php artisan migrate
php artisan db:seed --class=ModuleSeeder
```

**Acesse:** `/events/dashboard`
