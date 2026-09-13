import { db, lerMeta, type Registo } from './base';
import { anunciar, esperarMigracaoDoArranque, notificar, state } from './estado';
import { processQueue, refreshPendingCount } from './fila';
import { checkRealOnline, fetchJson } from './rede';
import { comLimite } from './util';
import { t } from '@/i18n';

/**
 * A SINCRONIZAÇÃO — envia a fila e descarrega o catálogo.
 */

/**
 * O MOLDE de cada documento: o próprio modelo de impressão do servidor, com
 * marcas no lugar dos valores. Sem rede o aparelho preenche-o, e o papel sai
 * igual ao do servidor porque É o do servidor. Renova-se uma vez por dia, ou
 * numa sincronização forçada (o logótipo e as contas mudam).
 */
export async function descarregarMoldes(forcar: boolean): Promise<void> {
    for (const tipo of ['FT', 'FR', 'proforma']) {
        try {
            const actual = await lerMeta<Registo>('molde_' + tipo);
            const velho = !actual || !actual.em || Date.now() - Date.parse(actual.em) > 24 * 3600 * 1000;
            if (!forcar && !velho) continue;

            const r = await fetch('/invoicing/offline/molde/' + tipo, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: actual?.etag ? { 'If-None-Match': actual.etag } : {},
            });

            // Não mudou: fica o que está, só se renova a data.
            if (r.status === 304 && actual?.html) {
                await db.meta.put({ key: 'molde_' + tipo, value: { ...actual, em: new Date().toISOString() } });
                continue;
            }

            if (!r.ok) continue;

            const html = await r.text();
            // Só se for mesmo um molde: uma página de login ou de erro não serve.
            if (!html.includes('%%NUMERO%%') || !html.includes('%%ITEM_NOME%%')) continue;

            await db.meta.put({ key: 'molde_' + tipo, value: { html, etag: r.headers.get('ETag') || null, em: new Date().toISOString() } });
        } catch {
            // Sem molde fica o desenho de recurso do aparelho.
        }
    }
}

/**
 * A sala do restaurante, guardada para funcionar sem rede.
 *
 * O estado das mesas que vem do servidor é o ponto de partida, não a verdade
 * final: uma mesa que ESTE aparelho abriu offline continua ocupada aqui. Sem
 * isso, uma sincronização a meio do serviço punha as mesas livres no ecrã do
 * empregado com os clientes sentados nelas.
 */
export async function guardarSalaDoRestaurante(sala: Registo | null | undefined): Promise<void> {
    // `null` é legítimo: a empresa não tem o módulo, e a sala local vai-se embora.
    await db.rest_venues.clear();
    await db.rest_areas.clear();
    await db.rest_tables.clear();

    if (!sala) {
        await db.meta.delete('restaurant_settings');

        return;
    }

    if (sala.venues?.length) await db.rest_venues.bulkPut(sala.venues);
    if (sala.areas?.length) await db.rest_areas.bulkPut(sala.areas);
    if (sala.tables?.length) await db.rest_tables.bulkPut(sala.tables);

    await db.meta.put({
        key: 'restaurant_settings',
        value: { ...(sala.settings || {}), recipe_product_ids: sala.recipe_product_ids || null },
    });

    const porSubir = await db.rest_orders
        .where('status').notEqual('fechada')
        .filter((c) => !c._synced && c.table_id)
        .toArray();

    for (const comanda of porSubir) {
        await db.rest_tables.update(comanda.table_id, { status: 'occupied' });
    }
}

/** A empresa do aparelho mudou: limpa o que é dela, e RETÉM (não apaga) a fila. */
async function limparDadosDaEmpresaAnterior(anterior: number, nova: number): Promise<void> {
    console.warn(`[PWA] Empresa mudou (${anterior}→${nova}) — a limpar dados locais`);

    await Promise.all([
        db.products.clear(), db.clients.clear(), db.series.clear(), db.tax_rates.clear(),
        db.draft_documents.clear(), db.pos_sales.clear(), db.employees.clear(),
        db.rest_venues.clear(), db.rest_areas.clear(), db.rest_tables.clear(),
    ]);

    for (const chave of ['modules', 'restaurant_settings', 'shift', 'last_sync', 'catalog_version',
        'auth_cache', 'offline_valid_until', 'pin_attempts']) {
        await db.meta.delete(chave);
    }

    try { sessionStorage.removeItem('pwa_unlocked'); } catch { /* sem armazenamento */ }

    // O que ficou por subir da empresa anterior fica RETIDO. Avisa-se: uma venda
    // parada que ninguém vê é uma venda perdida na mesma — só mais devagar.
    let retidos = 0;
    try {
        retidos = await db.sync_queue
            .where('status').anyOf('pending', 'outra_empresa')
            .filter((j) => !!j.tenant_id && j.tenant_id !== nova)
            .count();
    } catch { /* ignora */ }

    anunciar('pwa:tenant-changed', { prev: anterior, next: nova, retidos });

    if (retidos > 0) {
        console.warn(`[PWA] ${retidos} operação(ões) da empresa anterior ficaram retidas.`);
        state.retidos = retidos;
        notificar();
    }
}

let temporizadorDoErro: ReturnType<typeof setTimeout> | undefined;
let temporizadorDoSincronizado: ReturnType<typeof setTimeout> | undefined;

function mostrarErroDeSincronizacao(mensagem: string): void {
    state.erroDeSync = mensagem;
    notificar();
    clearTimeout(temporizadorDoErro);
    temporizadorDoErro = setTimeout(() => { state.erroDeSync = null; notificar(); }, 8000);
}

/**
 * Sincronização principal — envia a fila pendente e descarrega o catálogo.
 * @param forcar completa (não incremental), e repõe os trabalhos falhados.
 */
export async function sync(forcar = false): Promise<void> {
    // Nunca antes da migração do arranque — ver `esperarMigracaoDoArranque` (estado.ts).
    await esperarMigracaoDoArranque();

    if (state.syncing || !navigator.onLine) return;

    state.syncing = true;
    notificar();

    // Conectividade REAL antes de começar (Wi-Fi sem internet diz «online»).
    if (!(await checkRealOnline())) {
        state.syncing = false;
        notificar();

        return;
    }

    try {
        // Numa sincronização FORÇADA, os falhados voltam a tentar. O `last_error`
        // NÃO se apaga: era a única pista do que tinha corrido mal.
        if (forcar) {
            await db.sync_queue.where('status').equals('failed').modify({ status: 'pending', retries: 0 });
        }

        // 1) A fila primeiro.
        await processQueue();

        // 2) O catálogo, incremental quando dá.
        const lastSync = await lerMeta<string>('last_sync');
        const since = forcar || !lastSync ? null : lastSync;
        const json = await fetchJson<Registo>('/api/v1/invoicing/sync' + (since ? `?since=${encodeURIComponent(since)}` : ''));
        const dados = json.data || {};

        // 3) Guardar.
        if (dados.products?.length) await db.products.bulkPut(dados.products);

        if (dados.clients?.length) {
            // JUNTA-SE, NÃO SE SUBSTITUI: o registo do servidor não traz
            // `local_uuid`, e um cliente acabado de subir perdia-o na mesma
            // sincronização — os documentos na fila deixavam de o encontrar.
            for (const c of dados.clients) {
                const existente = await db.clients.get(c.id);
                await db.clients.put({ ...(existente || {}), ...c, _synced: 1 });
            }
        }

        if (dados.series?.length) await db.series.bulkPut(dados.series);

        // As tabelas da AGT substituem-se por inteiro: são listas fechadas.
        if (dados.iec_pautais) { await db.iec_pautais.clear(); await db.iec_pautais.bulkPut(dados.iec_pautais); }
        if (dados.is_verbas) { await db.is_verbas.clear(); await db.is_verbas.bulkPut(dados.is_verbas); }
        if (dados.tax_rates?.length) { await db.tax_rates.clear(); await db.tax_rates.bulkPut(dados.tax_rates); }

        // 3b) O que saiu do catálogo no servidor sai daqui — senão ficava vendável offline para sempre.
        if (dados.removed_products?.length) await db.products.bulkDelete(dados.removed_products);
        if (dados.removed_clients?.length) await db.clients.bulkDelete(dados.removed_clients);

        await db.meta.put({ key: 'last_sync', value: json.server_time });

        await descarregarMoldes(forcar);

        // Sincronização online bem-sucedida → sessão válida → destranca o separador.
        try { sessionStorage.setItem('pwa_unlocked', '1'); } catch { /* ignora */ }

        // A LIMPEZA É AO TROCAR DE EMPRESA, não de utilizador: o aparelho guarda
        // os dados da empresa inteira para qualquer funcionário entrar offline.
        const empresaAnterior = await lerMeta<number>('tenant_id');
        if (empresaAnterior && json.tenant_id && empresaAnterior !== json.tenant_id) {
            await limparDadosDaEmpresaAnterior(empresaAnterior, json.tenant_id);
        }

        await db.meta.put({ key: 'user', value: json.user });
        await db.meta.put({ key: 'tenant_id', value: json.tenant_id });

        // Os funcionários para o login offline: lista completa e autoritária
        // (quem saiu desaparece). Só os verificadores bcrypt, nunca o PIN.
        if (Array.isArray(json.employees)) {
            await db.employees.clear();
            if (json.employees.length) {
                await db.employees.bulkPut(json.employees.map((e: Registo) => ({
                    email: String(e.email || '').toLowerCase().trim(),
                    id: e.id,
                    name: e.name,
                    pin_hash: e.pin_hash,
                    updated_at: e.updated_at || null,
                    pode_repor_pin: !!e.pode_repor_pin,
                })));
            }
        }

        if (json.offline_valid_until) await db.meta.put({ key: 'offline_valid_until', value: json.offline_valid_until });
        // As regras do PIN são as do servidor: o aparelho recusa sem rede o mesmo que ele.
        if (json.pin_regras) await db.meta.put({ key: 'pin_regras', value: json.pin_regras });
        if (json.company) await db.meta.put({ key: 'company', value: json.company });

        if (json.shift) {
            // Não se sobrepõe o turno local se há abertura/fecho feitos offline por subir.
            const opsDeTurno = await db.sync_queue
                .where('status').anyOf('pending', 'failed')
                .filter((j) => j.op === 'open_pos_shift' || j.op === 'close_pos_shift')
                .count();
            if (!opsDeTurno) await db.meta.put({ key: 'shift', value: json.shift });
        }

        if (json.warehouse) await db.meta.put({ key: 'warehouse', value: json.warehouse });

        // Os MÓDULOS: sem eles o aparelho não sabe offline se a empresa tem restaurante.
        if (Array.isArray(json.modules)) {
            await db.meta.put({ key: 'modules', value: json.modules.map((m: Registo) => m.slug) });
        }

        // Os métodos de pagamento da tesouraria: o fecho de uma comanda precisa do ID.
        if (Array.isArray(dados.payment_methods)) await db.meta.put({ key: 'payment_methods', value: dados.payment_methods });

        await guardarSalaDoRestaurante(json.restaurant);

        state.lastSync = json.server_time;
        state.acabouDeSincronizar = true;
        clearTimeout(temporizadorDoSincronizado);
        temporizadorDoSincronizado = setTimeout(() => { state.acabouDeSincronizar = false; notificar(); }, 2000);

        anunciar('pwa:synced', json);
    } catch (err) {
        console.error('[PWA] Sync falhou:', err);
        mostrarErroDeSincronizacao((err as Error).message || t('desconhecido'));
        anunciar('pwa:sync-error', err);
    } finally {
        state.syncing = false;
        await refreshPendingCount();
    }
}

/**
 * Com rede, emite JÁ: espera pela sincronização desta venda.
 *
 * O provisório existe para quando NÃO há rede. Havendo, o cliente não sai da
 * loja com um talão «PEND-». Grava-se sempre primeiro em local; se a rede
 * falhar a meio, a venda fica na fila. O limite impede o balcão de ficar preso
 * a uma rede que existe mas está lenta.
 */
export async function emitirJa(
    localUuid: string,
    msLimite = 8000,
    deps = { navigator, checkRealOnline, sync, db },
): Promise<Registo | null> {
    try {
        if (!deps.navigator.onLine || !(await deps.checkRealOnline())) return null;

        await comLimite(deps.sync(false), msLimite);

        const actual = await deps.db.pos_sales.get(localUuid);

        return actual && actual._synced ? actual : null;
    } catch {
        return null;
    }
}
