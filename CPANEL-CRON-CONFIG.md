# 🖥️ CONFIGURAÇÃO DE CRON JOB - GUIA COMPLETO

## ✅ **COMPATIBILIDADE: cPanel, Linux/SSH e Windows**

---

## 🎯 **DIFERENÇAS ENTRE AMBIENTES**

### **❌ PROBLEMA ORIGINAL:**

As instruções mostravam apenas para **Linux/SSH**:
```bash
*/10 * * * * cd c:\laragon2\www\soserp && php artisan notifications:send-scheduled >> /dev/null 2>&1
```

**Problemas:**
- ❌ Caminho Windows (`c:\`) não funciona em Linux
- ❌ Sintaxe `cd ... &&` não é compatível com cPanel
- ❌ `/dev/null` não existe no Windows

---

## ✅ **SOLUÇÃO: INSTRUÇÕES PARA CADA AMBIENTE**

---

## 1️⃣ **cPanel (MAIS COMUM EM PRODUÇÃO)**

### **📋 Características do cPanel:**
- Interface gráfica para gerenciar cron jobs
- Não precisa de SSH
- Comandos mais simples (sem `cd`, `&&`, etc)
- Caminho do PHP pode variar

### **🔧 Como Configurar:**

#### **Passo 1: Acessar cPanel**
1. Faça login no cPanel da sua hospedagem
2. Procure por **"Cron Jobs"** ou **"Tarefas Cron"**
3. Clique para abrir

#### **Passo 2: Configurar Frequência**

No formulário "Add New Cron Job":

```
Minute:    */10
Hour:      *
Day:       *
Month:     *
Weekday:   *
```

Ou selecione no dropdown: **"Common Settings → Every 10 minutes"**

#### **Passo 3: Comando**

Cole este comando no campo **"Command"**:

```bash
/usr/local/bin/php /home/seuusuario/public_html/artisan notifications:send-scheduled
```

**⚠️ IMPORTANTE: Ajustar o comando:**

1. **Caminho do PHP:** Pode variar por hospedagem
   - `/usr/local/bin/php` (mais comum)
   - `/usr/bin/php`
   - `/opt/alt/php80/usr/bin/php` (CloudLinux com PHP Selector)
   - `/opt/alt/php81/usr/bin/php`

2. **Caminho do Projeto:**
   - Substitua `/home/seuusuario/public_html` pelo caminho real
   - Para descobrir: crie um arquivo `info.php` com `<?php echo __DIR__; ?>`

#### **Passo 4: Salvar**

Clique em **"Add New Cron Job"**

#### **Passo 5: Verificar**

O cron job aparecerá na lista abaixo. Aguarde 10 minutos e verifique os logs.

---

### **🔍 Como Descobrir o Caminho do PHP no cPanel:**

#### **Método 1: SSH (se tiver acesso)**
```bash
which php
# Resultado: /usr/local/bin/php
```

#### **Método 2: Criar arquivo PHP**
Crie `phpinfo.php`:
```php
<?php
echo 'PHP Binary: ' . PHP_BINARY;
phpinfo();
```

Acesse via navegador e procure por "PHP Binary"

#### **Método 3: cPanel → PHP Selector**
Se sua hospedagem usa CloudLinux:
- Acesse "Select PHP Version"
- Veja a versão selecionada
- Use: `/opt/alt/php{versao}/usr/bin/php`

Exemplos:
- PHP 8.0: `/opt/alt/php80/usr/bin/php`
- PHP 8.1: `/opt/alt/php81/usr/bin/php`
- PHP 8.2: `/opt/alt/php82/usr/bin/php`

---

### **📊 Exemplo Real no cPanel:**

**Hostgator/Hostinger/Locaweb:**
```bash
/usr/local/bin/php /home/usuario123/public_html/soserp/artisan notifications:send-scheduled
```

**SiteGround (CloudLinux):**
```bash
/opt/alt/php81/usr/bin/php /home/usuario123/public_html/soserp/artisan notifications:send-scheduled
```

---

## 2️⃣ **Linux/SSH (SERVIDOR DEDICADO/VPS)**

### **📋 Características:**
- Acesso via terminal SSH
- Editor de texto (vim/nano)
- Mais controle e flexibilidade

### **🔧 Como Configurar:**

#### **Passo 1: Conectar via SSH**
```bash
ssh usuario@seuservidor.com
```

#### **Passo 2: Abrir crontab**
```bash
crontab -e
```

#### **Passo 3: Adicionar linha**
```bash
*/10 * * * * cd /var/www/soserp && php artisan notifications:send-scheduled >> /dev/null 2>&1
```

**Explicação:**
- `*/10 * * * *` - A cada 10 minutos
- `cd /var/www/soserp` - Entra no diretório
- `&&` - Se anterior sucesso, executa próximo
- `php artisan notifications:send-scheduled` - Comando Laravel
- `>> /dev/null 2>&1` - Ignora output (ou use path para log)

#### **Passo 4: Salvar**
- Vim: `:wq` + Enter
- Nano: Ctrl+X, depois Y, depois Enter

#### **Passo 5: Verificar**
```bash
crontab -l
```

---

### **📊 Variações Úteis:**

#### **Com Log Personalizado:**
```bash
*/10 * * * * cd /var/www/soserp && php artisan notifications:send-scheduled >> /var/www/soserp/storage/logs/cron.log 2>&1
```

#### **Com Email de Notificação:**
```bash
MAILTO="seu@email.com"
*/10 * * * * cd /var/www/soserp && php artisan notifications:send-scheduled
```

#### **Apenas em Horário Comercial:**
```bash
*/10 8-18 * * 1-5 cd /var/www/soserp && php artisan notifications:send-scheduled >> /dev/null 2>&1
```
*(A cada 10 min, das 8h-18h, segunda a sexta)*

---

## 3️⃣ **Windows (DESENVOLVIMENTO LOCAL)**

### **📋 Características:**
- Usa Agendador de Tarefas (Task Scheduler)
- Interface gráfica
- Geralmente para desenvolvimento

### **🔧 Como Configurar:**

#### **Passo 1: Abrir Agendador**
1. Pressione `Win + R`
2. Digite: `taskschd.msc`
3. Enter

#### **Passo 2: Criar Tarefa**
1. Clique direito em "Biblioteca do Agendador de Tarefas"
2. Escolha "Criar Tarefa..."

#### **Passo 3: Aba Geral**
- **Nome:** Notificações SOSERP
- **Descrição:** Envia notificações agendadas a cada 10 minutos
- ☑️ Executar independentemente de usuário estar conectado (se quiser)

#### **Passo 4: Aba Disparadores**
1. Clique "Novo..."
2. **Iniciar a tarefa:** Ao agendar
3. **Configurações:**
   - ☑️ Repetir a tarefa a cada: **10 minutos**
   - Durante: **Indefinidamente**
4. OK

#### **Passo 5: Aba Ações**
1. Clique "Novo..."
2. **Ação:** Iniciar um programa
3. **Programa/script:**
   ```
   C:\laragon\bin\php\php-8.2-Win32\php.exe
   ```
   *(Ajuste para seu caminho do PHP)*

4. **Adicionar argumentos:**
   ```
   C:\laragon\www\soserp\artisan notifications:send-scheduled
   ```

5. **Iniciar em:**
   ```
   C:\laragon\www\soserp
   ```

6. OK

#### **Passo 6: Aba Condições**
- Desmarque "Iniciar a tarefa somente se o computador estiver ocioso"
- Desmarque "Parar se o computador deixar de estar ocioso"

#### **Passo 7: Aba Configurações**
- ☑️ Permitir que a tarefa seja executada sob demanda
- ☑️ Executar a tarefa assim que possível...
- Se falhar, reiniciar a cada: **1 minuto**

#### **Passo 8: OK e Testar**
1. Clique OK
2. Na lista, clique direito na tarefa → "Executar"
3. Verifique logs: `storage/logs/laravel.log`

---

## 🧪 **TESTES**

### **Teste 1: Execução Manual**

#### **cPanel:**
```bash
# Via SSH (se disponível)
cd /home/usuario/public_html/soserp
/usr/local/bin/php artisan notifications:send-scheduled
```

#### **Linux/SSH:**
```bash
cd /var/www/soserp
php artisan notifications:send-scheduled
```

#### **Windows:**
```powershell
cd C:\laragon\www\soserp
php artisan notifications:send-scheduled
```

### **Teste 2: Verificar Logs**

#### **Laravel Log:**
```bash
tail -f storage/logs/laravel.log
```

#### **Cron Log (se configurado):**
```bash
tail -f storage/logs/cron.log
```

#### **cPanel Cron Email:**
Verifique email cadastrado no cPanel

---

## ⚠️ **TROUBLESHOOTING**

### **Problema 1: Cron não executa no cPanel**

**Sintomas:**
- Nenhum email de erro
- Logs não atualizados

**Soluções:**
1. ✅ Verificar caminho do PHP
2. ✅ Verificar caminho do projeto
3. ✅ Testar comando manualmente via SSH
4. ✅ Verificar permissões (755 em artisan)
5. ✅ Adicionar output para debug:
   ```bash
   /usr/local/bin/php /caminho/artisan notifications:send-scheduled > /home/usuario/cron-debug.log 2>&1
   ```

### **Problema 2: "Command not found" no cPanel**

**Causa:** Caminho do PHP incorreto

**Solução:**
```bash
# Descobrir caminho correto via SSH
which php
type php
whereis php

# Ou criar teste no cPanel
/usr/local/bin/php -v  # Se funcionar, é este
/usr/bin/php -v
/opt/alt/php80/usr/bin/php -v
```

### **Problema 3: Permissão negada**

**Solução:**
```bash
# Via SSH
chmod +x /home/usuario/public_html/soserp/artisan
chmod 755 /home/usuario/public_html/soserp/artisan
```

### **Problema 4: Classes não encontradas**

**Causa:** Autoload não atualizado

**Solução:**
```bash
cd /caminho/projeto
php artisan optimize:clear
composer dump-autoload
```

---

## 📋 **CHECKLIST DE VERIFICAÇÃO**

### **cPanel:**
- [ ] Comando correto copiado
- [ ] Caminho do PHP correto (`which php` via SSH)
- [ ] Caminho do projeto correto (sem public_html duplicado)
- [ ] Frequência configurada (*/10 * * * *)
- [ ] Cron job salvo e aparece na lista
- [ ] Aguardou 10 minutos para teste
- [ ] Verificou email de erro do cPanel
- [ ] Testou comando manual via SSH

### **Linux/SSH:**
- [ ] Acesso SSH funcionando
- [ ] `crontab -e` abre editor
- [ ] Linha adicionada corretamente
- [ ] Salvo e fechado editor
- [ ] `crontab -l` mostra a linha
- [ ] Caminho do projeto correto
- [ ] Permissões de artisan (755)
- [ ] Logs sendo gerados

### **Windows:**
- [ ] Agendador de Tarefas aberto
- [ ] Tarefa criada com nome
- [ ] Disparador a cada 10 minutos
- [ ] Caminho do php.exe correto
- [ ] Argumentos corretos
- [ ] "Iniciar em" configurado
- [ ] Tarefa executada manualmente (teste)
- [ ] Logs verificados

---

## 🎉 **RESUMO**

| Ambiente | Comando | Interface | Complexidade |
|----------|---------|-----------|--------------|
| **cPanel** | `/usr/local/bin/php /path/artisan ...` | ✅ GUI | ⭐ Fácil |
| **Linux/SSH** | `*/10 * * * * cd /path && php artisan ...` | Terminal | ⭐⭐ Médio |
| **Windows** | Via Task Scheduler | ✅ GUI | ⭐⭐⭐ Médio |

---

## ✅ **AGORA 100% COMPATÍVEL!**

- ✅ **cPanel** - Instruções específicas
- ✅ **Linux/SSH** - Sintaxe correta
- ✅ **Windows** - Task Scheduler
- ✅ Interface com tabs selecionáveis
- ✅ Botões de copiar para cada ambiente
- ✅ Instruções passo a passo
- ✅ Troubleshooting completo

**O sistema detecta automaticamente o caminho correto e mostra o comando adequado para cada ambiente!** 🚀
