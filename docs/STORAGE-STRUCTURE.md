# 📂 Estrutura de Armazenamento - Multi-Tenant

**Data:** 12 de outubro de 2025  
**Organização:** Tenant → Módulo → Recurso → Documentos

---

## 🏗️ Estrutura Completa

```
storage/app/public/
└── tenants/
    ├── 1/                                    # Tenant: Empresa A
    │   ├── employees/
    │   │   ├── 1/                            # João Silva
    │   │   │   └── documentos/
    │   │   │       ├── bi.pdf
    │   │   │       ├── passport.pdf
    │   │   │       ├── work_permit.pdf
    │   │   │       ├── residence_permit.pdf
    │   │   │       ├── driver_license.pdf
    │   │   │       ├── health_insurance.pdf
    │   │   │       └── contract.pdf
    │   │   ├── 2/                            # Maria Santos
    │   │   │   └── documentos/
    │   │   │       ├── bi.pdf
    │   │   │       └── passport.pdf
    │   │   └── 3/                            # Pedro Costa
    │   │       └── documentos/
    │   │           └── bi.pdf
    │   │
    │   ├── leaves/                           # Licenças
    │   │   ├── 1/
    │   │   │   └── atestado_medico.pdf
    │   │   └── 2/
    │   │       └── documento.pdf
    │   │
    │   └── attachments/                      # Outros anexos
    │       └── misc/
    │
    ├── 2/                                    # Tenant: Empresa B
    │   ├── employees/
    │   │   └── 1/
    │   │       └── documentos/
    │   │           ├── bi.pdf
    │   │           └── contract.pdf
    │   │
    │   └── leaves/
    │       └── 1/
    │           └── atestado.pdf
    │
    └── 3/                                    # Tenant: Empresa C
        └── employees/
            └── 1/
                └── documentos/
                    └── bi.pdf
```

---

## 📋 Benefícios da Estrutura

### **1. Isolamento por Tenant ✅**
```
tenants/1/     → Empresa A (isolada)
tenants/2/     → Empresa B (isolada)
tenants/3/     → Empresa C (isolada)
```

**Vantagens:**
- ✅ Segurança total entre empresas
- ✅ Fácil backup por tenant
- ✅ Migração simplificada
- ✅ Exclusão em massa facilitada

---

### **2. Organização por Módulo ✅**
```
tenants/1/
├── employees/    → Módulo de Funcionários
├── leaves/       → Módulo de Licenças
├── vacations/    → Módulo de Férias
└── attachments/  → Outros anexos
```

**Vantagens:**
- ✅ Fácil localização de arquivos
- ✅ Manutenção simplificada
- ✅ Permissões por módulo

---

### **3. Recursos Individualizados ✅**
```
employees/
├── 1/          → João Silva
├── 2/          → Maria Santos
└── 3/          → Pedro Costa
```

**Vantagens:**
- ✅ Todos documentos de um funcionário juntos
- ✅ Fácil auditoria
- ✅ Download em lote por pessoa

---

### **4. Documentos Nomeados ✅**
```
documentos/
├── bi.pdf                    → Sempre mesmo nome
├── passport.pdf              → Fácil identificar
└── work_permit.pdf           → Padronizado
```

**Vantagens:**
- ✅ Sem nomes aleatórios
- ✅ Substituição automática
- ✅ Links sempre funcionam

---

## 🔐 Segurança

### **Regras de Acesso:**

```php
// Usuário só pode acessar arquivos do seu tenant
Route::get('/storage/tenants/{tenant}/...', function($tenant) {
    if ($tenant != auth()->user()->activeTenantId()) {
        abort(403, 'Acesso negado');
    }
    // ...
});
```

### **Middleware de Proteção:**

```php
class TenantStorageMiddleware
{
    public function handle($request, Closure $next)
    {
        $tenantId = auth()->user()->activeTenantId();
        
        // Verificar se path contém ID do tenant correto
        if (!str_contains($request->path(), "tenants/{$tenantId}")) {
            abort(403);
        }
        
        return $next($request);
    }
}
```

---

## 📊 Exemplo Real

### **Tenant 1 - Empresa A:**

```
storage/app/public/tenants/1/
├── employees/
│   ├── 1/                               # João Silva - RH
│   │   └── documentos/
│   │       ├── bi.pdf                   # 125 KB
│   │       ├── passport.pdf             # 89 KB
│   │       ├── work_permit.pdf          # 203 KB
│   │       ├── driver_license.pdf       # 67 KB
│   │       └── contract.pdf             # 412 KB
│   │
│   ├── 2/                               # Maria Santos - TI
│   │   └── documentos/
│   │       ├── bi.pdf                   # 98 KB
│   │       ├── passport.pdf             # 156 KB
│   │       └── contract.pdf             # 389 KB
│   │
│   └── 3/                               # Pedro Costa - Comercial
│       └── documentos/
│           ├── bi.pdf                   # 134 KB
│           └── contract.pdf             # 298 KB
│
├── leaves/                              # Licenças
│   ├── 1/                               # Licença #1
│   │   └── atestado_medico.pdf          # 187 KB
│   └── 2/                               # Licença #2
│       └── justificativa.pdf            # 92 KB
│
└── attachments/                         # Outros
    └── misc/
        └── documento_geral.pdf          # 234 KB

Total Tenant 1: ~2.6 MB
```

---

## 🛠️ Operações Comuns

### **1. Backup de Tenant:**
```bash
# Fazer backup completo de um tenant
zip -r tenant_1_backup.zip storage/app/public/tenants/1/

# Restaurar
unzip tenant_1_backup.zip -d storage/app/public/
```

### **2. Migração de Tenant:**
```bash
# Mover tenant 1 para outro servidor
rsync -avz storage/app/public/tenants/1/ user@server:/path/to/storage/tenants/1/
```

### **3. Limpar Documentos Antigos:**
```php
// Deletar documentos de funcionários inativos há mais de 5 anos
$inactiveEmployees = Employee::where('tenant_id', $tenantId)
    ->where('status', 'inactive')
    ->where('updated_at', '<', now()->subYears(5))
    ->get();

foreach ($inactiveEmployees as $employee) {
    Storage::disk('public')->deleteDirectory(
        "tenants/{$tenantId}/employees/{$employee->id}"
    );
}
```

### **4. Estatísticas de Uso:**
```php
// Ver tamanho usado por tenant
public function getTenantStorageSize($tenantId)
{
    $path = storage_path("app/public/tenants/{$tenantId}");
    $size = 0;
    
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
        $size += $file->getSize();
    }
    
    return round($size / 1024 / 1024, 2); // MB
}
```

---

## 📈 Escalabilidade

### **Limites Recomendados:**

| Métrica | Recomendação |
|---------|--------------|
| Tamanho por arquivo | 2 MB |
| Arquivos por funcionário | 10-15 |
| Funcionários por tenant | Ilimitado |
| Total por tenant | 10 GB |

### **Se ultrapassar limites:**

1. **Compressão:** Comprimir PDFs grandes
2. **Cloud Storage:** Migrar para S3/Azure
3. **Cleanup:** Remover documentos vencidos há 5+ anos
4. **Arquivamento:** Mover para cold storage

---

## ✅ Checklist de Implementação

- [x] Estrutura definida
- [x] Migration criada
- [ ] Método de upload implementado
- [ ] Middleware de segurança
- [ ] Testes de acesso
- [ ] Backup automático
- [ ] Monitoramento de espaço
- [ ] Documentação para usuários

---

**Estrutura profissional, segura e escalável! 📂✨**
