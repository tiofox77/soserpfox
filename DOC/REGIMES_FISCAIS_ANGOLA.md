# 🇦🇴 REGIMES FISCAIS EM ANGOLA
## SAFT-AO 2025 - Legislação Angolana

---

## 📋 ÍNDICE
1. [Visão Geral](#visão-geral)
2. [Regimes Disponíveis](#regimes-disponíveis)
3. [Características de Cada Regime](#características-de-cada-regime)
4. [Como Escolher](#como-escolher)
5. [Implementação no Sistema](#implementação-no-sistema)

---

## 🎯 VISÃO GERAL

O sistema de IVA em Angola contempla diferentes regimes fiscais conforme a atividade e características do contribuinte, regulamentados pela AGT (Administração Geral Tributária).

### Base Legal:
- **Lei nº 7/19** - Código do Imposto sobre o Valor Acrescentado
- **Decreto Presidencial 312/18** - Regulamentação SAFT-AO
- **Portaria nº 31.1/AGT/2020** - Software de Faturação

---

## 📊 REGIMES DISPONÍVEIS

### 1. **Regime Geral de IVA** 
   **Código:** `regime_geral`
   
   - ✅ **Regime padrão e mais comum**
   - ✅ Obrigatório para empresas com volume de negócios ≥ 10.000.000 Kz/ano
   - ✅ Permite dedução total do IVA suportado
   - ✅ Taxa normal: 14%
   - ✅ Obrigações: Faturação completa, declarações mensais

---

### 2. **Regime Simplificado**
   **Código:** `regime_simplificado`
   
   - 📋 Para pequenos contribuintes
   - 📋 Volume de negócios < 10.000.000 Kz/ano
   - 📋 IVA calculado de forma simplificada
   - 📋 Menos obrigações declarativas
   - 📋 Não permite dedução integral do IVA

   **Características:**
   - Declaração trimestral em vez de mensal
   - Contabilidade simplificada aceite
   - Faturação obrigatória mas simplificada

---

### 3. **Regime de Isenção**
   **Código:** `regime_isencao`
   
   - 🆓 Para atividades isentas de IVA
   - 🆓 Não cobram IVA nas vendas
   - 🆓 Não deduzem IVA nas compras
   
   **Atividades isentas (exemplos):**
   - Saúde e educação
   - Serviços financeiros e seguros
   - Transportes públicos
   - Produtos básicos (pão, arroz, etc.)
   - Jornais e livros

---

### 4. **Regime de Não Sujeição**
   **Código:** `regime_nao_sujeicao`
   
   - ⭕ Para operações fora do âmbito do IVA
   - ⭕ Não estão sujeitas ao imposto
   - ⭕ Diferentes de isentas
   
   **Exemplos:**
   - Operações fora de Angola
   - Transmissão de bens por morte
   - Certas operações financeiras
   - Doações entre familiares

---

### 5. **Regime Misto**
   **Código:** `regime_misto`
   
   - 🔀 Combina operações tributadas e isentas
   - 🔀 Empresa realiza atividades de diferentes regimes
   - 🔀 Dedução proporcional do IVA
   
   **Exemplo:**
   - Empresa que vende produtos (14% IVA) e serviços educacionais (isento)
   - Cálculo: Pro-rata de dedução do IVA

---

## 🎯 CARACTERÍSTICAS DE CADA REGIME

### Comparativo Rápido:

| Regime | Taxa IVA | Dedução IVA | Declaração | Faturação |
|--------|----------|-------------|------------|-----------|
| **Geral** | 14% | ✅ Total | Mensal | Completa |
| **Simplificado** | 14% | ⚠️ Parcial | Trimestral | Simplificada |
| **Isenção** | 0% | ❌ Não | Mensal | Obrigatória |
| **Não Sujeição** | N/A | ❌ Não | Conforme | Sim |
| **Misto** | Variável | ✅ Proporcional | Mensal | Completa |

---

## 💡 COMO ESCOLHER O REGIME

### ✅ **Escolha Regime Geral se:**
- Volume de negócios > 10.000.000 Kz/ano
- Empresa quer deduzir todo o IVA das compras
- Realiza operações comerciais normais
- **É o regime PADRÃO para a maioria das empresas**

### 📋 **Escolha Regime Simplificado se:**
- Pequeno negócio (< 10.000.000 Kz/ano)
- Quer menos burocracia
- Contabilidade simplificada
- Aceita não deduzir todo o IVA

### 🆓 **Escolha Regime de Isenção se:**
- Sua atividade está legalmente isenta
- Exemplos: Escola, hospital, banco, seguradora
- **ATENÇÃO:** Não é uma escolha livre, depende da atividade

### ⭕ **Escolha Regime de Não Sujeição se:**
- Suas operações não estão no âmbito do IVA
- Exportações, doações específicas
- **Consulte contador para confirmar**

### 🔀 **Escolha Regime Misto se:**
- Realiza diferentes tipos de operações
- Parte tributada a 14%, parte isenta
- Exemplo: Loja que vende produtos e dá formações

---

## 💻 IMPLEMENTAÇÃO NO SISTEMA

### Onde é Usado:

1. **Criação de Empresa**
   - Ao criar nova empresa no sistema
   - Campo obrigatório: Regime Fiscal
   - Default: Regime Geral

2. **Faturação**
   - O regime influencia os cálculos
   - Determinação de taxas de IVA
   - Regras de dedução

3. **SAFT-AO (Exportação Fiscal)**
   - Campo obrigatório no XML
   - Tag: `<TaxRegistrationBasis>`
   - Valores conforme regime escolhido

### Códigos no Sistema:

```php
// Valores aceites
'regime_geral'          // Regime Geral de IVA
'regime_simplificado'   // Regime Simplificado
'regime_isencao'        // Regime de Isenção
'regime_nao_sujeicao'   // Regime de Não Sujeição
'regime_misto'          // Regime Misto
```

### Estrutura na Base de Dados:

```sql
-- Tabela: tenants
ALTER TABLE tenants ADD COLUMN regime VARCHAR(50) DEFAULT 'regime_geral'
COMMENT 'Regime Fiscal SAFT-AO 2025';
```

### Validação:

```php
$this->validate([
    'newCompanyRegime' => 'required|in:regime_geral,regime_simplificado,regime_isencao,regime_nao_sujeicao,regime_misto',
]);
```

---

## 🔍 PERGUNTAS FREQUENTES

### **Q: Posso mudar de regime depois?**
**A:** Sim, mas precisa comunicar à AGT. Existem prazos específicos.

### **Q: Qual o regime mais comum?**
**A:** Regime Geral. É o padrão para empresas comerciais.

### **Q: Regime Simplificado é melhor?**
**A:** Depende. Menos burocracia, mas não deduz todo o IVA.

### **Q: Como sei se minha atividade é isenta?**
**A:** Consulte o Código do IVA (Lei nº 7/19, Artigo 9º).

### **Q: Posso ter mais de um regime?**
**A:** Não. Cada empresa tem um regime, mas pode ser Misto se fizer operações diferentes.

---

## 📚 REFERÊNCIAS LEGAIS

### Legislação Principal:
- **Lei nº 7/19** - Código do IVA
- **Decreto Presidencial 312/18** - SAFT-AO
- **Portaria nº 31.1/AGT/2020** - Software de Faturação

### Entidades:
- **AGT** - Administração Geral Tributária (www.agt.minfin.gov.ao)
- **Ministério das Finanças**

### Taxas de IVA em Angola:
- **Taxa Normal:** 14%
- **Taxa Reduzida:** 7% (produtos específicos)
- **Taxa Zero:** 0% (exportações)
- **Isentos:** Conforme lei

---

## ⚠️ AVISOS IMPORTANTES

1. ✅ **Escolha com cuidado**: O regime afeta toda a faturação
2. ✅ **Consulte contador**: Para casos específicos
3. ✅ **Documentação AGT**: Pode exigir comprovação do regime
4. ✅ **SAFT-AO**: Regime aparece obrigatoriamente no ficheiro fiscal
5. ✅ **Mudança de regime**: Requer comunicação à AGT

---

## 📝 CHECKLIST DE ESCOLHA

Antes de escolher, confirme:

- [ ] Volume de negócios anual estimado
- [ ] Tipo de atividade (comercial, serviços, mista)
- [ ] Se atividade está isenta por lei
- [ ] Se quer deduzir IVA das compras
- [ ] Capacidade de cumprir obrigações declarativas
- [ ] Consultou contador/advogado

---

**Documento atualizado:** 2025-01-03  
**Versão:** 1.0.0  
**Conforme:** SAFT-AO 2025, Lei nº 7/19  
**Status:** ✅ Implementado no Sistema
