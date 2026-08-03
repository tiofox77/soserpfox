<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interruptor geral
    |--------------------------------------------------------------------------
    | Desligar não apaga nada; só deixa de escrever. Útil para isolar um
    | problema de desempenho sem ter de reverter código.
    */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Modelos auditados (allowlist)
    |--------------------------------------------------------------------------
    | Registo por observer, um por modelo, no mesmo padrão dos 20 observers que
    | já existem no AppServiceProvider.
    |
    | Deliberadamente NÃO se usa um wildcard sobre eventos Eloquent: isso poria
    | as escritas dos 178 modelos a passar por aqui, incluindo as do próprio
    | registo de auditoria (recursão), e o raio de explosão de um erro passaria
    | a ser o sistema inteiro. Com allowlist, um erro afecta só o que lá está.
    |
    | Fase 1 — módulo de faturação.
    */
    'models' => [
        // ── Documentos fiscais ──
        \App\Models\Invoicing\SalesInvoice::class,
        \App\Models\Invoicing\SalesInvoiceItem::class,
        \App\Models\Invoicing\CreditNote::class,
        \App\Models\Invoicing\DebitNote::class,
        \App\Models\Invoicing\Receipt::class,
        \App\Models\Invoicing\SalesProforma::class,
        \App\Models\Invoicing\PurchaseInvoice::class,
        \App\Models\Invoicing\PurchaseProforma::class,
        \App\Models\Invoicing\TransportGuide::class,
        \App\Models\Invoicing\Advance::class,

        // ── Configuração que decide o que sai nos documentos ──
        // Mexer aqui muda a fiscalidade de tudo o que for emitido a seguir, e
        // não deixa rasto em documento nenhum. É dos sítios onde a auditoria
        // mais vale.
        \App\Models\Invoicing\InvoicingSeries::class,
        \App\Models\Invoicing\InvoicingSettings::class,
        \App\Models\Invoicing\Tax::class,
        \App\Models\Invoicing\Warehouse::class,

        // ── Catálogo e clientes ──
        \App\Models\Product::class,
        \App\Models\Client::class,

        // ── Linhas dos documentos ──
        // O documento já leva os totais, mas é na linha que vive o imposto, a
        // quantidade e o artigo — e é a linha que se altera quando alguém
        // corrige um documento "por dentro".
        \App\Models\Invoicing\CreditNoteItem::class,
        \App\Models\Invoicing\DebitNoteItem::class,
        \App\Models\Invoicing\SalesProformaItem::class,
        \App\Models\Invoicing\PurchaseInvoiceItem::class,
        \App\Models\Invoicing\PurchaseProformaItem::class,
        \App\Models\Invoicing\TransportGuideItem::class,
        \App\Models\Invoicing\LineTax::class,

        // ── Stock ──
        // Auditado por decisão do cliente. A minha reserva era o volume: o
        // movimento já é um livro append-only e a base tem milhares de linhas,
        // pelo que isto aproxima-se de duplicar o registo. Fica, porque o que
        // se ganha é ver QUEM mexeu — o movimento diz o que mudou, não quem o
        // mandou mudar. Ver a política de retenção mais abaixo.
        \App\Models\Invoicing\Stock::class,
        \App\Models\Invoicing\StockMovement::class,
        \App\Models\Invoicing\ProductBatch::class,

        // ── Dinheiro ──
        \App\Models\Treasury\Transaction::class,
        \App\Models\Invoicing\PosShift::class,
        \App\Models\Invoicing\PosShiftTransaction::class,

        // ── Importações ──
        // Uma importação altera muitos registos de uma vez e é dos actos com
        // maior potencial de estrago.
        \App\Models\Invoicing\Import::class,
        \App\Models\Invoicing\ImportItem::class,

        // ── Acessos ──
        // Quem criou uma conta, quem mudou de empresa, quem foi desactivado.
        \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deliberadamente FORA da auditoria
    |--------------------------------------------------------------------------
    | `ProductActivityLog`, `ImportHistory` — são eles próprios registos de
    | actividade. Auditar um registo de auditoria é recursão sem informação.
    |
    | `BatchAllocation`, `AdvanceUsage` — consequências automáticas de outro
    | acto já auditado (a venda, o adiantamento). Registam-se sozinhas.
    |
    | `PurchaseOrder` / `PurchaseOrderItem` — encomenda não é documento fiscal.
    | Entram quando o cliente o pedir.
    */

    /*
    |--------------------------------------------------------------------------
    | Campos NUNCA gravados
    |--------------------------------------------------------------------------
    | Esta lista tem de estar certa ANTES do primeiro insert em produção.
    |
    | A tabela é append-only e encadeada por hash: limpar um segredo que lá
    | tenha entrado obriga a apagar linhas, e apagar linhas parte a cadeia. Não
    | há segunda oportunidade.
    |
    | Compara-se por nome exacto, em minúsculas.
    */
    'redacted' => [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'token',
        'secret',
        'private_key',
        'jws_signature',
        'agt_client_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campos ignorados (ruído)
    |--------------------------------------------------------------------------
    | Alterações só nestes campos não geram linha de auditoria. Sem isto, cada
    | `touch()` do Eloquent produzia uma linha sem informação.
    */
    'ignored' => [
        'updated_at',
        'created_at',
        'remember_token',
        'last_login_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção
    |--------------------------------------------------------------------------
    | Dias a manter em linha. O comando de arquivo move o que for mais antigo
    | para ficheiro antes de remover — nunca apaga sem guardar.
    |
    | 0 = manter tudo (por omissão até haver política definida com o cliente;
    | um ERP fiscal costuma exigir vários anos).
    */
    'retention_days' => env('AUDIT_RETENTION_DAYS', 0),

    /*
    |--------------------------------------------------------------------------
    | Selagem
    |--------------------------------------------------------------------------
    | Cada linha leva sequência por empresa e hash do conteúdo encadeado com o
    | anterior. Torna a reescrita DETECTÁVEL — não a impede. Quem tem acesso
    | directo à base reescreve na mesma; o que não consegue é fazê-lo sem que a
    | verificação acuse.
    */
    'seal' => env('AUDIT_SEAL', true),
];
