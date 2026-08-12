# SOS ERP — App Móvel de Faturação (Flutter)

App Android/iOS do módulo de **Faturação** do SOS ERP, com a mesma UI/UX do sistema web
(azul + esmeralda, cards arredondados, POS com carrinho em bottom-sheet) e arquitetura
**offline-first** (igual à PWA). Spec completa: `../docs/flutter-invoicing-app.md`.

## Estrutura
```
lib/
  core/      config.dart · theme.dart (cores web) · storage.dart · api_client.dart
  data/      models.dart · local_db.dart (sqflite) · invoicing_api.dart · auth_api.dart
             sync_engine.dart · pos_service.dart
  state/     app_state.dart (Provider: catálogo, carrinho, sync)
  features/
    auth/    login_screen.dart
    home/    home_shell.dart (bottom nav: POS · Catálogo · Clientes)
    pos/     pos_screen.dart (grelha de produtos + carrinho bottom-sheet + pagamentos)
    catalog/ catalog_screen.dart
    clients/ clients_screen.dart
```

## Funcionalidades já implementadas
- Login (token Bearer) e arranque condicional (token guardado → Home).
- Sync do catálogo (incremental + completo + versão de catálogo) e fila de envio.
- POS: pesquisa, categorias, grelha com badge de quantidade e stock, carrinho,
  IVA por linha, métodos de pagamento, **finalizar venda offline** (nº provisório) e
  sincronização em segundo plano para obter o nº AGT.
- Catálogo e Clientes (lista + pesquisa).
- DB local sqflite (products, clients, pos_sales, sync_queue) + indicador de turno.

## Como correr
```bash
cd app-mobile
flutter pub get
flutter run            # com emulador/dispositivo ligado
```
Configurar o backend em `lib/core/config.dart`:
- Produção: `baseUrl = 'https://soserp.vip'`
- Emulador Android → Laragon local: `baseUrl = 'http://10.0.2.2'`

## Pendências no BACKEND (para login/funcionar 100%)
Ver `../docs/flutter-invoicing-app.md` §11. Em resumo:
1. **Auth por token (Sanctum)**: `POST /api/v1/auth/login` → `{ token, user, tenant_id }`,
   protegendo `api/v1/invoicing/*` com `auth:sanctum` e resolvendo o tenant do token
   (a app já envia `X-Tenant-Id`).
2. **Métodos de pagamento no `/sync`**: incluir `data.payment_methods` (a app usa
   `PaymentMethod.defaults` entretanto).

Enquanto o login não existir no backend, o ecrã de login devolve erro de credenciais —
o resto (sync/POS/DB offline) está pronto a ligar quando a auth estiver disponível.
