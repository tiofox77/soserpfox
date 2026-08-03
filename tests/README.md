# Testes

## Preparar (uma vez)

```bash
php scripts/prepare_test_db.php
```

Cria/recria a base `soserp_test` **só com o esquema** (nenhum dado é copiado) a
partir da base de desenvolvimento.

### Porque não `php artisan migrate`

Duas razões:

1. **sqlite não serve.** 85 das 272 migrações usam SQL específico do MySQL
   (`ENUM`, `ALTER TABLE ... MODIFY`, `DB::statement`). O `phpunit.xml` aponta
   para MySQL por isso mesmo.
2. **As migrações não correm de raiz.** A ordem está partida:
   `2025_01_11_220000_create_hr_departments_table` referencia `tenants`, que só
   é criada em `2025_10_02_001_create_tenants_table`. Uma instalação limpa
   morre em `Failed to open the referenced table 'tenants'`.

O ponto 2 é um problema por si só — impede CI e qualquer instalação nova. Está
por resolver.

## Correr

```bash
php artisan test
```

Cada teste corre dentro de uma transacção (`DatabaseTransactions`) que é
revertida no fim: a base de testes fica vazia e a de desenvolvimento nunca é
tocada.

## Escrever

Estender `Tests\TenantTestCase`, que monta uma empresa utilizável: utilizador
autenticado, subscrição activa (sem ela o `CheckSubscription` redirecciona tudo
para `/subscription-expired`), imposto por omissão a 14%, armazém, cliente e
séries de emissão FT/FR.

Atenção: **criar um `Tenant` já provisiona automaticamente armazém e impostos**.
O `TenantTestCase` reutiliza o que existe em vez de criar um segundo — dois
armazéns marcados `is_default` fazem o `Warehouse::getDefault()` devolver o
outro e a baixa de stock falha num armazém que nunca recebeu nada.

Ajudas disponíveis: `clienteEmpresa()` (pessoa colectiva, retém IRT) e
`produtoComStock()` (artigo com linha de stock materializada).

## O que está coberto

| Ficheiro | Cobre |
|---|---|
| `ModuleInvoiceServiceTest` | Emissor fiscal partilhado: imposto pelo regime, isenções com código, IRT só sobre serviços e só quando pedido, descontos de linha, identidade SAFT (`net + imposto = gross`), vínculo ao catálogo, origem do módulo |
| `WorkshopStockTest` | Oficina: baixa de peças pelo formulário, agregação de linhas repetidas, idempotência, ausência de duplo desconto ao faturar, devolução ao cancelar, atomicidade quando falta stock, IRT por tipo de cliente |
| `HotelReservationTest` | Hotel: adiantamento abatido por linha negativa, sem segunda fatura quando o sinal cobre tudo, aviso/bloqueio no ecrã, consumos do folio faturados, tecto no pagamento, double-booking, máquina de estados, no-show, folio fechado após check-out |
| `ModuleScreensRenderTest` | Fumo: todos os ecrãs de oficina/salão/hotel renderizam (descoberta automática), mais os que exigem parâmetro de rota, e as recusas da página pública de reservas |

## Por cobrir

POS e PWA, notas de crédito/débito, contabilidade, e o envio efectivo à AGT
(que precisa de chaves e de um ambiente de homologação).
