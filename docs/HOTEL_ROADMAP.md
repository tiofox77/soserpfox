# 🏨 Roadmap - Módulo de Gestão de Hotel

> Sistema completo de gestão hoteleira com booking online, housekeeping, POS integrado e analytics.

---

## 📊 Visão Geral do Módulo

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        GESTÃO DE HOTEL - SOSERP                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  FRONT      │  │  BACK       │  │  BOOKING    │  │  ANALYTICS  │        │
│  │  OFFICE     │  │  OFFICE     │  │  ENGINE     │  │  & REPORTS  │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│        │                │                │                │                 │
│        ▼                ▼                ▼                ▼                 │
│  ┌─────────────────────────────────────────────────────────────────┐       │
│  │                    BASE DE DADOS CENTRAL                        │       │
│  │  Quartos | Reservas | Hóspedes | Pagamentos | Inventário       │       │
│  └─────────────────────────────────────────────────────────────────┘       │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Fases de Desenvolvimento

### ✅ FASE 1: Core (CONCLUÍDO)
> Funcionalidades básicas do sistema

| Feature | Status | Descrição |
|---------|--------|-----------|
| Dashboard | ✅ | Estatísticas, check-ins/outs do dia, mapa de quartos |
| Tipos de Quarto | ✅ | CRUD completo com amenities e preços |
| Quartos | ✅ | Gestão de quartos com status e características |
| Hóspedes | ✅ | Cadastro de hóspedes com histórico |
| Reservas | ✅ | CRUD com fluxo de status |
| Check-in/Check-out | ✅ | Processo básico |
| Booking Online | ✅ | Página pública para reservas |

---

### 🔄 FASE 2: Front Office Avançado
> Operações diárias da recepção

#### 2.1 Gestão de Reservas Avançada
- [x] **Calendário Visual de Reservas** ✅
  - Vista mensal/semanal com navegação
  - Cores por status de reserva (pendente, confirmada, check-in, check-out)
  - Visualização por tipo de quarto com filtros
  - Reserva rápida clicando no dia/quarto
  - Modal de detalhes com ações rápidas (confirmar, check-in, check-out, cancelar)
  - Timeline Gantt-style com reservas por quarto
  
- [ ] **Overbooking Controlado**
  - Configurar % de overbooking permitido
  - Alertas automáticos
  - Gestão de lista de espera

- [ ] **Reservas de Grupo**
  - Múltiplos quartos numa reserva
  - Desconto por volume
  - Rooming list
  - Faturação consolidada

- [ ] **Reservas Recorrentes**
  - Hóspedes frequentes com reserva automática
  - Tarifas corporativas

#### 2.2 Check-in/Check-out Avançado
- [ ] **Check-in Expresso**
  - QR Code para self check-in
  - Pré-registo online
  - Assinatura digital
  
- [x] **Check-out com Faturação** ✅
  - Resumo de consumos
  - Split de conta (dividir entre hóspedes)
  - Envio de fatura por email
  
- [ ] **Early Check-in / Late Check-out**
  - Configuração de taxas
  - Disponibilidade automática
  
- [x] **Walk-in Rápido** ✅
  - Formulário simplificado
  - Atribuição automática de quarto

#### 2.3 Gestão de Hóspedes Avançada
- [ ] **Perfil Completo do Hóspede**
  - Preferências (tipo de almofada, andar, vista)
  - Alergias alimentares
  - Histórico de estadias
  - Gastos totais (lifetime value)
  
- [ ] **Programa de Fidelidade**
  - Pontos por estadia
  - Níveis (Bronze, Silver, Gold, Platinum)
  - Upgrades automáticos
  - Benefícios por nível
  
- [ ] **Comunicação com Hóspede**
  - SMS de confirmação
  - Email pré-chegada
  - Pesquisa de satisfação pós-estadia
  - WhatsApp integrado

---

### 🔧 FASE 3: Back Office
> Operações internas e housekeeping

#### 3.1 Housekeeping
- [x] **Dashboard de Housekeeping** ✅
  - Lista de quartos para limpar (vista Kanban)
  - Priorização automática (check-out → check-in)
  - Status em tempo real por quarto
  - Vista de quartos por andar
  - Auto geração de tarefas baseada em check-outs
  
- [x] **Gestão de Tarefas** ✅
  - Atribuição de quartos por funcionário
  - Checklists de limpeza dinâmicos por tipo
  - Tempo estimado vs real
  - Progresso visual do checklist
  
- [ ] **Inspeção de Quartos**
  - Checklist de inspeção
  - Registo de danos/avarias
  - Criação automática de ordem de manutenção
  
- [ ] **Turnos e Escalas**
  - Gestão de turnos de limpeza
  - Relatório de produtividade

#### 3.2 Manutenção
- [x] **Ordens de Manutenção** ✅
  - Preventiva vs Corretiva
  - Prioridade (urgente, normal, baixa)
  - Atribuição a técnicos
  - Histórico por quarto
  
- [ ] **Inventário de Manutenção**
  - Stock de peças
  - Alertas de stock mínimo
  - Custos por intervenção
  
- [ ] **Manutenção Preventiva**
  - Calendário de manutenções
  - Ar condicionado, TV, frigobar
  - Alertas automáticos

#### 3.3 Lavandaria
- [ ] **Gestão de Roupa**
  - Controlo de roupa de cama
  - Envio para lavandaria externa
  - Custos e inventário
  
- [ ] **Lavandaria de Hóspedes**
  - Serviço de lavagem de roupa
  - Preços e prazos
  - Cobrança na conta do quarto

---

### 💰 FASE 4: Revenue Management
> Maximização de receita

#### 4.1 Tarifas Dinâmicas
- [x] **Rate Manager** ✅
  - Preços por época (alta, média, baixa)
  - Preços por dia da semana
  - Preços por antecedência de reserva
  - Preços por ocupação
  
- [x] **Pacotes e Promoções** ✅
  - Pacote romântico (quarto + jantar + spa)
  - Desconto para estadias longas
  - Early bird discount
  - Last minute deals
  
- [x] **Códigos Promocionais** ✅
  - Cupões de desconto
  - Códigos corporativos
  - Rastreamento de campanhas

#### 4.2 Channel Manager
- [ ] **Integração com OTAs**
  - Booking.com
  - Expedia
  - Airbnb
  - Hotéis.com
  
- [ ] **Sincronização de Disponibilidade**
  - Inventário único
  - Atualização em tempo real
  - Evitar overbooking

#### 4.3 Yield Management
- [ ] **Forecasting**
  - Previsão de ocupação
  - Análise de tendências
  - Recomendações de preço
  
- [ ] **Competitive Intelligence**
  - Monitorização de preços da concorrência
  - Alertas de mudança de preço

---

### 🍽️ FASE 5: POS e Serviços
> Pontos de venda e serviços adicionais

#### 5.1 Restaurante/Bar
- [ ] **POS de Restaurante**
  - Mesas e pedidos
  - Menu digital
  - Integração com cozinha
  - Cobrança na conta do quarto
  
- [ ] **Room Service**
  - Menu disponível
  - Pedidos por telefone/app
  - Tracking de entrega
  
- [ ] **Minibar**
  - Controlo de consumo
  - Reposição automática
  - Preços configuráveis

#### 5.2 Spa e Wellness
- [ ] **Agendamento de Tratamentos**
  - Calendário de disponibilidade
  - Terapeutas
  - Pacotes de spa
  
- [ ] **Ginásio**
  - Controlo de acesso
  - Personal trainer

#### 5.3 Outros Serviços
- [ ] **Transfer/Transporte**
  - Aeroporto
  - City tours
  - Aluguer de viaturas
  
- [ ] **Business Center**
  - Salas de reunião
  - Equipamento
  - Reservas por hora/dia
  
- [ ] **Parking**
  - Gestão de vagas
  - Valet parking
  - Cobrança

---

### 📊 FASE 6: Analytics e Relatórios
> Business Intelligence

#### 6.1 Relatórios Operacionais
- [x] **Relatório de Ocupação** ✅
  - Por período
  - Por tipo de quarto
  - RevPAR, ADR, ocupação %
  
- [x] **Relatório de Receita** ✅
  - Por departamento
  - Por fonte de reserva
  - Por nacionalidade
  
- [x] **Relatório de Hóspedes** ✅
  - Origem geográfica
  - Tempo médio de estadia
  - Repeat guests

#### 6.2 Dashboards Executivos
- [ ] **KPIs em Tempo Real**
  - Ocupação atual
  - Receita do dia/mês
  - Comparativo com período anterior
  
- [ ] **Forecast Dashboard**
  - Previsão de ocupação
  - Receita prevista
  - Alertas

#### 6.3 Relatórios Legais
- [ ] **SEF (Serviço de Estrangeiros)**
  - Boletim de alojamento
  - Envio automático
  
- [ ] **INE (Instituto Nacional de Estatística)**
  - Dados estatísticos obrigatórios
  
- [ ] **Relatório Fiscal**
  - SAFT
  - IVA

---

### 📱 FASE 7: Mobile e Self-Service
> Experiência digital do hóspede

#### 7.1 App do Hóspede
- [ ] **Check-in Mobile**
  - Pré-registo
  - Upload de documentos
  - Chave digital (integração com fechaduras)
  
- [ ] **Serviços no App**
  - Room service
  - Housekeeping on-demand
  - Pedidos especiais
  - Chat com recepção
  
- [ ] **Feedback e Reviews**
  - Avaliação durante estadia
  - Integração com TripAdvisor/Google

#### 7.2 Quiosques Self-Service
- [ ] **Check-in Kiosk**
  - Leitura de documento
  - Pagamento
  - Dispensa de chave
  
- [ ] **Check-out Kiosk**
  - Revisão de conta
  - Pagamento
  - Entrega de chave

#### 7.3 Smart Room
- [ ] **Controlo de Quarto**
  - Luzes
  - Ar condicionado
  - TV/Entretenimento
  - Cortinas
  
- [ ] **Pedidos por Voz**
  - Integração com Alexa/Google

---

### 🔌 FASE 8: Integrações
> Conectividade com sistemas externos

#### 8.1 Pagamentos
- [ ] **Gateway de Pagamentos**
  - Multicaixas Express
  - Visa/Mastercard
  - PayPal
  - Crypto (opcional)
  
- [ ] **POS Físico**
  - Integração com TPA
  - Faturas automáticas

#### 8.2 Contabilidade
- [ ] **Integração com Módulo Contabilidade**
  - Lançamentos automáticos
  - Reconciliação
  - Exportação SAFT

#### 8.3 Externos
- [ ] **Fechaduras Eletrónicas**
  - ASSA ABLOY
  - Onity
  - Salto
  
- [ ] **PBX/Telefonia**
  - Chamadas por quarto
  - Wake-up calls
  - Cobrança automática
  
- [ ] **TV Interativa**
  - Welcome message
  - Menu de serviços
  - Checkout pela TV

---

## 📅 Cronograma Sugerido

| Fase | Duração Estimada | Prioridade |
|------|------------------|------------|
| Fase 1: Core | ✅ Concluído | Alta |
| Fase 2: Front Office | 3-4 semanas | Alta |
| Fase 3: Back Office | 2-3 semanas | Alta |
| Fase 4: Revenue | 2-3 semanas | Média |
| Fase 5: POS e Serviços | 3-4 semanas | Média |
| Fase 6: Analytics | 2 semanas | Média |
| Fase 7: Mobile | 4-6 semanas | Baixa |
| Fase 8: Integrações | Contínuo | Variável |

---

## 🗄️ Estrutura de Base de Dados Completa

```
hotel_room_types          ✅ Tipos de quarto
hotel_rooms               ✅ Quartos
hotel_guests              ✅ Hóspedes
hotel_reservations        ✅ Reservas
hotel_reservation_items   ✅ Itens da reserva
hotel_settings            ✅ Configurações

hotel_rates               📋 Tarifas dinâmicas
hotel_rate_seasons        📋 Épocas/temporadas
hotel_packages            📋 Pacotes promocionais
hotel_promo_codes         📋 Códigos promocionais

hotel_housekeeping_tasks  📋 Tarefas de limpeza
hotel_maintenance_orders  📋 Ordens de manutenção
hotel_room_inspections    📋 Inspeções

hotel_pos_orders          📋 Pedidos POS
hotel_pos_items           📋 Itens de pedido
hotel_minibar_consumptions 📋 Consumos minibar

hotel_loyalty_programs    📋 Programas de fidelidade
hotel_loyalty_points      📋 Pontos acumulados
hotel_loyalty_tiers       📋 Níveis de fidelidade

hotel_channel_mappings    📋 Mapeamento de canais OTA
hotel_channel_bookings    📋 Reservas de canais

hotel_guest_communications 📋 Comunicações com hóspedes
hotel_reviews             📋 Avaliações
hotel_guest_preferences   📋 Preferências
```

---

## 🎨 Screenshots Esperados

### Dashboard Principal
```
┌────────────────────────────────────────────────────────────────┐
│  🏨 Hotel Dashboard                              📅 01/12/2025 │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
│  │ 🛏️ 45    │ │ 🔴 32    │ │ 📊 71%   │ │ 💰 2.5M  │          │
│  │Disponível│ │ Ocupados │ │ Ocupação │ │ Receita  │          │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘          │
│                                                                │
│  ┌─────────────────────────────┐ ┌─────────────────────────┐  │
│  │ CHECK-INS HOJE (8)          │ │ CHECK-OUTS HOJE (5)     │  │
│  │ • João Silva - Suite 201    │ │ • Maria Santos - 105    │  │
│  │ • Ana Costa - Deluxe 302    │ │ • Pedro Lima - 203      │  │
│  │ • ...                       │ │ • ...                   │  │
│  └─────────────────────────────┘ └─────────────────────────┘  │
│                                                                │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ MAPA DE QUARTOS                                         │  │
│  │ ┌───┐┌───┐┌───┐┌───┐┌───┐┌───┐┌───┐┌───┐┌───┐┌───┐    │  │
│  │ │101││102││103││104││105││106││107││108││109││110│ 1º  │  │
│  │ │ 🟢││ 🔴││ 🟢││ 🟡││ 🔴││ 🔴││ 🟢││ 🔵││ 🔴││ 🟢│    │  │
│  │ └───┘└───┘└───┘└───┘└───┘└───┘└───┘└───┘└───┘└───┘    │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                │
│  🟢 Disponível  🔴 Ocupado  🟡 Manutenção  🔵 Limpeza         │
└────────────────────────────────────────────────────────────────┘
```

### Calendário de Reservas
```
┌────────────────────────────────────────────────────────────────┐
│  📅 Calendário de Reservas                    ◀ Dezembro ▶    │
├────────────────────────────────────────────────────────────────┤
│       │ 01 │ 02 │ 03 │ 04 │ 05 │ 06 │ 07 │ 08 │ 09 │ 10 │    │
│───────┼────┼────┼────┼────┼────┼────┼────┼────┼────┼────┤    │
│ S.101 │████████████│    │    │████████████████│    │    │    │
│ S.102 │    │████████████████████│    │    │████│    │    │    │
│ D.201 │████│    │████████████████████████│    │    │    │    │
│ D.202 │    │    │    │████████████│    │████████████│    │    │
│ STD01 │████████████████████│    │    │████████████████│    │    │
└────────────────────────────────────────────────────────────────┘
```

---

## 🚀 Próximos Passos Imediatos

1. ~~**Calendário Visual de Reservas**~~ ✅ - Vista de calendário timeline implementada
2. ~~**Housekeeping Dashboard**~~ ✅ - Dashboard com gestão de tarefas e checklists
3. ~~**Tarifas por Época**~~ ✅ - Sistema de preços dinâmicos
4. ~~**Relatórios Básicos**~~ ✅ - Ocupação, receita, hóspedes
5. ~~**Ordens de Manutenção**~~ ✅ - Preventiva/corretiva, atribuição a técnicos
6. ~~**Walk-in Rápido**~~ ✅ - Check-in imediato sem reserva
7. ~~**Check-out com Faturação**~~ ✅ - Resumo de consumos e emissão de fatura
8. **Comunicação por Email** - Confirmações e lembretes automáticos
9. ~~**Pacotes e Promoções**~~ ✅ - Descontos e pacotes especiais
10. **Inspeção de Quartos** - Checklist de inspeção e registo de danos

---

> 📝 **Nota**: Este roadmap é um guia. As funcionalidades podem ser priorizadas conforme as necessidades do cliente.

**Última atualização**: 11/12/2025
