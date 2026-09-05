# SOSERP - PRD do Modulo Restaurante

> Planeamento funcional e tecnico para implementacao do modulo `restaurant`.
> Este documento complementa `arquitetura/PRD.md` e `arquitetura/arquitetura.html`.
>
> Estado: **Módulo operacional concluído; extensões opcionais e testes de carga permanecem no roadmap**  
> Data: 2026-08-02  
> Regra principal: o Restaurante gere a operacao; a Facturacao continua a ser o unico emissor fiscal.

---

## 1. Objectivo

Criar um modulo para restaurantes, bares, pastelarias e estabelecimentos de atendimento a mesa, balcao, take-away e entrega. O modulo deve controlar sala, mesas, comandas, cozinha, receitas, consumo de stock, pagamentos e fecho, reutilizando integralmente os motores fiscais, financeiros e multi-tenant ja existentes.

O modulo nao sera um segundo POS nem uma segunda Facturacao. Sera uma camada operacional especializada que entrega a venda fechada aos servicos centrais:

- `ModuleInvoiceService`: cria FT/FR, linhas fiscais, totais SAFT, hash e submissao AGT;
- `TaxResolver`: resolve IVA, regime, isencao e regiao fiscal por linha;
- Facturacao: clientes, artigos, series, armazens, stock, documentos, NC/ND e SAFT-AO;
- Tesouraria: metodos de pagamento, caixas, contas, transaccoes e turnos;
- Contabilidade: recebe os documentos e movimentos pelos integradores existentes;
- Notificacoes: avisos de reserva, pedido pronto e falhas operacionais.

## 2. Principios inviolaveis

1. Todas as tabelas de negocio usam `tenant_id`, o trait `BelongsToTenant` e indices unicos compostos por tenant.
2. O tenant activo vem de `activeTenantId()`. Nunca usar directamente `auth()->user()->tenant_id`.
3. Uma comanda nao e documento fiscal. Pode ser alterada enquanto aberta e deve possuir historico de auditoria.
4. Uma FT/FR definitiva (`invoice_status = F`) nao pode ser editada nem apagada. Correcao posterior faz-se por NC/ND.
5. O Restaurante nunca chama `SalesInvoice::create()` directamente. Toda emissao passa por `ModuleInvoiceService::emitir()`.
6. O imposto nunca e fixado no modulo. Cada linha usa um `product_id` do catalogo e `TaxResolver`.
7. As series pertencem ao tenant e sao escolhidas pelo mecanismo da Facturacao. Com AGT activa, devem estar activas e registadas no ambiente correcto.
8. Venda paga no acto emite normalmente **FR**. Venda a credito emite **FT**. O pagamento posterior de FT gera **RC**; uma FR nao gera recibo duplicado.
9. O dinheiro entra exclusivamente pela Tesouraria. Nenhum saldo de caixa sera actualizado por codigo paralelo do Restaurante.
10. Emissao, ligacao das linhas faturadas, pagamento e movimento financeiro ficam numa transaccao local; comunicacao AGT ocorre apenas com `DB::afterCommit`.
11. Operacoes offline usam UUID/idempotency key unica por `(tenant_id, local_uuid)` e nunca duplicam facturas ou pagamentos.
12. Activar `restaurant` activa obrigatoriamente `invoicing`, que activa `treasury`.

## 3. Escopo funcional

### 3.1 Configuracao

- estabelecimentos/filiais operacionais dentro do tenant;
- zonas de sala: interior, esplanada, VIP, bar, take-away;
- mesas, capacidade, estado, QR opcional e ponto de impressao;
- estacoes de producao: cozinha, bar, pastelaria, grelha;
- impressoras/KDS por estacao;
- armazem de consumo por estabelecimento;
- series FT/FR apenas por referencia a `invoicing_series`;
- cliente, taxa, moeda, caixa e metodo de pagamento por omissao vindos dos modulos centrais;
- regras de servico: taxa de servico, gorjeta, couvert e arredondamento, sempre separadas das regras fiscais.

### 3.2 Sala e mesas

- mapa visual da sala, responsivo e utilizavel em tablet;
- estados: `livre`, `reservada`, `ocupada`, `aguarda_cozinha`, `servida`, `pede_conta`, `limpeza`, `bloqueada`;
- abrir mesa com empregado, numero de pessoas e cliente opcional;
- juntar, transferir e separar mesas sem alterar documentos fiscais ja emitidos;
- varias comandas por mesa e uma comanda sem mesa para balcao/take-away/delivery;
- reserva de mesa com tolerancia, no-show e contacto do cliente.

### 3.3 Comandas

- estados: `draft`, `confirmed`, `in_preparation`, `ready`, `served`, `partially_billed`, `billed`, `cancelled`;
- itens com produto, quantidade, preco capturado, desconto autorizado, observacoes, modificadores e estacao;
- cursos/tempos: entradas, pratos, sobremesas e bebidas;
- envio total ou parcial para cozinha;
- acrescentar itens depois do primeiro envio;
- cancelamento antes da producao com motivo e permissao;
- desperdicio/devolucao depois da producao como movimento auditado, nunca apagando silenciosamente consumo;
- historico de cada alteracao: utilizador, terminal, data, valor anterior e novo.

### 3.4 Cozinha e bar (KDS)

- filas separadas por estacao com tempo decorrido e prioridade;
- estados por item: `queued`, `accepted`, `preparing`, `ready`, `served`, `voided`;
- chamada em tempo real para sala e fallback por polling;
- impressao de ticket sem preco, configuravel;
- reimpressao marcada como copia;
- painel de tempos medios, atrasos e itens devolvidos.

### 3.5 Menu, receitas e stock

- cada item vendavel e obrigatoriamente um `Product` da Facturacao;
- categorias e precos reutilizam o catalogo central;
- variantes/modificadores podem ser produtos ou componentes de receita;
- ficha tecnica define ingredientes e quantidades por dose;
- ingredientes tambem sao artigos do catalogo, com unidade e stock;
- reserva de stock ao confirmar a comanda (configuravel);
- consumo efectivo ao enviar para producao;
- cancelamento antes da preparacao liberta a reserva;
- cancelamento depois do consumo gera movimento de desperdicio/devolucao, conforme permissao;
- nunca alterar directamente `invoicing_stocks.quantity`: usar o servico/movimento canonico de stock;
- suportar conversao de unidades (kg/g, l/ml, unidade/dose) com precisao decimal.

## 4. Fluxo operacional principal

1. O empregado abre mesa/comanda.
2. Adiciona produtos do catalogo e modificadores.
3. O servidor valida tenant, artigo activo, preco, imposto e disponibilidade.
4. Ao confirmar, a comanda recebe numero unico do tenant/estabelecimento e reserva stock.
5. Ao enviar, cada item entra na estacao correcta e o consumo da receita e registado.
6. A cozinha prepara, marca pronto e a sala marca servido.
7. Ao pedir conta, o sistema calcula apenas itens ainda nao faturados.
8. O caixa escolhe cliente, tipo de documento e pagamentos.
9. `RestaurantCheckoutService` bloqueia a comanda, valida turno/caixa e chama `ModuleInvoiceService::emitir()` com:
   - `tenant_id` da sessao;
   - `client_id` pertencente ao mesmo tenant;
   - linhas com `product_id`, quantidade, preco, desconto e `is_service`;
   - `invoice_type = FR` se integralmente paga no acto, ou `FT` se ficar credito;
   - `origem_modulo = restaurant`;
   - `origem = CMD-{numero}`.
10. As quantidades faturadas sao ligadas às linhas da comanda numa tabela de juncao.
11. O servico financeiro regista pagamentos mistos na Tesouraria e associa-os ao documento/turno.
12. A transaccao local faz commit; so depois ocorre submissao AGT.
13. A mesa so volta a `livre` quando nao restarem itens, valores ou pagamentos pendentes e o operador confirmar limpeza/fecho.

## 5. Faturacao e pagamentos

### 5.1 Escolha do documento

| Situacao | Documento | Regra |
|---|---|---|
| Pagamento integral no acto | FR | Documento fiscal e quitacao no mesmo acto |
| Consumo a credito/conta corrente | FT | Fica saldo em aberto |
| Pagamento posterior da FT | RC | Usa o fluxo de recebimento central |
| Correcao para reduzir documento definitivo | NC | Referencia obrigatoriamente o documento original |
| Correcao para acrescer valor | ND | Referencia obrigatoriamente o documento original |
| Cotacao/menu para evento | Proforma | Nao fiscal e nao baixa stock |

### 5.2 Divisao de conta

- uma comanda pode originar varias FT/FR;
- dividir por pessoa, item, quantidade ou valor proporcional;
- cada unidade/quantidade so pode ser faturada uma vez;
- `restaurant_order_item_billings` guarda `order_item_id`, `sales_invoice_item_id`, quantidade e valor consumidos;
- bloquear as linhas com `SELECT ... FOR UPDATE` durante o checkout;
- o saldo operacional resulta das quantidades ainda nao faturadas, nunca apenas de `order.invoice_id`;
- nao criar linha negativa nem desconto global para representar uma divisao.

### 5.3 Pagamentos mistos

- aceitar dinheiro, TPA/Multicaixa, transferencia, carteira digital, voucher e conta corrente conforme os metodos activos da Tesouraria;
- cada parcela cria um detalhe de pagamento e uma unica transaccao financeira;
- validar soma das parcelas contra o valor a liquidar;
- troco apenas em metodo `cash` e nao como pagamento negativo;
- gorjeta e taxa de servico devem ter configuracao contabilistica e fiscal explicita;
- reprocessamento usa chave idempotente e reutiliza o resultado anterior.

## 6. Modelo de dados proposto

Todas as tabelas abaixo incluem `id`, `tenant_id`, timestamps e indices por tenant.

| Tabela | Responsabilidade | Restricoes principais |
|---|---|---|
| `restaurant_settings` | Configuracao por tenant | `UNIQUE(tenant_id)` |
| `restaurant_venues` | Estabelecimentos/pontos de venda | `UNIQUE(tenant_id, code)` |
| `restaurant_areas` | Zonas da sala | `UNIQUE(tenant_id, venue_id, name)` |
| `restaurant_tables` | Mesas e mapa | `UNIQUE(tenant_id, venue_id, code)` |
| `restaurant_reservations` | Reservas de mesa | indice por tenant/data/estado |
| `restaurant_orders` | Cabecalho da comanda | `UNIQUE(tenant_id, order_number)` e `UNIQUE(tenant_id, local_uuid)` |
| `restaurant_order_items` | Itens e snapshot comercial | FK para `products`, estado e quantidade |
| `restaurant_order_item_modifiers` | Extras/remocoes | FK para item e produto/componente |
| `restaurant_order_events` | Auditoria imutavel | sem update/delete pela aplicacao |
| `restaurant_kitchen_stations` | Cozinha/bar/pastelaria | `UNIQUE(tenant_id, venue_id, code)` |
| `restaurant_kitchen_tickets` | Envios para KDS | `UNIQUE(tenant_id, ticket_number)` |
| `restaurant_kitchen_ticket_items` | Estado de producao | FK para order_item |
| `restaurant_recipes` | Ficha tecnica do produto vendavel | `UNIQUE(tenant_id, product_id)` |
| `restaurant_recipe_items` | Ingredientes/quantidades | FK para products |
| `restaurant_order_item_billings` | Quantidade ja faturada | FK para item e sales_invoice_item |
| `restaurant_payment_attempts` | Idempotencia do checkout | `UNIQUE(tenant_id, idempotency_key)` |

Nao criar tabelas duplicadas para clientes, produtos, impostos, armazens, stocks, series, facturas, recibos ou metodos de pagamento.

## 7. Servicos e fronteiras

### Novos servicos

- `RestaurantOrderService`: abertura, adicao, transferencia, divisao e estados da comanda;
- `RestaurantKitchenService`: tickets e estados da producao;
- `RestaurantRecipeService`: explosao de receitas e conversao de unidades;
- `RestaurantStockService`: reservas, consumos, libertacoes e desperdicios usando Facturacao;
- `RestaurantCheckoutService`: orquestracao atomica de faturacao + pagamento;
- `RestaurantAuthorizationService`: limites de desconto, voids e aprovacoes;
- `RestaurantMetricsService`: tempos, vendas, ticket medio e desperdicio.

### Servicos centrais que devem ser reutilizados

- `ModuleInvoiceService` e `TaxResolver`;
- mecanismo canonico de series de `InvoicingSeries`/`SalesInvoice`;
- servico de stock e `StockMovement`;
- servico de pagamentos/transaccoes da Tesouraria (deve ser extraido do componente Livewire se ainda estiver preso a `PaymentModal`);
- jobs e servicos AGT;
- integracao Facturacao -> Contabilidade.

### Divida tecnica a resolver antes do checkout

Actualmente parte da gravacao financeira vive em `Livewire\Invoicing\PaymentModal`. Antes de implementar o Restaurante, extrair essa logica para um servico de dominio reutilizavel, com idempotencia e transaccao. O componente Livewire e o Restaurante chamam o mesmo servico; nenhum deve actualizar saldos directamente.

## 8. Multi-tenant, planos e permissoes

### Modulo e dependencia

```php
'restaurant' => ['invoicing'], // invoicing resolve tambem treasury
```

Registar `restaurant` no catalogo de modulos e activa-lo apenas por `TenantModuleSyncService::activateModule()`. O seeder de pre-requisitos cria configuracao, estabelecimento, zona, armazem/caixa por omissao e papeis, sem inventar impostos ou formas de pagamento.

### Permissoes iniciais

- `restaurant.dashboard.view`
- `restaurant.floor.view`, `restaurant.floor.manage`
- `restaurant.orders.view`, `.create`, `.edit`, `.transfer`, `.split`, `.cancel`
- `restaurant.kitchen.view`, `.manage`
- `restaurant.checkout.view`, `.charge`, `.discount`, `.refund`
- `restaurant.reservations.view`, `.create`, `.edit`, `.cancel`
- `restaurant.menu.view`, `.manage`
- `restaurant.recipes.view`, `.manage`
- `restaurant.stock.view`, `.waste`
- `restaurant.reports.view`
- `restaurant.settings.view`, `.edit`

### Roles sugeridas

- Administrador Restaurante;
- Gerente de Sala;
- Empregado de Mesa;
- Caixa Restaurante;
- Cozinha/Bar;
- Gestor de Stock.

O mapa canonico deve entrar em `getDefaultRolePermissionMap()` e no sincronizador de permissoes. O menu usa `canAccessModuleMenu('restaurant')`.

## 9. UI/UX e navegacao

Menu proposto:

1. Dashboard
2. Sala e Mesas
3. Comandas
4. Cozinha / KDS
5. Reservas
6. Menu e Produtos
7. Receitas / Fichas Tecnicas
8. Stock e Desperdicios
9. Caixa / Fecho
10. Relatorios
11. Configuracoes

Requisitos:

- Livewire-first, conforme a arquitectura do sistema;
- operacao principal optimizada para tablet e toque;
- cores de estado acessiveis, mas nunca dependentes apenas da cor;
- botoes grandes para sala/cozinha e confirmacao para operacoes irreversiveis;
- actualizacao em tempo real com indicador de ligacao;
- modo offline limitado a abertura/edicao de comandas e pagamentos preparados, com sincronizacao idempotente;
- emissao fiscal offline segue a mesma fila segura do POS e nao cria numeracao local paralela.

## 10. Relatorios

- vendas por estabelecimento, zona, mesa, empregado, hora e canal;
- ticket medio, ocupacao e rotacao de mesas;
- tempos de aceitacao, preparacao, pronto e entrega;
- produtos, categorias, modificadores e combinacoes mais vendidos;
- consumo teorico por receita versus consumo real;
- desperdicios, cancelamentos, ofertas e descontos por autorizador;
- pagamentos por metodo e conciliacao com turno/caixa;
- documentos FR/FT/RC/NC/ND por origem `restaurant`;
- margem estimada por prato, usando custo dos ingredientes;
- exportacao contabilistica e fiscal sempre baseada nos documentos centrais.

## 11. Fases de implementacao

### Estado em 2026-08-02

- [x] módulo `restaurant` registado com dependência de `invoicing`;
- [x] permissões, prefixos de menu e três rotas protegidas;
- [x] tabelas multi-tenant de configurações, estabelecimentos, zonas, mesas, comandas, linhas e eventos;
- [x] modelos com `BelongsToTenant` e relações principais;
- [x] serviço de abertura idempotente, artigos, totais via `TaxResolver` e confirmação para cozinha;
- [x] Dashboard, Sala/Mesas e Comandas em Livewire, responsivos para tablet;
- [x] testes de isolamento, idempotência, imposto, estados e renderização;
- [x] KDS operacional com tickets, fila por estação, tempos e estados até entrega;
- [x] checkout integral com FR/FT, vínculo por quantidades, idempotência e entrada na Tesouraria;
- [x] reservas com CRUD, conflitos de horário, capacidade, no-show e libertação de mesa;
- [x] divisão por itens e pagamentos mistos com validação de soma;
- [x] fichas técnicas, rendimento, ingredientes, consumo e desperdício via movimentos canónicos;
- [x] configurações e relatórios operacionais/financeiros;
- [x] transferência e junção de mesas/comandas com bloqueio transacional e auditoria;
- [x] anulação pós-produção com motivo obrigatório, estado `voided` e desperdício auditável;
- [x] CRUD operacional de estabelecimentos, zonas, mesas e estações por tenant;
- [x] API protegida pelo plano com snapshot, catálogo, sala, comandas, transferência, junção, anulação e checkout;
- [x] validação no navegador interno e suíte completa: 108 testes / 225 asserções.
- [x] cache de aplicação/views reconstruído e KDS revalidado após 500 transitório de vista compilada antiga;
- [x] página de desperdícios distingue movimentos manuais de anulações depois da produção.
- [x] fichas técnicas com pesquisa, modos grelha/lista, custo estimado, estados e editor responsivo;
- [x] módulo e plano Restaurante apresentados na landing pública;
- [x] `Pacote Restaurante` inclui Restaurante + Faturação + Tesouraria;
- [x] FOX Friendly gratuito inclui Restaurante e sincroniza também subscrições gratuitas existentes.

### Validação operacional final

- `php artisan optimize:clear` e `php artisan view:cache` executados após as alterações Livewire;
- `/restaurant/kitchen` validado no navegador com fila, ticket, artigos, impressão, reimpressão e ação **Aceitar**;
- `/restaurant/settings` validado com criação, edição modal, ativação/desativação e eliminação protegida por histórico;
- `/restaurant/orders` validado com transferência de mesa, junção de comandas e checkout;
- `/restaurant/stock` validado sem erro e com separação entre desperdício manual e anulação pós-produção;
- suíte integral: **108 testes e 225 asserções aprovadas**.

### Fase 0 - Fundacoes e contratos

- [x] catalogar modulo, dependencia e permissoes;
- [x] criar roles especializadas do Restaurante;
- [x] checkout financeiro reutilizável e independente do `PaymentModal`;
- [x] serviço canónico de movimentos de stock/receitas;
- [x] testes de contrato de `ModuleInvoiceService` para origem `restaurant`;
- [x] migrations iniciais com FKs, tenant scope e indices compostos.

**Saida:** arquitectura aprovada e nenhum caminho paralelo de faturacao/pagamento.

### Fase 1 - MVP sala, comanda e cozinha — concluída

- [x] configuração, zonas, mesas e estabelecimentos;
- [x] abertura/edição de comanda;
- [x] menu vindo de `products`/`categories`;
- [x] tickets KDS e estados;
- [x] auditoria e permissões;
- [x] responsividade tablet/mobile.

**Saida:** pedido completo da mesa ate prato servido, ainda sem fecho fiscal.

### Fase 2 - Checkout fiscal e Tesouraria — concluída

- [x] checkout com FR/FT;
- [x] pagamentos simples e mistos;
- [x] séries AGT, cliente e Consumidor Final reutilizados da Faturação;
- [x] movimentos da Tesouraria e turno;
- [x] PDF/impressão e submissão AGT pelo motor central;
- [x] divisão de conta e faturação parcial;
- [x] NC/ND pelo módulo central.

**Saida:** venda fiscal de ponta a ponta, sem duplicacao.

### Fase 3 - Receitas e stock — concluída

- [x] fichas técnicas, unidades e rendimento;
- [x] reserva/consumo/libertação;
- [x] desperdícios manuais e pós-produção;
- [x] disponibilidade e alertas de rutura.

### Fase 4 - Reservas, offline e integracoes

- [x] reservas de mesa (CRUD, conflitos, capacidade, no-show, libertação);
- [x] API tablet/offline com UUID e checkout idempotente;
- [x] **restaurante offline no PWA** — sala, comanda e recebimento sem rede,
      com a comanda a subir inteira numa só viagem (`ComandaOffline`);
- [x] **menu online público e QR por mesa** — carta em `/menu/{slug}`, QR com
      logótipo e zona segura da máscara, pedido por WhatsApp com a mesa à
      cabeça, e pedido pela própria página a entrar no ecrã da sala;
- lista de espera;
- impressoras de cozinha;
- KDS em tempo real (hoje é `wire:poll.15s`);
- notificacoes de reserva e pedido pronto;
- API para app movel.

### Fase 5 - Analitica e endurecimento

- [x] dashboards e relatorios — painel com consumo por dia, venda por hora,
      pratos mais pedidos, estado da sala e receita por mesa;
- [x] testes de falha de rede — suíte de browser com rede cortada ao nível do
      browser E do sistema (modo avião no Android), e ensaio de ponta a ponta
      em PRODUÇÃO com cinco empregados;
- [x] auditoria de isolamento multi-tenant (ver `MenuOnlineTest`: a carta
      pública nunca mostra pratos de outra empresa);
- carga, concorrencia e multiplos terminais;
- piloto controlado e rollout por feature flag.

### Validação em produção — 2026-08-28

Empresa de ensaio criada em produção (`bancada:producao`) com gerente e cinco
empregados, cada um com o seu PIN. Ensaio de ponta a ponta num Android real
apontado a `soserp.vip`, **17 de 17 passos sem falha**:

- turno aberto do aparelho e sincronizado (`POS-2026-001`);
- cliente criado com rede e sem rede, ambos repostos com o id do servidor;
- venda com rede, venda sem rede, e sincronizar duas vezes sem duplicar;
- proforma, factura e factura-recibo — os três a subir com número;
- comanda numa mesa **sem rede**, recebida e facturada (`FR FR/000015`);
- os cinco PIN a abrir offline, e o PIN de um a ser recusado na conta de outro;
- **17 documentos, cadeia de assinaturas intacta** (`agt:verificar-cadeia`).

Defeito encontrado e corrigido nesse ensaio: todo o cliente criado sem
contribuinte era **engolido em silêncio** — o `999999999` do POS batia com o
Consumidor Final na desduplicação por NIF, e o servidor devolvia-o em vez de
criar o cliente. Sem erro, sem fila parada. Corrigido com `nif` nulável e um
NIF genérico que deixa de identificar (ver `ClienteDoPwaTest`).

### Roadmap não bloqueante

O núcleo operacional está concluído e validado em produção. Por ordem do que
vale mais, o que falta:

1. **Takeaway e delivery.** A coluna `channel` das comandas já aceita
   `takeaway` e `delivery` — mas não há ecrã nem fluxo para nenhum dos dois. A
   base promete um canal que o produto não sabe operar, e é aí que está o
   crescimento de um restaurante hoje. **É o maior buraco.**
2. **KDS em tempo real.** Hoje `wire:poll.15s`: a cozinha pode ver um pedido
   quinze segundos depois de entrar. Numa casa cheia nota-se.
3. **Impressora de cozinha.** Não existe. Um KDS num ecrã serve, mas muitas
   cozinhas querem o papel na bancada.
4. **Gorjeta / taxa de serviço.** Não existe em lado nenhum — e é dinheiro que
   passa pela caixa sem ficar registado.
5. **Lista de espera.** O par natural das reservas numa casa que enche.
6. **Notificações** de reserva confirmada e de pedido pronto (há para o hotel,
   não para o restaurante).
7. **Testes de carga** com vários terminais em simultâneo — é o que ainda não
   sabemos: o módulo nunca foi posto sob concorrência real.
8. Aplicação móvel dedicada, sobre a API já disponível.

### Regras de plano e publicação

- `pacote-restaurante`: 14.900 Kz/mês, 10 utilizadores, 1 empresa, 5 GB e 14 dias de trial;
- módulos efetivos: `restaurant`, `invoicing` e `treasury` (Tesouraria entra pela dependência da Faturação);
- `fox-friendly`: gratuito/promocional e auto-ativado, contém todos os módulos do catálogo;
- ao surgir um módulo novo, executar `php artisan create:fox-friendly-plan` para atualizar o JSON,
  o pivot do plano e os tenants FOX Friendly com subscrição ativa;
- planos pagos permanecem pendentes até validação do Super Admin; nenhum módulo deve ser ativado antes disso.

## 12. Criterios de aceitacao criticos

1. Dois tenants podem usar os mesmos codigos de mesa/comanda sem colisao e nunca veem dados um do outro.
2. Uma comanda dividida em tres pagamentos/documentos nao fatura nenhuma quantidade duas vezes.
3. FR paga no acto gera uma factura e movimentos financeiros correctos, sem RC duplicado.
4. FT a credito nao e marcada paga; o recebimento posterior cria RC e Tesouraria.
5. Tenant em regime geral, simplificado e nao sujeicao produz impostos correctos sem constantes no Restaurante.
6. Linha a 0% leva codigo e motivo de isencao validos.
7. Documento definitivo nao pode ser alterado/apagado; correccao usa NC/ND.
8. Falha da AGT nao perde a venda nem duplica submissao; o estado fica pendente e pode ser retomado.
9. Repetir o mesmo checkout offline devolve o documento anterior, sem nova factura ou transaccao.
10. Fecho de turno e mesa e bloqueado enquanto existirem comandas, documentos ou sincronizacoes pendentes.
11. Cancelar item ja preparado nao apaga consumo; cria desperdicio auditado.
12. Todas as facturas aparecem nos relatorios de Facturacao, Tesouraria, Contabilidade e nos filtros por `source_module = restaurant`.

## 13. Testes obrigatorios

- unitarios: estados, calculos de saldo, receitas, unidades, autorizacoes;
- integracao: ModuleInvoiceService, TaxResolver, series, stock e Tesouraria;
- concorrencia: dois caixas a fechar/dividir a mesma comanda;
- idempotencia: repeticao HTTP/offline e timeout apos commit;
- multi-tenant: FKs logicas e queries manuais por tenant;
- fiscal: FR, FT, RC, NC, ND, IVA 14/7/0, isencao, desconto, IRT apenas quando aplicavel;
- AGT: sandbox, pendente, validado, rejeitado, DNS indisponivel e retry;
- E2E: mesa -> cozinha -> servido -> divisao -> pagamento -> factura -> caixa -> contabilidade;
- UI: desktop, tablet, telemovel, toque, teclado e acessibilidade.

## 14. Fora do MVP

- marketplaces externos de delivery;
- fidelizacao complexa e campanhas;
- ementa publica com pagamento online;
- compras automaticas por previsao;
- multi-cozinha com despacho logistico avancado;
- comissoes de estafetas.

Estes itens so entram depois de o checkout fiscal, stock e Tesouraria estarem estaveis.

## 15. Decisoes que nao podem ser alteradas durante a implementacao

- Restaurante nao possui tabela propria de facturas.
- Restaurante nao possui tabela propria de clientes, produtos, impostos, series ou metodos de pagamento.
- Comanda aberta nao consome numeracao fiscal.
- Toda linha faturada aponta para artigo do catalogo.
- Uma comanda pode originar varios documentos; a ligacao e por linhas/quantidades, nao por um unico `invoice_id`.
- Fiscalidade e calculada no servidor, nunca confiada ao browser/tablet.
- AGT e chamada depois do commit exterior.
- Pagamento e saldo de caixa passam por um servico central da Tesouraria.
