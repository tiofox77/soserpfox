# Bancada de ensaio do PWA

Ambiente para exercitar a aplicação de faturação offline **com rede e sem
rede**, num browser a sério.

## Porque existe

O PWA é a única parte do sistema cujo comportamento importante só aparece
**quando a rede falha** — e é a parte onde um defeito custa uma venda perdida,
não um ecrã feio. Os testes de PHP não chegam: correm no servidor, e o que se
quer medir passa-se no telemóvel.

O `context.setOffline(true)` do Playwright corta a rede **ao nível do browser**.
É diferente de mexer no `navigator.onLine`, que só engana o código que o
consulta e deixa os `fetch` a passar — e é por isso que um PWA pode parecer
funcionar em ensaio e falhar na rua.

## Arrancar

```bash
npm run pwa:bancada     # monta a empresa de ensaio (só em APP_ENV=local)
npm run pwa:test        # corre tudo, sem janela
```

O servidor sobe e desce sozinho na porta **8123**. Não é preciso ter nada a
correr antes.

Outros comandos:

```bash
npm run pwa:test:ver     # com janela visível, para ver o que se passa
npm run pwa:relatorio    # abre o relatório da última corrida
npm run pwa:limpar       # apaga a empresa de ensaio
```

## A bancada

`php artisan bancada:pwa` monta uma empresa completa: utilizador com todas as
permissões, módulos ligados, subscrição activa, armazém, imposto, cliente e
cinco artigos com stock.

| | |
|---|---|
| Empresa | Bancada PWA |
| Email | `bancada@pwa.local` |
| Password | `bancada-pwa-2026` |
| PIN de turno | `4321` |

**O comando recusa-se a correr fora de `APP_ENV=local`.** As credenciais são
fixas e conhecidas — num servidor a sério seriam uma porta aberta. A verificação
é um `abort`, não um aviso.

## O que está coberto

**Com rede** (`pwa-online.spec.js`)
- a aplicação abre e o service worker assume o comando
- o motor offline arranca e abre a base local
- **o motor e o desenho ficam guardados em cache** — Dexie, Alpine, Tailwind e
  o `pwa-invoicing.js`. É o ensaio que apanha o defeito de o precache apontar
  para ficheiros que a aplicação já não pede
- o catálogo e os clientes descem para o IndexedDB
- as cinco páginas do PWA respondem

**Sem rede** (`pwa-offline.spec.js`)
- **o POS abre sem rede** — se isto falhar, nada do resto interessa
- o catálogo continua legível
- o aparelho reconhece que não há rede
- uma venda feita sem rede fica na fila, **com identificador local e carimbo da
  empresa**
- a fila sobrevive a recarregar

**A rede volta** (`pwa-reconexao.spec.js`)
- o que ficou por enviar sobe
- **reenviar a mesma operação não cria dois registos** (idempotência)
- um trabalho de outra empresa fica **retido**, não sobe
- uma operação recusada (4xx) não fica a ser tentada para sempre

## Escrever mais ensaios

`apoio.js` tem o que se repete: `entrar()`, `esperarServiceWorker()`,
`esperarMotor()`, `sincronizar()`, `lerBase()`, `contar()`.

Duas armadilhas já pagas:

- **Esperar pelo service worker a CONTROLAR**, não só a registar. Na primeira
  visita ele instala mas só assume o comando no carregamento seguinte; cortar a
  rede antes disso mede uma página sem service worker nenhum.
- **O PWA recarrega-se sozinho** depois do primeiro sync. Um `page.evaluate()`
  apanhado a meio dessa navegação morre com *"Execution context was destroyed"*
  — usar `expect.poll()`, que sobrevive.

A base lê-se por `window.SosPwa.db`, o Dexie que o próprio motor abriu. Abrir
uma segunda ligação pelo nome funciona até haver uma migração de versão à
espera: aí as duas bloqueiam-se e o ensaio fica pendurado sem dizer porquê.

## Limites

Isto corre em **Chromium de secretária com viewport de telemóvel**. Não
substitui um Android a sério: não mede o comportamento do service worker quando
o sistema mata a aplicação em segundo plano, nem a Background Sync real, nem o
armazenamento a encher.
