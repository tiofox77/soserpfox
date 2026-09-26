# Campanha — preparação dos anúncios (26/09/2026)

Só preparação. **Nenhum anúncio foi criado, alterado ou publicado**, e nada
disto gasta do orçamento (tecto aprovado: 50 USD no total).

## 1. O caminho, validado em produção

Visita por telemóvel (Android) à página de cada módulo, e depois `/register` na
mesma sessão. Verificado com pedidos simples ao servidor (sem JavaScript e sem
submeter nada): **não conta visitas nem inscrições na analítica**.

| Módulo | Página | Teste anunciado | Links «Experimentar» | WhatsApp | `/register` guarda o módulo |
|---|---|---|---|---|---|
| Vendas e Faturação | `/modulos/vendas` 200 | 14 dias grátis | 4 | 2 | sim — «Vendas e Faturação» |
| Recursos Humanos | `/modulos/rh` 200 | 14 dias grátis | 4 | 2 | sim — «Recursos Humanos» |
| Hotel | `/modulos/hotel` 200 | **30 dias grátis** | 4 | 2 | sim — «Hotel» |
| Salão de Beleza | `/modulos/salao` 200 | 14 dias grátis | 4 | 2 | sim — «Salão de Beleza» |
| Oficina | `/modulos/oficina` 200 | 14 dias grátis | 4 | 2 | sim — «Oficina» |
| Restaurante | `/modulos/restaurant` 200 | 14 dias grátis | 4 | 2 | sim — «Restaurante» |

O registo completo de ponta a ponta **não** foi feito em produção: exige uma
conta de teste controlada e autorizada, excluída das métricas (ver §4).

## 2. O que se mede (sem dados pessoais)

| Passo | Evento | Onde |
|---|---|---|
| Clicou «Experimentar» | `click_register` | `sos-tracker.js`, qualquer link para `/register` |
| Clicou «Pedir ajuda pelo WhatsApp» | `click_whatsapp` | `sos-tracker.js`, links `https://wa.me/` |
| Iniciou o formulário | `registo_iniciado` | servidor, 1× por sessão, depois do 1.º passo válido |
| Criou a empresa | `CompleteRegistration` | Pixel com `event_id` determinístico, só com consentimento de marketing |
| Activou o teste | subscrição `trial` | `campanha:conversoes` |
| Primeira utilização | 1.º registo de negócio > 60 s depois de criada | `campanha:conversoes` |
| Pediu ajuda na 1.ª utilização | `pedido_de_ajuda` | servidor, botão «Preciso de ajuda para começar» |

Comparar **«Experimentar» vs «WhatsApp»** por módulo: painel Analítica da
plataforma (cartão de cliques por página) ou `campanha:conversoes`.

## 3. Materiais — o demo de 15–20 s por módulo

Filmar no telemóvel, numa **empresa de demonstração** (nunca numa conta de
cliente, e sem dados reais — nomes e NIF inventados só aí dentro).

| Módulo | 0–5 s (o problema) | 5–15 s (o ecrã) | 15–20 s (a chamada) |
|---|---|---|---|
| Vendas | fila ao balcão | POS: 3 toques → factura-recibo impressa | «14 dias grátis — Experimentar» |
| RH | folha de salários em papel | processar salário com IRT calculado | idem |
| Hotel | caderno de reservas | mapa de quartos → check-in → factura | «**30** dias grátis» |
| Salão | agenda no WhatsApp | marcação na agenda → serviço → talão | «14 dias grátis» |
| Oficina | fichas soltas | ordem de reparação com fotos antes/depois | idem |
| Restaurante | comandas em papel | mesa → pedido na cozinha → conta | idem |

O último plano mostra sempre o botão **Experimentar** e o **WhatsApp**, iguais
aos da página — é o que o anúncio promete e o que a pessoa encontra.

## 4. Antes de gastar

1. Conta de teste controlada para o registo completo, que fica fora das
   métricas por uma de duas vias (`config/campanha.php`, `testes`): o email ou
   o NIF na lista `CAMPANHA_TESTES_EMAILS` / `CAMPANHA_TESTES_NIFS`, ou um nome
   ou email com um dos padrões (`teste`, `demo`, `+test`, `mailinator`…).
2. Confirmar que o Pixel só dispara com o consentimento de marketing.
3. Um anúncio por módulo, com o link directo para `/modulos/{módulo}` e os
   parâmetros `utm_source`/`utm_campaign`, dentro dos 50 USD já aprovados.
