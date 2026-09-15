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
    | as escritas dos 217 modelos a passar por aqui, incluindo as do próprio
    | registo de auditoria (recursão), e o raio de explosão de um erro passaria
    | a ser o sistema inteiro. Com allowlist, um erro afecta só o que lá está.
    |
    | Cobertura: TODAS as áreas do sistema (2026-09-02). Eram 35 modelos, só
    | da facturação; passaram a 170. O critério de entrada é um só — o registo
    | carrega consequência: dinheiro, stock, fiscalidade, o que uma pessoa
    | recebe ao fim do mês, quem tem acesso a quê, ou configuração que muda o
    | resultado de tudo o que vier a seguir.
    |
    | Quem acrescentar um modelo de LINHA (sem `tenant_id` próprio) tem de
    | acrescentar também o nome da relação que a leva ao pai em
    | `AuditRecorder::empresaDoRegisto` — senão a linha cai no último recurso
    | (a empresa activa) e desaparece fora de um pedido web.
    */
    'models' => [
        // ── Documentos fiscais ──
        \App\Models\Invoicing\SalesInvoice::class,
        \App\Models\Invoicing\CreditNote::class,
        \App\Models\Invoicing\DebitNote::class,
        \App\Models\Invoicing\Receipt::class,
        \App\Models\Invoicing\SalesProforma::class,
        \App\Models\Invoicing\SalesQuote::class,
        \App\Models\Invoicing\PurchaseInvoice::class,
        \App\Models\Invoicing\PurchaseProforma::class,
        \App\Models\Invoicing\TransportGuide::class,
        \App\Models\Invoicing\Advance::class,

        // ── Linhas dos documentos ──
        // O documento já leva os totais, mas é na linha que vive o imposto, a
        // quantidade e o artigo — e é a linha que se altera quando alguém
        // corrige um documento "por dentro".
        \App\Models\Invoicing\SalesInvoiceItem::class,
        \App\Models\Invoicing\CreditNoteItem::class,
        \App\Models\Invoicing\DebitNoteItem::class,
        \App\Models\Invoicing\SalesProformaItem::class,
        \App\Models\Invoicing\SalesQuoteItem::class,
        \App\Models\Invoicing\PurchaseInvoiceItem::class,
        \App\Models\Invoicing\PurchaseProformaItem::class,
        \App\Models\Invoicing\TransportGuideItem::class,
        \App\Models\Invoicing\LineTax::class,

        // ── Configuração que decide o que sai nos documentos ──
        // Mexer aqui muda a fiscalidade de tudo o que for emitido a seguir, e
        // não deixa rasto em documento nenhum. É dos sítios onde a auditoria
        // mais vale.
        \App\Models\Invoicing\InvoicingSeries::class,
        \App\Models\Invoicing\InvoicingSettings::class,
        \App\Models\Invoicing\Tax::class,
        \App\Models\Invoicing\Warehouse::class,
        \App\Models\Invoicing\PaymentTerm::class,
        \App\Models\Invoicing\QuoteTemplate::class,

        // ── Catálogo e clientes ──
        \App\Models\Product::class,
        \App\Models\Client::class,
        \App\Models\Supplier::class,
        \App\Models\Category::class,
        \App\Models\Brand::class,

        // ── Stock ──
        // Auditado por decisão do cliente. A minha reserva era o volume: o
        // movimento já é um livro append-only e a base tem milhares de linhas,
        // pelo que isto aproxima-se de duplicar o registo. Fica, porque o que
        // se ganha é ver QUEM mexeu — o movimento diz o que mudou, não quem o
        // mandou mudar. Ver a política de retenção mais abaixo.
        \App\Models\Invoicing\Stock::class,
        \App\Models\Invoicing\StockMovement::class,
        \App\Models\Invoicing\ProductBatch::class,
        \App\Models\Invoicing\StockCount::class,
        \App\Models\Invoicing\StockCountItem::class,
        \App\Models\Invoicing\Waste::class,

        // ── Dinheiro ──
        \App\Models\Treasury\Transaction::class,
        \App\Models\Treasury\Account::class,
        \App\Models\Treasury\CashRegister::class,
        \App\Models\Treasury\PaymentMethod::class,
        \App\Models\Treasury\Transfer::class,
        \App\Models\Treasury\Reconciliation::class,
        \App\Models\Treasury\TransactionCategory::class,
        \App\Models\Treasury\TransactionType::class,
        \App\Models\Invoicing\PosShift::class,
        \App\Models\Invoicing\PosShiftTransaction::class,
        \App\Models\Invoicing\SalePayment::class,

        // ── Contabilidade ──
        // O livro-razão. É a área onde a pergunta "quem mudou isto, e o que
        // dizia antes" é a razão de existir do módulo — sobretudo fechar um
        // período, que congela um exercício inteiro.
        \App\Models\Accounting\Account::class,
        \App\Models\Accounting\Journal::class,
        \App\Models\Accounting\Move::class,
        \App\Models\Accounting\MoveLine::class,
        \App\Models\Accounting\Period::class,
        \App\Models\Accounting\Tax::class,
        \App\Models\Accounting\Withholding::class,
        \App\Models\Accounting\Budget::class,
        \App\Models\Accounting\CostCenter::class,
        \App\Models\Accounting\DocumentType::class,
        \App\Models\Accounting\BankReconciliation::class,
        \App\Models\Accounting\BankReconciliationItem::class,
        \App\Models\Accounting\AccountingIntegration::class,
        \App\Models\Accounting\IntegrationMapping::class,
        \App\Models\Accounting\AllocationMatrix::class,
        \App\Models\Accounting\AnalyticDimension::class,

        // ── Recursos Humanos ──
        // Salários, contratos e descontos. Mexer aqui mexe no que uma pessoa
        // recebe ao fim do mês, e a assiduidade é a base do processamento —
        // corrigir uma picagem antiga muda dinheiro.
        \App\Models\HR\Employee::class,
        \App\Models\HR\Contract::class,
        \App\Models\HR\Payroll::class,
        \App\Models\HR\PayrollItem::class,
        \App\Models\HR\SalaryAdvance::class,
        \App\Models\HR\SalaryDiscount::class,
        \App\Models\HR\Overtime::class,
        \App\Models\HR\Attendance::class,
        \App\Models\HR\Leave::class,
        \App\Models\HR\Vacation::class,
        \App\Models\HR\Position::class,
        \App\Models\HR\Department::class,
        \App\Models\HR\Shift::class,
        \App\Models\HR\HRSetting::class,
        \App\Models\HR\IRTTaxBracket::class,

        // ── Compras ──
        // A encomenda é o compromisso com o fornecedor e a requisição é quem
        // autorizou a despesa. Nenhuma delas é documento fiscal, mas ambas
        // decidem dinheiro que sai — e a recepção decide stock que entra.
        \App\Models\Compras\Requisicao::class,
        \App\Models\Compras\RequisicaoItem::class,
        \App\Models\Compras\Encomenda::class,
        \App\Models\Compras\EncomendaItem::class,

        // ── Projetos ──
        // O projeto guarda o orçamento e o preço/hora acordados, e a folha de
        // horas é o que sustenta a factura ao cliente. Mudar um preço ou uma
        // hora depois do facto é exactamente o que uma trilha existe para ver.
        \App\Models\Projetos\Projeto::class,
        \App\Models\Projetos\Tarefa::class,
        \App\Models\Projetos\HoraLancada::class,

        // ── CRM ──
        // A integração Meta guarda tokens cifrados: quem os trocou é uma
        // pergunta de segurança, não de comercial.
        \App\Models\CRM\Lead::class,
        \App\Models\CRM\Opportunity::class,
        \App\Models\CRM\Activity::class,
        \App\Models\CRM\Stage::class,
        \App\Models\CRM\MetaIntegration::class,

        // ── Restaurante ──
        // A comanda é o documento de venda antes de haver factura, e a receita
        // decide o que sai do stock por cada prato servido.
        \App\Models\Restaurant\Order::class,
        \App\Models\Restaurant\OrderItem::class,
        \App\Models\Restaurant\OrderItemBilling::class,
        \App\Models\Restaurant\PaymentAttempt::class,
        \App\Models\Restaurant\Recipe::class,
        \App\Models\Restaurant\RecipeItem::class,
        \App\Models\Restaurant\Waste::class,
        \App\Models\Restaurant\RestaurantSettings::class,
        \App\Models\Restaurant\MenuDestaque::class,
        \App\Models\Restaurant\MenuOrder::class,
        \App\Models\Restaurant\Reservation::class,
        \App\Models\Restaurant\Waitlist::class,
        \App\Models\Restaurant\DiningTable::class,
        \App\Models\Restaurant\Area::class,
        \App\Models\Restaurant\Venue::class,
        \App\Models\Restaurant\VenueLimitRequest::class,
        \App\Models\Restaurant\KitchenStation::class,

        // ── Hotel ──
        \App\Models\Hotel\Reservation::class,
        \App\Models\Hotel\ReservationItem::class,
        \App\Models\Hotel\Guest::class,
        \App\Models\Hotel\Room::class,
        \App\Models\Hotel\RoomType::class,
        \App\Models\Hotel\RateSeason::class,
        \App\Models\Hotel\Package::class,
        \App\Models\Hotel\PromoCode::class,
        \App\Models\Hotel\MaintenanceOrder::class,
        \App\Models\Hotel\HousekeepingTask::class,
        \App\Models\Hotel\Staff::class,
        \App\Models\Hotel\HotelSettings::class,

        // ── Salão ──
        \App\Models\Salon\Appointment::class,
        \App\Models\Salon\AppointmentService::class,
        \App\Models\Salon\Service::class,
        \App\Models\Salon\ServiceCategory::class,
        \App\Models\Salon\Professional::class,
        \App\Models\Salon\Client::class,
        \App\Models\Salon\Product::class,
        \App\Models\Salon\SalonSettings::class,

        // ── Oficina ──
        \App\Models\Workshop\WorkOrder::class,
        \App\Models\Workshop\WorkOrderItem::class,
        \App\Models\Workshop\WorkOrderPayment::class,
        \App\Models\Workshop\WorkOrderAttachment::class,
        \App\Models\Workshop\Vehicle::class,
        \App\Models\Workshop\VehiclePhoto::class,
        \App\Models\Workshop\WorkOrderCheckin::class,
        \App\Models\Workshop\InspectionTemplate::class,
        \App\Models\Workshop\WorkOrderInspection::class,
        \App\Models\Workshop\Bay::class,
        \App\Models\Workshop\Appointment::class,
        \App\Models\Workshop\TimeEntry::class,
        \App\Models\Workshop\ServicePackage::class,
        \App\Models\Workshop\Mechanic::class,
        \App\Models\Workshop\Service::class,

        // ── Eventos e equipamento ──
        // O equipamento sai de casa e volta: quem o levou, quando, e para que
        // evento é a pergunta que se faz quando não volta.
        \App\Models\Events\Event::class,
        \App\Models\Events\EventType::class,
        \App\Models\Events\EventEquipment::class,
        \App\Models\Events\EventStaff::class,
        \App\Models\Events\EventReport::class,
        \App\Models\Events\Equipment::class,
        \App\Models\Events\EquipmentMovement::class,
        \App\Models\Events\Venue::class,
        \App\Models\Events\Team::class,
        \App\Models\Events\TeamMember::class,
        \App\Models\Events\Technician::class,
        \App\Models\Events\Checklist::class,
        \App\Models\Equipment::class,
        \App\Models\EquipmentCategory::class,
        \App\Models\EquipmentSet::class,
        \App\Models\EquipmentSetItem::class,

        // ── AGT ──
        // A submissão é o acto de comunicar um documento ao Estado.
        \App\Models\AGT\AGTSubmission::class,

        // ── Importações ──
        // Uma importação altera muitos registos de uma vez e é dos actos com
        // maior potencial de estrago.
        \App\Models\Invoicing\Import::class,
        \App\Models\Invoicing\ImportItem::class,

        // ── Acessos e a própria empresa ──
        // Quem criou uma conta, quem convidou quem, quem foi desactivado — e
        // quem mudou o NIF, o regime fiscal ou o estado da empresa. O Tenant
        // não tem coluna `tenant_id`: é ele a empresa, e o AuditRecorder
        // trata-o como caso próprio.
        \App\Models\User::class,
        \App\Models\UserInvitation::class,
        \App\Models\Tenant::class,

        // ── Subscrição e licenças ──
        // O que uma empresa paga e a que tem direito.
        \App\Models\Subscription::class,
        \App\Models\Invoice::class,
        \App\Models\LicencaEmitida::class,
        \App\Models\LicenseRequest::class,
        \App\Models\AppUpdateTarget::class,

        // ── Comunicação com o cliente ──
        // O que sai em nome da casa: modelos de email/SMS e as definições de
        // envio. Mudar um modelo muda o que milhares de pessoas recebem.
        \App\Models\SmtpSetting::class,
        \App\Models\SmsSetting::class,
        \App\Models\SmsTemplate::class,
        \App\Models\NotificationTemplate::class,
        \App\Models\TenantNotificationSetting::class,

        // ── Apoio ──
        \App\Models\Support\Ticket::class,
        \App\Models\Support\TicketMessage::class,
        \App\Models\Support\FeatureRequest::class,

        // ── Encomendas da loja ──
        \App\Models\Order::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deliberadamente FORA da auditoria
    |--------------------------------------------------------------------------
    | Quatro motivos, e nenhum deles é "esqueci-me".
    |
    | 1. SÃO ELES PRÓPRIOS REGISTOS DE ACTIVIDADE — auditar um log é recursão
    |    sem informação: `AuditTrail`, `ErroDoSistema`, `EmailLog`, `SmsLog`,
    |    `AnalyticsEvent`, `ProductActivityLog`, `ImportHistory`,
    |    `WorkOrderHistory`, `AGTCommunicationLog`, `OrderEvent`,
    |    `EquipmentHistory`, `AgentMessage`, `UserNotification`.
    |
    | 2. NASCEM SOZINHAS como consequência de um acto já auditado:
    |    `BatchAllocation` e `AdvanceUsage` (da venda e do adiantamento),
    |    `MetaContact` (do webhook do Meta), `PlatformMessageRead`.
    |
    | 3. OPERACIONAL DE ALTÍSSIMO VOLUME, derivado de um documento que já está
    |    auditado: `KitchenTicket` e `KitchenTicketItem` nascem da comanda, que
    |    é auditada. `PwaDevice` é telemetria de aparelhos.
    |
    | 4. NÃO TÊM EMPRESA NEM PAI POR ONDE SUBIR. A trilha é por empresa —
    |    `audit_trail.tenant_id` é NOT NULL — e atribuir uma linha à empresa
    |    que por acaso estava activa na sessão é PIOR do que não a escrever:
    |    poria na conta de um cliente um acto que não foi dele.
    |
    |    São globais da plataforma: `Plan`, `Module`, `SystemSetting`,
    |    `SoftwareSetting`, `EmailTemplate`, `WhatsAppSetting`, `ApiToken`,
    |    `AgentToken`, `AgentRequest`, `AppUpdate`, `PlatformMessage`,
    |    `ContactMessage`, `Treasury\Bank`, `Accounting\Currency`,
    |    `Accounting\ExchangeRate`, `Accounting\AnalyticTag`, e as tabelas de
    |    códigos da AGT (`AGTCaeCode`, `AGTIecPautalCode`, `AGTIsVerba`,
    |    `AGTTaxExemptionCode`).
    |
    |    ⚠️ Isto é uma LACUNA REAL, não uma decisão de mérito: mudar o preço de
    |    um plano ou ligar um módulo a uma empresa fica hoje sem rasto. Fechá-la
    |    exige uma trilha de PLATAFORMA — `tenant_id` nulo, com a selagem por
    |    hash a encadear por uma sequência própria. Fica anotado por decidir.
    |
    | `PurchaseOrder` / `PurchaseOrderItem` — código morto: sem UI e sem rota,
    | sobrepostos pelo módulo Compras que os substituiu.
    */

    /*
    |--------------------------------------------------------------------------
    | Actos que não são alteração de modelo
    |--------------------------------------------------------------------------
    | O observer só vê ESCRITAS. Levar o SAFT para fora, imprimir uma factura,
    | entrar na casa de um cliente ou correr um comando contra a produção não
    | mudam linha nenhuma — e sem isto não apareciam na trilha.
    |
    | Gravam-se por `AuditRecorder::acto()` (ou pelos atalhos `exportou()` e
    | `imprimiu()`, que existem para o nome do evento não derivar em dez sítios).
    | Esta lista é o vocabulário; quem acrescentar um acto novo acrescenta-o
    | aqui, senão o ecrã de auditoria não sabe por onde filtrar.
    |
    |   login · login_falhado · logout          entradas e saídas
    |   empresa.trocada                         entrou noutra empresa
    |   empresa.troca_recusada                  tentou entrar onde não pertence
    |   personificacao.entrou                   super admin entrou num cliente
    |   exportacao                              dados que saíram (SAFT, PDF, Excel)
    |   impressao                               documento que saiu em papel
    |   manutencao.comando                      comando corrido por HTTP na produção
    |   agt.*                                   comunicação ao Estado
    |     agt.ambiente.activado                   trocou o ambiente que emite (de, para, pendentes)
    |     agt.chaves.guardadas / .removidas       par RSA do contribuinte (sha256 da PÚBLICA)
    |     agt.chave_legado.guardada / .removida   chave do modo antigo (sha256 da pública derivada)
    |     agt.series.sincronizadas                registo de séries na AGT (série a série)
    |     agt.submissao.reposta                   «Repor e reenviar» (contador e erro de antes)
    |   agente.*                                decisões do agente externo
    |
    | ⚠️ Os metadados de um acto NÃO passam pela lista `redacted` abaixo — essa
    | só cobre valores de modelo. Quem passar argumentos a um acto oculta os
    | segredos ANTES (ver `MaintenanceController::registarNaTrilha`).
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
        // O PIN de turno é a senha do POS: a impressão cifrada de um PIN de 4 a
        // 6 dígitos adivinha-se em segundos, e a trilha não se apaga.
        'pos_pin_hash',
        'pin_hash',
        'pin',
        'senha',
        'palavra_passe',
        'current_password',
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
