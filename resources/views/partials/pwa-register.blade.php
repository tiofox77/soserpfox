{{-- PWA Service Worker registration + auto-update prompt --}}
<script>
(function() {
    if (!('serviceWorker' in navigator)) return;

    // Dizer alto quando o modo offline NÃO vai funcionar.
    //
    // Um service worker só corre em contexto seguro: HTTPS com certificado de
    // confiança, ou http://localhost. Um domínio local como https://soserp.test
    // com certificado auto-assinado NÃO é contexto seguro — o browser recusa o
    // registo, e recusa-o em silêncio. O sintoma é o pior possível: a aplicação
    // parece bem enquanto há rede, e no dia em que o servidor cai aparece a
    // página de erro do browser, como se o modo offline nunca tivesse existido.
    //
    // Fica no console porque é lá que se vai ver quando se está a testar isto.
    if (!window.isSecureContext) {
        console.warn(
            '[PWA] Sem contexto seguro (%s). O browser NÃO regista o service worker aqui, '
            + 'e o modo offline não vai funcionar. Use https com certificado de confiança, '
            + 'ou http://localhost.',
            location.origin
        );
        return;
    }

    try { localStorage.setItem('soserp-last-online', Date.now().toString()); } catch (e) {}

    // Na PRIMEIRA instalação não se recarrega. O `clients.claim()` do worker
    // novo dispara o 'controllerchange' também quando não havia controlador
    // nenhum — e a página recarregava a meio do email que o operador estava a
    // escrever na entrada, num aparelho acabado de instalar. Passar a ser
    // controlada não precisa de recarga; trocar de versão precisa.
    let controlada = !!navigator.serviceWorker.controller;
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!controlada) { controlada = true; return; }
        if (refreshing) return;
        refreshing = true;
        window.location.reload();
    });

    // Aplica a versão nova sozinho, sem perguntar nem avisar: manda
    // SKIP_WAITING assim que o worker novo instala, o que dispara o
    // 'controllerchange' acima e recarrega a página. Antes ficava um toast
    // fixo a pedir clique — decisão trocada por pedido: prioriza não
    // incomodar sobre o risco (raro) de recarregar a meio de um ecrã com
    // algo por gravar.
    function applyUpdate(reg) {
        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
    }

    function trackInstalling(worker, reg) {
        worker.addEventListener('statechange', () => {
            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                applyUpdate(reg);
            }
        });
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then((reg) => {
                // Se já há um worker à espera quando esta página carrega
                if (reg.waiting && navigator.serviceWorker.controller) {
                    applyUpdate(reg);
                }
                if (reg.installing) {
                    trackInstalling(reg.installing, reg);
                }
                reg.addEventListener('updatefound', () => {
                    if (reg.installing) trackInstalling(reg.installing, reg);
                });
                // Verificar atualização periodicamente (a cada 30 min)
                setInterval(() => reg.update().catch(() => {}), 30 * 60 * 1000);

                // Sincronização periódica com a aplicação FECHADA.
                //
                // Só existe no Chrome/Android e só para quem tem a aplicação
                // instalada; o browser é que decide quando corre, e costuma ser
                // de doze em doze horas. Não substitui a sincronização de
                // dentro da aplicação — é um extra para o aparelho não ficar
                // parado dias a fio sem trazer nada.
                //
                // Pede-se sempre e falha em silêncio onde não existe: um
                // `catch` vazio aqui vale mais do que uma lista de browsers
                // que envelhece connosco.
                if ('periodicSync' in reg) {
                    navigator.permissions?.query({ name: 'periodic-background-sync' })
                        .then((estado) => {
                            if (estado.state !== 'granted') return;

                            return reg.periodicSync.register('manter-catalogo', {
                                minInterval: 6 * 60 * 60 * 1000,
                            });
                        })
                        .catch(() => {});
                }
                // E sempre que a janela volta a ficar visível
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') reg.update().catch(() => {});
                });
            })
            .catch((err) => console.warn('[PWA] Falha ao registar SW:', err));
    });

    // Indicadores de status online/offline
    window.addEventListener('offline', () => {
        document.body.classList.add('app-offline');
        if (typeof toastr !== 'undefined') {
            toastr.warning('Sem conexão. A trabalhar em modo offline.', 'Offline', { timeOut: 5000 });
        }
    });
    window.addEventListener('online', () => {
        document.body.classList.remove('app-offline');
        try { localStorage.setItem('soserp-last-online', Date.now().toString()); } catch (e) {}
        if (typeof toastr !== 'undefined') {
            toastr.success('Conexão restaurada!', 'Online', { timeOut: 3000 });
        }
    });
})();
</script>
