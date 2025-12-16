# Ajustes de Margens e Dimensionamento do PDF (DomPDF)

Data: 28/10/2025  
Arquivo: `resources/views/pdf/invoicing/sales-invoice.blade.php`

## Problema Identificado

O PDF gerado pelo DomPDF estava cortando conteúdo nas laterais e criando uma segunda página desnecessária.

## Solução Implementada

Foi criado um sistema de **variáveis CSS personalizadas** no início do arquivo que permite ajustar facilmente:

### 1. Variáveis de Margem do Papel A4

```css
--margin-top: 7mm;        /* Margem superior */
--margin-right: 10mm;     /* Margem direita */
--margin-bottom: 6mm;     /* Margem inferior */
--margin-left: 10mm;      /* Margem esquerda */
```

### 2. Controle de Altura do Conteúdo

```css
--max-content-height: 275mm;  /* Altura máxima para evitar 2ª página */
```

### 3. Espaçamentos Internos

```css
--header-spacing: 10px;     /* Espaço após cabeçalho */
--section-spacing: 8px;     /* Espaço entre seções */
--footer-spacing: 12px;     /* Espaço antes do footer */
```

## Como Ajustar

### Para resolver conteúdo passando para 2ª página:

1. **Reduza a altura máxima**:
   ```css
   --max-content-height: 273mm;  /* ou 270mm, 268mm */
   ```

2. **Reduza as margens**:
   ```css
   --margin-top: 6mm;
   --margin-bottom: 5mm;
   ```

3. **Reduza os espaçamentos internos**:
   ```css
   --header-spacing: 8px;
   --section-spacing: 6px;
   --footer-spacing: 10px;
   ```

### Para ajustar largura (conteúdo cortado lateralmente):

1. **Reduza as margens laterais**:
   ```css
   --margin-left: 8mm;
   --margin-right: 8mm;
   ```

### Para aumentar espaço do footer:

1. **Aumente o footer-spacing**:
   ```css
   --footer-spacing: 20px;  /* mais espaço antes do footer */
   ```

2. **Reduza outros espaçamentos para compensar**:
   ```css
   --header-spacing: 8px;
   --section-spacing: 6px;
   ```

## Valores Recomendados

### Configuração Padrão (atual):
- margin-top: 7mm
- margin-right: 10mm
- margin-bottom: 6mm
- margin-left: 10mm
- max-content-height: 275mm
- header-spacing: 10px
- section-spacing: 8px
- footer-spacing: 12px

### Configuração Compacta (para faturas com muitos itens):
- margin-top: 6mm
- margin-right: 8mm
- margin-bottom: 5mm
- margin-left: 8mm
- max-content-height: 272mm
- header-spacing: 8px
- section-spacing: 6px
- footer-spacing: 10px

### Configuração com Mais Espaço no Footer:
- margin-top: 6mm
- margin-right: 10mm
- margin-bottom: 8mm
- margin-left: 10mm
- max-content-height: 273mm
- header-spacing: 8px
- section-spacing: 6px
- footer-spacing: 18px

## Área de Conteúdo Utilizável

A área útil é **calculada automaticamente**:
- **Largura**: `210mm - margin-left - margin-right`
- **Altura**: `297mm - margin-top - margin-bottom`

Com as configurações padrão:
- Largura útil: 190mm (210 - 10 - 10)
- Altura útil: 284mm (297 - 7 - 6)

## Otimizações Aplicadas

1. **Tamanhos de fonte reduzidos**:
   - Tabelas: 9px → 8px
   - Footer: 8px → 7px
   - System info: 8px → 7px
   - AGT description: 8px → 7px

2. **Espaçamentos otimizados**:
   - Todas as seções usam variáveis CSS
   - Padding da summary section: 10px → 8px
   - Client info padding: 10px → 8px

3. **Controle de overflow**:
   - `overflow: hidden` no page-wrapper
   - `max-height` definido
   - `word-wrap: break-word` nas tabelas

## Teste e Validação

Para testar as alterações:
1. Acesse: `http://soserp.test/invoicing/sales/invoices/{id}/preview`
2. Clique em **"Gerar PDF"**
3. Verifique se o conteúdo cabe em 1 página
4. Ajuste as variáveis conforme necessário

## Notas Importantes

- O DomPDF tem limitações de CSS (não suporta todos os recursos)
- Sempre teste com faturas que tenham muitos itens
- A impressão do navegador pode diferir do PDF gerado
- Mantenha um backup antes de fazer alterações
