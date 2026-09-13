import { db, lerMeta } from './base';
import { deveSincronizarAoVoltar, deveSincronizarNoTemporizador, PENDENTES_A_CADA } from './cadencia';
import { marcarMigracaoDoArranque, notificar, state } from './estado';
import { refreshPendingCount } from './fila';
import { ligarInstalacao } from './instalar';
import { checkRealOnline } from './rede';
import { sync } from './sincronizar';

/**
 * O ARRANQUE DO MOTOR — uma vez por página.
 */

/**
 * A versão do formato do catálogo. Tem MESMO de subir a cada campo novo: a
 * sincronização é incremental (só artigos com `updated_at` recente) e
 * acrescentar colunas não mexe no `updated_at` de linha nenhuma. Sem subir, um
 * artigo que ninguém edite fica no aparelho na forma antiga — foi um
 * psicotrópico a entrar no carrinho offline sem a pergunta de confirmação.
 */
export const CATALOG_VERSION = 4; // v4: campos de farmácia e vestuário

let arrancado = false;

export async function arrancarMotor(): Promise<void> {
    if (arrancado) return;
    arrancado = true;

    // A migração do catálogo PRIMEIRO, e toda a sincronização espera por ela.
    // Versão nova do formato → limpa os artigos e força uma descarga completa.
    const migracao = (async () => {
        if ((await lerMeta('catalog_version')) !== CATALOG_VERSION) {
            await db.products.clear();
            await db.meta.delete('last_sync');
            await db.meta.put({ key: 'catalog_version', value: CATALOG_VERSION });
        }
    })();
    marcarMigracaoDoArranque(migracao);
    await migracao.catch((e) => console.warn('[PWA] migração do catálogo falhou', e));

    ligarInstalacao();

    window.addEventListener('online', async () => {
        state.online = true;
        const real = await checkRealOnline();
        notificar();
        if (real) await sync(false);
    });

    window.addEventListener('offline', () => {
        // O navegador a dizer «offline» é definitivo: o `realOnline` também
        // (ficava com o último ping e dizia que havia rede sem haver).
        state.online = false;
        state.realOnline = false;
        notificar();
    });

    // A sincronização em segundo plano do service worker (aplicação fechada ou
    // rede recuperada) chega como mensagem.
    navigator.serviceWorker?.addEventListener('message', (evento) => {
        if (evento.data?.type === 'BG_SYNC' && !state.syncing) {
            void checkRealOnline().then((ok) => { if (ok) void sync(false); });
        }
    });

    await refreshPendingCount();


    // Os falhados (5+ tentativas) voltam a tentar no arranque — por exemplo
    // depois de uma correcção no servidor; senão ficavam presos para sempre.
    try {
        await db.sync_queue.where('status').equals('failed').modify({ status: 'pending', retries: 0, last_error: null });
    } catch { /* ignora */ }

    await refreshPendingCount();

    // A última sincronização que o aparelho conhece, para o cabeçalho a dizer
    // mesmo sem rede («sync há 3 h»).
    const ultima = await lerMeta<string>('last_sync');
    state.lastSync = ultima;
    notificar();

    // Sincroniza SEMPRE com rede: completa na 1.ª vez, incremental depois.
    if (navigator.onLine) await sync(!ultima);

    setInterval(async () => {
        if (!deveSincronizarNoTemporizador(state)) return;
        if (await checkRealOnline()) void sync(false);
    }, PENDENTES_A_CADA);

    // Ao voltar à aplicação: é o momento em que quem está ao balcão vai começar
    // a usar aquilo, e o melhor para descobrir que falta um artigo.
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState !== 'visible' || state.syncing) return;
        if (!(await checkRealOnline())) return;

        const pendentes = await refreshPendingCount();
        if (deveSincronizarAoVoltar(pendentes, state.lastSync)) void sync(false);
    });
}
