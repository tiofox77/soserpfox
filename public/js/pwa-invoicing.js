/**
 * SOS ERP — PWA Faturação Engine
 * IndexedDB (Dexie) + Sync queue + Online/offline detection
 */
(function () {
    'use strict';

    if (typeof Dexie === 'undefined') {
        console.error('[PWA] Dexie not loaded');
        return;
    }

    // ========================
    // INDEXEDDB SCHEMA
    // ========================
    const db = new Dexie('SosErpInvoicing');
    db.version(1).stores({
        products: 'id, name, sku, barcode, type, category',
        clients: 'id, local_uuid, name, nif, _synced',
        series: 'id, document_type',
        tax_rates: '++id, rate',
        draft_invoices: 'local_uuid, client_id, client_local_uuid, created_at, _synced, _status',
        sync_queue: '++id, op, created_at, retries, status',
        meta: 'key',
    });
    // v2 — adiciona draft_documents (4 tipos)
    db.version(2).stores({
        draft_documents: 'local_uuid, doc_type, client_id, client_local_uuid, created_at, _synced, _server_id',
    });
    // v3 — adiciona pos_sales (vendas POS offline → Fatura-Recibo real ao sincronizar)
    db.version(3).stores({
        pos_sales: 'local_uuid, created_at, _synced, _server_id, _server_number',
    });
    // v4 — tabelas da AGT para o IEC e o Imposto de Selo
    db.version(4).stores({
        iec_pautais: 'pautal_code',
        is_verbas: 'verba_no',
    });
    // v5 — funcionários do tenant para login offline (PIN de turno).
    // Aditiva: não toca em products, sync_queue nem pos_sales — a fila de
    // vendas por sincronizar não é afectada por este upgrade.
    db.version(5).stores({
        employees: 'email, id',
    });

    // ========================
    // STATE
    // ========================
    const state = {
        online: navigator.onLine,
        realOnline: navigator.onLine, // verificado via /ping (sem falsos positivos)
        syncing: false,
        lastSync: null,
        pendingCount: 0,
        sessionExpired: false,
    };

    // ========================
    // VERIFICAÇÃO REAL DE CONECTIVIDADE
    // ========================
    async function checkRealOnline() {
        if (!navigator.onLine) { state.realOnline = false; return false; }
        try {
            const r = await fetch('/api/v1/invoicing/ping', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: AbortSignal.timeout(5000),
            });
            if (r.status === 401 || r.status === 419) {
                handleSessionExpired();
                state.realOnline = false;
                return false;
            }
            state.realOnline = r.ok;
            return r.ok;
        } catch (_) {
            state.realOnline = false;
            return false;
        }
    }

    // ========================
    // SESSÃO EXPIRADA
    // ========================
    function handleSessionExpired() {
        if (state.sessionExpired) return;
        state.sessionExpired = true;
        // Sessão Laravel expirou: invalidar unlock deste tab para forçar
        // re-autenticação (online via /login OU offline via verifyOfflineAuth).
        try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}
        // Banner de sessão expirada
        const bar = document.getElementById('pwa-status-bar');
        if (bar) {
            bar.classList.remove('hidden');
            bar.querySelectorAll('div').forEach(d => d.classList.add('hidden'));
            let sb = document.getElementById('pwa-status-session');
            if (!sb) {
                sb = document.createElement('div');
                sb.id = 'pwa-status-session';
                sb.className = 'bg-orange-600 text-white text-center py-2 text-xs font-bold shadow-lg cursor-pointer';
                sb.onclick = () => { window.location.href = '/login'; };
                bar.appendChild(sb);
            }
            sb.classList.remove('hidden');
            sb.innerHTML = '<i class="fas fa-lock mr-1"></i>'
                + __('Sessão expirada — toque para autenticar novamente');
        }
        window.dispatchEvent(new CustomEvent('pwa:session-expired'));
    }

    /**
     * A data de HOJE no relógio de quem está a vender.
     *
     * Aqui usava-se `new Date().toISOString().slice(0, 10)`, que devolve a data
     * em UTC. Angola está uma hora à frente, por isso entre a meia-noite e a
     * uma da manhã o UTC ainda está no dia anterior — e este valor vai para o
     * invoice_date, que é a data fiscal do documento. Uma venda feita à
     * 00:30 de dia 15 saía datada de dia 14.
     *
     * Os carimbos de instante (created_at_local e afins) continuam em ISO com
     * fuso, que é o correcto para um instante; o que não pode vir de lá é uma
     * DATA de calendário.
     */
    function dataDeHoje() {
        const agora = new Date();
        const doisDigitos = (n) => String(n).padStart(2, '0');

        return agora.getFullYear()
            + '-' + doisDigitos(agora.getMonth() + 1)
            + '-' + doisDigitos(agora.getDate());
    }

    /**
     * As formas equivalentes de um código de barras lido.
     *
     * Espelha App\Support\CodigoDeBarras no servidor — se um dia mudar lá,
     * muda aqui. O mesmo artigo pode estar guardado com o envelope GS1 à
     * frente (o "01" é um identificador de aplicação, não faz parte do
     * código) ou só com o EAN-13 de dentro, e o leitor tanto manda um como
     * o outro.
     */
    function formasDeCodigo(lido) {
        const t = String(lido || '').trim();
        const d = t.replace(/\D/g, '');
        const formas = [t];

        if (d && d !== t) formas.push(d);

        if (d.length >= 16 && d.startsWith('01')) {
            const gtin = d.substr(2, 14);
            formas.push(gtin);
            if (gtin[0] === '0') formas.push(gtin.substr(1));
        }

        if (d.length === 14 && d[0] === '0') formas.push(d.substr(1));

        if (d.length === 13) {
            formas.push('0' + d);
            formas.push('010' + d);
        }

        return formas.filter((f, i) => f !== '' && formas.indexOf(f) === i);
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // ========================
    // UI HELPERS
    // ========================
    function updateStatusBar() {
        const bar = document.getElementById('pwa-status-bar');
        const off = document.getElementById('pwa-status-offline');
        const syn = document.getElementById('pwa-status-syncing');
        const ok = document.getElementById('pwa-status-synced');
        const pending = document.getElementById('pwa-pending-count');

        if (!bar) return;

        bar.classList.remove('hidden');
        off.classList.add('hidden');
        syn.classList.add('hidden');
        ok.classList.add('hidden');

        hideWaitingBar();

        if (state.syncing) {
            // ÚNICO caso com spinner: está realmente a sincronizar agora
            syn.classList.remove('hidden');
        } else if (!state.online) {
            off.classList.remove('hidden');
            if (pending) {
                pending.textContent = state.pendingCount > 0
                    ? __(':n por sincronizar', { n: state.pendingCount })
                    : '';
            }
        } else if (state.pendingCount > 0) {
            // online, parado, mas com pendentes → barra estática clicável (sem spinner infinito)
            showWaitingBar(bar);
        } else {
            // tudo OK — esconder após 2s
            ok.classList.remove('hidden');
            setTimeout(() => { if (!state.syncing && state.pendingCount === 0) bar.classList.add('hidden'); }, 2000);
        }
    }

    function hideWaitingBar() {
        document.getElementById('pwa-status-waiting')?.classList.add('hidden');
    }

    function showWaitingBar(bar) {
        let w = document.getElementById('pwa-status-waiting');
        if (!w) {
            w = document.createElement('div');
            w.id = 'pwa-status-waiting';
            w.className = 'bg-amber-500 hover:bg-amber-600 text-white text-center py-1.5 text-xs font-semibold shadow-lg cursor-pointer';
            w.onclick = () => sync(true);
            bar.appendChild(w);
        }
        // "documento(s)" não existe em inglês nem em francês: plural a sério.
        w.innerHTML = '<i class="fas fa-rotate mr-1"></i>' + __n(
            ':n documento por sincronizar — toque para sincronizar|:n documentos por sincronizar — toque para sincronizar',
            state.pendingCount,
            { n: state.pendingCount }
        );
        w.classList.remove('hidden');
    }

    /**
     * Quantas coisas faltam MESMO enviar.
     *
     * Isto somava as vendas por sincronizar, os clientes, os rascunhos E o
     * comprimento da fila. Mas cada um desses registos TEM um trabalho na
     * fila — são a mesma coisa contada de dois lados. Uma venda offline dava
     * 2, e o ecrã do POS, que conta só as vendas, dava 1. O operador via dois
     * números diferentes para a mesma coisa e não sabia em qual acreditar.
     *
     * A fila é a fonte: tudo o que tem de subir é enfileirado, e um trabalho
     * na fila é uma coisa por enviar. Os marcadores `_synced` continuam a
     * servir para mostrar o estado de CADA registo — não para os contar.
     */
    async function refreshPendingCount() {
        state.pendingCount = await db.sync_queue.where('status').equals('pending').count();
        updateStatusBar();

        return state.pendingCount;
    }


    // ========================
    // ONLINE/OFFLINE EVENTS
    // ========================
    window.addEventListener('online', async () => {
        state.online = true;
        const real = await checkRealOnline();
        updateStatusBar();
        if (real) await sync(false);
    });

    window.addEventListener('offline', () => {
        state.online = false;
        // `realOnline` também. Ficava com o valor do último ping bem-sucedido,
        // ou seja, a dizer que havia rede depois de o sistema operativo avisar
        // que não há — e quem lê `realOnline` para decidir se tenta enviar
        // acreditava nele. O navegador a dizer "offline" é definitivo: não há
        // caso em que o aparelho reporte offline e exista ligação.
        state.realOnline = false;
        updateStatusBar();
    });

    // ========================
    // SYNC ENGINE
    // ========================
    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
                ...(options.headers || {}),
            },
            ...options,
        });
        // Detetar sessão expirada (401 = não autenticado, 419 = CSRF expirado)
        if (response.status === 401 || response.status === 419) {
            handleSessionExpired();
            throw new Error(`SESSION_EXPIRED:${response.status}`);
        }
        if (!response.ok) {
            const text = await response.text();

            // Um 4xx (tirando 408 e 429) é um pedido que o servidor recusou por
            // aquilo que ele É: falta um campo, o valor não serve, não há
            // permissão. Repetir cinco vezes não o compõe — só gasta as
            // tentativas e atrasa a fila que ainda tem coisas boas atrás.
            //
            // O 408 e o 429 ficam de fora porque dizem "agora não", e não
            // "nunca": um é tempo esgotado, o outro é excesso de pedidos.
            const definitivo = response.status >= 400
                && response.status < 500
                && response.status !== 408
                && response.status !== 429;

            const erro = new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            erro.definitivo = definitivo;
            erro.status = response.status;

            throw erro;
        }
        return response.json();
    }

    /**
     * Sync principal — descarrega catálogo + envia fila pendente
     * @param {boolean} force Se true, faz sync completo (não incremental)
     */
    async function sync(force = false) {
        if (state.syncing) return;
        if (!navigator.onLine) {
            return;
        }

        state.syncing = true;
        updateStatusBar();

        // Verificar conectividade real antes de iniciar (evita falso positivo WiFi)
        const actuallyOnline = await checkRealOnline();
        if (!actuallyOnline) {
            state.syncing = false;
            updateStatusBar();
            return;
        }

        try {
            // 0) Numa sincronização FORÇADA (manual), repõe jobs que tinham falhado
            //    definitivamente (5+ tentativas) para nova tentativa. Evita que uma
            //    venda fique presa para sempre após um erro entretanto corrigido.
            if (force) {
                // O last_error NAO se apaga.
                //
                // Apagava-se aqui, e cada toque em "Enviar agora" levava com ele
                // a unica pista do que tinha corrido mal — o trabalho voltava a
                // "a espera", limpo, e falhava outra vez em silencio. Quem esta
                // ao balcao via tres vendas paradas sem uma palavra sobre porque.
                await db.sync_queue.where('status').equals('failed').modify({
                    status: 'pending',
                    retries: 0,
                });
            }

            // 1) Enviar fila de operações pendentes (clientes novos, rascunhos)
            await processQueue();

            // 2) Descarregar catálogo
            const lastSync = await db.meta.get('last_sync');
            const since = (force || !lastSync) ? null : lastSync.value;
            const url = '/api/v1/invoicing/sync' + (since ? `?since=${encodeURIComponent(since)}` : '');

            const json = await fetchJson(url);

            // 3) Guardar dados
            if (json.data.products?.length) {
                await db.products.bulkPut(json.data.products);
            }
            if (json.data.clients?.length) {
                // não sobrescrever clientes locais ainda não sincronizados
                for (const c of json.data.clients) {
                    c._synced = 1;
                    await db.clients.put(c);
                }
            }
            if (json.data.series?.length) {
                await db.series.bulkPut(json.data.series);
            }
            // As tabelas da AGT: substituem-se por inteiro, nao se juntam.
            // Sao listas fechadas — um pautal que saia da tabela tem de sair
            // tambem do aparelho, senao continua a ser oferecido.
            if (json.data.iec_pautais) {
                await db.iec_pautais.clear();
                await db.iec_pautais.bulkPut(json.data.iec_pautais);
            }

            if (json.data.is_verbas) {
                await db.is_verbas.clear();
                await db.is_verbas.bulkPut(json.data.is_verbas);
            }

            if (json.data.tax_rates?.length) {
                await db.tax_rates.clear();
                await db.tax_rates.bulkPut(json.data.tax_rates);
            }

            // 3b) Apagar o que saiu do catálogo no servidor.
            //
            // O bulkPut acima junta e actualiza, mas nunca apaga: um produto
            // desactivado ou eliminado deixava de vir na resposta e ficava
            // aqui para sempre — visível e vendável, offline, sem nada que o
            // denunciasse. O servidor passa a dizer quais saíram.
            //
            // Os clientes ainda não sincronizados não se tocam: têm id local e
            // nunca aparecem nesta lista, que só traz ids do servidor.
            if (json.data.removed_products?.length) {
                await db.products.bulkDelete(json.data.removed_products);
            }

            if (json.data.removed_clients?.length) {
                await db.clients.bulkDelete(json.data.removed_clients);
            }

            await db.meta.put({ key: 'last_sync', value: json.server_time });

            // Sync online bem-sucedido → sessão Laravel válida → desbloqueia este tab.
            try { sessionStorage.setItem('pwa_unlocked', '1'); } catch (_) {}

            // ---- WIPE ao trocar de EMPRESA ----
            // Antes limpava-se ao trocar de UTILIZADOR — mas agora o aparelho
            // guarda os dados de toda a empresa (produtos, funcionários) para
            // qualquer funcionário entrar offline. Trocar de operador da MESMA
            // empresa não pode apagar o catálogo nem a lista de funcionários.
            // Só quando o TENANT muda (o aparelho passou a ser de outra
            // empresa) é que se limpa tudo o que é dessa empresa. A fila de
            // sincronização é sempre preservada.
            const prevTenantMeta = await db.meta.get('tenant_id');
            const prevTenant = prevTenantMeta?.value;
            const newTenant = json.tenant_id;
            if (prevTenant && newTenant && prevTenant !== newTenant) {
                console.warn('[PWA] Empresa mudou (' + prevTenant + '→' + newTenant + ') — a limpar dados locais');
                await db.products.clear();
                await db.clients.clear();
                await db.series.clear();
                await db.tax_rates.clear();
                await db.draft_documents.clear();
                await db.pos_sales.clear();
                await db.employees.clear();
                // sync_queue é preservada intencionalmente
                await db.meta.delete('shift');
                await db.meta.delete('last_sync');
                await db.meta.delete('catalog_version');
                await db.meta.delete('auth_cache'); // login offline legado da empresa anterior
                await db.meta.delete('offline_valid_until');
                await db.meta.delete('pin_attempts');
                try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}

                // O que ficou por subir da empresa anterior fica RETIDO, não
                // apagado e não enviado. Avisa-se, porque uma venda parada que
                // ninguém vê é uma venda perdida na mesma — só mais devagar.
                let retidos = 0;
                try {
                    retidos = await db.sync_queue
                        .where('status').anyOf('pending', 'outra_empresa')
                        .filter((j) => j.tenant_id && j.tenant_id !== newTenant)
                        .count();
                } catch (_) {}

                window.dispatchEvent(new CustomEvent('pwa:tenant-changed', {
                    detail: { prev: prevTenant, next: newTenant, retidos },
                }));

                if (retidos > 0) {
                    console.warn('[PWA] ' + retidos + ' operação(ões) da empresa anterior ficaram retidas.');
                    // Barra de aviso, como a dos erros de sincronização: este
                    // ficheiro não tem toast() e um alert() bloqueava o ecrã a
                    // meio de uma sincronização.
                    mostrarAvisoRetidos(retidos);
                }
            }

            await db.meta.put({ key: 'user', value: json.user });
            await db.meta.put({ key: 'tenant_id', value: json.tenant_id });

            // ---- Funcionários para login offline ----
            // Lista completa e autoritária: substitui-se a local por esta, para
            // quem foi desactivado desaparecer. Guardam-se só os verificadores
            // (bcrypt do PIN), nunca o PIN. É isto que deixa QUALQUER
            // funcionário activo abrir turno offline neste aparelho.
            if (Array.isArray(json.employees)) {
                await db.employees.clear();
                if (json.employees.length) {
                    await db.employees.bulkPut(json.employees.map(e => ({
                        email: (e.email || '').toLowerCase().trim(),
                        id: e.id,
                        name: e.name,
                        pin_hash: e.pin_hash,
                        updated_at: e.updated_at || null,
                    })));
                }
            }
            // Até quando o login offline vale sem nova sincronização.
            if (json.offline_valid_until) {
                await db.meta.put({ key: 'offline_valid_until', value: json.offline_valid_until });
            }

            if (json.company) {
                await db.meta.put({ key: 'company', value: json.company });
            }
            if (json.shift) {
                // Não sobrescrever o estado local do turno se ainda há operações
                // de turno por sincronizar (abertura/fecho feitos offline).
                const pendingShiftOps = await db.sync_queue
                    .where('status').anyOf('pending', 'failed')
                    .filter(j => j.op === 'open_pos_shift' || j.op === 'close_pos_shift')
                    .count();
                if (!pendingShiftOps) {
                    await db.meta.put({ key: 'shift', value: json.shift });
                }
            }
            if (json.warehouse) {
                await db.meta.put({ key: 'warehouse', value: json.warehouse });
            }

            state.lastSync = json.server_time;

            // dispatch evento para páginas atualizarem
            window.dispatchEvent(new CustomEvent('pwa:synced', { detail: json }));
        } catch (err) {
            console.error('[PWA] Sync falhou:', err);
            // Mostra alerta visível ao utilizador
            const bar = document.getElementById('pwa-status-bar');
            if (bar) {
                bar.classList.remove('hidden');
                bar.querySelectorAll('div').forEach(d => d.classList.add('hidden'));
                let errBar = document.getElementById('pwa-status-error');
                if (!errBar) {
                    errBar = document.createElement('div');
                    errBar.id = 'pwa-status-error';
                    errBar.className = 'bg-red-600 text-white text-center py-1.5 text-xs font-semibold shadow-lg cursor-pointer';
                    errBar.onclick = () => bar.classList.add('hidden');
                    bar.appendChild(errBar);
                }
                errBar.classList.remove('hidden');
                // A mensagem técnica (err.message) é dado, não se traduz; a frase
                // à volta dela sim — e leva os dois pontos dentro da cadeia por
                // causa do francês, que escreve "Erreur :" com espaço antes.
                errBar.innerHTML = '<i class="fas fa-circle-exclamation mr-1"></i>'
                    + __('Erro de sincronização: :erro (toca para fechar)', {
                        erro: err.message || __('desconhecido'),
                    });
                setTimeout(() => errBar.classList.add('hidden'), 8000);
            }
            window.dispatchEvent(new CustomEvent('pwa:sync-error', { detail: err }));
        } finally {
            state.syncing = false;
            await refreshPendingCount();
        }
    }

    /**
     * Processa fila de operações offline pendentes.
     * Operações suportadas: create_client, create_draft (Fase 4)
     */
    /** Os trabalhos ja entregues ha mais de uma semana. */
    async function limparEntreguesAntigos(dias = 7) {
        const limite = new Date(Date.now() - dias * 24 * 60 * 60 * 1000).toISOString();

        try {
            await db.sync_queue
                .where('status').equals('done')
                .filter((j) => (j.synced_at || j.created_at || '') < limite)
                .delete();
        } catch (_) {
            // Falhar a limpar nunca pode impedir uma venda de subir.
        }
    }

    /**
     * Avisa que ficaram operações da empresa anterior por enviar.
     *
     * Fica na barra e NÃO desaparece sozinha: uma venda retida que ninguém vê
     * é uma venda perdida na mesma, só mais devagar. Sai quando se toca.
     */
    function mostrarAvisoRetidos(quantos) {
        try {
            const bar = document.getElementById('pwa-status-bar');
            if (!bar) return;

            // A barra esconde-se quando não há nada a dizer; um aviso destes
            // tem de a trazer de volta.
            bar.classList.remove('hidden');

            let aviso = document.getElementById('pwa-retidos');
            if (!aviso) {
                aviso = document.createElement('div');
                aviso.id = 'pwa-retidos';
                aviso.className = 'bg-amber-500 text-white text-xs px-3 py-2 cursor-pointer';
                aviso.onclick = () => aviso.remove();
                bar.appendChild(aviso);
            }

            aviso.innerHTML = '<i class="fas fa-triangle-exclamation mr-1"></i>'
                + __(':n operação(ões) da empresa anterior ficaram por enviar. Volte a entrar nessa empresa para as sincronizar.', { n: quantos });
        } catch (_) {}
    }

    async function processQueue() {
        const pending = await db.sync_queue.where('status').equals('pending').sortBy('created_at');
        if (!pending.length) return;

        // A empresa a que a sessão actual pertence. Um trabalho criado noutra
        // empresa NÃO pode ser enviado com esta sessão — ver a guarda no ciclo.
        const empresaActual = (await db.meta.get('tenant_id'))?.value ?? null;

        for (const job of pending) {
            // ── A venda de uma empresa nunca entra nos livros de outra ──────
            //
            // O aparelho pode mudar de empresa (o wipe mais abaixo existe para
            // isso) e a fila é preservada de propósito, para não se perder o
            // que ainda não subiu. Mas sem esta guarda, uma venda feita offline
            // para a empresa A era enviada com a sessão da empresa B e ficava
            // gravada nos livros de B — com número fiscal de B e comunicada à
            // AGT em nome de B. O servidor valida o OPERADOR, mas quando ele
            // não pertence à empresa cai para o utilizador da sessão em vez de
            // recusar: não é defesa contra isto.
            //
            // Fica parada, visível, à espera de alguém decidir. Apagar seria
            // perder uma venda; enviar seria pior.
            if (job.tenant_id && empresaActual && job.tenant_id !== empresaActual) {
                console.warn('[PWA] Trabalho de outra empresa, retido:', job.op, job.tenant_id, '≠', empresaActual);
                await db.sync_queue.update(job.id, { status: 'outra_empresa' });
                continue;
            }

            try {
                let result;
                if (job.op === 'create_client') {
                    result = await fetchJson('/api/v1/invoicing/clients', {
                        method: 'POST',
                        body: JSON.stringify(job.payload),
                    });
                    if (result.id && job.payload.local_uuid) {
                        await db.clients.where('local_uuid').equals(job.payload.local_uuid).modify({
                            id: result.id,
                            _synced: 1,
                        });
                    }
                } else if (job.op === 'create_draft') {
                    // Antes de enviar, se o cliente ainda for local_uuid, resolver para ID real
                    let payload = { ...job.payload };
                    if (payload.client_local_uuid && !payload.client_id) {
                        const localClient = await db.clients.where('local_uuid').equals(payload.client_local_uuid).first();
                        if (localClient && Number.isInteger(localClient.id)) {
                            payload.client_id = localClient.id;
                        } else {
                            // cliente ainda não sincronizado — adiar este job
                            throw new Error(__('Cliente ainda não sincronizado — a reagendar'));
                        }
                    }
                    result = await fetchJson('/api/v1/invoicing/drafts', {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                    if (result.id && job.payload.local_uuid) {
                        await db.draft_documents.where('local_uuid').equals(job.payload.local_uuid).modify({
                            _server_id: result.id,
                            _synced: 1,
                            _server_number: result.invoice_number || result.proforma_number || null,
                        });
                    }
                } else if (job.op === 'open_pos_shift') {
                    // Abertura de turno feita offline → cria turno real (idempotente)
                    result = await fetchJson('/api/v1/invoicing/pos/shift/open', {
                        method: 'POST',
                        body: JSON.stringify(job.payload),
                    });
                    if (result.success && result.shift) {
                        await db.meta.put({ key: 'shift', value: {
                            open: true,
                            number: result.shift.number,
                            opened_at: result.shift.opened_at,
                            opening_balance: result.shift.opening_balance,
                            cash_sales: result.shift.cash_sales,
                            total_sales: result.shift.total_sales,
                        }});
                        window.dispatchEvent(new CustomEvent('pwa:shift-synced', {
                            detail: { action: 'open', shift: result.shift },
                        }));
                    }
                } else if (job.op === 'close_pos_shift') {
                    // FECHO de turno: só pode executar depois de TODAS as vendas
                    // offline estarem sincronizadas (senão não entram no fecho).
                    const unsyncedSales = await db.pos_sales.where('_synced').equals(0).count();
                    if (unsyncedSales > 0) {
                        throw new Error(__('Vendas por sincronizar — fecho de turno adiado'));
                    }
                    result = await fetchJson('/api/v1/invoicing/pos/shift/close', {
                        method: 'POST',
                        body: JSON.stringify(job.payload),
                    });
                    if (result.success) {
                        await db.meta.put({ key: 'shift', value: { open: false, number: null, opened_at: null } });
                        window.dispatchEvent(new CustomEvent('pwa:shift-synced', {
                            detail: { action: 'close', shift: result.shift || null },
                        }));
                    }
                } else if (job.op === 'logout') {
                    // A saída pedida sem rede. O servidor fecha a sessão
                    // agora; se ela já tiver caído, responde na mesma que
                    // está feito — senão o trabalho ficava a repetir-se.
                    result = await fetchJson('/invoicing/offline/sair', {
                        method: 'POST',
                        body: JSON.stringify({ da_fila: true }),
                    });
                } else if (job.op === 'create_pos_sale') {
                    // Venda POS offline → cria Fatura-Recibo real (idempotente via local_uuid)
                    let payload = { ...job.payload };
                    // Resolver cliente local → ID real, se aplicável
                    if (payload.client_local_uuid && !payload.client_id) {
                        const localClient = await db.clients.where('local_uuid').equals(payload.client_local_uuid).first();
                        if (localClient && Number.isInteger(localClient.id)) {
                            payload.client_id = localClient.id;
                        } else {
                            throw new Error(__('Cliente ainda não sincronizado — a reagendar'));
                        }
                    }
                    delete payload.client_local_uuid;
                    result = await fetchJson('/api/v1/invoicing/pos/sale', {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                    if (result.success && job.payload.local_uuid) {
                        // Substituir também os TOTAIS pelos do servidor: o talão
                        // reimpresso misturava número/QR/ATCUD reais com totais
                        // calculados no dispositivo (podiam estar a 14% vindos de
                        // um catálogo desactualizado). O servidor é a autoridade.
                        const _upd = {
                            _server_id: result.id,
                            _synced: 1,
                            _server_number: result.invoice_number || null,
                            _server_atcud: result.atcud || null,
                            _server_qr: result.qr_image || null,
                            _server_hash: result.hash_short || null,
                        };
                        if (typeof result.total === 'number') { _upd.total = result.total; }
                        if (typeof result.subtotal === 'number') { _upd.subtotal = result.subtotal; }
                        if (typeof result.tax_amount === 'number') { _upd.tax = result.tax_amount; }
                        if (typeof result.discount_amount === 'number') { _upd.discount_amount = result.discount_amount; }
                        if (Array.isArray(result.items) && result.items.length) { _upd.items = result.items; }

                        await db.pos_sales.where('local_uuid').equals(job.payload.local_uuid).modify(_upd);
                        // Notifica a UI para atualizar número provisório → definitivo
                        window.dispatchEvent(new CustomEvent('pwa:pos-sale-synced', {
                            detail: {
                                local_uuid: job.payload.local_uuid,
                                invoice_number: result.invoice_number,
                                atcud: result.atcud,
                            },
                        }));
                    }
                }

                await db.sync_queue.update(job.id, { status: 'done', synced_at: new Date().toISOString() });

                // Os entregues de ha mais de uma semana saem. Guardam-se uns
                // dias para se poder conferir o que subiu e quando; para alem
                // disso e so uma tabela a crescer num telemovel.
                await limparEntreguesAntigos();
            } catch (err) {
                console.error('[PWA] Job falhou:', job, err);
                await db.sync_queue.update(job.id, {
                    retries: (job.retries || 0) + 1,
                    last_error: err.message,
                });
                // SESSION_EXPIRED: não contar como retry — parar tudo imediatamente
                if (err.message && err.message.startsWith('SESSION_EXPIRED')) {
                    console.warn('[PWA] Sessão expirada — a parar queue');
                    break;
                }

                // Recusa definitiva do servidor (4xx): não se repete.
                //
                // Isto contava como falha normal e voltava a tentar mais quatro
                // vezes. Um cliente sem NIF, por exemplo, ia ser recusado as
                // cinco — e cada tentativa punha-se à frente das vendas boas
                // que estavam atrás na fila.
                if (err.definitivo) {
                    console.warn('[PWA] Recusado pelo servidor, não se repete:', err.status, job.op);
                    await db.sync_queue.update(job.id, { status: 'failed' });
                    continue;
                }

                // se já tentou 5x, marcar como erro permanente
                if ((job.retries || 0) >= 5) {
                    await db.sync_queue.update(job.id, { status: 'failed' });
                }
            }
        }
    }

    /**
     * Adiciona uma operação à fila de sync
     */
    /**
     * Põe um trabalho na fila.
     *
     * `autoSync` a false serve a quem quer ESPERAR pelo resultado: o disparo
     * automático marca a sincronização como a decorrer, e uma segunda chamada
     * a sync() sai logo sem esperar por nada. Quem espera, dispara.
     */
    async function enqueue(op, payload, autoSync = true) {
        // Carimbar QUEM fez a operação offline nas ops fiscais. O login por PIN
        // é do lado do cliente; a sessão Laravel do aparelho continua a ser a
        // do último a sincronizar. Sem isto, a venda de B (entrou por PIN) era
        // comunicada à AGT em nome de A. O servidor valida este operador antes
        // de o aceitar (ver ResolveOperadorOffline).
        const OPS_COM_OPERADOR = ['create_pos_sale', 'open_pos_shift', 'close_pos_shift'];
        if (OPS_COM_OPERADOR.includes(op) && payload && payload.operator_id === undefined) {
            try {
                const u = (await db.meta.get('user'))?.value;
                if (u) {
                    payload = { ...payload, operator_id: u.id || null, operator_email: u.email || null };
                }
            } catch (_) {}
        }
        // A empresa fica CARIMBADA no trabalho, no momento em que ele nasce.
        // É o que permite ao processQueue recusar-se a enviar para outra
        // empresa depois de o aparelho mudar de mãos.
        let empresaDoTrabalho = null;
        try {
            empresaDoTrabalho = (await db.meta.get('tenant_id'))?.value ?? null;
        } catch (_) {}

        await db.sync_queue.add({
            op,
            payload,
            tenant_id: empresaDoTrabalho,
            created_at: new Date().toISOString(),
            retries: 0,
            status: 'pending',
        });
        await refreshPendingCount();
        // Pede ao SW para registar Background Sync (sincroniza mesmo com app fechada)
        try {
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'REGISTER_SYNC' });
            }
        } catch (_) {}
        // tenta sincronizar imediatamente se online
        if (autoSync && navigator.onLine) {
            checkRealOnline().then(ok => { if (ok) sync(false); });
        }
    }

    /**
     * Com rede, emite já: espera pela sincronização desta venda.
     *
     * O documento provisório existe para o caso de NÃO haver rede. Havendo,
     * não há razão para o cliente sair da loja com um talão "PEND-" e o
     * número fiscal aparecer meia hora depois — nem para quem está ao balcão
     * ter de saber o que é um documento por sincronizar.
     *
     * Grava-se sempre primeiro em local. Se a rede falhar a meio, a venda
     * não se perde: fica na fila, como sempre esteve.
     *
     * @return {Promise<object|null>} a venda já com número real, ou null
     */
    async function emitirJa(local_uuid, msLimite = 8000) {
        try {
            if (!navigator.onLine || !(await checkRealOnline())) return null;

            // Um limite de espera para o balcão não ficar preso a uma rede
            // que existe mas está lenta. Passando disto, segue como pendente
            // e a fila trata do resto.
            //
            // O temporizador limpa-se quando a sincronização ganha: senão
            // ficava pendurado até ao fim, e num POS que fecha dezenas de
            // vendas por hora isso é lixo a acumular.
            let travao;
            const limite = new Promise(resolve => { travao = setTimeout(resolve, msLimite); });

            try {
                await Promise.race([sync(false), limite]);
            } finally {
                clearTimeout(travao);
            }

            const actual = await db.pos_sales.get(local_uuid);

            return (actual && actual._synced) ? actual : null;
        } catch (_) {
            return null;
        }
    }

    // ========================
    // PUBLIC API
    // ========================
    window.SosPwa = {
        db,
        state,
        sync,
        enqueue,
        refreshPendingCount,

        // Helpers de consulta
        async getProducts(filter = {}) {
            let coll = db.products.toCollection();
            if (filter.search) {
                const s = filter.search.toLowerCase();

                // As formas equivalentes do que foi lido — o mesmo que o
                // servidor faz em App\Support\CodigoDeBarras. Sem isto, ler
                // a caixa offline falhava sempre que o catálogo guardasse o
                // código na outra forma, e é offline que não há alternativa.
                const formas = formasDeCodigo(filter.search);

                return (await coll.toArray()).filter(p =>
                    (p.barcode && formas.includes(p.barcode.trim())) ||
                    (p.name || '').toLowerCase().includes(s) ||
                    (p.sku || '').toLowerCase().includes(s) ||
                    (p.barcode || '').toLowerCase().includes(s) ||
                    // Numa farmácia pergunta-se pela substância, não pela marca:
                    // quem pede "paracetamol" não sabe se a caixa diz Ben-u-ron.
                    // Numa loja de roupa pergunta-se pelo tamanho, que não está
                    // no nome do artigo nem no código.
                    (p.active_ingredient || '').toLowerCase().includes(s) ||
                    (p.size || '').toLowerCase().includes(s)
                );
            }
            return coll.toArray();
        },

        async getClients(filter = {}) {
            let coll = db.clients.toCollection();
            if (filter.search) {
                const s = filter.search.toLowerCase();
                return (await coll.toArray()).filter(c =>
                    (c.name || '').toLowerCase().includes(s) ||
                    (c.nif || '').toLowerCase().includes(s)
                );
            }
            return coll.toArray();
        },

        async createClientOffline(data) {
            const local_uuid = 'c_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            const record = {
                id: 'local_' + local_uuid,
                local_uuid,
                _synced: 0,
                created_offline_at: new Date().toISOString(),
                ...data,
            };
            await db.clients.put(record);
            await enqueue('create_client', { local_uuid, ...data });
            return record;
        },

        /**
         * Cria rascunho de documento offline
         * @param {Object} draft - { doc_type, client_id?, client_local_uuid?, items[], notes, reference, invoice_date, due_date }
         */
        async createDraftOffline(draft) {
            // O QUE ENTRA NA BASE TEM DE SER DADOS SIMPLES.
            //
            // O ecrã manda um objecto reactivo do Alpine, que é um Proxy, e o
            // IndexedDB não sabe clonar Proxies: rebentava com
            // "could not be cloned" e o documento não chegava a ser gravado.
            // Passar por JSON deixa só os valores.
            draft = JSON.parse(JSON.stringify(draft ?? {}));

            const local_uuid = 'd_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

            // Calcular totais localmente para mostrar na lista
            let subtotal = 0, tax = 0;
            for (const itm of (draft.items || [])) {
                const qty = parseFloat(itm.quantity) || 0;
                const price = parseFloat(itm.unit_price) || 0;
                const taxRate = parseFloat(itm.tax_rate) || 0;
                const discPct = parseFloat(itm.discount_percent) || 0;
                const gross = qty * price;
                const disc = gross * discPct / 100;
                const net = gross - disc;
                subtotal += net;
                tax += net * taxRate / 100;
            }
            const total = subtotal + tax;

            const record = {
                local_uuid,
                doc_type: draft.doc_type,
                client_id: draft.client_id || null,
                client_local_uuid: draft.client_local_uuid || null,
                client_name: draft.client_name || '',
                items: draft.items || [],
                notes: draft.notes || '',
                reference: draft.reference || '',
                invoice_date: draft.invoice_date || dataDeHoje(),
                due_date: draft.due_date || null,
                subtotal: Math.round(subtotal * 100) / 100,
                tax: Math.round(tax * 100) / 100,
                total: Math.round(total * 100) / 100,
                _synced: 0,
                _server_id: null,
                _server_number: null,
                created_at: new Date().toISOString(),
            };
            await db.draft_documents.put(record);
            await enqueue('create_draft', {
                local_uuid,
                doc_type: draft.doc_type,
                client_id: draft.client_id || null,
                client_local_uuid: draft.client_local_uuid || null,
                notes: draft.notes || '',
                reference: draft.reference || '',
                invoice_date: record.invoice_date,
                due_date: record.due_date,
                // Os descontos do documento e a entrega. Sem isto ficavam no
                // ecra e nunca chegavam ao documento emitido — o operador via
                // um total com desconto e saia uma factura sem ele.
                discount_commercial: parseFloat(draft.discount_commercial) || 0,
                discount_financial: parseFloat(draft.discount_financial) || 0,
                delivery_date: draft.delivery_date || null,
                delivery_location: draft.delivery_location || null,
                is_service: !!draft.is_service,
                withholding_percentage: draft.is_service
                    ? (parseFloat(draft.withholding_percentage) || 6.5)
                    : null,
                items: draft.items,
            });
            return record;
        },

        async getDrafts() {
            return await db.draft_documents.orderBy('created_at').reverse().toArray();
        },

        /**
         * Cria venda POS offline. Calcula totais localmente, persiste em pos_sales
         * com número provisório, e enfileira para sincronização ordenada (FIFO).
         * @param {Object} sale - { client_id?, client_local_uuid?, client_name, client_nif,
         *                          payment_method, amount_received, discount_commercial,
         *                          notes, items[] }
         * @returns {Object} registo gravado (inclui provisional_number)
         */
        async createPosSaleOffline(sale) {
            const local_uuid = 'pos_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            const createdAt = new Date().toISOString();

            // ---- DECREMENTAR STOCK LOCAL (produtos físicos) ----
            // Garante que o grid reflete o stock "pessimista" offline.
            // O servidor é a fonte de verdade — o stock é reposto no próximo sync.
            for (const itm of (sale.items || [])) {
                const pid = Number.isInteger(itm.product_id) ? itm.product_id : null;
                if (!pid) continue;
                const prod = await db.products.get(pid);
                // Nao decrementar artigos que nao controlam stock.
                if (!prod || prod.type === 'servico' || !prod.manage_stock) continue;
                const current = parseFloat(prod.stock_quantity) || 0;
                const sold = parseFloat(itm.quantity) || 0;
                await db.products.update(pid, {
                    stock_quantity: Math.max(0, current - sold),
                });
            }

            // Totais locais (IVA por linha, desconto comercial %)
            let subtotal = 0, tax = 0;
            const discPct = parseFloat(sale.discount_commercial) || 0;
            for (const itm of (sale.items || [])) {
                const qty = parseFloat(itm.quantity) || 0;
                const price = parseFloat(itm.unit_price) || 0;
                const taxRate = parseFloat(itm.tax_rate) || 0;
                const lineNet = qty * price;
                subtotal += lineNet;
                tax += lineNet * taxRate / 100;
            }
            const discountAmount = subtotal * discPct / 100;
            const baseAfterDisc = subtotal - discountAmount;
            const taxAfterDisc = tax * (subtotal > 0 ? baseAfterDisc / subtotal : 1);
            const total = baseAfterDisc + taxAfterDisc;

            // Número provisório legível (substituído pelo nº AGT ao sincronizar)
            const provisional_number = 'PEND-' + dataDeHoje().replace(/-/g, '') + '-' + local_uuid.slice(-6).toUpperCase();

            const record = {
                local_uuid,
                provisional_number,
                client_id: sale.client_id || null,
                client_local_uuid: sale.client_local_uuid || null,
                client_name: sale.client_name || 'Consumidor Final',
                client_nif: sale.client_nif || '999999999',
                payment_method: sale.payment_method || 'cash',
                amount_received: parseFloat(sale.amount_received) || total,
                discount_commercial: discPct,
                notes: sale.notes || '',
                items: sale.items || [],
                subtotal: Math.round(subtotal * 100) / 100,
                discount_amount: Math.round(discountAmount * 100) / 100,
                tax: Math.round(taxAfterDisc * 100) / 100,
                total: Math.round(total * 100) / 100,
                created_at: createdAt,
                created_at_local: createdAt,
                _synced: 0,
                _server_id: null,
                _server_number: null,
                _server_atcud: null,
                _server_qr: null,
                _server_hash: null,
            };
            await db.pos_sales.put(record);

            await enqueue('create_pos_sale', {
                local_uuid,
                client_id: sale.client_id || null,
                client_local_uuid: sale.client_local_uuid || null,
                payment_method: record.payment_method,
                amount_received: record.amount_received,
                discount_commercial: discPct,
                notes: record.notes,
                created_at_local: createdAt,
                items: (sale.items || []).map(i => ({
                    product_id: Number.isInteger(i.product_id) ? i.product_id : null,
                    product_name: i.product_name,
                    quantity: parseFloat(i.quantity) || 0,
                    unit_price: parseFloat(i.unit_price) || 0,
                    // NÃO usar "|| 14": 0% (isento) é falsy e viraria 14%.
                    tax_rate: Number.isFinite(parseFloat(i.tax_rate)) ? parseFloat(i.tax_rate) : 0,
                    // A ESCOLHA, nunca o valor: quem apura e o servidor.
                    iec_pautal: i.iec_pautal || null,
                    is_verba: i.is_verba || null,
                    is_service: !!i.is_service,
                    unit: i.unit || 'UN',
                })),
            }, false);

            // Havendo rede, a factura sai já com o número fiscal. Sem rede —
            // ou se a rede demorar — segue o provisório e a fila trata dela.
            const emitida = await emitirJa(local_uuid);

            return emitida || record;
        },

        /**
         * Sair, com rede ou sem ela.
         *
         * Sem rede o servidor não pode saber já — mas a sessão dele continua
         * aberta, e deixá-la aberta é deixar a conta acessível a quem apanhe
         * o aparelho com rede outra vez. Por isso a saída entra na fila como
         * qualquer outra coisa e é comunicada quando houver ligação.
         *
         * NÃO SE APAGA A BASE LOCAL. Pode ter vendas por enviar, e limpá-la
         * ao sair fazia desaparecer facturas que o servidor ainda não viu.
         */
        async sair() {
            // Tranca este aparelho: o acesso offline guardado continua lá, e é
            // com ele que a próxima pessoa entra sem rede. Apagá-lo aqui
            // obrigaria a uma ida à internet só para voltar a trabalhar.
            try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}
            try { sessionStorage.removeItem('pwa_unlocked_at'); } catch (_) {}

            await enqueue('logout', { pedido_em: new Date().toISOString() }, false);

            // Com rede tenta-se já, para a sessão do servidor fechar agora.
            if (navigator.onLine) {
                try { await sync(false); } catch (_) {}
            }

            return true;
        },

        async getPosSales() {
            return await db.pos_sales.orderBy('created_at').reverse().toArray();
        },

        async getCompany() {
            const m = await db.meta.get('company');
            return m ? m.value : null;
        },

        async getWarehouse() {
            const m = await db.meta.get('warehouse');
            return m ? m.value : null;
        },

        async getShift() {
            const m = await db.meta.get('shift');
            return m ? m.value : { open: false, number: null, opened_at: null };
        },

        async getLastSyncDate() {
            const m = await db.meta.get('last_sync');
            return m ? m.value : null;
        },

        /**
         * A fila de envio, em linguagem de quem está ao balcão.
         *
         * Contar quantos faltam não chega: quando um fica preso, quem está na
         * caixa precisa de ver O QUÊ ficou preso e porquê. "3 por enviar" não
         * se distingue de "3 perdidos".
         */
        async getQueue() {
            const nomes = {
                create_pos_sale: 'Venda',
                create_client: 'Cliente',
                create_draft: 'Documento',
                open_pos_shift: 'Abertura de turno',
                close_pos_shift: 'Fecho de turno',
                logout: 'Saída de sessão',
            };

            // SÓ o que ainda falta: pendentes e falhados.
            //
            // Os entregues ficam na tabela marcados 'done' e nunca sao
            // removidos. Listá-los aqui punha vendas JÁ ENTREGUES a aparecer
            // como "à espera" — que foi o que se viu: tres vendas paradas no
            // ecrã, e o servidor com as tres recebidas.
            const itens = await db.sync_queue
                .where('status').anyOf('pending', 'failed')
                .sortBy('created_at');

            return itens.map(j => {
                const p = j.payload || {};
                let detalhe = '';

                if (j.op === 'create_pos_sale') {
                    const n = (p.items || []).length;
                    const total = (p.items || []).reduce(
                        (s, i) => s + (parseFloat(i.quantity) || 0) * (parseFloat(i.unit_price) || 0), 0
                    );
                    detalhe = `${n} artigo(s) · ${total.toFixed(2)} Kz`;
                } else if (j.op === 'create_client') {
                    detalhe = p.name || '';
                } else if (j.op === 'create_draft') {
                    detalhe = (p.doc_type || '').toUpperCase();
                }

                return {
                    id: j.id,
                    tipo: nomes[j.op] || j.op,
                    detalhe,
                    quando: j.created_at,
                    estado: j.status,
                    tentativas: j.retries || 0,
                    erro: j.last_error || null,
                };
            });
        },

        async getFailedJobs() {
            return await db.sync_queue.where('status').equals('failed').toArray();
        },

        async retryFailedJob(jobId) {
            // Guarda-se o erro anterior: se voltar a falhar do mesmo modo,
            // isso e informacao, e nao um ecra em branco.
            await db.sync_queue.update(jobId, { status: 'pending', retries: 0 });
            await refreshPendingCount();
            if (navigator.onLine) {
                checkRealOnline().then(ok => { if (ok) sync(false); });
            }
        },

        async retryAllFailed() {
            await db.sync_queue.where('status').equals('failed').modify({
                status: 'pending', retries: 0, last_error: null,
            });
            await refreshPendingCount();
            if (navigator.onLine) {
                checkRealOnline().then(ok => { if (ok) sync(false); });
            }
        },

        checkRealOnline,

        // ========================
        // OFFLINE AUTH (login local quando sem rede)
        // ========================
        // Modelo: ao logar online, o utilizador define a sua password no PWA e
        // guardamos em IndexedDB { email, name, hash, salt, expires_at }.
        // Quando offline, o overlay pede credenciais; verificamos hash localmente
        // e marcamos sessionStorage.pwa_unlocked=1 para libertar a app neste tab.
        // O hash é PBKDF2-like via SHA-256(salt + ':' + email + ':' + password)
        // repetido 10000x. Não substitui a sessão Laravel — apenas é gate local.

        async _hashCredentials(email, password, salt) {
            const enc = new TextEncoder();
            let data = enc.encode(salt + ':' + email.toLowerCase().trim() + ':' + password);
            // 10000 rounds para encarecer ataques de força bruta
            for (let i = 0; i < 10000; i++) {
                const buf = await crypto.subtle.digest('SHA-256', data);
                data = new Uint8Array(buf);
            }
            // Converter para hex
            return Array.from(data).map(b => b.toString(16).padStart(2, '0')).join('');
        },

        _generateSalt() {
            const arr = new Uint8Array(16);
            crypto.getRandomValues(arr);
            return Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
        },

        async enableOfflineAuth(password) {
            const userMeta = await db.meta.get('user');
            const user = userMeta?.value;
            if (!user || !user.email) throw new Error(__('Sem utilizador autenticado para configurar login offline.'));
            if (!password || password.length < 4) throw new Error(__('Password muito curta (mínimo 4 caracteres).'));

            const salt = this._generateSalt();
            const hash = await this._hashCredentials(user.email, password, salt);

            const expiresAt = new Date();
            expiresAt.setDate(expiresAt.getDate() + 90); // 90 dias

            await db.meta.put({
                key: 'auth_cache',
                value: {
                    email: user.email.toLowerCase().trim(),
                    name: user.name || user.email,
                    user_id: user.id,
                    tenant_id: (await db.meta.get('tenant_id'))?.value || null,
                    salt,
                    hash,
                    enabled_at: new Date().toISOString(),
                    expires_at: expiresAt.toISOString(),
                },
            });
            return true;
        },

        // ---- Verificação do PIN com bcrypt (o mesmo hash que o servidor) ----
        // O bcryptjs é servido pelo próprio domínio e está em cache do SW, por
        // isso funciona sem rede. A forma com callback não tranca o ecrã.
        // O motor de bcrypt (self-hosted, em cache do SW). Se não carregou,
        // devolve null — e quem chama TEM de distinguir isso de PIN errado,
        // senão tranca toda a gente em silêncio e ainda conta como tentativa.
        _bcryptEngine() {
            return window.bcrypt || (window.dcodeIO && window.dcodeIO.bcrypt) || null;
        },

        _bcryptCompare(secret, hash) {
            const bc = this._bcryptEngine();
            return new Promise((resolve) => {
                if (!bc || !hash) return resolve(false);
                try { bc.compare(String(secret), hash, (err, ok) => resolve(!err && !!ok)); }
                catch (_) { resolve(false); }
            });
        },

        // A janela de validade offline (14 dias). FAIL-CLOSED: este método só
        // é consultado no caminho do PIN, onde há funcionários sincronizados —
        // e um sync real grava SEMPRE offline_valid_until. Faltar essa chave
        // (apagada à mão, estado incoerente) tem de NEGAR, não permitir; senão
        // apagar a chave reabriria o acesso para sempre.
        async _offlineWindowOk() {
            const m = await db.meta.get('offline_valid_until');
            if (!m?.value) return false;
            return new Date(m.value) >= new Date();
        },

        // Desactiva mesmo o acesso offline deste aparelho: apaga os
        // verificadores e a janela. Só volta a haver login offline após nova
        // sincronização com rede — que reflecte demissões e mudanças de PIN.
        async _expirarAcessoOffline() {
            try {
                await db.employees.clear();
                await db.meta.delete('auth_cache');
                await db.meta.delete('offline_valid_until');
                await db.meta.delete('pin_attempts');
                try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}
            } catch (_) {}
        },

        // ---- Trava anti-força-bruta por funcionário ----
        // Um PIN é de baixa entropia; sem trava, um tablet roubado permitiria
        // tentar milhares de PIN. 5 falhas → 60 s de espera.
        async _pinLockedUntil(email) {
            const all = (await db.meta.get('pin_attempts'))?.value || {};
            const until = all[email]?.until || 0;
            return until > Date.now() ? until : 0;
        },
        async _recordPinFail(email) {
            const all = (await db.meta.get('pin_attempts'))?.value || {};
            const rec = all[email] || { count: 0, until: 0 };
            rec.count = (rec.count || 0) + 1;
            if (rec.count >= 5) { rec.until = Date.now() + 60000; rec.count = 0; }
            all[email] = rec;
            await db.meta.put({ key: 'pin_attempts', value: all });
        },
        async _resetPinFail(email) {
            const all = (await db.meta.get('pin_attempts'))?.value || {};
            if (all[email]) { delete all[email]; await db.meta.put({ key: 'pin_attempts', value: all }); }
        },

        async isOfflineAuthEnabled() {
            // Novo modelo: há funcionários sincronizados e a janela é válida.
            const count = await db.employees.count();
            if (count > 0 && await this._offlineWindowOk()) return true;
            // Recurso: o verificador legado de um único operador.
            const m = await db.meta.get('auth_cache');
            if (m?.value && new Date(m.value.expires_at) >= new Date()) return true;
            return false;
        },

        async getOfflineAuthInfo() {
            const count = await db.employees.count();
            const win = await db.meta.get('offline_valid_until');
            const legacy = await db.meta.get('auth_cache');
            return {
                employees: count,
                valid_until: win?.value || null,
                window_expired: win?.value ? new Date(win.value) < new Date() : false,
                legacy: legacy?.value ? {
                    email: legacy.value.email,
                    name: legacy.value.name,
                    expires_at: legacy.value.expires_at,
                } : null,
            };
        },

        /**
         * Entra offline. `secret` é o PIN de turno (modelo novo) ou a password
         * (verificador legado). Descobre-se qual pelo email: se o email está na
         * lista de funcionários, é PIN via bcrypt; senão, tenta o cache legado.
         */
        async verifyOfflineAuth(email, secret) {
            const mail = (email || '').toLowerCase().trim();
            if (!mail || !secret) return { ok: false, reason: 'MISSING' };

            const lockedUntil = await this._pinLockedUntil(mail);
            if (lockedUntil) return { ok: false, reason: 'LOCKED', until: lockedUntil };

            // ---- Modelo novo: funcionário com PIN ----
            const emp = await db.employees.get(mail);
            if (emp && emp.pin_hash) {
                if (!(await this._offlineWindowOk())) {
                    // A janela caducou: desactiva mesmo o aparelho — apaga os
                    // verificadores, não apenas recusa (senão os hashes ficavam
                    // lá para sempre). Obriga a sincronizar com rede.
                    await this._expirarAcessoOffline();
                    return { ok: false, reason: 'EXPIRED_WINDOW' };
                }
                // Motor de cripto ausente ≠ PIN errado. Não conta como
                // tentativa (não escala a trava) e diz-se ao operador.
                if (!this._bcryptEngine()) {
                    return { ok: false, reason: 'NO_ENGINE' };
                }
                const ok = await this._bcryptCompare(secret, emp.pin_hash);
                if (!ok) {
                    await this._recordPinFail(mail);
                    return { ok: false, reason: 'BAD_PIN' };
                }
                await this._resetPinFail(mail);
                // O operador que entra passa a ser o operador activo — é ele
                // que o talão e a comunicação AGT vão registar.
                const tenantId = (await db.meta.get('tenant_id'))?.value || null;
                await db.meta.put({ key: 'user', value: {
                    id: emp.id, name: emp.name, email: mail, tenant_id: tenantId,
                } });
                try { sessionStorage.setItem('pwa_unlocked', '1'); } catch (_) {}
                try { sessionStorage.setItem('pwa_unlocked_at', new Date().toISOString()); } catch (_) {}
                return { ok: true, name: emp.name, email: mail };
            }

            // ---- Recurso: verificador legado (SHA-256 da password) ----
            const m = await db.meta.get('auth_cache');
            if (!m?.value) return { ok: false, reason: 'NO_CACHE' };
            const cache = m.value;
            if (new Date(cache.expires_at) < new Date()) return { ok: false, reason: 'EXPIRED' };
            if (mail !== cache.email) return { ok: false, reason: 'EMAIL_MISMATCH' };
            const hash = await this._hashCredentials(mail, secret, cache.salt);
            if (hash !== cache.hash) {
                await this._recordPinFail(mail);
                return { ok: false, reason: 'BAD_PASSWORD' };
            }
            await this._resetPinFail(mail);
            try { sessionStorage.setItem('pwa_unlocked', '1'); } catch (_) {}
            try { sessionStorage.setItem('pwa_unlocked_at', new Date().toISOString()); } catch (_) {}
            return { ok: true, name: cache.name, email: cache.email };
        },

        async clearOfflineAuth() {
            await db.meta.delete('auth_cache');
            try { sessionStorage.removeItem('pwa_unlocked'); } catch (_) {}
            try { sessionStorage.removeItem('pwa_unlocked_at'); } catch (_) {}
        },

        isPwaUnlocked() {
            try { return sessionStorage.getItem('pwa_unlocked') === '1'; } catch (_) { return false; }
        },

        /**
         * Abre o turno OFFLINE: atualiza estado local imediatamente e enfileira
         * a abertura real (idempotente no servidor). Funciona com ou sem rede.
         */
        async openShiftOffline(data) {
            const local_uuid = 'shift_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            const openedAt = new Date().toISOString();
            const optimistic = {
                open: true,
                number: 'LOCAL-' + local_uuid.slice(-6).toUpperCase(),
                opened_at: openedAt,
                opening_balance: parseFloat(data.opening_balance) || 0,
                cash_sales: 0,
                total_sales: 0,
                _local: true,
            };
            await db.meta.put({ key: 'shift', value: optimistic });
            await enqueue('open_pos_shift', {
                local_uuid,
                opening_balance: parseFloat(data.opening_balance) || 0,
                opening_notes: data.opening_notes || null,
                opened_at_local: openedAt,
            });
            return optimistic;
        },

        /**
         * Fecha o turno OFFLINE: estado local fecha já; o fecho real é enfileirado
         * e só executa DEPOIS de todas as vendas pendentes sincronizarem (FIFO + guarda).
         */
        async closeShiftOffline(data) {
            const local_uuid = 'shiftc_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            const closedAt = new Date().toISOString();
            await db.meta.put({ key: 'shift', value: { open: false, number: null, opened_at: null, _localClose: true } });
            await enqueue('close_pos_shift', {
                local_uuid,
                actual_cash: parseFloat(data.actual_cash) || 0,
                closing_notes: data.closing_notes || null,
                difference_reason: data.difference_reason || null,
                closed_at_local: closedAt,
            });
            return { open: false, closed_at_local: closedAt };
        },

        promptInstall,
        isStandalone,

        async getMeta(key) {
            const m = await db.meta.get(key);
            return m ? m.value : null;
        },

        /**
         * Cópia de segurança do que está por sincronizar.
         *
         * A fila vive em IndexedDB, no dispositivo. Se o telemóvel se perde,
         * se o navegador limpa os dados do site, ou se alguém carrega em
         * "Reiniciar tudo" antes de sincronizar, as vendas desaparecem — e
         * essas vendas já aconteceram, com dinheiro trocado e talão entregue.
         * Depois não há de onde as tirar.
         *
         * Vai só o que está POR ENVIAR. O catálogo, as séries e as taxas vêm
         * do servidor e não se perdem — inclui-los fazia um ficheiro dez vezes
         * maior sem salvar nada.
         *
         * Funciona sem rede: o Blob e o createObjectURL são do navegador.
         */
        async exportarCopia() {
            const fila = await db.sync_queue.where('status').notEqual('done').toArray();
            const vendas = await db.pos_sales.where('_synced').equals(0).toArray();
            const clientes = await db.clients.where('_synced').equals(0).toArray();
            const rascunhos = await db.draft_documents.where('_synced').equals(0).toArray();

            const meta = {};
            for (const chave of ['shift', 'last_sync', 'tenant_id', 'user']) {
                const m = await db.meta.get(chave);
                if (m) meta[chave] = m.value;
            }

            const copia = {
                formato: 'soserp.pwa.copia',
                versao: 1,
                gerado_em: new Date().toISOString(),
                tenant_id: window.SOS_TENANT_ID || meta.tenant_id || null,
                utilizador: {
                    id: window.SOS_USER_ID || null,
                    nome: window.SOS_USER_NAME || (meta.user && meta.user.name) || null,
                },
                dispositivo: navigator.userAgent,
                contagens: {
                    fila: fila.length,
                    vendas: vendas.length,
                    clientes: clientes.length,
                    rascunhos: rascunhos.length,
                },
                dados: {
                    sync_queue: fila,
                    pos_sales: vendas,
                    clients: clientes,
                    draft_documents: rascunhos,
                    meta,
                },
            };

            const carimbo = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            const nome = `sos-copia-offline_${carimbo}.json`;

            const blob = new Blob([JSON.stringify(copia, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nome;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            // Sem isto o Blob fica em memória até a página fechar. Num turno
            // longo, com exportações repetidas, isso conta.
            setTimeout(() => URL.revokeObjectURL(url), 1000);

            return { nome, contagens: copia.contagens };
        },
    };

    // ========================
    // INSTALL PROMPT
    // ========================
    let deferredPrompt = null;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    }

    // Mostra/oculta a UI de instalação (banner + botão no cabeçalho)
    function refreshInstallUI() {
        const banner = document.getElementById('pwa-install-banner');
        const hdrBtn = document.getElementById('pwa-install-header');
        const installable = !isStandalone() && (deferredPrompt || isIos());

        if (hdrBtn) hdrBtn.classList.toggle('hidden', !installable);
        if (banner) {
            const dismissed = localStorage.getItem('pwa-install-dismissed');
            banner.classList.toggle('hidden', !(installable && !dismissed));
        }
    }

    // Dispara o prompt nativo (ou instruções no iOS)
    async function promptInstall() {
        if (isStandalone()) return;
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            refreshInstallUI();
            return;
        }
        if (isIos()) {
            alert(__('Para instalar no iPhone/iPad:\n\n1. Toque no botão Partilhar (□↑)\n2. Escolha "Adicionar ao ecrã principal"'));
            return;
        }
        alert(__('Para instalar: abra o menu do navegador (⋮) e escolha "Instalar aplicação" / "Adicionar ao ecrã principal".'));
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        refreshInstallUI();
    });

    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('pwa-install-btn');
        const hdrBtn = document.getElementById('pwa-install-header');
        const dismiss = document.getElementById('pwa-install-dismiss');
        const banner = document.getElementById('pwa-install-banner');

        btn?.addEventListener('click', () => promptInstall());
        hdrBtn?.addEventListener('click', () => promptInstall());
        dismiss?.addEventListener('click', () => {
            banner?.classList.add('hidden');
            localStorage.setItem('pwa-install-dismissed', '1');
        });

        refreshInstallUI();

        // Receber mensagem de Background Sync do Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data?.type === 'BG_SYNC' && !state.syncing) {
                    checkRealOnline().then(ok => { if (ok) sync(false); });
                }
            });
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        document.getElementById('pwa-install-banner')?.classList.add('hidden');
        document.getElementById('pwa-install-header')?.classList.add('hidden');
    });

    // ========================
    // BOOTSTRAP
    // ========================
    // Versão do payload do catálogo. Incrementar quando o formato muda (ex.: passou
    // a enviar o NOME da categoria) para forçar um re-sync completo nos dispositivos.
    //
    // Tem MESMO de subir a cada campo novo, e a razão é discreta: a sincronização
    // normal é incremental (só traz artigos com updated_at mais recente) e
    // acrescentar colunas à base de dados não mexe no updated_at de linha
    // nenhuma. Sem subir aqui, um artigo que ninguém edite nunca mais é enviado
    // e fica no dispositivo na versão antiga do registo — ao balcão isso era um
    // psicotrópico já em prateleira a entrar no carrinho offline sem a pergunta
    // de confirmação, porque o is_controlled nunca lá chegou.
    const CATALOG_VERSION = 4;   // v4: campos de farmácia e vestuário (receita, controlado, dosagem, tamanho, cor…)

    document.addEventListener('DOMContentLoaded', async () => {
        await refreshPendingCount();
        updateStatusBar();

        // Migração de catálogo: se a versão mudou, limpa produtos e força sync completa.
        const cv = await db.meta.get('catalog_version');
        if (!cv || cv.value !== CATALOG_VERSION) {
            await db.products.clear();
            await db.meta.delete('last_sync');
            await db.meta.put({ key: 'catalog_version', value: CATALOG_VERSION });
        }

        // Repõe jobs que tinham falhado (5+ tentativas) para nova tentativa no
        // arranque — ex.: após uma correção no servidor. Evita ficarem presos
        // como "pendentes" para sempre sem nunca reprocessar.
        try {
            await db.sync_queue.where('status').equals('failed').modify({
                status: 'pending', retries: 0, last_error: null,
            });
        } catch (_) {}
        await refreshPendingCount();

        // Sincroniza sempre que estiver online: full na 1ª vez, incremental depois.
        // (ANTES só sincronizava se houvesse pendentes na fila — produtos novos ou
        //  alterados no servidor nunca chegavam ao dispositivo, mesmo com refresh.)
        const lastSync = await db.meta.get('last_sync');
        if (navigator.onLine) {
            await sync(!lastSync);
        }

        // AUTO-SYNC.
        //
        // Isto só corria quando havia pendentes POR ENVIAR. Quem não vendia
        // nada nunca puxava nada: um posto que ficasse aberto o dia inteiro sem
        // uma única venda ficava com o catálogo do dia anterior, e artigos
        // novos, preços alterados e clientes novos só chegavam ao reabrir a
        // aplicação. Quando o servidor caísse, faltava precisamente o que
        // tinha mudado entretanto.
        //
        // Passa a haver duas cadências: a dos pendentes, rápida, porque uma
        // venda por enviar é dinheiro parado; e a do catálogo, mais lenta,
        // porque puxar produtos de dezenas de postos a cada 45 segundos é
        // carga no servidor sem retorno. A descarga é incremental — leva um
        // `since` — pelo que quando não mudou nada quase não custa.
        const PENDENTES_A_CADA = 45 * 1000;
        const CATALOGO_A_CADA = 5 * 60 * 1000;
        const CATALOGO_AO_VOLTAR = 2 * 60 * 1000;

        function desdeAUltimaSync() {
            if (!state.lastSync) return Infinity;

            const quando = new Date(state.lastSync).getTime();

            return Number.isNaN(quando) ? Infinity : Date.now() - quando;
        }

        setInterval(async () => {
            if (state.syncing) return;

            const temPendentes = state.pendingCount > 0;
            const catalogoVelho = desdeAUltimaSync() >= CATALOGO_A_CADA;

            if (!temPendentes && !catalogoVelho) return;

            const ok = await checkRealOnline();
            if (ok) sync(false);
        }, PENDENTES_A_CADA);

        // Ao voltar à aplicação (trocar de separador, reabrir o PWA): envia o
        // que está pendente, e aproveita para refrescar o catálogo se já tiver
        // algum tempo. É o momento em que quem está ao balcão vai começar a
        // usar aquilo, e o melhor para descobrir que falta um artigo.
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState !== 'visible' || state.syncing) return;

            const ok = await checkRealOnline();
            if (!ok) return;

            const pendentes = await refreshPendingCount();

            if (pendentes > 0 || desdeAUltimaSync() >= CATALOGO_AO_VOLTAR) {
                sync(false);
            }
        });

        // Indicador "última sync há X min" no header PWA — atualiza a cada 30s
        function updateSyncBadge() {
            const badge = document.getElementById('pwa-last-sync-badge');
            if (!badge) return;
            if (!state.lastSync) {
                badge.classList.add('hidden');
                return;
            }
            const diff = Math.round((Date.now() - new Date(state.lastSync).getTime()) / 60000);
            let label;
            // Nada de '· sync há ' + n + 'min': noutras línguas o número não fica
            // no meio da frase. O número entra por :n.
            if (diff < 1) label = __('· sync agora');
            else if (diff < 60) label = __('· sync há :n min', { n: diff });
            else if (diff < 1440) label = __('· sync há :n h', { n: Math.round(diff / 60) });
            else label = __('· sync há :n d', { n: Math.round(diff / 1440) });
            badge.textContent = label;
            badge.classList.remove('hidden');
        }
        updateSyncBadge();
        setInterval(updateSyncBadge, 30000);
        window.addEventListener('pwa:synced', updateSyncBadge);
    });
})();
