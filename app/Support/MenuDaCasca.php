<?php

namespace App\Support;

use App\Models\HR\HRSetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * O MENU LATERAL — num sítio só.
 *
 * Vivia em 1.500 linhas do `layouts/app.blade.php`, uma ligação de cada vez,
 * cada uma com a sua permissão, o seu ícone e a sua regra de "está activa".
 * Ao migrar a casca para React, o menu passou a ser DADOS: o Blade desenha-os
 * e o ecrã em React desenha-os, dos mesmos dados. Uma ligação nova entra
 * aqui e aparece nos dois.
 *
 * O QUE DECIDE SE UMA ENTRADA APARECE, pela ordem em que se pergunta:
 *
 *  · `modulo`   — o grupo só existe se a empresa tem o módulo activo no plano
 *                 E o utilizador tem alguma permissão dele (canAccessModuleMenu);
 *                 o super admin da plataforma vê tudo.
 *  · `permissao` / `qualquer` — a permissão da entrada (`@can` / `@canany`).
 *  · `quando`   — uma condição própria, quando as duas de cima não chegam.
 *
 * "ACTIVA" é o `routeIs` de sempre: um padrão, vários, ou uma função para os
 * casos que olham à query (as facturas FT e FR partilham a rota).
 */
final class MenuDaCasca
{
    /** O menu inteiro, já decidido para este utilizador e este pedido. */
    public static function montar(User $u, Request $r): array
    {
        $tenant = $u->activeTenant();
        $plano = $tenant?->activeSubscription?->plan;

        return [
            'principal' => self::resolver(self::principal(), $u, $r),
            'grupos' => array_values(array_filter(
                array_map(fn ($g) => self::grupo($g, $u, $r), self::grupos()),
            )),
            'superadmin' => $u->isSuperAdmin()
                ? array_map(fn ($s) => ['titulo' => __($s['titulo']), 'entradas' => self::resolver($s['entradas'], $u, $r)], self::superAdmin())
                : [],
            'fox' => (bool) ($plano && str_contains(strtolower((string) $plano->slug), 'fox')),
            'suporte' => [
                'url' => route('support.tickets'),
                'activo' => $r->routeIs('support.*'),
                'rotulo' => __('Suporte'),
                'extra' => __('Novo'),
            ],
            'utilizador' => [
                'nome' => $u->name,
                'papel' => $u->isSuperAdmin() ? 'Super Admin' : 'Utilizador',
                'ligacoes' => array_values(array_filter([
                    ['url' => route('my-account'), 'rotulo' => 'Minha Conta', 'icone' => 'fa-user-circle', 'cor' => 'blue-600'],
                    // O PIN de turno é de cada um, como a conta. A entrada sem
                    // rede mandava defini-lo «em PIN de turno», e essa página
                    // não tinha ligação em lado nenhum.
                    ['url' => route('invoicing.offline.pin'), 'rotulo' => 'PIN de turno', 'icone' => 'fa-key', 'cor' => 'amber-600'],
                    // Os dados da empresa — NIF, morada e regime fiscal — são de
                    // quem a gere. O link não aparece a quem a página recusa.
                    $u->can('settings.view')
                        ? ['url' => route('company.profile'), 'rotulo' => 'Dados da Empresa', 'icone' => 'fa-building', 'cor' => 'indigo-600']
                        : null,
                    $u->canManageAccount()
                        ? ['url' => route('my-account') . '?tab=companies', 'rotulo' => 'Minhas Empresas', 'icone' => 'fa-building', 'cor' => 'purple-600']
                        : null,
                    $u->canManageAccount()
                        ? ['url' => route('my-account') . '?tab=plan', 'rotulo' => 'Meu Plano', 'icone' => 'fa-crown', 'cor' => 'yellow-600']
                        : null,
                    // As cópias de segurança só dos dados desta empresa.
                    $u->canManageAccount()
                        ? ['url' => route('company.copias'), 'rotulo' => 'Cópias de Segurança', 'icone' => 'fa-shield-halved', 'cor' => 'emerald-600']
                        : null,
                ])),
                'atualizacoes' => ['url' => route('changelog'), 'rotulo' => 'Atualizações', 'versao' => 'v' . config('changelog.current', '1.0')],
                'sair' => route('logout'),
            ],
        ];
    }

    /**
     * O MENU DO PAINEL DA PLATAFORMA — a mesma barra lateral (o ecrã `casca`),
     * com as áreas do dono da plataforma.
     *
     * Estava escrito à mão no `layouts/superadmin`, em Blade com Alpine, com
     * «Perfil» e «Configurações» a apontar para «#». Passou a ter a forma do
     * menu da aplicação, para a mesma barra o desenhar: grupos vazios, sem
     * suporte (é a plataforma que o dá), e as secções da plataforma.
     */
    public static function daPlataforma(User $u, Request $r): array
    {
        return [
            'principal' => self::resolver(self::principal(), $u, $r),
            'grupos' => [],
            'superadmin' => array_map(
                fn ($s) => ['titulo' => __($s['titulo']), 'entradas' => self::resolver($s['entradas'], $u, $r)],
                self::plataforma(),
            ),
            'fox' => false,
            'suporte' => null,
            'utilizador' => [
                'nome' => $u->name,
                'papel' => 'Super Admin',
                'ligacoes' => [
                    ['url' => route('my-account'), 'rotulo' => __('Minha Conta'), 'icone' => 'fa-user-circle', 'cor' => 'blue-600'],
                    ['url' => route('superadmin.system-settings'), 'rotulo' => __('Configurações'), 'icone' => 'fa-cog', 'cor' => 'gray-600'],
                ],
                'atualizacoes' => ['url' => route('changelog'), 'rotulo' => __('Atualizações'), 'versao' => 'v' . config('changelog.current', '1.0')],
                'sair' => route('logout'),
            ],
        ];
    }

    /** As áreas do painel da plataforma, pela ordem do layout de sempre. */
    private static function plataforma(): array
    {
        $e = fn (string $rota, string $icone, string $cor, string $rotulo, bool $marca = false) => [
            'rota' => $rota, 'rotulo' => $rotulo, 'icone' => $icone, 'cor' => $cor, 'activo' => $rota, 'topo' => true, 'marca' => $marca,
        ];

        return [
            ['titulo' => 'Principal', 'entradas' => [
                $e('superadmin.dashboard', 'fa-chart-line', 'yellow-400', 'Dashboard'),
                $e('superadmin.analytics', 'fa-fire', 'orange-400', 'Analytics & Leads'),
            ]],
            ['titulo' => 'Comercial', 'entradas' => [
                $e('superadmin.tenants', 'fa-building', 'green-400', 'Empresas / Tenants'),
                $e('superadmin.restaurant-venue-requests', 'fa-store', 'orange-400', 'Pedidos de Estabelecimentos'),
                $e('superadmin.plans', 'fa-tags', 'pink-400', 'Planos'),
                $e('superadmin.modules', 'fa-puzzle-piece', 'purple-400', 'Módulos'),
                $e('superadmin.billing', 'fa-file-invoice-dollar', 'emerald-400', 'Faturação / Billing'),
                $e('superadmin.licenciamento', 'fa-key', 'indigo-400', 'Licenciamento Offline'),
                $e('superadmin.aparelhos-pwa', 'fa-mobile-screen-button', 'emerald-400', 'Aparelhos com PWA'),
            ]],
            ['titulo' => 'Comunicação', 'entradas' => [
                $e('superadmin.mensagens', 'fa-bullhorn', 'indigo-400', 'Mensagens às Empresas'),
                $e('superadmin.contact-messages', 'fa-comments', 'cyan-400', 'Mensagens de Contacto'),
                $e('superadmin.email-templates', 'fa-envelope', 'blue-400', 'Email Templates'),
                $e('superadmin.smtp-settings', 'fa-server', 'emerald-400', 'SMTP'),
                $e('superadmin.email-logs', 'fa-history', 'yellow-400', 'Email Logs'),
                $e('superadmin.sms-settings', 'fa-sms', 'green-400', 'SMS'),
                $e('superadmin.sms-empresas', 'fa-comment-sms', 'teal-400', 'SMS às Empresas'),
                $e('superadmin.whatsapp-notifications', 'fa-whatsapp', 'green-400', 'WhatsApp', true),
            ]],
            ['titulo' => 'Sistema', 'entradas' => [
                $e('superadmin.system-updates', 'fa-cloud-download-alt', 'cyan-400', 'Atualizações'),
                $e('superadmin.system-commands', 'fa-terminal', 'green-400', 'Comandos & Seeders'),
                $e('superadmin.script-runner', 'fa-code', 'amber-400', 'Script Runner'),
                $e('superadmin.system-optimization', 'fa-rocket', 'yellow-400', 'Otimização'),
                $e('superadmin.copias', 'fa-shield-halved', 'emerald-400', 'Cópias de Segurança'),
            ]],
            ['titulo' => 'Configuração', 'entradas' => [
                $e('superadmin.system-settings', 'fa-cog', 'purple-400', 'Gerais'),
                $e('superadmin.software-settings', 'fa-shield-alt', 'red-400', 'Software'),
                $e('superadmin.saft', 'fa-key', 'orange-400', 'SAFT-AO'),
            ]],
        ];
    }

    /* ─── O esquema ────────────────────────────────────────────────────── */

    private static function principal(): array
    {
        return [
            ['rota' => 'home', 'rotulo' => 'Início', 'icone' => 'fa-home', 'cor' => 'blue-300', 'activo' => 'home', 'topo' => true],
        ];
    }

    /** @return array<int, array> */
    private static function grupos(): array
    {
        return [
            [
                'chave' => 'users', 'rotulo' => 'Utilizadores', 'icone' => 'fa-users', 'cor' => 'purple-300', 'leve' => true,
                'aberto' => 'users.*',
                // Apenas Super Admin.
                'quando' => fn (User $u) => $u->isSuperAdmin() || $u->hasRole('Super Admin'),
                'entradas' => [
                    ['rota' => 'users.index', 'rotulo' => 'Gestão de Utilizadores', 'icone' => 'fa-user-friends', 'cor' => 'purple-400', 'activo' => 'users.index'],
                    ['rota' => 'users.roles-permissions', 'rotulo' => 'Roles & Permissões', 'icone' => 'fa-shield-alt', 'cor' => 'purple-400', 'activo' => 'users.roles-permissions'],
                ],
            ],

            [
                'chave' => 'invoicing', 'rotulo' => 'Facturação', 'icone' => 'fa-file-invoice-dollar', 'cor' => 'yellow-400',
                'modulo' => 'invoicing', 'aberto' => 'invoicing.*',
                'entradas' => [
                    ['rota' => 'invoicing.dashboard', 'rotulo' => 'Dashboard', 'prefixo' => '📊', 'forte' => true, 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'invoicing.dashboard', 'permissao' => 'invoicing.dashboard.view'],
                    ['separador' => true],
                    ['rota' => 'invoicing.pos', 'rotulo' => 'POS - Ponto de Venda', 'prefixo' => '🛒', 'forte' => true, 'icone' => 'fa-cash-register', 'cor' => 'emerald-400', 'activo' => 'invoicing.pos', 'permissao' => 'invoicing.pos.access'],
                    ['rota' => 'invoicing.offline.pos', 'rotulo' => 'POS Offline (PWA)', 'prefixo' => '📱', 'forte' => true, 'icone' => 'fa-mobile-screen-button', 'cor' => 'cyan-400', 'activo' => 'invoicing.offline.*', 'permissao' => 'invoicing.pos.access'],
                    // Fica logo a seguir ao POS Offline: quem precisa disto vem
                    // de lá, com um aparelho que não sincronizou nas mãos.
                    ['rota' => 'invoicing.importar-copia-offline', 'rotulo' => 'Importar Cópia Offline', 'icone' => 'fa-file-import', 'cor' => 'emerald-400', 'activo' => 'invoicing.importar-copia-offline', 'permissao' => 'invoicing.pos.sell', 'hover' => 'bg-blue-800'],
                    ['rota' => 'invoicing.pos.shifts', 'rotulo' => 'Turnos de Caixa', 'prefixo' => '⏰', 'icone' => 'fa-user-clock', 'cor' => 'yellow-400', 'activo' => 'invoicing.pos.shifts', 'permissao' => 'invoicing.pos.access'],
                    ['rota' => 'invoicing.pos.shift-history', 'rotulo' => 'Histórico de Turnos', 'prefixo' => '📋', 'icone' => 'fa-history', 'cor' => 'purple-400', 'activo' => 'invoicing.pos.shift-history', 'permissao' => 'invoicing.pos.access'],
                    ['rota' => 'invoicing.pos.reports', 'rotulo' => 'Relatórios POS', 'prefixo' => '📊', 'icone' => 'fa-chart-line', 'cor' => 'cyan-400', 'activo' => 'invoicing.pos.reports', 'permissao' => 'invoicing.pos.reports'],
                    ['separador' => true],
                    ['rota' => 'invoicing.clients', 'rotulo' => 'Clientes', 'icone' => 'fa-users', 'cor' => 'green-400', 'activo' => 'invoicing.clients*', 'permissao' => 'invoicing.clients.view'],
                    ['rota' => 'invoicing.suppliers', 'rotulo' => 'Fornecedores', 'icone' => 'fa-truck', 'cor' => 'orange-400', 'activo' => 'invoicing.suppliers*', 'permissao' => 'invoicing.suppliers.view'],
                    ['rota' => 'invoicing.products', 'rotulo' => 'Produtos', 'icone' => 'fa-box', 'cor' => 'purple-400', 'activo' => 'invoicing.products*', 'permissao' => 'invoicing.products.view'],
                    ['rota' => 'invoicing.categories', 'rotulo' => 'Categorias', 'icone' => 'fa-folder', 'cor' => 'cyan-400', 'activo' => 'invoicing.categories*', 'permissao' => 'invoicing.categories.view'],
                    ['rota' => 'invoicing.brands', 'rotulo' => 'Marcas', 'icone' => 'fa-tag', 'cor' => 'pink-400', 'activo' => 'invoicing.brands*', 'permissao' => 'invoicing.brands.view'],

                    ['sub' => [
                        'chave' => 'documents', 'rotulo' => 'Documentos', 'icone' => 'fa-file-alt', 'cor' => 'purple-400',
                        'aberto' => ['invoicing.sales.*', 'invoicing.purchases.*', 'invoicing.receipts.*', 'invoicing.credit-notes.*', 'invoicing.debit-notes.*', 'invoicing.advances.*'],
                        'entradas' => [
                            ['rota' => 'invoicing.sales.proformas', 'rotulo' => 'Proformas Venda', 'icone' => 'fa-file-invoice-dollar', 'cor' => 'purple-400', 'barra' => 'purple-400', 'activo' => 'invoicing.sales.proformas*', 'permissao' => 'invoicing.sales.proformas.view'],
                            // routeIs('...quotes') exacto: com `quotes*`, estar
                            // nos Modelos acendia também os Orçamentos.
                            ['rota' => 'invoicing.sales.quotes', 'rotulo' => 'Orçamentos', 'icone' => 'fa-file-signature', 'cor' => 'teal-400', 'barra' => 'teal-400', 'activo' => ['invoicing.sales.quotes', 'invoicing.sales.quotes.*'], 'permissao' => 'invoicing.sales.quotes.view'],
                            ['rota' => 'invoicing.sales.quote-templates', 'rotulo' => 'Modelos de Proposta', 'icone' => 'fa-pen-ruler', 'cor' => 'violet-400', 'barra' => 'violet-400', 'activo' => 'invoicing.sales.quote-templates*', 'permissao' => 'invoicing.sales.quotes.view'],
                            ['rota' => 'invoicing.sales.invoices', 'rotulo' => 'Faturas Venda', 'icone' => 'fa-file-invoice', 'cor' => 'indigo-400', 'barra' => 'indigo-400', 'permissao' => 'invoicing.sales.invoices.view',
                                'activo' => fn (Request $r) => $r->routeIs('invoicing.sales.invoices*') && $r->query('type') !== 'FR'],
                            // Fatura-Recibo (FR): mesma sequência do POS, paga no acto.
                            ['rota' => 'invoicing.sales.invoices', 'parametros' => ['type' => 'FR'], 'rotulo' => 'Faturas-Recibo', 'icone' => 'fa-receipt', 'cor' => 'emerald-400', 'barra' => 'emerald-400', 'permissao' => 'invoicing.sales.invoices.view',
                                'activo' => fn (Request $r) => $r->routeIs('invoicing.sales.invoices*') && $r->query('type') === 'FR'],
                            ['rota' => 'invoicing.purchases.proformas', 'rotulo' => 'Proformas Compra', 'icone' => 'fa-file-invoice', 'cor' => 'orange-400', 'barra' => 'orange-400', 'activo' => 'invoicing.purchases.proformas*', 'permissao' => 'invoicing.purchases.proformas.view'],
                            ['rota' => 'invoicing.purchases.invoices', 'rotulo' => 'Faturas Compra', 'icone' => 'fa-file-invoice-dollar', 'cor' => 'red-400', 'barra' => 'red-400', 'activo' => 'invoicing.purchases.invoices*', 'permissao' => 'invoicing.purchases.invoices.view'],
                            ['rota' => 'invoicing.imports.index', 'rotulo' => 'Importações', 'icone' => 'fa-ship', 'cor' => 'cyan-400', 'barra' => 'cyan-400', 'activo' => 'invoicing.imports*', 'permissao' => 'invoicing.imports.view'],
                            ['rota' => 'invoicing.receipts.index', 'rotulo' => 'Recibos', 'icone' => 'fa-receipt', 'cor' => 'blue-400', 'barra' => 'blue-400', 'activo' => 'invoicing.receipts*', 'permissao' => 'invoicing.receipts.view'],
                            ['rota' => 'invoicing.credit-notes.index', 'rotulo' => 'Notas Crédito', 'icone' => 'fa-file-circle-minus', 'cor' => 'red-400', 'barra' => 'red-400', 'activo' => 'invoicing.credit-notes*', 'permissao' => 'invoicing.credit-notes.view'],
                            ['rota' => 'invoicing.debit-notes.index', 'rotulo' => 'Notas Débito', 'icone' => 'fa-file-circle-plus', 'cor' => 'green-400', 'barra' => 'green-400', 'activo' => 'invoicing.debit-notes*', 'permissao' => 'invoicing.debit-notes.view'],
                            ['rota' => 'invoicing.transport-guides', 'rotulo' => 'Guias Transporte', 'icone' => 'fa-truck', 'cor' => 'orange-400', 'barra' => 'orange-400', 'activo' => 'invoicing.transport-guides*', 'qualquer' => ['invoicing.transport-guides.view', 'invoicing.debit-notes.view']],
                            ['rota' => 'invoicing.advances.index', 'rotulo' => 'Adiantamentos', 'icone' => 'fa-coins', 'cor' => 'yellow-400', 'barra' => 'yellow-400', 'activo' => 'invoicing.advances*', 'permissao' => 'invoicing.advances.view'],
                        ],
                    ]],

                    ['rota' => 'invoicing.warehouses', 'rotulo' => 'Armazéns', 'icone' => 'fa-warehouse', 'cor' => 'indigo-400', 'activo' => 'invoicing.warehouses*', 'permissao' => 'invoicing.warehouses.view'],
                    ['rota' => 'invoicing.stock', 'rotulo' => 'Gestão Stock', 'icone' => 'fa-boxes', 'cor' => 'yellow-400', 'activo' => 'invoicing.stock', 'permissao' => 'invoicing.stock.view'],
                    ['rota' => 'invoicing.quebras', 'rotulo' => 'Quebras e Desperdício', 'icone' => 'fa-dumpster-fire', 'cor' => 'rose-400', 'activo' => 'invoicing.quebras', 'permissao' => 'invoicing.stock.view'],
                    ['rota' => 'invoicing.product-batches', 'rotulo' => 'Lotes e Validades', 'icone' => 'fa-calendar-check', 'cor' => 'orange-400', 'activo' => 'invoicing.product-batches', 'permissao' => 'invoicing.product-batches.view'],
                    ['rota' => 'invoicing.expiry-report', 'rotulo' => 'Relatório Validade', 'prefixo' => '📊', 'icone' => 'fa-chart-line', 'cor' => 'red-400', 'activo' => 'invoicing.expiry-report', 'permissao' => 'invoicing.reports.view'],
                    ['rota' => 'invoicing.warehouse-transfer', 'rotulo' => 'Transfer. Armazéns', 'icone' => 'fa-exchange-alt', 'cor' => 'blue-400', 'activo' => 'invoicing.warehouse-transfer', 'permissao' => 'invoicing.warehouse-transfer.view'],
                    ['rota' => 'invoicing.inter-company-transfer', 'rotulo' => 'Transfer. Inter-Empresa', 'icone' => 'fa-building-circle-arrow-right', 'cor' => 'teal-400', 'activo' => 'invoicing.inter-company-transfer', 'permissao' => 'invoicing.inter-company-transfer.view'],
                    ['separador' => true],

                    // Hub de Relatórios — submenu em árvore (oculto para
                    // Caixa/Vendedor sem permissão).
                    ['sub' => [
                        'chave' => 'reports', 'rotulo' => 'Relatórios', 'prefixo' => '📈', 'icone' => 'fa-chart-column', 'cor' => 'emerald-400',
                        'permissao' => 'invoicing.reports.view',
                        'aberto' => ['invoicing.reports.*', 'invoicing.expiry-report'],
                        'entradas' => array_merge(
                            [['rota' => 'invoicing.reports.hub', 'rotulo' => 'Painel de Relatórios', 'icone' => 'fa-table-cells', 'cor' => 'yellow-400', 'activo' => 'invoicing.reports.hub']],
                            self::relatorios()
                        ),
                    ]],
                    ['separador' => true],

                    ['rota' => 'invoicing.taxes', 'rotulo' => 'Impostos (IVA)', 'icone' => 'fa-percent', 'cor' => 'green-400', 'activo' => 'invoicing.taxes', 'permissao' => 'invoicing.taxes.view'],
                    ['rota' => 'invoicing.series', 'rotulo' => 'Séries de Documentos', 'icone' => 'fa-hashtag', 'cor' => 'pink-400', 'activo' => 'invoicing.series', 'permissao' => 'invoicing.series.view'],
                    ['rota' => 'invoicing.payment-terms', 'rotulo' => 'Condições de Pagamento', 'icone' => 'fa-calendar-check', 'cor' => 'indigo-300', 'activo' => 'invoicing.payment-terms', 'permissao' => 'invoicing.settings.view'],
                    ['rota' => 'invoicing.saft-generator', 'rotulo' => 'Gerador SAFT-AO', 'icone' => 'fa-file-code', 'cor' => 'purple-400', 'activo' => 'invoicing.saft-generator', 'permissao' => 'invoicing.saft.view'],
                    ['rota' => 'invoicing.settings', 'rotulo' => 'Configurações', 'icone' => 'fa-cogs', 'cor' => 'purple-400', 'activo' => 'invoicing.settings', 'permissao' => 'invoicing.settings.view'],
                    // Auditoria: quem fez o quê. Mesma permissão das configurações.
                    ['rota' => 'invoicing.audit', 'rotulo' => 'Auditoria', 'icone' => 'fa-clipboard-list', 'cor' => 'slate-300', 'activo' => 'invoicing.audit', 'permissao' => 'invoicing.settings.view'],
                    ['rota' => 'invoicing.agt-settings', 'rotulo' => 'AGT Angola', 'icone' => 'fa-file-signature', 'cor' => 'orange-400', 'activo' => 'invoicing.agt-settings', 'permissao' => 'invoicing.agt.view'],
                ],
            ],

            // Tesouraria, integrada com a facturação: o mesmo módulo.
            [
                'chave' => 'treasury', 'rotulo' => 'Tesouraria', 'icone' => 'fa-coins', 'cor' => 'green-400',
                'modulo' => 'invoicing', 'aberto' => 'treasury.*', 'barra' => 'green-400',
                'entradas' => [
                    /*
                     * O PAINEL, que nunca esteve no menu.
                     *
                     * A página existia e só se lá chegava escrevendo o URL —
                     * era a única do sistema com um painel e sem entrada. Todos
                     * os outros módulos abrem o submenu pelo seu, e é ali que se
                     * vê quanto dinheiro há antes de se ir ao extracto.
                     */
                    ['rota' => 'treasury.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'treasury.dashboard', 'permissao' => 'treasury.transactions.view'],
                    ['rota' => 'treasury.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-file-invoice-dollar', 'cor' => 'purple-400', 'activo' => 'treasury.reports*', 'permissao' => 'treasury.reports.view'],
                    ['rota' => 'treasury.accounts', 'rotulo' => 'Contas Bancárias', 'icone' => 'fa-wallet', 'cor' => 'purple-400', 'activo' => 'treasury.accounts*', 'permissao' => 'treasury.accounts.view'],
                    ['rota' => 'treasury.transactions', 'rotulo' => 'Transações', 'icone' => 'fa-exchange-alt', 'cor' => 'teal-400', 'activo' => 'treasury.transactions*', 'permissao' => 'treasury.transactions.view'],
                    ['rota' => 'treasury.transaction-types', 'rotulo' => 'Tipos de Transação', 'icone' => 'fa-list', 'cor' => 'cyan-400', 'activo' => 'treasury.transaction-types', 'permissao' => 'treasury.transactions.view'],
                    ['rota' => 'treasury.transaction-categories', 'rotulo' => 'Categorias de Transação', 'icone' => 'fa-tags', 'cor' => 'orange-400', 'activo' => 'treasury.transaction-categories', 'permissao' => 'treasury.transactions.view'],
                    ['rota' => 'treasury.transfers', 'rotulo' => 'Transferências', 'icone' => 'fa-right-left', 'cor' => 'yellow-400', 'activo' => 'treasury.transfers*', 'permissao' => 'treasury.transfers.view'],
                    ['rota' => 'treasury.payment-methods', 'rotulo' => 'Métodos de Pagamento', 'icone' => 'fa-money-bill-wave', 'cor' => 'green-400', 'activo' => 'treasury.payment-methods*', 'permissao' => 'treasury.payment-methods.view'],
                    ['rota' => 'treasury.banks', 'rotulo' => 'Bancos', 'icone' => 'fa-university', 'cor' => 'blue-400', 'activo' => 'treasury.banks*', 'permissao' => 'treasury.banks.view'],
                    ['rota' => 'treasury.cash-registers', 'rotulo' => 'Caixas', 'icone' => 'fa-cash-register', 'cor' => 'orange-400', 'activo' => 'treasury.cash-registers*', 'permissao' => 'treasury.cash-registers.view'],
                ],
            ],

            [
                'chave' => 'events', 'rotulo' => 'Eventos', 'icone' => 'fa-calendar-alt', 'cor' => 'pink-400',
                'modulo' => 'eventos', 'aberto' => 'events.*', 'barra' => 'pink-400',
                'entradas' => [
                    ['rota' => 'events.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-pie', 'cor' => 'blue-400', 'activo' => 'events.dashboard', 'permissao' => 'events.dashboard.view'],
                    ['rota' => 'events.calendar', 'rotulo' => 'Calendário', 'icone' => 'fa-calendar-alt', 'cor' => 'indigo-400', 'activo' => 'events.calendar', 'permissao' => 'events.calendar.view'],
                    ['rota' => 'events.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-bar', 'cor' => 'purple-400', 'activo' => 'events.reports', 'permissao' => 'events.reports.view'],
                    ['rota' => 'events.equipment.index', 'rotulo' => 'Equipamentos', 'icone' => 'fa-tools', 'cor' => 'purple-400', 'activo' => 'events.equipment.*', 'permissao' => 'events.equipment.view'],
                    ['rota' => 'events.venues.index', 'rotulo' => 'Locais', 'icone' => 'fa-map-marker-alt', 'cor' => 'red-400', 'activo' => 'events.venues.*', 'permissao' => 'events.venues.view'],
                    ['rota' => 'events.types.index', 'rotulo' => 'Tipos de Eventos', 'icone' => 'fa-tags', 'cor' => 'yellow-400', 'activo' => 'events.types.*', 'permissao' => 'events.types.view'],
                    ['rota' => 'events.technicians.index', 'rotulo' => 'Técnicos', 'icone' => 'fa-user-tie', 'cor' => 'cyan-400', 'activo' => 'events.technicians.*', 'permissao' => 'events.technicians.view'],
                ],
            ],

            [
                'chave' => 'hr', 'rotulo' => 'Recursos Humanos', 'icone' => 'fa-user-tie', 'cor' => 'cyan-400',
                'modulo' => 'rh', 'aberto' => 'hr.*', 'barra' => 'cyan-400',
                /*
                 * CADA ENTRADA ATRÁS DA SUA PERMISSÃO.
                 *
                 * Com a migração para React, as vinte e seis rotas do RH
                 * ganharam guarda — e um menu que continua a oferecer o que a
                 * rota recusa manda o utilizador para um 403, que se lê como
                 * avaria do sistema quando é a guarda a funcionar.
                 */
                'entradas' => [
                    ['rota' => 'hr.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'cyan-400', 'activo' => 'hr.dashboard', 'permissao' => 'hr.dashboard.view'],
                    ['separador' => true],
                    ['rota' => 'hr.employees.index', 'rotulo' => 'Funcionários', 'icone' => 'fa-users', 'cor' => 'blue-400', 'activo' => 'hr.employees*', 'permissao' => 'employees.view'],
                    ['rota' => 'hr.departments.index', 'rotulo' => 'Departamentos', 'icone' => 'fa-building', 'cor' => 'purple-400', 'activo' => 'hr.departments*', 'permissao' => 'hr.departments.view'],
                    // OS CARGOS ganham entrada própria: em Livewire estavam
                    // escondidos dentro do ecrã dos departamentos e ninguém
                    // que não soubesse lá chegava.
                    ['rota' => 'hr.positions.index', 'rotulo' => 'Cargos', 'icone' => 'fa-user-tie', 'cor' => 'cyan-400', 'activo' => 'hr.positions*', 'permissao' => 'hr.positions.view'],
                    // OS CONTRATOS: a tabela existia desde o princípio e o
                    // `PayrollService` lia dela o salário pago — sem ecrã
                    // nenhum por onde a ver.
                    ['rota' => 'hr.contracts.index', 'rotulo' => 'Contratos', 'icone' => 'fa-file-signature', 'cor' => 'teal-400', 'activo' => 'hr.contracts*', 'permissao' => 'hr.contracts.view'],
                    ['separador' => true],
                    ['rota' => 'hr.attendance.index', 'rotulo' => 'Presenças', 'icone' => 'fa-clock', 'cor' => 'green-400', 'activo' => 'hr.attendance*', 'permissao' => 'attendance.manage'],
                    ['rota' => 'hr.vacations.index', 'rotulo' => 'Férias', 'icone' => 'fa-umbrella-beach', 'cor' => 'yellow-400', 'activo' => 'hr.vacations*', 'permissao' => 'hr.vacations.view'],
                    ['rota' => 'hr.leaves', 'rotulo' => 'Licenças e Faltas', 'icone' => 'fa-calendar-times', 'cor' => 'orange-400', 'activo' => 'hr.leaves*', 'permissao' => 'hr.leaves.view'],
                    ['rota' => 'hr.overtime', 'rotulo' => 'Horas Extras', 'icone' => 'fa-business-time', 'cor' => 'pink-400', 'activo' => 'hr.overtime', 'permissao' => 'hr.overtime.view'],
                    ['rota' => 'hr.overtime-night-shift', 'rotulo' => 'Turno Noturno', 'icone' => 'fa-moon', 'cor' => 'indigo-300', 'activo' => 'hr.overtime-night-shift', 'permissao' => 'hr.overtime.view'],
                    ['rota' => 'hr.salary-discounts', 'rotulo' => 'Descontos Salariais', 'icone' => 'fa-percentage', 'cor' => 'red-400', 'activo' => 'hr.salary-discounts', 'permissao' => 'hr.discounts.view'],
                    // Só quando a empresa trabalha por turnos.
                    ['rota' => 'hr.shifts.index', 'rotulo' => 'Turnos', 'icone' => 'fa-clock', 'cor' => 'purple-400', 'activo' => 'hr.shifts*', 'permissao' => 'hr.shifts.view', 'quando' => fn () => self::usaTurnos()],
                    ['separador' => true],
                    ['rota' => 'hr.payroll', 'rotulo' => 'Folha de Pagamento', 'icone' => 'fa-money-check-alt', 'cor' => 'emerald-400', 'activo' => 'hr.payroll*', 'permissao' => 'payroll.process'],
                    ['rota' => 'hr.advances', 'rotulo' => 'Adiantamentos', 'icone' => 'fa-hand-holding-usd', 'cor' => 'teal-400', 'activo' => 'hr.advances*', 'permissao' => 'hr.advances.view'],
                    ['separador' => true],
                    ['rota' => 'hr.irt-map', 'rotulo' => 'Mapa de IRT', 'icone' => 'fa-landmark', 'cor' => 'rose-400', 'activo' => 'hr.irt-map*', 'permissao' => 'hr.irt.view'],
                    ['rota' => 'hr.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-pie', 'cor' => 'violet-400', 'activo' => 'hr.reports*', 'permissao' => 'hr.reports.view'],
                    ['rota' => 'hr.settings', 'rotulo' => 'Configurações RH', 'icone' => 'fa-cogs', 'cor' => 'purple-400', 'activo' => 'hr.settings*', 'permissao' => 'hr.settings.view'],
                ],
            ],

            [
                'chave' => 'accounting', 'rotulo' => 'Contabilidade', 'icone' => 'fa-chart-line', 'cor' => 'emerald-400',
                'modulo' => 'contabilidade', 'aberto' => 'accounting.*', 'barra' => 'emerald-400',
                'entradas' => [
                    ['rota' => 'accounting.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-pie', 'cor' => 'emerald-400', 'activo' => 'accounting.dashboard', 'permissao' => 'accounting.dashboard.view'],
                    ['separador' => true],
                    ['rota' => 'accounting.accounts', 'rotulo' => 'Plano de Contas', 'icone' => 'fa-sitemap', 'cor' => 'purple-400', 'activo' => 'accounting.accounts*', 'permissao' => 'accounting.accounts.view'],
                    ['rota' => 'accounting.journals', 'rotulo' => 'Diários', 'icone' => 'fa-book', 'cor' => 'blue-400', 'activo' => 'accounting.journals*', 'permissao' => 'accounting.journals.view'],
                    ['rota' => 'accounting.document-types', 'rotulo' => 'Tipos de Documentos', 'icone' => 'fa-file-alt', 'cor' => 'purple-400', 'activo' => 'accounting.document-types*', 'permissao' => 'accounting.document-types.view'],
                    ['rota' => 'accounting.moves', 'rotulo' => 'Lançamentos', 'icone' => 'fa-file-invoice', 'cor' => 'green-400', 'activo' => 'accounting.moves*', 'permissao' => 'accounting.moves.view'],
                    ['rota' => 'accounting.periods', 'rotulo' => 'Períodos', 'icone' => 'fa-calendar-alt', 'cor' => 'yellow-400', 'activo' => 'accounting.periods*', 'permissao' => 'accounting.periods.view'],
                    ['separador' => true],
                    ['rota' => 'accounting.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-bar', 'cor' => 'cyan-400', 'activo' => 'accounting.reports*', 'permissao' => 'accounting.reports.view'],
                    ['separador' => true],
                    ['rota' => 'accounting.reconciliation', 'rotulo' => 'Reconciliação', 'icone' => 'fa-exchange-alt', 'cor' => 'blue-400', 'activo' => 'accounting.reconciliation*', 'permissao' => 'accounting.reconciliation.view'],
                    ['rota' => 'accounting.fixed-assets', 'rotulo' => 'Imobilizado', 'icone' => 'fa-building', 'cor' => 'purple-400', 'activo' => 'accounting.fixed-assets*', 'permissao' => 'accounting.fixed-assets.view'],
                    ['rota' => 'accounting.currencies', 'rotulo' => 'Moedas', 'icone' => 'fa-coins', 'cor' => 'green-400', 'activo' => 'accounting.currencies*', 'permissao' => 'accounting.currencies.view'],
                    ['rota' => 'accounting.cost-centers', 'rotulo' => 'Centros Custo', 'icone' => 'fa-sitemap', 'cor' => 'orange-400', 'activo' => 'accounting.cost-centers*', 'permissao' => 'accounting.cost-centers.view'],
                    ['rota' => 'accounting.analytics', 'rotulo' => 'Analítica', 'icone' => 'fa-chart-pie', 'cor' => 'pink-400', 'activo' => 'accounting.analytics*', 'permissao' => 'accounting.analytics.view'],
                    ['rota' => 'accounting.budgets', 'rotulo' => 'Orçamentos', 'icone' => 'fa-calculator', 'cor' => 'indigo-400', 'activo' => 'accounting.budgets*', 'permissao' => 'accounting.budgets.view'],
                    ['separador' => true],
                    ['rota' => 'accounting.settings', 'rotulo' => 'Configurações', 'icone' => 'fa-cog', 'cor' => 'gray-400', 'activo' => 'accounting.settings*', 'permissao' => 'accounting.settings.view'],
                ],
            ],

            [
                'chave' => 'oficina', 'rotulo' => 'Gestão de Oficina', 'icone' => 'fa-wrench', 'cor' => 'orange-400',
                'modulo' => 'oficina', 'aberto' => 'workshop.*', 'barra' => 'orange-400',
                'entradas' => [
                    // Cada entrada atrás da permissão que a rota exige — as
                    // dezanove existiam e nenhuma das oito rotas as aplicava.
                    ['rota' => 'workshop.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'workshop.dashboard', 'permissao' => 'workshop.dashboard.view'],
                    // OS CLIENTES DA CASA, dentro da oficina — o mesmo ecrã da facturação
                    // (a viatura liga-se a um cliente e é a ele que se factura). Pedido de 15/09/2026.
                    ['rota' => 'workshop.clients', 'rotulo' => 'Clientes', 'icone' => 'fa-users', 'cor' => 'green-400', 'activo' => 'workshop.clients*', 'permissao' => 'invoicing.clients.view'],
                    ['rota' => 'workshop.vehicles', 'rotulo' => 'Veículos', 'icone' => 'fa-car', 'cor' => 'blue-400', 'activo' => 'workshop.vehicles*', 'permissao' => 'workshop.vehicles.view'],
                    ['rota' => 'workshop.vehicle-statuses', 'rotulo' => 'Estados de Viatura', 'icone' => 'fa-traffic-light', 'cor' => 'teal-400', 'activo' => 'workshop.vehicle-statuses*', 'permissao' => 'workshop.vehicles.view'],
                    ['rota' => 'workshop.bays', 'rotulo' => 'Elevadores e Baias', 'icone' => 'fa-warehouse', 'cor' => 'blue-400', 'activo' => 'workshop.bays*', 'permissao' => 'workshop.work-orders.view'],
                    ['rota' => 'workshop.inspection-templates', 'rotulo' => 'Modelos de Inspecção', 'icone' => 'fa-list-check', 'cor' => 'green-400', 'activo' => 'workshop.inspection-templates*', 'permissao' => 'workshop.work-orders.view'],
                    ['rota' => 'workshop.mechanics', 'rotulo' => 'Mecânicos', 'icone' => 'fa-user-cog', 'cor' => 'orange-400', 'activo' => 'workshop.mechanics*', 'permissao' => 'workshop.mechanics.view'],
                    ['rota' => 'workshop.services', 'rotulo' => 'Serviços', 'icone' => 'fa-tools', 'cor' => 'purple-400', 'activo' => 'workshop.services*', 'permissao' => 'workshop.services.view'],
                    ['rota' => 'workshop.packages', 'rotulo' => 'Pacotes de Serviço', 'icone' => 'fa-box-open', 'cor' => 'violet-400', 'activo' => 'workshop.packages*', 'permissao' => 'workshop.work-orders.view'],
                    ['rota' => 'workshop.parts', 'rotulo' => 'Peças', 'icone' => 'fa-boxes', 'cor' => 'orange-400', 'activo' => 'workshop.parts*', 'permissao' => 'workshop.parts.view'],
                    ['rota' => 'workshop.schedule', 'rotulo' => 'Agenda', 'icone' => 'fa-calendar-days', 'cor' => 'cyan-400', 'activo' => 'workshop.schedule*', 'permissao' => 'workshop.work-orders.view'],
                    ['rota' => 'workshop.board', 'rotulo' => 'Quadro de Trabalho', 'icone' => 'fa-table-columns', 'cor' => 'pink-400', 'activo' => 'workshop.board*', 'permissao' => 'workshop.work-orders.view'],
                    ['rota' => 'workshop.work-orders', 'rotulo' => 'Ordens de Serviço', 'icone' => 'fa-clipboard-list', 'cor' => 'yellow-400', 'activo' => 'workshop.work-orders*', 'permissao' => 'workshop.work-orders.view'],
                    ['rota' => 'workshop.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-bar', 'cor' => 'green-400', 'activo' => 'workshop.reports*', 'permissao' => 'workshop.reports.view'],
                ],
            ],

            [
                'chave' => 'hotel', 'rotulo' => 'Gestão de Hotel', 'icone' => 'fa-hotel', 'cor' => 'purple-400',
                'modulo' => 'hotel', 'aberto' => 'hotel.*', 'barra' => 'purple-400',
                'entradas' => [
                    ['rota' => 'hotel.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'hotel.dashboard', 'permissao' => 'hotel.dashboard.view'],
                    ['rota' => 'hotel.reservations', 'rotulo' => 'Reservas', 'icone' => 'fa-calendar-check', 'cor' => 'green-400', 'activo' => 'hotel.reservations*', 'permissao' => 'hotel.reservations.view'],
                    ['rota' => 'hotel.walk-in', 'rotulo' => 'Walk-in', 'icone' => 'fa-walking', 'cor' => 'emerald-400', 'activo' => 'hotel.walk-in*', 'permissao' => 'hotel.walk-in.create'],
                    ['rota' => 'hotel.checkout', 'rotulo' => 'Check-out', 'icone' => 'fa-sign-out-alt', 'cor' => 'red-400', 'activo' => 'hotel.checkout*', 'permissao' => 'hotel.checkout.manage'],
                    ['rota' => 'hotel.calendar', 'rotulo' => 'Calendário', 'icone' => 'fa-calendar-alt', 'cor' => 'indigo-400', 'activo' => 'hotel.calendar*', 'permissao' => 'hotel.reservations.view'],
                    ['rota' => 'hotel.housekeeping', 'rotulo' => 'Housekeeping', 'icone' => 'fa-broom', 'cor' => 'teal-400', 'activo' => 'hotel.housekeeping*', 'permissao' => 'hotel.housekeeping.view'],
                    ['rota' => 'hotel.maintenance', 'rotulo' => 'Manutenção', 'icone' => 'fa-tools', 'cor' => 'orange-400', 'activo' => 'hotel.maintenance*', 'permissao' => 'hotel.maintenance.view'],
                    ['rota' => 'hotel.staff', 'rotulo' => 'Funcionários', 'icone' => 'fa-users', 'cor' => 'cyan-400', 'activo' => 'hotel.staff*', 'permissao' => 'hotel.staff.view'],
                    ['rota' => 'hotel.rooms', 'rotulo' => 'Quartos', 'icone' => 'fa-door-open', 'cor' => 'orange-400', 'activo' => 'hotel.rooms*', 'permissao' => 'hotel.rooms.view'],
                    ['rota' => 'hotel.room-types', 'rotulo' => 'Tipos de Quarto', 'icone' => 'fa-bed', 'cor' => 'purple-400', 'activo' => 'hotel.room-types*', 'permissao' => 'hotel.room-types.view'],
                    ['rota' => 'hotel.guests', 'rotulo' => 'Hóspedes', 'icone' => 'fa-users', 'cor' => 'cyan-400', 'activo' => 'hotel.guests*', 'permissao' => 'hotel.guests.view'],
                    ['rota' => 'hotel.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-bar', 'cor' => 'amber-400', 'activo' => 'hotel.reports*', 'permissao' => 'hotel.reports.view'],
                    ['rota' => 'hotel.rates', 'rotulo' => 'Tarifas', 'icone' => 'fa-tags', 'cor' => 'amber-400', 'activo' => 'hotel.rates*', 'permissao' => 'hotel.rates.view'],
                    ['rota' => 'hotel.packages', 'rotulo' => 'Pacotes', 'icone' => 'fa-gift', 'cor' => 'pink-400', 'activo' => 'hotel.packages*', 'permissao' => 'hotel.packages.view'],
                    ['rota' => 'hotel.kiandastay', 'rotulo' => 'KiandaStay', 'sem_traducao' => true, 'icone' => 'fa-link', 'cor' => 'gray-400', 'activo' => 'hotel.kiandastay*', 'permissao' => 'hotel.settings.view'],
                    ['rota' => 'hotel.settings', 'rotulo' => 'Configurações', 'icone' => 'fa-cog', 'cor' => 'gray-400', 'activo' => 'hotel.settings*', 'permissao' => 'hotel.settings.view'],
                ],
            ],

            [
                'chave' => 'salon', 'rotulo' => 'Salão de Beleza', 'icone' => 'fa-spa', 'cor' => 'pink-400',
                'modulo' => 'salon', 'aberto' => 'salon.*', 'barra' => 'pink-400',
                'entradas' => [
                    ['rota' => 'salon.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'salon.dashboard', 'permissao' => 'salon.dashboard.view'],
                    ['rota' => 'salon.appointments', 'rotulo' => 'Agendamentos', 'icone' => 'fa-calendar-check', 'cor' => 'green-400', 'activo' => 'salon.appointments*', 'permissao' => 'salon.appointments.view'],
                    ['rota' => 'salon.clients', 'rotulo' => 'Clientes', 'icone' => 'fa-users', 'cor' => 'cyan-400', 'activo' => 'salon.clients*', 'permissao' => 'salon.clients.view'],
                    ['rota' => 'salon.services', 'rotulo' => 'Serviços', 'icone' => 'fa-cut', 'cor' => 'purple-400', 'activo' => 'salon.services', 'permissao' => 'salon.services.view'],
                    ['rota' => 'salon.services.categories', 'rotulo' => 'Categorias', 'icone' => 'fa-folder', 'cor' => 'pink-300', 'activo' => 'salon.services.categories', 'permissao' => 'salon.categories.view'],
                    ['rota' => 'salon.professionals', 'rotulo' => 'Profissionais', 'icone' => 'fa-user-tie', 'cor' => 'orange-400', 'activo' => 'salon.professionals*', 'permissao' => 'salon.professionals.view'],
                    ['rota' => 'salon.products', 'rotulo' => 'Produtos', 'icone' => 'fa-boxes', 'cor' => 'emerald-400', 'activo' => 'salon.products*', 'permissao' => 'salon.products.view'],
                    ['rota' => 'salon.pos', 'rotulo' => 'POS / Faturar', 'icone' => 'fa-cash-register', 'cor' => 'yellow-400', 'activo' => 'salon.pos*', 'permissao' => 'salon.pos.access'],
                    ['rota' => 'salon.reports.time', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-bar', 'cor' => 'cyan-400', 'activo' => 'salon.reports.*', 'permissao' => 'salon.reports.view'],
                    ['separador' => true],
                    ['rota' => 'salon.settings', 'rotulo' => 'Configurações', 'icone' => 'fa-cog', 'cor' => 'gray-400', 'activo' => 'salon.settings', 'permissao' => 'salon.settings.view'],
                ],
            ],

            [
                'chave' => 'restaurant', 'rotulo' => 'Restaurante', 'icone' => 'fa-utensils', 'cor' => 'orange-400',
                'modulo' => 'restaurant', 'aberto' => 'restaurant.*', 'barra' => 'orange-400',
                'entradas' => [
                    ['rota' => 'restaurant.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'orange-300', 'activo' => 'restaurant.dashboard', 'permissao' => 'restaurant.dashboard.view'],
                    ['rota' => 'restaurant.floor', 'rotulo' => 'Sala e Mesas', 'icone' => 'fa-chair', 'cor' => 'emerald-300', 'activo' => 'restaurant.floor', 'permissao' => 'restaurant.floor.view'],
                    ['rota' => 'restaurant.pos', 'rotulo' => 'POS Restaurante', 'icone' => 'fa-cash-register', 'cor' => 'orange-300', 'activo' => 'restaurant.pos', 'permissao' => 'restaurant.orders.view'],
                    ['rota' => 'restaurant.orders', 'rotulo' => 'Comandas', 'icone' => 'fa-receipt', 'cor' => 'violet-300', 'activo' => 'restaurant.orders', 'permissao' => 'restaurant.orders.view'],
                    ['rota' => 'restaurant.products', 'rotulo' => 'Produtos e Menu', 'icone' => 'fa-bowl-food', 'cor' => 'amber-300', 'activo' => 'restaurant.products', 'permissao' => 'restaurant.menu.view'],
                    ['rota' => 'restaurant.carta', 'rotulo' => 'Montar o Menu', 'icone' => 'fa-book-open-reader', 'cor' => 'pink-300', 'activo' => ['restaurant.carta', 'restaurant.categories'], 'permissao' => 'restaurant.menu.view'],
                    ['rota' => 'restaurant.carta.aparencia', 'rotulo' => 'Aparência da Carta', 'icone' => 'fa-palette', 'cor' => 'rose-300', 'activo' => 'restaurant.carta.aparencia', 'quando' => fn (User $u) => $u->can('restaurant.orders.view') && $u->can('restaurant.settings.view')],
                    ['rota' => 'restaurant.contacts', 'rotulo' => 'Clientes e Fornecedores', 'icone' => 'fa-address-book', 'cor' => 'blue-300', 'activo' => 'restaurant.contacts', 'permissao' => 'restaurant.orders.view'],
                    ['rota' => 'restaurant.shifts', 'rotulo' => 'Abrir / Fechar Turno', 'icone' => 'fa-clock', 'cor' => 'cyan-300', 'activo' => 'restaurant.shifts', 'permissao' => 'restaurant.orders.view'],
                    ['rota' => 'restaurant.shift-history', 'rotulo' => 'Histórico de Turnos', 'icone' => 'fa-clock-rotate-left', 'cor' => 'purple-300', 'activo' => 'restaurant.shift-history', 'permissao' => 'restaurant.orders.view'],
                    ['rota' => 'restaurant.kitchen', 'rotulo' => 'Cozinha / KDS', 'icone' => 'fa-fire-burner', 'cor' => 'red-300', 'activo' => 'restaurant.kitchen', 'permissao' => 'restaurant.kitchen.view'],
                    ['rota' => 'restaurant.reservations', 'rotulo' => 'Reservas', 'icone' => 'fa-calendar-check', 'cor' => 'cyan-300', 'activo' => 'restaurant.reservations', 'permissao' => 'restaurant.reservations.view'],
                    ['rota' => 'restaurant.recipes', 'rotulo' => 'Fichas Técnicas', 'icone' => 'fa-book-open', 'cor' => 'lime-300', 'activo' => 'restaurant.recipes', 'permissao' => 'restaurant.recipes.view'],
                    ['rota' => 'restaurant.stock', 'rotulo' => 'Stock e Desperdícios', 'icone' => 'fa-boxes-stacked', 'cor' => 'emerald-300', 'activo' => 'restaurant.stock', 'permissao' => 'restaurant.stock.view'],
                    ['rota' => 'restaurant.sales-report', 'rotulo' => 'Vendas e Caixa', 'icone' => 'fa-receipt', 'cor' => 'amber-300', 'activo' => 'restaurant.sales-report', 'permissao' => 'restaurant.reports.view'],
                    ['rota' => 'restaurant.reports', 'rotulo' => 'Relatórios', 'icone' => 'fa-chart-column', 'cor' => 'indigo-300', 'activo' => 'restaurant.reports', 'permissao' => 'restaurant.reports.view'],
                    ['rota' => 'restaurant.settings', 'rotulo' => 'Configurações', 'icone' => 'fa-gear', 'cor' => 'slate-300', 'activo' => 'restaurant.settings', 'permissao' => 'restaurant.settings.view'],
                ],
            ],

            // Notificações: não é um grupo que abre e fecha — é uma ligação
            // de topo com uma dependente.
            [
                'chave' => 'notifications', 'rotulo' => 'Notificações', 'icone' => 'fa-bell', 'cor' => 'yellow-400',
                'modulo' => 'notifications', 'simples' => true,
                'rota' => 'notifications.settings', 'activo' => 'notifications.settings',
                'entradas' => [
                    // O ecrã de modelos existe e não estava em lado nenhum do menu.
                    ['rota' => 'notifications.templates', 'rotulo' => 'Modelos', 'icone' => 'fa-file-lines', 'cor' => 'slate-300', 'activo' => 'notifications.templates', 'permissao' => 'notifications.view'],
                ],
            ],

            [
                'chave' => 'crm', 'rotulo' => 'CRM', 'icone' => 'fa-user-check', 'cor' => 'teal-400',
                'modulo' => 'crm', 'aberto' => 'crm.*', 'barra' => 'teal-400',
                'entradas' => [
                    ['rota' => 'crm.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'crm.dashboard', 'permissao' => 'crm.view'],
                    ['rota' => 'crm.leads', 'rotulo' => 'Leads', 'icone' => 'fa-user-plus', 'cor' => 'green-400', 'activo' => 'crm.leads*', 'permissao' => 'crm.leads.view'],
                    ['rota' => 'crm.oportunidades', 'rotulo' => 'Oportunidades', 'icone' => 'fa-bullseye', 'cor' => 'yellow-400', 'activo' => 'crm.oportunidades*', 'permissao' => 'crm.opportunities.view'],
                    ['rota' => 'crm.funil-vendas', 'rotulo' => 'Funil de Vendas', 'icone' => 'fa-filter', 'cor' => 'purple-400', 'activo' => 'crm.funil-vendas*', 'permissao' => 'crm.opportunities.view'],
                    ['rota' => 'crm.integracoes', 'rotulo' => 'Integração Meta', 'icone' => 'fa-facebook-messenger', 'marca' => true, 'cor' => 'sky-400', 'activo' => 'crm.integracoes*', 'permissao' => 'crm.integrations.manage'],
                ],
            ],

            [
                'chave' => 'inventario', 'rotulo' => 'Inventário', 'icone' => 'fa-boxes', 'cor' => 'amber-400',
                'modulo' => 'inventario', 'aberto' => 'inventario.*', 'barra' => 'amber-400',
                'entradas' => [
                    ['rota' => 'inventario.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'inventario.dashboard', 'permissao' => 'inventario.view'],
                    ['rota' => 'inventario.armazens', 'rotulo' => 'Armazéns', 'icone' => 'fa-warehouse', 'cor' => 'indigo-400', 'activo' => 'inventario.armazens*', 'permissao' => 'inventario.view'],
                    ['rota' => 'inventario.movimentos', 'rotulo' => 'Movimentos', 'icone' => 'fa-exchange-alt', 'cor' => 'green-400', 'activo' => 'inventario.movimentos*', 'permissao' => 'inventario.view'],
                    ['rota' => 'inventario.contagem', 'rotulo' => 'Contagem de Stock', 'icone' => 'fa-clipboard-check', 'cor' => 'purple-400', 'activo' => 'inventario.contagem*', 'permissao' => 'inventario.contagem.manage'],
                ],
            ],

            [
                'chave' => 'compras', 'rotulo' => 'Compras', 'icone' => 'fa-shopping-cart', 'cor' => 'lime-400',
                'modulo' => 'compras', 'aberto' => 'compras.*', 'barra' => 'lime-400',
                'entradas' => [
                    ['rota' => 'compras.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'compras.dashboard', 'permissao' => 'compras.view'],
                    ['rota' => 'compras.fornecedores', 'rotulo' => 'Fornecedores', 'icone' => 'fa-truck', 'cor' => 'orange-400', 'activo' => 'compras.fornecedores*', 'permissao' => 'compras.view'],
                    ['rota' => 'compras.requisicoes', 'rotulo' => 'Requisições', 'icone' => 'fa-file-alt', 'cor' => 'yellow-400', 'activo' => 'compras.requisicoes*', 'permissao' => 'compras.requisicoes.view'],
                    ['rota' => 'compras.encomendas', 'rotulo' => 'Encomendas', 'icone' => 'fa-clipboard-list', 'cor' => 'green-400', 'activo' => 'compras.encomendas*', 'permissao' => 'compras.encomendas.view'],
                ],
            ],

            [
                'chave' => 'projetos', 'rotulo' => 'Projetos', 'icone' => 'fa-project-diagram', 'cor' => 'violet-400',
                'modulo' => 'projetos', 'aberto' => 'projetos.*', 'barra' => 'violet-400',
                'entradas' => [
                    ['rota' => 'projetos.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'blue-400', 'activo' => 'projetos.dashboard', 'permissao' => 'projetos.view'],
                    ['rota' => 'projetos.lista', 'rotulo' => 'Projetos', 'icone' => 'fa-briefcase', 'cor' => 'indigo-400', 'activo' => 'projetos.lista*', 'permissao' => 'projetos.view'],
                    ['rota' => 'projetos.tarefas', 'rotulo' => 'Tarefas', 'icone' => 'fa-tasks', 'cor' => 'green-400', 'activo' => 'projetos.tarefas*', 'permissao' => 'projetos.tarefas.view'],
                    ['rota' => 'projetos.timesheet', 'rotulo' => 'Folha de Horas', 'icone' => 'fa-clock', 'cor' => 'yellow-400', 'activo' => 'projetos.timesheet*', 'permissao' => 'projetos.horas.registar'],
                ],
            ],
        ];
    }

    /**
     * Os relatórios da facturação, por grupo.
     *
     * Esta lista é MANTIDA À MÃO e vive separada do hub. Um relatório novo
     * tem de entrar nos dois sítios, senão só aparece no painel e ninguém o
     * encontra pelo menu — foi o que aconteceu ao extracto e aos ajustes de
     * stock.
     */
    private static function relatorios(): array
    {
        $grupos = [
            'Rentabilidade' => [
                ['invoicing.reports.profit-loss', 'Lucros e Perdas (DRE)', 'fa-chart-line'],
                ['invoicing.reports.margin', 'Análise de Margem', 'fa-percentage'],
                ['invoicing.reports.product-performance', 'Desempenho de Produtos', 'fa-chart-pie'],
                ['invoicing.reports.comparative', 'Comparativo', 'fa-balance-scale'],
            ],
            'Vendas' => [
                ['invoicing.reports.sales', 'Mapa de Vendas', 'fa-file-invoice'],
                ['invoicing.reports.top-clients', 'Top Clientes', 'fa-crown'],
                ['invoicing.reports.top-products', 'Top Produtos', 'fa-star'],
                ['invoicing.reports.sales-by-user', 'Vendas por Vendedor', 'fa-user-tie'],
            ],
            'Compras' => [
                ['invoicing.reports.purchases', 'Mapa de Compras', 'fa-shopping-cart'],
                ['invoicing.reports.top-suppliers', 'Top Fornecedores', 'fa-truck'],
                ['invoicing.reports.best-supplier', 'Melhor Fornecedor', 'fa-medal'],
            ],
            'Contas Correntes' => [
                ['invoicing.reports.accounts-receivable', 'Contas a Receber', 'fa-hand-holding-usd'],
                ['invoicing.reports.accounts-payable', 'Contas a Pagar', 'fa-money-bill-wave'],
                ['invoicing.reports.payment-methods', 'Recebimentos por Meio', 'fa-money-check-alt'],
                ['invoicing.reports.aging-clients', 'Aging de Clientes', 'fa-clock'],
                ['invoicing.reports.account-statement', 'Extracto de Conta Corrente', 'fa-file-invoice-dollar'],
            ],
            'Fiscal & SAFT' => [
                ['invoicing.reports.vat', 'Mapa de IVA', 'fa-percent'],
                ['invoicing.reports.documents', 'Mapa de Documentos', 'fa-file-alt'],
            ],
            'Produtos & Serviços' => [
                ['invoicing.reports.price-list', 'Tabela de Preços e Lucro', 'fa-tags'],
                ['invoicing.reports.services', 'Mapa de Serviços', 'fa-concierge-bell'],
                ['invoicing.expiry-report', 'Validade de Produtos', 'fa-calendar-check'],
            ],
            'Stock & Controlo' => [
                ['invoicing.reports.stock-adjustments', 'Ajustes de Stock', 'fa-sliders'],
            ],
        ];

        $entradas = [];

        foreach ($grupos as $titulo => $relatorios) {
            $entradas[] = ['titulo' => $titulo];

            foreach ($relatorios as [$rota, $rotulo, $icone]) {
                $entradas[] = ['rota' => $rota, 'rotulo' => $rotulo, 'icone' => $icone, 'cor' => 'emerald-300', 'barra' => 'emerald-400', 'activo' => $rota, 'relatorio' => true];
            }
        }

        return $entradas;
    }

    private static function superAdmin(): array
    {
        return [
            ['titulo' => 'Super Admin', 'entradas' => [
                ['rota' => 'superadmin.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'fa-chart-line', 'cor' => 'yellow-400', 'activo' => 'superadmin.dashboard', 'topo' => true],
                ['rota' => 'superadmin.tenants', 'rotulo' => 'Tenants', 'icone' => 'fa-building', 'cor' => 'green-400', 'activo' => 'superadmin.tenants', 'topo' => true],
                ['rota' => 'superadmin.modules', 'rotulo' => 'Módulos', 'icone' => 'fa-puzzle-piece', 'cor' => 'purple-400', 'activo' => 'superadmin.modules', 'topo' => true],
                ['rota' => 'superadmin.plans', 'rotulo' => 'Planos', 'icone' => 'fa-tags', 'cor' => 'pink-400', 'activo' => 'superadmin.plans', 'topo' => true],
                ['rota' => 'superadmin.billing', 'rotulo' => 'Billing', 'icone' => 'fa-file-invoice-dollar', 'cor' => 'emerald-400', 'activo' => 'superadmin.billing', 'topo' => true],
            ]],
            ['titulo' => 'Sistema', 'entradas' => [
                ['rota' => 'superadmin.system-updates', 'rotulo' => 'Atualizações do Sistema', 'icone' => 'fa-cloud-download-alt', 'cor' => 'cyan-400', 'activo' => 'superadmin.system-updates', 'topo' => true],
                ['rota' => 'superadmin.script-runner', 'rotulo' => 'Executar Scripts', 'icone' => 'fa-code', 'cor' => 'green-400', 'activo' => 'superadmin.script-runner', 'topo' => true],
            ]],
            ['titulo' => 'Configurações', 'entradas' => [
                ['rota' => 'superadmin.system-settings', 'rotulo' => 'Configurações do Sistema', 'icone' => 'fa-cog', 'cor' => 'purple-400', 'activo' => 'superadmin.system-settings', 'topo' => true],
                ['rota' => 'superadmin.saft', 'rotulo' => 'SAFT Configurações', 'icone' => 'fa-key', 'cor' => 'orange-400', 'activo' => 'superadmin.saft', 'topo' => true],
                ['rota' => 'superadmin.system-optimization', 'rotulo' => 'Optimização & OPcache', 'icone' => 'fa-tachometer-alt', 'cor' => 'cyan-400', 'activo' => 'superadmin.system-optimization', 'topo' => true],
            ]],
        ];
    }

    /* ─── Decidir ──────────────────────────────────────────────────────── */

    /** Um grupo já decidido, ou null se este utilizador não o vê. */
    private static function grupo(array $g, User $u, Request $r): ?array
    {
        if (isset($g['modulo']) && ! ($u->isSuperAdmin() || $u->canAccessModuleMenu($g['modulo']))) {
            return null;
        }

        if (isset($g['permissao']) && ! $u->can($g['permissao'])) {
            return null;
        }

        if (isset($g['quando']) && ! ($g['quando'])($u)) {
            return null;
        }

        $entradas = self::resolver($g['entradas'] ?? [], $u, $r, $g['barra'] ?? 'yellow-400');

        return [
            'chave' => $g['chave'],
            'rotulo' => __($g['rotulo']),
            'prefixo' => $g['prefixo'] ?? null,
            'leve' => (bool) ($g['leve'] ?? false),
            'icone' => $g['icone'],
            'cor' => $g['cor'],
            'simples' => (bool) ($g['simples'] ?? false),
            'url' => isset($g['rota']) ? route($g['rota']) : null,
            'activo' => isset($g['activo']) ? self::estaActivo($g['activo'], $r) : false,
            'aberto' => isset($g['aberto']) ? self::estaActivo($g['aberto'], $r) : false,
            'entradas' => $entradas,
        ];
    }

    /** As entradas já decididas: só as que este utilizador vê, com url e activo. */
    private static function resolver(array $entradas, User $u, Request $r, string $barra = 'yellow-400'): array
    {
        $saida = [];

        foreach ($entradas as $e) {
            if (isset($e['separador'])) {
                $saida[] = ['separador' => true];
                continue;
            }

            if (isset($e['titulo'])) {
                $saida[] = ['titulo' => __($e['titulo'])];
                continue;
            }

            if (isset($e['sub'])) {
                $sub = self::grupo($e['sub'], $u, $r);
                if ($sub) {
                    $saida[] = ['sub' => $sub];
                }
                continue;
            }

            if (isset($e['permissao']) && ! $u->can($e['permissao'])) {
                continue;
            }
            if (isset($e['qualquer']) && ! $u->canAny($e['qualquer'])) {
                continue;
            }
            if (isset($e['quando']) && ! ($e['quando'])($u)) {
                continue;
            }

            $saida[] = [
                'rotulo' => ! empty($e['sem_traducao']) ? $e['rotulo'] : __($e['rotulo']),
                'prefixo' => $e['prefixo'] ?? null,
                'forte' => (bool) ($e['forte'] ?? false),
                'icone' => $e['icone'],
                'marca' => (bool) ($e['marca'] ?? false),
                'cor' => $e['cor'],
                'barra' => $e['barra'] ?? $barra,
                'hover' => $e['hover'] ?? 'hover:bg-blue-700/50',
                'url' => route($e['rota'], $e['parametros'] ?? []),
                'activo' => self::estaActivo($e['activo'], $r),
                'topo' => (bool) ($e['topo'] ?? false),
                'relatorio' => (bool) ($e['relatorio'] ?? false),
            ];
        }

        // Um separador no fim, ou dois seguidos, é um traço sem nada a separar.
        return self::semSeparadoresSoltos($saida);
    }

    private static function estaActivo(string|array|Closure $regra, Request $r): bool
    {
        if ($regra instanceof Closure) {
            return (bool) $regra($r);
        }

        return $r->routeIs(...(array) $regra);
    }

    private static function semSeparadoresSoltos(array $entradas): array
    {
        $saida = [];

        foreach ($entradas as $e) {
            if (isset($e['separador']) && (empty($saida) || isset($saida[array_key_last($saida)]['separador']))) {
                continue;
            }
            $saida[] = $e;
        }

        while ($saida && isset($saida[array_key_last($saida)]['separador'])) {
            array_pop($saida);
        }

        return $saida;
    }

    /** A empresa trabalha por turnos? Falha em silêncio, como o menu de sempre. */
    private static function usaTurnos(): bool
    {
        try {
            return HRSetting::getValue('uses_shifts', '0') == '1';
        } catch (\Throwable) {
            return false;
        }
    }
}
