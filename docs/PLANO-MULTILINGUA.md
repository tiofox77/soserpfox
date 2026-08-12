# Plano multi-língua — PT · EN · FR

*Escrito a 13/08/2026, medido contra o código desse dia.*

O sistema fala português de Portugal, escrito directamente nos ecrãs. Este
plano descreve como passa a falar três línguas — português, inglês e francês —
começando pelo módulo de facturação, que é onde os clientes passam o dia.

## O terreno, medido

| O que há | Quanto |
|---|---|
| Blades do módulo de facturação (ecrãs + PDFs) | **142 ficheiros** |
| Componentes Livewire de facturação | **65 ficheiros** |
| Cadeias visíveis nos blades (piso, contado por varrimento) | **~2.900** |
| Mensagens `dispatch('success'/'error'/...)` nos componentes | **90** |
| Uso actual de `__()` / `trans()` / `@lang` | **zero** |
| `lang/` actual | só `pt/` com validação, auth, paginação |
| Colunas de língua (`users.locale`, `tenants.locale`, `clients.locale`) | **nenhuma** |

Com atributos (`placeholder`, `title`, `wire:confirm`) e as mensagens de
validação dos componentes, a conta realista do módulo de facturação fica
entre **3.000 e 3.500 cadeias**.

## As decisões (e porquê)

### 1. O texto português é a chave

```blade
{{ __('Guardar fatura') }}          {{-- e não __('invoicing.buttons.save') --}}
```

Traduções em `lang/en.json` e `lang/fr.json`; **não existe `pt.json`** — o
português é o texto no código, e quando falta uma tradução o Laravel mostra a
chave, ou seja, o português. O sistema nunca mostra `invoicing.buttons.save` a
ninguém.

Porquê assim e não chaves curtas: com ~3.000 cadeias, inventar e manter três
mil nomes de chave é um projecto dentro do projecto, e cada ecrã novo obrigava
a baptizar cada botão. Com o texto como chave, escrever um ecrã novo continua
a ser escrever português — só que dentro de `__()`.

O custo conhecido: mudar uma frase em PT parte a ligação às traduções dela.
Aceita-se, e o detector de faltas (decisão 5) acusa-o no próprio dia.

### 2. De quem é a língua

- **A do ecrã é do utilizador** — `users.locale`, escolhida no perfil e num
  selector no topo. O caixa trabalha em francês e o contabilista em português,
  na mesma empresa.
- **A da empresa é o ponto de partida** — `tenants.locale` define a língua dos
  utilizadores novos e dos documentos, até alguém escolher outra.
- **A do documento é do cliente** — `clients.locale`. Uma proforma para um
  cliente de Kinshasa sai em francês, seja qual for a língua de quem a emitiu.

### 3. O que NUNCA se traduz

**Os documentos fiscais saem em português, sempre.** A facturação certificada
AGT — factura, factura-recibo, nota de crédito, os códigos FT/FR/NC, as
menções legais de isenção, o SAFT-AO — é matéria legal angolana e a língua
oficial é o português. Traduzir uma menção de isenção é fabricar um documento
que a AGT não reconhece.

O que a língua do cliente muda é o **não-fiscal**: proformas, orçamentos,
emails de cobrança, o portal do cliente. Nos fiscais, no máximo, admite-se no
futuro uma segunda linha entre parênteses (bilingue), nunca a substituição.

Também não se traduzem: nomes de planos, "Kz", NIFs, e tudo o que é dado (nomes
de artigos, de clientes — são dos clientes, não nossos).

### 4. A mecânica

- Migração: `users.locale` e `tenants.locale` (`varchar(5)`, nulo = herda),
  `clients.locale` na fase 2.
- Middleware `DefinirLingua`: `app()->setLocale()` + `Carbon::setLocale()` por
  pedido — utilizador → empresa → `pt`. Datas do género `diffForHumans()`
  seguem de borla.
- Selector: bandeira/sigla no topo do layout + campo no perfil. Sem sessão
  (registo, landing): parâmetro `?lang=` guardado em cookie.
- Livewire: as 90 mensagens `dispatch` passam a `__()` dentro do componente —
  o `setLocale` do middleware já correu, portanto funcionam sem mais nada.
- Validação: `lang/en/validation.php` e `lang/fr/validation.php` vêm do pacote
  `laravel-lang/lang` (traduções oficiais, custo zero).
- Números e moeda **não mudam**: `1.234,56 Kz` é formato da casa nas três
  línguas — mudar separadores em documentos com dinheiro é pedir erros de
  leitura.

### 5. O detector de faltas — antes da primeira tradução

Um teste, no feitio do detector de chaves do RH que já existe: varre os blades
e componentes do módulo à procura de `__('...')`, e compara com `en.json` e
`fr.json`. **Cadeia usada sem tradução = teste vermelho com a lista.** É ele
que transforma "acho que está tudo traduzido" em "está tudo traduzido".

Em produção, um listener no evento de tradução falhada regista a chave em
falta no log — apanha o que o varrimento estático não vê (cadeias montadas).

## As fases

### Fase 0 — Fundação *(1 dia, uma pessoa)*

Migração das colunas, middleware, selector no topo e no perfil,
`laravel-lang` para validação EN/FR, o detector de faltas, e **um ecrã piloto**
(sugestão: o de transferências entre armazéns — já bem testado) traduzido de
ponta a ponta para provar o circuito. Critério: mudar a língua no perfil muda
o piloto inteiro, validação incluída, e a suite passa.

### Fase 1 — Facturação, o grosso *(2–3 semanas, ao ritmo de lotes)*

Os 142 blades + 65 componentes, por lotes que espelham o uso:

| Lote | Ecrãs | Peso |
|---|---|---|
| 1 | Vendas: facturas, proformas, recibos, notas de crédito/débito | ~25% |
| 2 | POS (atenção ao offline: cadeias no JS do PWA) | ~15% |
| 3 | Stock: artigos, armazéns, movimentos, transferências | ~20% |
| 4 | Compras + fornecedores | ~15% |
| 5 | Relatórios + definições + o resto | ~25% |

Processo por lote: embrulhar em `__()` (meio-mecânico, com guião: apanhar
texto visível, `placeholder`, `title`, `wire:confirm`, mensagens `dispatch` e
de validação) → detector acusa as faltas → traduzir EN e FR → revisão de
contexto (um "Save" que devia ser "Post", um falso amigo em FR) → lote fechado
quando o detector está verde e a suite passa.

A tradução em si: EN interno com revisão; FR de tradutor com o glossário do
documento fiscal (facture, avoir, bon de livraison...). **Um glossário
PT→EN→FR dos ~40 termos fiscais escreve-se primeiro** e é a referência de
tudo — é ele que evita "invoice" num sítio e "bill" noutro.

### Fase 2 — Documentos na língua do cliente *(1 semana)*

`clients.locale`, ficha do cliente pergunta a língua, e os documentos
**não-fiscais** (proforma, orçamento, extracto de conta) saem na língua dele.
Os fiscais ficam como estão, pela razão da decisão 3.

### Fase 3 — Emails e notificações *(1 semana)*

Os modelos de email e de notificação ganham variante por língua, com o PT como
fallback. O catálogo `ModelosPadrao` que já existe passa a semear as três.

### Fase 4 — O resto do sistema *(a fatiar por módulo)*

Pela ordem de uso real (os sinais de vida da lista de empresas dizem-na):
POS já foi na fase 1; depois RH, tesouraria, hotel/restauração/oficina/salão,
portal do cliente, superadmin por último (o dono da plataforma lê PT).

## Riscos que já se conhecem

- **Cadeias concatenadas** (`'Fatura ' . $n . ' criada'`) não se traduzem bem
  — passar a `__('Fatura :numero criada', ['numero' => $n])`. O varrimento
  estático não as apanha todas; o listener de produção apanha o resto.
- **Plurais**: `trans_choice` onde hoje está "1 artigo(s)".
- **Texto em PHP fora dos componentes** (helpers, serviços, PDFs DomPDF):
  entra no lote do ecrã que o mostra, não fica para o fim.
- **O PWA offline** tem cadeias em JavaScript — precisa de um dicionário JS
  gerado dos mesmos JSON, senão o POS offline fica numa língua e o resto
  noutra.
- **Blades com HTML dentro da cadeia**: partir em cadeias limpas ao embrulhar.

## O que fica feito no fim da fase 1

Um utilizador escolhe EN ou FR no perfil e trabalha o módulo de facturação
inteiro nessa língua — ecrãs, botões, mensagens de sucesso e erro, validação —
com os documentos fiscais em português como a lei manda, e um teste que
rebenta no dia em que alguém acrescentar um ecrã sem tradução.
