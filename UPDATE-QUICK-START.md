# ⚡ Guia Rápido: Atualização do Sistema

## 🚀 Comando Principal

```bash
php artisan system:update
```

### **Novo! Interface Interativa** 🎯

Ao executar o comando, você verá um menu de seleção:

```
┌─────────────────────────────────────────────────┐
│     🎯 MODO DE ATUALIZAÇÃO                      │
└─────────────────────────────────────────────────┘

⚙️  Como deseja executar a atualização?
  [automatic] 🚀 Automático - Executa tudo sem perguntar (recomendado)
  [interactive] ✋ Interativo - Pergunta antes de cada seeder
  [cancel] ❌ Cancelar atualização
```

**Escolha sua opção e pressione Enter!**

## 📋 O que Faz

✅ **Migrations** - Executa pendentes  
✅ **Seeders** - Executa apenas NOVOS (nunca executados)  
✅ **Cache** - Limpa automaticamente  
✅ **Integridade BD** - Verifica conexão e estrutura  
✅ **Log** - Gera relatório completo em `storage/logs/`

## 🎯 Uso Comum

### Uso Normal (Com Menu Interativo)
```bash
git pull
php artisan system:update
# Escolha o modo e confirme
```

### Modo Automático (Sem Perguntas - CI/CD)
```bash
php artisan system:update --force
# Executa tudo automaticamente
```

## 📊 Sistema Inteligente de Seeders

**O sistema NÃO EXECUTA seeders já executados!**

Exemplo:
```
Execução 1:
✅ CreateDefaultPaymentMethods  → Executado
✅ CreateDefaultSeries          → Executado

Execução 2:
⏭️  CreateDefaultPaymentMethods  → IGNORADO (já executado)
⏭️  CreateDefaultSeries          → IGNORADO (já executado)  
✅ CreateDefaultCategories      → Executado (novo)
```

## 📄 Log

Cada execução gera um log:
```
storage/logs/system-update-2025-10-05_16-08-42.log
```

## 🆘 Problemas?

```bash
# Ver seeders já executados
php artisan tinker
>>> DB::table('seeders')->get()

# Limpar cache
php artisan optimize:clear

# Ver migrations
php artisan migrate:status
```

## 📚 Documentação Completa

Ver `SYSTEM-UPDATE.md` para documentação detalhada.

---

**SOS ERP** - Sistema Inteligente de Atualização
