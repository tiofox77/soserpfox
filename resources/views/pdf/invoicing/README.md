# 📄 PDFs de Faturação

Esta pasta contém os templates para geração de PDFs dos documentos de faturação.

---

## 📁 Estrutura de Pastas

```
resources/views/pdf/
└── invoicing/
    ├── invoice.blade.php       # Template da Fatura de Venda
    ├── proforma.blade.php      # Template da Proforma (já existente)
    └── README.md               # Este arquivo
```

---

## 📄 Fatura de Venda (invoice.blade.php)

### Características:

- ✅ **Formato A4** (210mm × 297mm)
- ✅ **Conformidade AGT Angola** 
- ✅ **Logo da empresa** (ou fallback)
- ✅ **QR Code** (placeholder implementado)
- ✅ **Informações do cliente**
- ✅ **Tabela de produtos/serviços**
- ✅ **Resumo de impostos (IVA)**
- ✅ **Descontos (Comercial e Financeiro)**
- ✅ **Retenção na fonte (IRT 6,5%)**
- ✅ **Total por extenso**
- ✅ **Status de documento (Anulada)**

### Layout:

```
┌─────────────────────────────────────────────────┐
│ HEADER                                          │
│ ┌──────────────────┬──────────────────────┐    │
│ │ Logo + Empresa   │  Cliente + QR Code   │    │
│ └──────────────────┴──────────────────────┘    │
├─────────────────────────────────────────────────┤
│ TÍTULO: Fatura de Venda n.º FT A/2025/000001   │
├─────────────────────────────────────────────────┤
│ INFO: Moeda | Data | Hora | Vencimento | etc   │
├─────────────────────────────────────────────────┤
│ TABELA DE PRODUTOS                              │
│ ┌─────┬─────────┬─────┬──────┬──────────┐     │
│ │Cód. │Produto  │Qtd  │Preço │Total     │     │
│ └─────┴─────────┴─────┴──────┴──────────┘     │
├─────────────────────────────────────────────────┤
│ FOOTER                                          │
│ ┌──────────────────┬──────────────────────┐    │
│ │ Resumo Impostos  │  Totais              │    │
│ │ Regime Fiscal    │  - Ilíquido          │    │
│ │                  │  - Descontos         │    │
│ │                  │  - IVA               │    │
│ │                  │  - Total a Pagar     │    │
│ └──────────────────┴──────────────────────┘    │
│ AGT Compliance Info                             │
└─────────────────────────────────────────────────┘
```

---

## 🔧 Controller

**Arquivo:** `app/Http/Controllers/Invoicing/InvoiceController.php`

### Métodos:

#### 1. `generatePdf($id)` - Visualizar no Navegador
```php
// Abre o PDF diretamente no navegador
Route: GET /invoicing/sales/invoices/{id}/pdf
Nome: invoicing.sales.invoices.pdf
```

#### 2. `downloadPdf($id)` - Forçar Download
```php
// Força o download do PDF
Route: GET /invoicing/sales/invoices/{id}/download
Nome: invoicing.sales.invoices.download
```

---

## 🚀 Como Usar

### No Blade (Botões):

```blade
{{-- Visualizar PDF --}}
<a href="{{ route('invoicing.sales.invoices.pdf', $invoice->id) }}" 
   target="_blank"
   class="btn btn-primary">
    <i class="fas fa-file-pdf"></i> Ver PDF
</a>

{{-- Download PDF --}}
<a href="{{ route('invoicing.sales.invoices.download', $invoice->id) }}" 
   class="btn btn-success">
    <i class="fas fa-download"></i> Baixar PDF
</a>
```

### No Livewire:

```php
// Redirecionar para PDF
public function viewPdf($invoiceId)
{
    return redirect()->route('invoicing.sales.invoices.pdf', $invoiceId);
}

// Abrir em nova aba (JavaScript)
public function openPdfNewTab($invoiceId)
{
    $this->dispatch('open-url', [
        'url' => route('invoicing.sales.invoices.pdf', $invoiceId)
    ]);
}
```

---

## 📊 Dados Necessários

### Variáveis Passadas ao Template:

```php
[
    'invoice' => SalesInvoice::with([
        'client',       // Cliente
        'items',        // Itens da fatura
        'warehouse',    // Armazém
        'user'          // Utilizador que criou
    ]),
    'tenant' => Tenant  // Empresa (logo, NIF, endereço, etc)
]
```

### Relacionamentos Usados:

- `$invoice->client` - Informações do cliente
- `$invoice->items` - Produtos/serviços da fatura
- `$invoice->warehouse` - Armazém de origem
- `$invoice->user` - Operador
- `$tenant->logo` - Logo da empresa
- `$tenant->regime` - Regime fiscal

---

## 🇦🇴 Conformidade AGT Angola

### Elementos Obrigatórios:

1. ✅ **NIF** da empresa e cliente
2. ✅ **Data e hora** de emissão
3. ✅ **Número sequencial** da fatura
4. ✅ **Discriminação** de produtos/serviços
5. ✅ **Base de incidência IVA** (subtotal)
6. ✅ **Taxa de IVA** aplicada (14%)
7. ✅ **Valor de IVA** calculado
8. ✅ **Retenção na fonte** (IRT 6,5% para serviços)
9. ✅ **Total a pagar**
10. ✅ **Regime fiscal** da empresa
11. ✅ **Validação AGT** (hash/certificado)

### Cálculos SAFT-AO:

```
1. Total Bruto = Σ(Quantidade × Preço Unitário)
2. Desconto Comercial = Σ(Desconto por linha)
3. Valor Líquido = Total Bruto - Desconto Comercial
4. Desconto Financeiro = Aplicado sobre Valor Líquido
5. Incidência IVA = Valor Líquido - Desconto Financeiro
6. IVA = Incidência IVA × 14%
7. Retenção = Incidência IVA × 6,5% (só serviços)
8. Total a Pagar = Incidência + IVA - Retenção
```

---

## 🎨 Personalização

### Alterar Cores:

No template `invoice.blade.php`, procure por:

```css
.company-name { color: #2c5aa0; }  /* Azul empresa */
.doc-title { color: #2c5aa0; }     /* Azul título */
```

### Adicionar Logo:

A logo é carregada automaticamente de:
```php
{{ asset('storage/' . $tenant->logo) }}
```

Para funcionar, execute:
```bash
php artisan storage:link
```

### QR Code:

Atualmente é um placeholder. Para implementar QR Code real:

1. Instalar biblioteca QR Code
2. Gerar QR com dados: número fatura, total, NIF cliente
3. Substituir o placeholder na linha 84 do template

---

## 📝 Notas Importantes

### Fontes:

O template usa `DejaVu Sans` que suporta caracteres portugueses e é incluída no DomPDF por padrão.

### Tamanho do PDF:

- **Largura:** 210mm (A4)
- **Altura:** 297mm (A4)
- **Margens:** 15mm em todos os lados

### Performance:

Para PDFs com muitos produtos (>50), considere paginar a tabela.

### Impressão:

Use `@media print` para ajustar estilos específicos da impressão.

---

## 🐛 Troubleshooting

### PDF não gera:

```bash
# Limpar cache
php artisan optimize:clear
php artisan view:clear
```

### Logo não aparece:

```bash
# Verificar storage link
php artisan storage:link

# Verificar permissões
chmod -R 775 storage/app/public
```

### Erro de relacionamento:

Verifique se os relacionamentos existem no model:
```php
// App\Models\Invoicing\SalesInvoice.php
public function client() { ... }
public function items() { ... }
```

---

## 📚 Referências

- **DomPDF:** https://github.com/barryvdh/laravel-dompdf
- **SAFT-AO:** Decreto Presidencial 312/18
- **AGT Angola:** Portaria nº 31.1/AGT/2020
- **Código IVA Angola:** Lei nº 7/19

---

**Criado:** 2025-10-03  
**Versão:** 1.0.0  
**Status:** ✅ Funcional
