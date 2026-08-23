# Checklist — Licenciamento Offline: Segurança & Atualizações

Companheiro do [PRD-LICENCIAMENTO-OFFLINE](PRD-LICENCIAMENTO-OFFLINE.md). Foco pedido: **segurança** e **atualizações**. `[x]` = já feito no repo; `[ ]` = por fazer.

Legenda de fase: **F0** núcleo · **F1** instalador · **F2** enforcement · **F3** phone-home · **F4** updates · **F5** painel.

---

## A. Licença & Criptografia (segurança)

- [x] Assinatura **Ed25519** (sodium) — `LicenseIssuer` / `LicenseVerifier` (F0)
- [x] Chave **pública** embutida na config; **privada** nunca no cliente/repo — `config/licensing.php` (F0)
- [x] Formato de token versionado (`SOSERP-LIC.v1…`) e assinatura sobre os bytes que viajam (F0)
- [x] Verificação **falha fechada** (ausente/adulterada/versão/formato → INVÁLIDA) (F0)
- [x] **Fingerprint de máquina** para prender a 1 PC — `MachineFingerprint` (F0)
- [x] **Anti-batota de relógio** (high-water mark do tempo) — `LicenseStore` + `LicenseVerifier` (F0)
- [x] Testes das regras de verificação — `tests/Unit/Licensing/LicenseVerifierTest.php` (F0)
- [ ] Guardar a **chave privada do vendor em cofre** (não no repo, não no `.env` versionado) (F0/ops)
- [ ] Procedimento de **rotação de chaves** + reemissão em massa de licenças (F3)
- [ ] Emissão de licença **ligada à subscrição** real (gerar no painel, não à mão) (F5)
- [ ] Revogação de licença (lista de revogados verificada no check-in) (F3)

## B. Proteção do código (anti-pirataria)

- [ ] Escolher e integrar **ionCube** *ou* **SourceGuardian** (encriptar o PHP no cliente) (F2)
- [ ] Garantir o **loader** para a versão de PHP embutida no XAMPP (F1/F2)
- [ ] **Verificações de licença espalhadas e redundantes** (não um único ponto) (F2)
- [ ] Verificações **dentro do código encriptado** (não em ficheiros a claro) (F2)
- [ ] "Tripwires" — comportamento degradado silencioso se detetar adulteração (F2)
- [ ] Remover rotas/painéis de debug e `APP_DEBUG=false` no build do cliente (F1)

## C. Segredos & dados em repouso (segurança)

- [ ] `APP_KEY` **única por instalação** (gerada no 1.º arranque) (F1)
- [ ] Password de BD **aleatória por instalação** (F1)
- [ ] **Chave AGT do cliente encriptada** em repouso (não a claro no `.env`) (F1)
- [ ] Apache a servir **só `localhost`** por omissão; BD **não** exposta à rede (F1)
- [ ] Ficheiros da app **fora do docroot** (docroot = `public/`) — mesmo princípio já aplicado na cloud (F1)
- [ ] Permissões de pasta restritas (só o serviço lê o `.env`/licença) (F1)
- [ ] Trilha de **auditoria append-only** ativa no cliente (já existe no produto) (F1)

## D. Phone-home & controlo remoto (segurança operacional)

- [x] **Servidor de Licenças** — endpoint `/api/license/checkin` que renova (emite) por tenant — `LicenseServerController` (F3). *Falta revogação explícita.*
- [x] Check-in **confia na assinatura** do token para identificar o tenant; renovação **assinada** de volta (F3). *Falta anti-replay (nonce/timestamp).*
- [x] Check-in à boleia do tráfego (middleware `CheckinDeLicenca`, terminate + tranca por intervalo); **contador só reinicia com renovação assinada válida** — `LicenseCheckin` (F3)
- [x] Suspender aplicado no próximo check-in (`is_active` na cloud → `acao:bloquear` → bloqueio remoto local) (F3)
- [ ] **Notificações** entregues no check-in + email/SMS (reusa subscrições/openclaw) — hook pronto, entrega por fazer (F3)
- [x] **Dias de graça configuráveis por tenant** (campo `graca` na licença) (F0)
- [x] Escada de bloqueio **gradual** (aviso→banner→só-leitura→bloqueio) — bloqueio total ligado; só-leitura ainda só banner (F2)
- [ ] Telemetria mínima no check-in (versão, último erro, saúde) para suporte (F3)

## E. Atualizações (o segundo foco)

- [x] **Servidor de Updates** publica versões **assinadas** (manifesto Ed25519 com versão, min, url, sha256) — `atualizacao:publicar` + `app_updates` (F4)
- [x] Cliente **verifica a assinatura** do manifesto E o **SHA-256** do pacote antes de aplicar — `UpdateVerifier` (F4)
- [x] **Super admin decide por-tenant** (rollout/canary) — `app_update_targets` + `atualizacao:alvo` (dados/comando; **ecrã = F5**) (F4)
- [x] **Backup automático da BD antes** de qualquer `migrate` (aborta se o backup falhar) — `UpdateService::backupBd` (F4)
- [x] Aplicar: baixar → verificar hash → `migrate` → **health-check** — `UpdateService::aplicar` (F4)
- [x] **Rollback da BD** se a atualização falhar (restaura o dump) (F4). *[ ] rollback de FICHEIROS (snapshot de release) — refinamento.*
- [ ] Janela de manutenção / lock durante o update (ninguém a escrever a meio) (F4)
- [x] `view:cache` no fim do apply (senão as views recompilam a frio — lição da cloud) (F4)
- [ ] Atualização do **stack** (PHP/MariaDB do XAMPP) como fluxo separado e opcional (F4)
- [ ] Registo de cada update aplicado (versão, quando, resultado) — visível no painel (F5)
- [x] Backup **verificado** (existe e não-vazio) antes de aplicar; sem ele, aborta (F4)

## F. Instalador `.exe` + XAMPP (segurança de base)

- [ ] `.exe` (Inno Setup/NSIS) com **XAMPP embutido** (Apache+MariaDB+PHP) (F1)
- [ ] Instala XAMPP em pasta dedicada, **portas próprias** (não colide com XAMPP existente) (F1)
- [ ] Regista **serviços Windows com arranque automático** (Apache + MariaDB) (F1)
- [ ] Cria BD + utilizador + corre migrations/seeders no 1.º arranque (F1)
- [ ] Gera `.env` (APP_KEY, BD, `LICENSE_PUBLIC_KEY`, ambiente=prod) (F1)
- [ ] Ecrã de **ativação de licença** (`licenca:ver` por trás) (F1)
- [ ] Desinstalador que pára serviços e **oferece backup da BD** antes de remover (F1)
- [ ] Instalador **assinado** (Authenticode) para o Windows não marcar como suspeito (F1)

## G. Enforcement (ligar a lógica à app)

- [ ] Middleware global que lê `LicenseManager::estado()` e aplica: banner / só-leitura / bloqueio (F2)
- [ ] Página de bloqueio amigável (com o motivo e o que fazer) (F2)
- [ ] Gate por **módulo** (a licença lista `modulos[]`; esconder/negar os não licenciados) (F2)
- [ ] **Nunca** trancar dados do cliente de forma irreversível (só-leitura, nunca apagar) (F2)
- [ ] Testar o modo bloqueado **sem** trancar acidentalmente instalações reais (F2)

---

## Como validar hoje (F0, já disponível)

```bash
# 1) gerar o par de chaves do emissor (guardar a privada em cofre)
php artisan licenca:chaves

# 2) emitir uma licença (lado do vendor; precisa da chave privada)
LICENSE_SIGNING_KEY=<privada> php artisan licenca:emitir \
  --tenant=42 --empresa="Empresa X" --plano=enterprise --modulos='*' \
  --dias=30 --graca=15 --maquina --out=license.key

# 3) VER a licença OFFLINE (o cliente faz isto sem internet)
LICENSE_PUBLIC_KEY=<publica> php artisan licenca:ver --ficheiro=license.key
```
