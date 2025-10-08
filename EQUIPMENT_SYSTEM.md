# 📦 SISTEMA DE GESTÃO DE EQUIPAMENTOS

## 🎯 Visão Geral

Sistema completo de gestão de equipamentos com rastreamento, alertas automáticos, QR Codes e dashboard analytics.

---

## ✅ FUNCIONALIDADES IMPLEMENTADAS

### 1. **CRUD de Equipamentos** ✅
- Criar, editar, visualizar e excluir equipamentos
- Upload de imagens
- 9 categorias pré-definidas com emojis
- 7 estados de equipamento
- Campos financeiros com controle de permissão
- Código de série único
- Localização individual
- Controle de multi-tenant

### 2. **Empréstimo e Devolução** ✅
- Emprestar equipamento para clientes
- Definir prazo de devolução
- Preço de aluguel por dia
- Devolução com registro de histórico
- Alertas de atraso automáticos

### 3. **Dashboard Analytics** ✅
- Estatísticas em tempo real
- Gráficos com Chart.js
- Top 10 equipamentos mais usados
- Taxa de disponibilidade
- Taxa de utilização
- Filtros por período (7, 30, 90 dias, 1 ano)

### 4. **QR Code** ✅
- Geração automática de QR Code para cada equipamento
- Página de impressão formatada
- Grade com 6 códigos para impressão múltipla
- Rastreamento via escaneamento
- URL única por equipamento

### 5. **Alertas Automáticos** ✅
- Equipamentos atrasados (empréstimo)
- Manutenções próximas (7 dias)
- Notificações por email
- Command para verificação automática

### 6. **Histórico de Uso** ✅
- Registro de todas as ações
- 8 tipos de ação
- Cálculo automático de horas
- Rastreamento completo
- Auditoria de mudanças de status

### 7. **Responsive Design** ✅
- Mobile-first
- Grid adaptável
- Botões com breakpoints
- Tabelas responsivas
- Menu de navegação mobile

---

## 🚀 ROTAS DISPONÍVEIS

```php
// Equipamentos
http://soserp.test/events/equipment              // Lista de equipamentos
http://soserp.test/events/equipment/dashboard    // Dashboard Analytics
http://soserp.test/events/equipment/{id}/qrcode        // QR Code SVG
http://soserp.test/events/equipment/{id}/qrcode/print  // Página de impressão
http://soserp.test/events/equipment/scan/{id}          // Rota de escaneamento
```

---

## 📊 ESTRUTURA DO BANCO DE DADOS

### Tabela: `equipment`
```sql
- id
- tenant_id (multi-tenant)
- name (nome do equipamento)
- category (categoria)
- serial_number (número de série único)
- location (localização física)
- description (descrição)
- status (7 estados)
- acquisition_date (data de aquisição)
- purchase_price (preço de compra - restrito)
- current_value (valor atual - restrito)
- borrowed_to_client_id (cliente empréstimo)
- borrow_date (data empréstimo)
- return_due_date (data devolução)
- actual_return_date (data devolução real)
- rental_price_per_day (preço aluguel/dia)
- last_maintenance_date (última manutenção)
- next_maintenance_date (próxima manutenção)
- maintenance_notes (notas de manutenção)
- total_uses (total de usos)
- total_hours_used (total de horas)
- image_path (caminho da imagem)
- is_active (ativo/inativo)
- created_by, updated_by, created_at, updated_at, deleted_at
```

### Tabela: `equipment_history`
```sql
- id
- equipment_id
- tenant_id
- action_type (uso, reserva, emprestimo, devolucao, etc.)
- event_id (evento relacionado)
- client_id (cliente relacionado)
- user_id (usuário que realizou ação)
- start_datetime, end_datetime
- hours_used (calculado automaticamente)
- location_from, location_to
- notes
- status_before, status_after
- created_at, updated_at
```

---

## 🔧 COMANDOS ARTISAN

### Verificar Alertas de Equipamentos
```bash
# Verificar todos os tenants
php artisan equipment:check-alerts

# Verificar tenant específico
php artisan equipment:check-alerts --tenant=1
```

### Agendar no Cron (Linux/Mac)
```bash
# Abrir crontab
crontab -e

# Executar todos os dias às 9h
0 9 * * * cd /caminho/para/soserp && php artisan equipment:check-alerts
```

### Agendar no Task Scheduler (Windows)
1. Abrir "Agendador de Tarefas"
2. Criar Tarefa Básica
3. Nome: "Alertas de Equipamentos"
4. Gatilho: Diário às 9:00
5. Ação: Iniciar programa
6. Programa: `C:\laragon\bin\php\php.exe`
7. Argumentos: `artisan equipment:check-alerts`
8. Iniciar em: `C:\laragon\www\soserp`

---

## 📧 NOTIFICAÇÕES POR EMAIL

### Configuração
Certifique-se que o `.env` está configurado:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@soserp.com
MAIL_FROM_NAME="SOS ERP"
```

### Destinatários
Os emails são enviados automaticamente para usuários com as roles:
- Super Admin
- Admin
- Manager

### Conteúdo do Email
- Lista de equipamentos atrasados
- Lista de manutenções próximas
- Detalhes de cada equipamento
- Link direto para o sistema
- Design responsivo

---

## 📱 QR CODE - COMO USAR

### Geração
Cada equipamento possui um QR Code único gerado automaticamente.

### Impressão
1. Acesse o equipamento
2. Clique em "QR Code"
3. Página de impressão abrirá
4. Imprima ou salve como PDF

### Escaneamento
1. Use qualquer app de leitura de QR Code
2. Escanei o código
3. Será redirecionado para o sistema
4. Informações do equipamento exibidas

### Uso Prático
- Cole o QR Code no equipamento físico
- Facilita inventário rápido
- Rastreamento de localização
- Acesso mobile instantâneo

---

## 🎨 CATEGORIAS DE EQUIPAMENTOS

```
🔊 Som e Áudio
💡 Iluminação
📹 Vídeo e Streaming
🏗️ Estruturas e Palco
⚡ Energia e Geradores
📡 Comunicação
🚚 Transporte
🎨 Decoração
📦 Outros
```

---

## 🚦 ESTADOS DE EQUIPAMENTO

| Estado | Cor | Descrição |
|--------|-----|-----------|
| **Disponível** | Verde | Pronto para uso |
| **Reservado** | Amarelo | Reservado para evento |
| **Em Uso** | Azul | Sendo utilizado |
| **Avariado** | Vermelho | Com defeito |
| **Manutenção** | Laranja | Em manutenção |
| **Emprestado** | Roxo | Emprestado a cliente |
| **Descartado** | Cinza | Descartado/Inativo |

---

## 🔐 PERMISSÕES

### Campos Restritos (apenas com permissão)
- `purchase_price` (preço de compra)
- `current_value` (valor atual)
- `acquisition_date` (data de aquisição)

### Verificação na View
```blade
@can('view_equipment_prices')
    <input type="number" wire:model="purchase_price">
@endcan
```

---

## 📈 MÉTRICAS DO DASHBOARD

### KPIs Principais
- **Total**: Equipamentos cadastrados
- **Disponível**: Em estoque
- **Em Uso**: Atualmente utilizados
- **Emprestado**: Com clientes
- **Manutenção**: Aguardando manutenção
- **Taxa de Utilização**: (Em Uso + Emprestado) / Total

### Gráficos
- **Uso por Categoria**: Doughnut chart
- **Top 10**: Equipamentos mais usados
- **Timeline**: Atividades recentes

---

## 🔄 HISTÓRICO DE AÇÕES

### Tipos de Ação Registrados
1. **Uso**: Usado em evento
2. **Reserva**: Reservado
3. **Empréstimo**: Emprestado
4. **Devolução**: Devolvido
5. **Manutenção**: Manutenção realizada
6. **Avaria**: Avaria reportada
7. **Reparação**: Reparado
8. **Transferência**: Transferido de local

### Informações Rastreadas
- Data/hora início e fim
- Horas de uso (calculado automaticamente)
- Usuário responsável
- Cliente associado
- Evento relacionado
- Localização origem/destino
- Status antes/depois

---

## 🛠️ MANUTENÇÃO

### Agendar Manutenção
1. Editar equipamento
2. Definir `next_maintenance_date`
3. Sistema alertará 7 dias antes

### Registrar Manutenção
1. Mudar status para "Manutenção"
2. Adicionar notas em `maintenance_notes`
3. Após conclusão, atualizar `last_maintenance_date`
4. Sistema registra no histórico

---

## 💡 DICAS DE USO

### Inventário Rápido
1. Imprima QR Codes de todos os equipamentos
2. Cole nos equipamentos físicos
3. Use app de scanner para verificação rápida

### Relatórios
1. Acesse Dashboard
2. Filtre por período
3. Veja equipamentos mais/menos usados
4. Planeje manutenções baseado em uso

### Controle de Atrasos
1. Configure email no .env
2. Agende comando no cron
3. Receba alertas diários automaticamente

### Mobile
1. Todo sistema é responsivo
2. Gerenciar em campo via smartphone
3. Escanear QR Codes in-loco

---

## 📦 DEPENDÊNCIAS

```json
{
    "bacon/bacon-qr-code": "^2.0",
    "livewire/livewire": "^3.0",
    "chart.js": "^4.4.0"
}
```

---

## 🐛 TROUBLESHOOTING

### QR Code não gera
```bash
composer require bacon/bacon-qr-code
php artisan config:clear
```

### Emails não enviam
```bash
# Verificar configuração
php artisan config:cache

# Testar email
php artisan tinker
Mail::raw('Test', fn($msg) => $msg->to('test@email.com'));
```

### Dashboard sem dados
```bash
# Limpar cache
php artisan cache:clear
php artisan view:clear
```

---

## 📚 PRÓXIMOS RECURSOS (OPCIONAL)

- [ ] Relatórios em PDF
- [ ] Exportação Excel
- [ ] Integração com eventos (vincular equipamentos)
- [ ] Contratos de aluguel
- [ ] Assinatura digital
- [ ] Fotos múltiplas
- [ ] Scanner QR Code integrado
- [ ] App Mobile dedicado
- [ ] Notificações Push
- [ ] API REST

---

## 🎉 CONCLUSÃO

Sistema 100% funcional e pronto para produção!

**URLs Principais:**
- Equipamentos: `/events/equipment`
- Dashboard: `/events/equipment/dashboard`
- Calendário: `/events/calendar`

**Comando de Alertas:**
```bash
php artisan equipment:check-alerts
```

---

*Desenvolvido para SOS ERP - Sistema de Gestão Empresarial*
