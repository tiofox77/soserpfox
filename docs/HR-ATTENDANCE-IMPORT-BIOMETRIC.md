# Importação de Presenças de Biométricos 📊✨

## 🎯 Funcionalidade

Botão e modal para **importar registros de presença** de sistemas biométricos **ZKTeco** e **Hikvision** via arquivo Excel.

---

## 🏗️ Arquitetura

### **Componentes Criados:**

1. **Botão "Importar Excel"** no header
2. **Modal de Importação** com upload de arquivo
3. **Métodos Livewire** (estrutura preparada)
4. **Validação** de arquivo e sistema

---

## 🎨 Interface do Usuário

### **1. Botão no Header**

```blade
<button wire:click="openImportModal" 
        class="bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white px-5 py-3 rounded-xl">
    <i class="fas fa-file-excel mr-2"></i>Importar Excel
</button>
```

**Localização:** Ao lado do botão "Registrar Presença"

**Estilo:**
- ✅ Background branco transparente (20%)
- ✅ Backdrop blur
- ✅ Border branco transparente
- ✅ Hover effect

---

### **2. Modal de Importação**

#### **Header Gradiente Verde**
- Ícone Excel em badge
- Título: "Importar Presenças de Biométrico"
- Subtítulo: "ZKTeco ou Hikvision - Formato Excel"

#### **Seção 1: Instruções**
```
📘 Card azul com instruções passo a passo:
1. Exporte o relatório de presenças do seu biométrico
2. O arquivo deve estar em formato Excel (.xlsx ou .xls)
3. Selecione o arquivo e clique em "Processar"
4. O sistema validará e importará automaticamente
```

#### **Seção 2: Seleção do Sistema**

**Cards visuais com radio buttons:**

| ZKTeco | Hikvision |
|--------|-----------|
| 🔵 Ícone fingerprint azul | 🔴 Ícone câmera vermelha |
| "Relógio de Ponto ZK" | "Terminal Facial" |
| Radio button | Radio button |

**Comportamento:**
- Seleção visual (border verde + fundo verde claro)
- Ring effect ao selecionar
- Hover effect

#### **Seção 3: Upload de Arquivo**

**Drag & Drop Area:**

**Estado Vazio:**
```
☁️ Ícone cloud upload
"Clique para selecionar ou arraste o arquivo"
"Formatos aceitos: .xlsx, .xls (Máximo 5MB)"
[Botão: Selecionar Arquivo]
```

**Estado Com Arquivo:**
```
📄 Ícone Excel verde
nome-do-arquivo.xlsx
123.45 KB
[Botão: Remover arquivo]
```

**Progress Bar:**
- Animação durante upload
- Porcentagem exibida
- Gradiente verde-esmeralda

#### **Seção 4: Formatos Esperados**

**Card amarelo de aviso:**
```
⚠️ Formatos de Colunas Esperados:

ZKTeco: Nº Funcionário | Nome | Data | Hora Entrada | Hora Saída
Hikvision: Employee ID | Name | Date | Check In | Check Out
```

---

## 💻 Código Backend

### **Propriedades Livewire:**

```php
use WithFileUploads;

public $showImportModal = false;
public $importFile;
public $biometricSystem = 'zkteco';
```

### **Métodos Implementados:**

#### **1. openImportModal()**
```php
public function openImportModal()
{
    $this->showImportModal = true;
    $this->importFile = null;
    $this->biometricSystem = 'zkteco';
}
```

#### **2. closeImportModal()**
```php
public function closeImportModal()
{
    $this->showImportModal = false;
    $this->importFile = null;
    $this->biometricSystem = 'zkteco';
}
```

#### **3. processImport()** (Estrutura)
```php
public function processImport()
{
    $this->validate([
        'importFile' => 'required|file|mimes:xlsx,xls|max:5120',
        'biometricSystem' => 'required|in:zkteco,hikvision',
    ]);

    try {
        // TODO: Implementar lógica de importação
        // 1. Ler arquivo Excel (PhpSpreadsheet)
        // 2. Validar estrutura
        // 3. Mapear dados por sistema
        // 4. Validar funcionários
        // 5. Criar registros
        // 6. Retornar estatísticas
        
        session()->flash('success', 'Funcionalidade será implementada em breve!');
        
        logger()->info('Import solicitado', [
            'file' => $this->importFile->getClientOriginalName(),
            'system' => $this->biometricSystem,
        ]);
        
    } catch (\Exception $e) {
        session()->flash('error', 'Erro: ' . $e->getMessage());
    }
}
```

---

## 📋 Validações

### **Arquivo:**
- ✅ Obrigatório
- ✅ Tipo: `.xlsx` ou `.xls`
- ✅ Tamanho máximo: 5MB (5120 KB)

### **Sistema Biométrico:**
- ✅ Obrigatório
- ✅ Valores: `zkteco` ou `hikvision`

---

## 🔄 Fluxo de Uso

```
1. Usuário clica "Importar Excel"
   ↓
2. Modal abre com animação
   ↓
3. Usuário seleciona sistema (ZKTeco/Hikvision)
   ↓
4. Usuário faz upload do arquivo Excel
   ↓
5. Progress bar mostra upload
   ↓
6. Arquivo aparece com nome e tamanho
   ↓
7. Usuário clica "Processar Importação"
   ↓
8. Validação do arquivo
   ↓
9. [TODO] Lógica de importação
   ↓
10. Flash message de sucesso/erro
   ↓
11. Modal fecha
```

---

## 📊 Estruturas de Excel Esperadas

### **ZKTeco:**

| Nº Funcionário | Nome | Data | Hora Entrada | Hora Saída |
|----------------|------|------|--------------|------------|
| EMP001 | João Silva | 12/10/2025 | 08:00 | 17:00 |
| EMP002 | Maria Santos | 12/10/2025 | 08:15 | 17:30 |

**Colunas:**
1. Código do funcionário
2. Nome completo
3. Data (formato brasileiro)
4. Hora de entrada
5. Hora de saída

### **Hikvision:**

| Employee ID | Name | Date | Check In | Check Out |
|-------------|------|------|----------|-----------|
| 001 | João Silva | 2025-10-12 | 08:00:00 | 17:00:00 |
| 002 | Maria Santos | 2025-10-12 | 08:15:00 | 17:30:00 |

**Colunas:**
1. ID do funcionário
2. Nome
3. Data (formato ISO)
4. Hora entrada (com segundos)
5. Hora saída (com segundos)

---

## 🚀 Implementação Futura

### **Passos para Implementar:**

#### **1. Instalar PhpSpreadsheet**
```bash
composer require phpoffice/phpspreadsheet
```

#### **2. Criar Service de Importação**
```php
// app/Services/BiometricImportService.php

class BiometricImportService
{
    public function import(string $filePath, string $system)
    {
        // Lógica de importação
    }
    
    private function readExcel($filePath) { }
    private function mapZKTecoData($rows) { }
    private function mapHikvisionData($rows) { }
    private function validateEmployee($employeeCode) { }
    private function createAttendance($data) { }
}
```

#### **3. Mapear Colunas por Sistema**
```php
private function getColumnMapping(string $system): array
{
    return match($system) {
        'zkteco' => [
            'employee_code' => 0,  // Coluna A
            'name' => 1,           // Coluna B
            'date' => 2,           // Coluna C
            'check_in' => 3,       // Coluna D
            'check_out' => 4,      // Coluna E
        ],
        'hikvision' => [
            'employee_code' => 0,
            'name' => 1,
            'date' => 2,
            'check_in' => 3,
            'check_out' => 4,
        ],
    };
}
```

#### **4. Validar e Importar**
```php
foreach ($rows as $row) {
    // Buscar funcionário por código
    $employee = Employee::where('employee_number', $row['employee_code'])->first();
    
    if (!$employee) {
        $errors[] = "Funcionário {$row['employee_code']} não encontrado";
        continue;
    }
    
    // Criar registro de presença
    Attendance::updateOrCreate([
        'tenant_id' => auth()->user()->activeTenantId(),
        'employee_id' => $employee->id,
        'date' => Carbon::parse($row['date']),
    ], [
        'check_in' => $row['check_in'],
        'check_out' => $row['check_out'],
        'hours_worked' => $this->calculateHours($row),
        'status' => 'present',
    ]);
    
    $imported++;
}
```

#### **5. Retornar Estatísticas**
```php
return [
    'success' => true,
    'imported' => $imported,
    'errors' => $errors,
    'duplicates' => $duplicates,
    'message' => "$imported registros importados com sucesso!",
];
```

---

## 🎨 Features do Modal

### **Animações:**
- ✅ Fade in backdrop
- ✅ Scale in modal
- ✅ Progress bar animada
- ✅ Hover effects nos cards

### **Validação Visual:**
- ✅ Radio buttons estilizados
- ✅ Upload area interativa
- ✅ Mensagens de erro em vermelho
- ✅ Preview do arquivo

### **UX:**
- ✅ Fechar ao clicar fora
- ✅ Botão X no header
- ✅ Botão Cancelar no footer
- ✅ Drag & drop (nativo HTML5)
- ✅ Remoção de arquivo

### **Feedback:**
- ✅ Progress bar durante upload
- ✅ Nome e tamanho do arquivo
- ✅ Mensagens de sucesso/erro
- ✅ Logs no Laravel

---

## 📱 Responsividade

### **Desktop:**
- Grid 2 colunas para sistemas
- Modal centralizado
- Upload area ampla

### **Mobile:**
- Grid 1 coluna (stack)
- Modal full width
- Botões full width

---

## 🔍 Debugging

### **Ver Logs:**
```bash
tail -f storage/logs/laravel.log | grep "Import"
```

### **Console do Navegador:**
```javascript
// Ver uploads Livewire
Livewire.all()[0].__instance.serverMemo.data.importFile
```

### **Testar Upload:**
1. Selecionar arquivo
2. Ver progress bar
3. Ver console para erros
4. Verificar logs Laravel

---

## ✅ Checklist de Implementação

### **Fase 1: Interface (✅ COMPLETO)**
- ✅ Botão "Importar Excel"
- ✅ Modal de upload
- ✅ Seleção de sistema
- ✅ Upload area
- ✅ Progress bar
- ✅ Validação frontend

### **Fase 2: Backend (🔄 PENDENTE)**
- ⏳ Instalar PhpSpreadsheet
- ⏳ Criar BiometricImportService
- ⏳ Implementar leitura de Excel
- ⏳ Mapear colunas por sistema
- ⏳ Validar funcionários
- ⏳ Criar registros de presença
- ⏳ Tratar duplicados
- ⏳ Retornar estatísticas

### **Fase 3: Melhorias (📋 FUTURO)**
- 📋 Importação em background (Jobs)
- 📋 Notificações de progresso
- 📋 Relatório de erros em Excel
- 📋 Preview antes de importar
- 📋 Histórico de importações

---

## 🎓 Referências

### **PhpSpreadsheet:**
- Docs: https://phpspreadsheet.readthedocs.io/
- GitHub: https://github.com/PHPOffice/PhpSpreadsheet

### **Livewire File Uploads:**
- Docs: https://livewire.laravel.com/docs/uploads

### **Sistemas Biométricos:**
- **ZKTeco:** https://www.zkteco.com/
- **Hikvision:** https://www.hikvision.com/

---

## 📝 Notas

### **Formato de Dados:**
- Datas podem vir em formatos diferentes (BR vs ISO)
- Horas podem ter ou não segundos
- Nomes podem ter acentos/caracteres especiais
- Códigos de funcionário devem ser únicos

### **Tratamento de Erros:**
- Funcionário não encontrado → Pular e logar
- Data inválida → Converter ou rejeitar
- Horários inválidos → Validar formato
- Linhas duplicadas → Atualizar ou ignorar

### **Performance:**
- Usar transações para inserções em massa
- Processar em chunks se arquivo grande
- Considerar Jobs para arquivos > 1000 linhas

---

**Status:** ✅ **INTERFACE COMPLETA**  
**Backend:** ⏳ **ESTRUTURA PREPARADA (Implementar depois)**  
**UX:** 🎨 **Enterprise Grade**  
**Data:** 12 de outubro de 2025
