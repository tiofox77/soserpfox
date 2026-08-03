<?php

/**
 * Changelog do SOS ERP
 *
 * Como adicionar uma nova versão:
 *   1. Insira UMA NOVA ENTRADA no TOPO do array 'releases'.
 *   2. Defina 'version' (semver), 'date' (Y-m-d) e 'type' ('major'|'minor'|'patch').
 *   3. Liste as alterações por categoria: 'features', 'improvements', 'fixes', 'security'.
 *   4. Actualize 'current' para reflectir a versão mais recente.
 *
 * A página /changelog mostra esta informação em formato timeline.
 */

return [

    // Versão actualmente em produção (mostrada no badge)
    'current' => '2026.06.12.2',

    'releases' => [

        [
            'version'      => '2026.06.12.2',
            'date'         => '2026-06-12',
            'type'         => 'minor',
            'title'        => 'Stock — reconciliação automática e comando artisan',
            'features'     => [
                'StockObserver: sempre que uma linha em invoicing_stocks é criada/alterada/apagada, o agregado invoicing_products.stock_quantity é recalculado automaticamente (soma de todas as linhas do produto). Impede divergências futuras.',
                'Comando `php artisan stock:reconcile`: corrige divergências existentes em todos os tenants (ou um específico com --tenant=ID). Suporta --dry-run para apenas listar sem alterar.',
            ],
            'improvements' => [],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.12.1',
            'date'         => '2026-06-12',
            'type'         => 'patch',
            'title'        => 'POS — fix stock fantasma com multi-armazém',
            'features'     => [],
            'improvements' => [],
            'fixes'        => [
                'CRÍTICO: POS (online + PWA offline) mostrava stock incorreto para produtos cujo armazém ativo não tinha linha em invoicing_stocks mas o agregado legado invoicing_products.stock_quantity era > 0. A query caía indevidamente no fallback agregado e ignorava a realidade do armazém. Agora: se o produto já tem linhas em invoicing_stocks (qualquer armazém), o stock no armazém ativo é 0 quando não houver linha — só usa o agregado em tenants legados sem multi-armazém. Aplicado em POSSystem.php (render + stockInWarehouse) e SyncController (catálogo offline).',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.4',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'Criação rápida de cliente — fix ENUM type',
            'features'     => [],
            'improvements' => [],
            'fixes'        => [
                'POS online — "Novo Cliente" rebentava com SQLSTATE 1265 "Data truncated for column type" porque enviava type=individual; a coluna invoicing_clients.type é ENUM(pessoa_fisica, pessoa_juridica). Corrigido em POSSystem, GuestManagement e ReservationManagement.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.3',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'PWA — auto-update do service worker + botão Atualizar App',
            'features'     => [
                'Botão "Atualizar App" no painel de manutenção: apaga Cache Storage, força verificação do service worker (update + SKIP_WAITING) e recarrega com cache-bust — sem tocar nos dados do IndexedDB.',
                'Indicação da versão instalada no painel de manutenção.',
            ],
            'improvements' => [],
            'fixes'        => [
                'CRÍTICO: o layout do PWA nunca registava o service worker (faltava o partial pwa-register) — sem registo não havia deteção de novas versões nem prompt de atualização. O PWA agora verifica updates ao carregar, a cada 30 min e quando a janela volta a ficar visível.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.2',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'PWA — painel de manutenção e exibição de isenção',
            'features'     => [
                'Painel "Manutenção & Sincronização" na página inicial do PWA: Sync Parcial (incremental), Sync Completa (re-descarrega catálogo), Limpar Catálogo (preserva pendentes) e Reset Total (com dupla confirmação se houver pendentes).',
                'Secção "Ver detalhes" com raio-X do cache local: produtos isentos vs com IVA, clientes/vendas por sincronizar, fila de sync, estado do login offline e turno.',
            ],
            'improvements' => [
                'POS offline: itens e totais mostram "Isento" em vez de "IVA 0%" quando aplicável.',
            ],
            'fixes'        => [
                'KPI "Rascunhos locais" da página inicial lia tabela inexistente (draft_invoices → draft_documents).',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.1',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'POS Offline — herdar impostos da empresa/produtos',
            'features'     => [],
            'improvements' => [
                'O catálogo offline agora espelha exatamente a lógica de impostos do POS online: produto isento → 0%, senão taxa vinculada ao produto, senão tax default do tenant.',
                'Empresas em regime de isenção (tax default ISE): TODOS os produtos chegam ao PWA com IVA 0% e tax_type=isento.',
            ],
            'fixes'        => [
                'CRÍTICO: PWA offline cobrava IVA 14% mesmo quando a empresa/produtos eram isentos — o fallback "|| 14" convertia 0% (falsy) em 14% em três sítios (POS, rascunhos, payload de venda).',
                'API de sync deixou de usar 14% hardcoded como fallback — usa a tax default do tenant.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11',
            'date'         => '2026-06-11',
            'type'         => 'minor',
            'title'        => 'PWA — Login offline com sincronização',
            'features'     => [
                'Login offline completo: PWA permite autenticar localmente quando não há internet, usando hash PBKDF2 (SHA-256, 10 000 rounds) com salt aleatório por dispositivo.',
                'Setup opt-in: após sync online o PWA mostra banner "Ativar login offline" — o utilizador insere a sua password e fica habilitado por 90 dias.',
                'Overlay de gate: se a sessão Laravel expira ou o tab é aberto offline, mostra ecrã de bloqueio fullscreen pedindo email + password.',
                'Re-autenticação assíncrona: quando volta online, o sync valida a sessão Laravel; se 401/419 o overlay reaparece para re-login local.',
                'sessionStorage.pwa_unlocked: desbloqueio é por aba, garantindo logout efetivo ao fechar a janela.',
            ],
            'improvements' => [
                'Wipe ao trocar utilizador agora também limpa auth_cache e sessionStorage.',
                'Background Sync API adicionado ao sw.js correto (resources/pwa/sw.js) — sincroniza com app fechada.',
                'Helpers públicos: SosPwa.enableOfflineAuth, verifyOfflineAuth, isOfflineAuthEnabled, getOfflineAuthInfo, clearOfflineAuth, isPwaUnlocked.',
            ],
            'fixes'        => [
                'public/sw.js (stub legado) removido — o controller PwaController serve sempre o ficheiro real de resources/pwa/sw.js.',
            ],
            'security'     => [
                'Password nunca é armazenada em claro: apenas hash + salt único por instalação, válidos 90 dias.',
                'Sessão expira automaticamente ao fechar o tab (sessionStorage).',
            ],
        ],

        [
            'version'      => '2026.06.10.1',
            'date'         => '2026-06-10',
            'type'         => 'patch',
            'title'        => 'POS Offline — polimento e UX',
            'features'     => [
                'Campo de desconto comercial (%) na UI do POS offline — aplica-se ao subtotal e recalcula IVA proporcionalmente.',
                'Leitor de código de barras via câmara (BarcodeDetector API) — botão de câmara na barra de pesquisa para PDAs/telemóveis sem scanner físico.',
                'Quantidade editável por input direto — toque no número para digitar a quantidade em vez de +/− repetidos.',
                'Auto-purge de vendas sincronizadas com mais de 30 dias — limpa automaticamente o IndexedDB ao arranque.',
                'Indicador "última sync há X min" no header do PWA — atualiza a cada 30s para dar confiança ao operador.',
                'Feedback tátil (vibração) ao adicionar produto e ao incrementar quantidade (navigator.vibrate).',
            ],
            'improvements' => [
                'Produtos esgotados (stock 0) ficam com overlay "ESGOTADO" e botão desabilitado — impossível adicionar.',
                'Totais do carrinho mostram a linha de desconto quando > 0%.',
                'Botão câmara com overlay fullscreen, detecção de EAN-13/8, Code-128/39, UPC-A/E, QR.',
                'clearCart e checkout repõem desconto a 0.',
            ],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.10',
            'date'         => '2026-06-10',
            'type'         => 'minor',
            'title'        => 'POS Offline — melhorias críticas e médias',
            'features'     => [
                'Stock local decrementa ao vender offline — grid bloqueia produtos esgotados e mostra badge "ESGOTADO".',
                'Aviso no carrinho quando quantidade excede stock local disponível.',
                'Wipe automático do IndexedDB ao trocar de utilizador (preserva fila de sincronização).',
                'Secção "Com erro" no drawer de pendentes — mostra jobs falhados com mensagem de erro e botão Retry individual/global.',
                'Background Sync API (sw.js) — sincroniza mesmo com o PWA fechado (Chrome/Android).',
                'Verificação real de conectividade via /api/v1/invoicing/ping — elimina falsos positivos do navigator.onLine.',
                'Deteção de sessão expirada (401/419) — banner "Sessão expirada" com redirect para login.',
                'Relatório X/Z de fecho de turno offline — ticket 80mm impresso automaticamente ao fechar turno.',
                'Data da última sincronização visível no modal de fecho e no drawer de pendentes.',
                'Aviso "Valores locais podem não incluir outros dispositivos" quando há pendentes.',
            ],
            'improvements' => [
                'Auto-sync periódico (45s) e ao voltar à app usam ping real em vez de navigator.onLine.',
                'Service Worker completo com cache offline (cache-first para assets, network-first para páginas).',
                'enqueue() regista Background Sync no SW para sincronizar com app fechada.',
                'Sessão expirada para a queue imediatamente sem consumir retries.',
                'Botão de fecho de turno agora diz "Fechar e Imprimir".',
            ],
            'fixes'        => [
                'Stock no grid POS não atualizava após venda offline — agora decrementa localmente.',
                'Drawer de pendentes renomeado para "Documentos Offline" com informação mais completa.',
            ],
            'security'     => [
                'Dados sensíveis (vendas, clientes, turno) limpos automaticamente ao trocar de operador no mesmo dispositivo.',
            ],
        ],

        [
            'version'      => '2026.06.05.3',
            'date'         => '2026-06-05',
            'type'         => 'patch',
            'title'        => 'PWA abre directamente no POS Offline',
            'features'     => [
                'Página inicial do PWA passa a ser o POS Offline — abre mesmo sem internet.',
            ],
            'improvements' => [
                'Service Worker serve o POS Offline cached quando /dashboard, /pos ou /invoicing/offline/* são abertos sem rede.',
            ],
            'fixes'        => [
                'PWAs já instalados (start_url=/dashboard) passam a abrir no POS Offline em vez de outra página.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.05.2',
            'date'         => '2026-06-05',
            'type'         => 'patch',
            'title'        => 'Correção 404 ao abrir o PWA instalado',
            'features'     => [
                'Novo shortcut "PWA Offline" no manifest (acesso rápido ao modo offline a partir do atalho do telefone).',
            ],
            'improvements' => [
                'Manifest dinâmico: start_url corrigido (era /dashboard, rota não existente no topo).',
                'Shortcuts apontam para rotas válidas (/invoicing/pos em vez de /pos).',
            ],
            'fixes'        => [
                'PWAs já instalados que abriam com 404 — adicionados redirects /dashboard e /pos.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.05.1',
            'date'         => '2026-06-05',
            'type'         => 'patch',
            'title'        => 'PWA Offline robusto + warmup automático',
            'features'     => [
                'Warmup automático: ao entrar online no PWA, todas as páginas e scripts críticos são pré-cacheados — agora o PWA abre sempre offline, mesmo na primeira tentativa.',
                'Versão do sistema visível no header do PWA (sub-título).',
            ],
            'improvements' => [
                'Service Worker: fallback inteligente — se uma rota /invoicing/offline/* não tem cache, devolve qualquer outra rota PWA cached em vez de "Sem conexão".',
                'Cache HTML aumentado de 80 → 250 entradas para dar espaço a todas as páginas do PWA.',
                'Cache de imagens aumentado de 120 → 200.',
            ],
            'fixes'        => [
                'PWA não carregava produtos/interface ao abrir offline em algumas situações (faltava precache das páginas) — corrigido com warmup.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.05',
            'date'         => '2026-06-05',
            'type'         => 'minor',
            'title'        => 'PWA dinâmico, POS+ e criação rápida de clientes',
            'features'     => [
                'Página de Atualizações (/changelog) com histórico de versões.',
                'Criação rápida de cliente no POS — botão "Novo Cliente" no modal de selecção.',
                'Manifest PWA e ícones gerados dinamicamente a partir do logo do sistema.',
                'Service Worker com auto-update — notificação "Nova versão disponível" com botão para atualizar.',
                'Setting POS: Ocultar produtos esgotados (configurável em Faturação → Configurações → POS).',
            ],
            'improvements' => [
                'Auto-cleanup transparente dos ficheiros estáticos PWA legados (manifest.json/sw.js).',
                'POS: serviços (type=servico) deixam de ser ocultados por falta de stock.',
                'CACHE_VERSION do Service Worker atrelada a deploy + logo (hash automático).',
                'Sincronização do PWA Offline (SyncController) respeita a flag pos_hide_out_of_stock.',
            ],
            'fixes'        => [
                'Erro 500 em /invoicing/pos com MySQL ONLY_FULL_GROUP_BY (HAVING → WHERE com COALESCE).',
                'Manifest PWA passa a usar o logo configurado pelo tenant (antes era genérico).',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.05.25',
            'date'         => '2026-05-25',
            'type'         => 'minor',
            'title'        => 'Integração AGT e notificações',
            'features'     => [
                'Notificação por email para validações AGT (configurável em Settings).',
                'Campo agt_notification_emails em invoicing_settings.',
            ],
            'improvements' => [
                'Reforço da validação SAFT antes do submit AGT.',
            ],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2025.10.05',
            'date'         => '2025-10-05',
            'type'         => 'minor',
            'title'        => 'POS Pro: stock, formato numérico e séries',
            'features'     => [
                'Configurações POS dedicadas (auto-print, sons, validação de stock, imagens, produtos por página).',
                'Séries POS independentes (POS prefix configurável).',
                'Formato numérico Angola/Internacional + casas decimais configuráveis.',
            ],
            'improvements' => [
                'POS valida stock por armazém default do tenant (fonte única de verdade).',
            ],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2025.10.03',
            'date'         => '2025-10-03',
            'type'         => 'major',
            'title'        => 'Faturação multi-tenant',
            'features'     => [
                'Módulo de Faturação completo: PROFORMA, FT, RC com séries por tipo.',
                'Tabela invoicing_settings por tenant (defaults: armazém, cliente, fornecedor, imposto, moeda).',
                'Multi-armazém com stock por linha (invoicing_stocks).',
            ],
            'improvements' => [],
            'fixes'        => [],
            'security'     => [],
        ],

    ],

];
