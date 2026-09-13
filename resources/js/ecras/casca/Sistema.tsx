import { useCallback, useEffect, useRef, useState } from 'react';

import { EVENTO_DE_AVISO, avisar, type Aviso, type TipoDeAviso } from '@/casca/avisos';
import { ligarPdfDoDocumento } from '@/casca/pdfDoDocumento';
import { ligarServicoOffline } from '@/casca/servicoOffline';
import { t } from '@/i18n';
import { cls } from '@/ui/tokens';

/**
 * O QUE CORRE POR BAIXO DE TODAS AS PÁGINAS — sem ecrã próprio.
 *
 * Tudo isto vivia em `<script>` soltos no fim do layout, a pendurar-se no
 * jQuery e no toastr. Juntou-se numa peça só, montada uma vez por página:
 *
 *  • OS AVISOS DE CANTO — quem quer avisar dispara `casca:aviso` (ver
 *    `casca/avisos.ts`); o gerador de PDF e o service worker não sabem React.
 *  • A SESSÃO QUE MORREU — o keep-alive pergunta de 5 em 5 minutos, e ao
 *    voltar ao separador. Antes recarregava às cegas e a pessoa aterrava no
 *    login sem perceber; agora diz o que se passou e deixa voltar a entrar.
 *  • A BARRA DE PROGRESSO — corre do clique numa ligação até a página seguinte
 *    chegar: numa rede fraca, vê-se que o clique foi ouvido.
 *  • O PDF DO ECRÃ e o SERVICE WORKER — ligados uma vez.
 *  • OS RECADOS DA SESSÃO — o `->with('error', …)` de quem redirecciona para
 *    um ecrã React, que de outra forma ninguém via (ver RecadosDaSessao).
 */
type Props = { login: string; manterViva: string | null; recados?: Array<{ tipo: TipoDeAviso; texto: string }> };

type AvisoNoEcra = Aviso & { id: number; aSair?: boolean };

const ESTILO: Record<TipoDeAviso, { icone: string; cor: string; barra: string; titulo: string }> = {
    ok: { icone: 'fa-circle-check', cor: 'text-emerald-600', barra: 'bg-emerald-500', titulo: 'border-t-emerald-500' },
    erro: { icone: 'fa-circle-xmark', cor: 'text-red-600', barra: 'bg-red-500', titulo: 'border-t-red-500' },
    aviso: { icone: 'fa-triangle-exclamation', cor: 'text-amber-600', barra: 'bg-amber-500', titulo: 'border-t-amber-500' },
    info: { icone: 'fa-circle-info', cor: 'text-blue-600', barra: 'bg-blue-500', titulo: 'border-t-blue-500' },
};

const CINCO_MINUTOS = 5 * 60 * 1000;

export default function Sistema({ login, manterViva, recados = [] }: Props) {
    const [avisos, porAvisos] = useState<AvisoNoEcra[]>([]);
    const [sessaoMorta, porSessaoMorta] = useState(false);
    const [progresso, porProgresso] = useState<{ largura: number; acabou: boolean }>({ largura: 0, acabou: false });
    const contador = useRef(0);

    useEffect(() => {
        ligarPdfDoDocumento();
        ligarServicoOffline();
    }, []);

    /* ── Os avisos ─────────────────────────────────────────────────── */

    const tirar = useCallback((id: number) => {
        porAvisos((l) => l.map((a) => (a.id === id ? { ...a, aSair: true } : a)));
        window.setTimeout(() => porAvisos((l) => l.filter((a) => a.id !== id)), 220);
    }, []);

    useEffect(() => {
        const receber = (e: Event) => {
            const aviso = (e as CustomEvent<Aviso>).detail;
            if (!aviso?.texto) return;
            const id = ++contador.current;
            // No máximo quatro de uma vez: uma rajada de erros não tapa a página.
            porAvisos((l) => [...l.slice(-3), { ...aviso, id }]);
            window.setTimeout(() => tirar(id), aviso.duracao ?? 3000);
        };

        window.addEventListener(EVENTO_DE_AVISO, receber);
        return () => window.removeEventListener(EVENTO_DE_AVISO, receber);
    }, [tirar]);

    /* Os recados do redirect: depois do ouvinte, senão o aviso passava sem ninguém a ouvir. Um erro fica mais tempo. */
    const recadosDados = useRef(false);
    useEffect(() => {
        // Só à chegada da página, e uma vez (o StrictMode corre os efeitos duas).
        if (recadosDados.current) return;
        recadosDados.current = true;
        recados.forEach((r) => avisar(r.texto, r.tipo, { duracao: r.tipo === 'erro' ? 8000 : 5000 }));
    }, [recados]);

    /* ── A sessão ──────────────────────────────────────────────────── */

    useEffect(() => {
        // O portal do cliente não tem keep-alive: a sessão é de outro guarda.
        if (!manterViva) return;
        let ultimo = 0;

        const perguntar = () => {
            if (document.hidden) return;
            const agora = performance.now();
            // No máximo um pedido a cada 30 s: o voltar ao separador repete-se muito.
            if (ultimo && agora - ultimo < 30_000) return;
            ultimo = agora;

            fetch(manterViva, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                // Um 302 para o login chega como `opaqueredirect`: é a sessão morta.
                redirect: 'manual',
            })
                .then((r) => {
                    if (r.type === 'opaqueredirect' || r.status === 401 || r.status === 419) porSessaoMorta(true);
                })
                .catch(() => { /* sem rede não é sessão morta — o modo offline trata disso */ });
        };

        const relogio = window.setInterval(perguntar, CINCO_MINUTOS);
        document.addEventListener('visibilitychange', perguntar);

        return () => {
            window.clearInterval(relogio);
            document.removeEventListener('visibilitychange', perguntar);
        };
    }, [manterViva]);

    /* ── A barra de progresso ──────────────────────────────────────── */

    useEffect(() => {
        let relogio = 0;

        const comecar = () => {
            window.clearInterval(relogio);
            let largura = 0;
            porProgresso({ largura: 0, acabou: false });
            relogio = window.setInterval(() => {
                largura += (95 - largura) * 0.08;
                porProgresso({ largura, acabou: false });
                if (largura >= 94) window.clearInterval(relogio);
            }, 80);
        };

        const clique = (e: MouseEvent) => {
            const a = (e.target as Element | null)?.closest?.('a[href]') as HTMLAnchorElement | null;
            if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (a.target && a.target !== '_self') return;
            if (a.hasAttribute('download') || a.origin !== location.origin) return;
            if (a.getAttribute('href')!.startsWith('#') || (a.pathname === location.pathname && a.search === location.search && a.hash)) return;
            comecar();
        };

        // Voltar atrás pela cache do browser: a barra não pode ficar a meio.
        const voltou = () => { window.clearInterval(relogio); porProgresso({ largura: 0, acabou: true }); };

        document.addEventListener('click', clique);
        window.addEventListener('pageshow', voltou);

        return () => {
            window.clearInterval(relogio);
            document.removeEventListener('click', clique);
            window.removeEventListener('pageshow', voltou);
        };
    }, []);

    return (
        <>
            <div id="spa-progress" className={cls('spa-progress', progresso.acabou && 'done')} style={{ width: `${progresso.largura}%` }} aria-hidden="true" />

            <div className="pointer-events-none fixed right-4 top-4 z-[100001] flex w-[22rem] max-w-[calc(100vw-2rem)] flex-col gap-3" aria-live="polite">
                {avisos.map((a) => {
                    const e = ESTILO[a.tipo] ?? ESTILO.info;

                    return (
                        <div
                            key={a.id}
                            role={a.tipo === 'erro' ? 'alert' : 'status'}
                            className={cls(
                                'pointer-events-auto relative overflow-hidden rounded-xl border-t-4 bg-white shadow-2xl ring-1 ring-black/5 transition-all duration-200',
                                e.titulo,
                                a.aSair ? 'translate-x-4 opacity-0' : 'animate-scale-in',
                            )}
                        >
                            <div className="flex items-start gap-3 p-4">
                                <i className={cls('fas mt-0.5 text-lg', e.icone, e.cor)} aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    {a.titulo && <p className="text-sm font-bold text-gray-900">{t(a.titulo)}</p>}
                                    <p className="text-sm text-gray-700">{t(a.texto)}</p>
                                </div>
                                <button type="button" onClick={() => tirar(a.id)} className="text-gray-400 transition hover:text-gray-700" aria-label={t('Fechar')}>
                                    <i className="fas fa-xmark" aria-hidden="true" />
                                </button>
                            </div>
                            <div className={cls('absolute bottom-0 left-0 h-1 opacity-30', e.barra)} style={{ animation: `sosAvisoGasta ${a.duracao ?? 3000}ms linear forwards` }} />
                        </div>
                    );
                })}
            </div>

            {sessaoMorta && (
                <div className="animate-fade-in fixed inset-0 z-[100000] flex items-center justify-center bg-gray-900/85 p-5 backdrop-blur-sm" role="alertdialog" aria-modal="true" aria-labelledby="sessao-expirada-titulo">
                    <div className="animate-scale-in w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg">
                            <i className="fas fa-lock text-2xl text-white" aria-hidden="true" />
                        </div>
                        <h2 id="sessao-expirada-titulo" className="mb-2 text-xl font-extrabold text-gray-900">{t('Sessão expirada')}</h2>
                        <p className="mb-6 text-sm leading-relaxed text-gray-600">{t('Por inatividade, a sua sessão terminou. Inicie sessão novamente para continuar.')}</p>
                        <a href={login} className="btn-press inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 font-bold text-white shadow-lg hover:shadow-xl">
                            <i className="fas fa-right-to-bracket" aria-hidden="true" />
                            {t('Iniciar sessão')}
                        </a>
                    </div>
                </div>
            )}
        </>
    );
}

export { avisar };
