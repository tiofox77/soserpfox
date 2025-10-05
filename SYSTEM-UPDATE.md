# 🚀 Sistema de Atualização Automática - SOS ERP

## 📋 Descrição

Comando Artisan para atualização completa do sistema, incluindo:
- ✅ Verificação e execução de migrations
- ✅ Verificação e execução de seeders
- ✅ Limpeza de cache
- ✅ Verificação de integridade da BD
- ✅ Geração de log detalhado

## 🔧 Como Usar

### Modo Normal (Com Menu de Seleção) ⭐ NOVO!
```bash
php artisan system:update
```

O sistema exibirá um menu interativo:

```
┌─────────────────────────────────────────────────┐
│     🎯 MODO DE ATUALIZAÇÃO                      │
└─────────────────────────────────────────────────┘

⚙️  Como deseja executar a atualização?
  [automatic] 🚀 Automático - Executa tudo sem perguntar (recomendado)
  [interactive] ✋ Interativo - Pergunta antes de cada seeder
  [cancel] ❌ Cancelar atualização

> automatic

✅ Modo Automático selecionado
   Todos os seeders novos serão executados automaticamente.

📋 Iniciar atualização agora? (yes/no) [yes]:
```

**Opções disponíveis:**
- **Automático**: Executa tudo sem perguntar (ideal para maioria dos casos)
- **Interativo**: Pergunta antes de executar cada seeder (útil para revisar)
- **Cancelar**: Sair sem fazer nada

### Modo Forçado (Para Automação/CI/CD)
```bash
php artisan system:update --force
```

Pula o menu e executa tudo automaticamente sem confirmações.
**Uso:** Scripts de deploy, CI/CD, automação.

## 📊 O que o Comando Faz

### 1. Verifica Migrations Pendentes
- Lista todas as migrations que ainda não foram executadas
- Mostra quantas estão pendentes

### 2. Executa Migrations
- Executa todas as migrations pendentes
- Atualiza a estrutura da base de dados
- Registra sucesso ou erros

### 3. Verifica e Executa Seeders (APENAS NOVOS)
- **Sistema inteligente:** Rastreia seeders já executados
- **Executa apenas seeders novos** que nunca foram executados
- Lista todos os seeders disponíveis (exceto DatabaseSeeder)
- Verifica tabela `seeders` para saber quais já foram executados
- Modo interativo: pergunta antes de executar cada seeder novo
- Modo force: executa todos os seeders novos automaticamente
- **Registra cada seeder executado** na base de dados
- Sistema de batch: organiza seeders por lote de execução
- Exemplos:
  - `CreateDefaultPaymentMethods`
  - `CreateDefaultSeries`
  - etc.

### 4. Limpa Cache
- Limpa todos os caches do sistema:
  - config cache
  - route cache
  - view cache
  - compiled classes
  - application cache

### 5. Verifica Integridade da BD
- Testa conexão com a base de dados
- Conta total de tabelas
- Conta migrations executadas
- Valida estrutura

### 6. Gera Log Detalhado
- Cria arquivo de log em `storage/logs/`
- Nome: `system-update-YYYY-MM-DD_HH-mm-ss.log`
- Contém:
  - Data e hora da atualização
  - Usuário que executou
  - Versão PHP e Laravel
  - Todas as ações realizadas
  - Erros encontrados (se houver)

## 📄 Exemplo de Log Gerado

```
═══════════════════════════════════════════════════
   LOG DE ATUALIZAÇÃO DO SISTEMA - SOS ERP
═══════════════════════════════════════════════════

Data/Hora: 05/10/2025 15:57:22
Usuário: Admin
Versão PHP: 8.3.16
Versão Laravel: 12.x

───────────────────────────────────────────────────

[15:57:22] Iniciando atualização do sistema em 05/10/2025 15:57:22
[15:57:23] Nenhuma migration pendente
[15:57:23] Nenhuma migration para executar
[15:57:24] Seeder executado: CreateDefaultPaymentMethods
[15:57:25] Cache limpo com sucesso
[15:57:25] Conexão com BD: OK
[15:57:25] Total de tabelas: 45
[15:57:25] Migrations executadas: 52
[15:57:26] Log de atualização gerado

───────────────────────────────────────────────────
Atualização concluída em 05/10/2025 15:57:26
═══════════════════════════════════════════════════
```

## 💡 Exemplo Prático: Sistema de Seeders

### Primeira Execução (Batch 1)
```bash
$ php artisan system:update --force

🌱 Verificando seeders...
  ✅ Encontrados 2 seeders novos:
     - CreateDefaultPaymentMethods
     - CreateDefaultSeries
     
  ✅ Seeder executado com sucesso
  ✅ Seeder executado com sucesso
```

**Resultado na BD:**
```
seeders:
| id | seeder                        | batch | executed_at         |
|----|-------------------------------|-------|---------------------|
| 1  | CreateDefaultPaymentMethods   | 1     | 2025-10-05 16:00:00 |
| 2  | CreateDefaultSeries           | 1     | 2025-10-05 16:00:05 |
```

### Segunda Execução (Batch 2 - Novo Seeder Adicionado)
```bash
# Adicionado novo seeder: CreateDefaultCategories.php

$ php artisan system:update --force

🌱 Verificando seeders...
  ✅ Total de seeders: 3
  ✅ Seeders executados: 2
  ✅ Encontrados 1 seeders novos:
     - CreateDefaultCategories
     
  ✅ Seeder executado com sucesso
```

**Resultado na BD:**
```
seeders:
| id | seeder                        | batch | executed_at         |
|----|-------------------------------|-------|---------------------|
| 1  | CreateDefaultPaymentMethods   | 1     | 2025-10-05 16:00:00 |
| 2  | CreateDefaultSeries           | 1     | 2025-10-05 16:00:05 |
| 3  | CreateDefaultCategories       | 2     | 2025-10-05 17:30:00 |
```

### Terceira Execução (Sem Seeders Novos)
```bash
$ php artisan system:update --force

🌱 Verificando seeders...
  ✅ Total de seeders: 3
  ✅ Seeders executados: 3
  ⚠️  Nenhum seeder novo para executar
```

**Nenhuma alteração na BD** - seeders já executados não são repetidos! ✅

---

## 🎯 Quando Usar

### Após Atualizar Código do Repositório (Desenvolvimento)
```bash
git pull
php artisan system:update
# Menu aparece → Escolhe "Automático" → Confirma → Pronto!
```

### Após Atualizar Código (Produção/Deploy)
```bash
git pull
php artisan system:update --force
# Executa tudo automaticamente, sem perguntas
```

### Após Adicionar Nova Migration
```bash
php artisan system:update
# Menu aparece → Escolhe o modo → Migrations executadas
```

### Após Adicionar Novo Seeder
```bash
php artisan system:update
# Menu aparece → Modo Interativo → Revisa seeders antes de executar
```

### Para Limpar Cache e Verificar Sistema
```bash
php artisan system:update --force
# Execução rápida automática
```

### Em CI/CD Pipeline (GitHub Actions, GitLab CI, etc)
```bash
php artisan system:update --force
# Sem interação humana, totalmente automático
```

## ⚠️ Avisos Importantes

1. **Backup**: Sempre faça backup da base de dados antes de executar migrations em produção
2. **Seeders**: Tenha cuidado ao executar seeders em produção - podem duplicar dados
3. **Logs**: Verifique os logs gerados em `storage/logs/` após cada atualização
4. **Permissões**: Certifique-se que o diretório `storage/logs/` tem permissões de escrita

## 🔄 Fluxo de Atualização Recomendado

### Desenvolvimento (Com Menu Interativo):
```bash
# 1. Atualizar código
git pull

# 2. Executar atualização do sistema
php artisan system:update

# 3. Menu aparece - escolher modo
> Seleciona "automatic"
> Confirma com "yes"

# 4. Sistema atualiza automaticamente
# ✅ Migrations executadas
# ✅ Seeders novos executados
# ✅ Cache limpo
# ✅ Log gerado

# 5. Verificar log (opcional)
cat storage/logs/system-update-*.log | tail -1

# 6. Testar aplicação
```

### Produção/CI/CD (Automático Total):
```bash
# 1. Atualizar código
git pull

# 2. Executar atualização (sem perguntas)
php artisan system:update --force

# 3. Verificar log
cat storage/logs/system-update-*.log | tail -1

# 4. Testar aplicação
```

## 📁 Estrutura de Arquivos

```
app/
└── Console/
    └── Commands/
        └── SystemUpdate.php  ← Comando de atualização

database/
├── migrations/
│   └── 2025_10_05_150905_create_seeders_table.php  ← Tabela de rastreamento
└── seeders/
    ├── CreateDefaultPaymentMethods.php
    └── CreateDefaultSeries.php

storage/
└── logs/
    └── system-update-2025-10-05_15-57-22.log  ← Logs gerados
```

## 📊 Tabela de Rastreamento de Seeders

O sistema cria automaticamente uma tabela `seeders` na base de dados:

```sql
CREATE TABLE seeders (
    id BIGINT PRIMARY KEY,
    seeder VARCHAR(255),      -- Nome do seeder
    batch INT,                -- Número do lote de execução
    executed_at TIMESTAMP     -- Data/hora de execução
);
```

**Exemplo de dados:**
```
| id | seeder                        | batch | executed_at         |
|----|-------------------------------|-------|---------------------|
| 1  | CreateDefaultPaymentMethods   | 1     | 2025-10-05 16:00:00 |
| 2  | CreateDefaultSeries           | 1     | 2025-10-05 16:00:05 |
| 3  | CreateDefaultCategories       | 2     | 2025-10-05 17:30:00 |
```

**Funcionalidades:**
- ✅ **Não executa seeders duplicados**
- ✅ **Organiza por batch** (lote de execução)
- ✅ **Registra data/hora** de cada execução
- ✅ **Sistema similar às migrations**

## 🆘 Troubleshooting

### Erro: "Nenhuma migration pendente mas sistema não atualiza"
```bash
php artisan migrate:status
php artisan migrate --force
```

### Erro: "Seeder não encontrado"
```bash
composer dump-autoload
php artisan system:update --force
```

### Ver seeders já executados
```bash
# Ver todos os seeders executados
php -r "echo json_encode(DB::table('seeders')->get(), JSON_PRETTY_PRINT);"

# Ou via Tinker
php artisan tinker
>>> DB::table('seeders')->get()
```

### Forçar re-execução de um seeder
```bash
# Remover seeder da tabela (USE COM CUIDADO!)
php artisan tinker
>>> DB::table('seeders')->where('seeder', 'CreateDefaultPaymentMethods')->delete()

# Executar atualização novamente
php artisan system:update --force
```

### Limpar todos os seeders registrados (PERIGOSO!)
```bash
# Limpar tabela de seeders (USE COM EXTREMO CUIDADO!)
php artisan tinker
>>> DB::table('seeders')->truncate()
```

### Erro: "Permissão negada no log"
```bash
chmod -R 775 storage/logs/
```

### Erro de Conexão com BD
```bash
php artisan config:clear
php artisan system:update --force
```

## 📞 Suporte

Em caso de problemas:
1. Verifique o log mais recente em `storage/logs/`
2. Execute `php artisan migrate:status` para ver status das migrations
3. Verifique permissões dos diretórios
4. Contate o suporte técnico

---

**SOS ERP** - Sistema de Gestão Empresarial
Versão 1.0 - 2025
