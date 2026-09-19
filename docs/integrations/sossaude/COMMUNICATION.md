# Ponte SOSSaúde — entrega do adaptador comercial v1

Data: 2026-09-19. Não publicado. Nenhuma credencial real ou emissão fiscal criada.

Contrato de referência: `C:/laragon2/www/clinica/docs/integrations/soserp/CONTRACT.md`.
SOSSaúde/cliente: tarefa `019f6f7c-c399-7252-b9f5-117575e1062f`.
SOSERP/adaptador: tarefa `019f6f7c-b6f0-7fa1-8df7-27db25eb8e2f`.
Não existe canal confirmado com a sessão Claude. Esta nota é passagem de informação, não confirmação de leitura. Alterações existentes de SEO, páginas públicas e scripts PWA/DB foram preservadas.

## Interfaces

- GET `/api/integrations/sossaude/v1/connection`
- POST `/api/integrations/sossaude/v1/billing-drafts`

Headers: Bearer, X-Bridge-Version: 1, X-ERP-Tenant: id exacto em string. Escrita requer Idempotency-Key UUID. Payload JSON conforme contrato; campos adicionais recusados. Preços são propostas comerciais, sem cálculo ou aprovação fiscal nesta etapa.

401: credencial inválida/expirada/revogada. 403: empresa/escopo indisponível ou tenant divergente. 422: versão/validação. 409: chave ou referência com outro corpo. 429: limite 60 pedidos/minuto por IP.

O tenant vem exclusivamente da credencial. Não existe login de utilizador, sessão, troca de empresa ou actor fiscal nesta fase. O actor de auditoria é credential_id. Hash SHA-256 do segredo; scopes JSON; validade e revogação. Corpo comercial cifrado com APP_KEY. Nunca copiar a APP_KEY ou o segredo para documentação/chat.

Gravação usa transação e bloqueio da linha do tenant, serializando referências mesmo entre credenciais diferentes. Índices únicos protegem chave e referência. Chaves de objectos são ordenadas antes do hash; ordem das linhas e representação decimal fazem parte do conteúdo. Nova chave com referência e corpo iguais devolve o mesmo draft (200). Replay da mesma chave devolve a resposta e código originais com Idempotent-Replay: true. Nenhum registo parcial de idempotência sobrevive a rollback.

## Instalação e testes

Migração: `database/migrations/2026_09_19_180000_create_sossaude_bridge_tables.php`.
Foi aplicada exclusivamente à base local `soserp_test`. A base de desenvolvimento e produção não foram migradas nesta entrega.

Teste: `php artisan test tests/Feature/SossaudeBridgeTest.php` — 6 testes / 29 assertions aprovados. Usa dados sintéticos e DatabaseTransactions na base soserp_test.

Tabelas: sossaude_credentials, sossaude_billing_drafts, sossaude_requests. Para fixture isolada usar campos da tabela sossaude_credentials: tenant_id, name, token_hash=sha256(segredo_sintetico), scopes=["billing-drafts.write"], expires_at, created_at, updated_at. revoked_at e last_used_at são nullable. O teste existente demonstra a montagem completa.

Comando de gestão (só quando a activação for autorizada): `php artisan sossaude:credential TENANT --name=SOSSaude --days=90`. Mostra o segredo uma única vez. Revogação: mesmo comando com `--revoke=ID`. Rotação: emitir novo e revogar o anterior; referências são deduplicadas por empresa/origem entre ambos.

## Recuperação e limites

Desactivar integração ou revogar credencial preserva dados. Não executar migration rollback para desactivar: down apaga o histórico. Backup cifrado e APP_KEY são necessários para recuperação. Depois de timeout, reenviar a mesma referência/chave/corpo. Não há endpoint GET de rascunho nesta etapa, nem UI ERP de aprovação.

Sem emissão, numeração fiscal, assinatura fiscal, stock, pagamentos, PDF ou AGT. Não chama DraftController, PosSaleService ou EmissorFiscal. Persistir rascunho comercial não significa documento fiscal emitido.

Testes locais cobrem replay, referência, conflito, isolamento de artigos, campos clínicos, expiração, revogação, escopo e rollback injectado. Entrega guardada em commit exclusivo da ponte, sem push ou deploy; alterações anteriores permanecem fora do commit.

## Confirmação bilateral — 2026-09-19

A tarefa SOSSaúde comunicou aprovação de `SoserpExportTest::test_cross_app_contract_against_real_erp_receiver_in_isolated_process`: 1 teste, 4 assertions. Cliente real SoserpClient/BillingBridge chama um subprocesso com autoload/bootstrap SOSERP e AuthenticateSossaude/SossaudeController reais. SQLite em memória, tabelas sintéticas e Http::preventStrayRequests; nenhuma credencial ou empresa real.

Validado: handshake, envio do snapshot para rascunho e replay da mesma chave, mantendo draft_id e draft_count=1. Runner: `C:/laragon2/www/clinica/tests/Support/soserp-peer.php`. Reproduzir no workspace clinica com SOSERP_TEST_WORKSPACE=C:/laragon2/www/soserp e `php artisan test --filter=test_cross_app_contract`.

Este resultado foi comunicado pelo responsável SOSSaúde. Não cobre socket HTTP, reverse proxy, TLS, concorrência real nem publicação. O teste concorrente em processos independentes e a homologação HTTP permanecem pendentes. Emissão fiscal, pagamentos, PDFs e eventos pertencem aos contratos seguintes.
