import { useEffect, useRef, useState } from 'react';

import { type PropsDaCasca } from '@/api/casca';
import { cls } from '@/ui/tokens';
import { t } from '@/i18n';

import { GrupoDoMenu, GrupoSimples, LigacaoDoMenu, ProcuraNoMenu, ResultadosDaProcura, Titulo } from './MenuDaBarra';

/**
 * A CASCA — a barra lateral com o menu, o suporte e o utilizador.
 *
 * O menu não se decide aqui: vem do `MenuDaCasca`, no servidor, já com as
 * permissões e o "está activo" resolvidos — os mesmos dados que o Blade de
 * desenhava. Este ecrã só sabe a forma: abrir e fechar grupos, encolher
 * a barra, o menu do utilizador.
 *
 * A BARRA DO TOPO é feita de peças soltas (empresa, subscrição, língua,
 * notificações). O botão de encolher é uma delas (`casca/alternar`) e fala com
 * esta barra por um evento na janela.
 *
 * As ligações são ligações: cada uma abre a página, como o menu de sempre.
 */

const CHAVE_DE_ABERTA = 'casca:aberta';
const COOKIE_DE_ABERTA = 'casca_aberta';
const CHAVE_DO_SCROLL = 'sidebar-scroll-position';
const LARGURA_TABLET = 1024;

/**
 * A entrada em cascata só na primeira página do separador: cada ligação abre
 * uma página nova, e a cascata a cada clique cansava.
 */
function primeiraPagina(): boolean {
    try {
        const ja = sessionStorage.getItem('casca:cascata');
        sessionStorage.setItem('casca:cascata', '1');
        return !ja;
    } catch {
        return false;
    }
}

export default function Casca({ menu, logo, nome, csrf }: PropsDaCasca) {
    const [aberta, porAberta] = useState<boolean>(() => {
        if (window.innerWidth < LARGURA_TABLET) return false;
        try {
            return localStorage.getItem(CHAVE_DE_ABERTA) !== '0';
        } catch {
            return true;
        }
    });
    const [movel, porMovel] = useState(window.innerWidth < 768);
    const [menuDoUtilizador, porMenuDoUtilizador] = useState(false);
    const [cascata, porCascata] = useState(primeiraPagina);
    const [procura, porProcura] = useState('');
    const lista = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!cascata) return;
        const relogio = window.setTimeout(() => porCascata(false), 1200);
        return () => window.clearTimeout(relogio);
    }, [cascata]);

    /* O botão do topo mostra a seta certa: o layout ouve este aviso. */
    useEffect(() => {
        window.dispatchEvent(new CustomEvent('casca:estado', { detail: { aberta } }));
    }, [aberta]);

    /*
     * O MENU É COMPRIDO: ABRE-SE COM A PÁGINA ACTIVA À VISTA.
     *
     * Guardava-se a posição em píxeis e repunha-se na página seguinte. Mas cada
     * página abre grupos diferentes (o servidor abre o da página activa), e a
     * lista muda de altura: 2416 px numa, 1228 na outra. A posição guardada
     * era cortada, o corte sobrescrevia a guardada, e quem carregava em
     * «Modelos» no fundo do menu abria a página com «Clientes» fora de vista —
     * ou com o menu no topo. Agora repõe-se a posição só se a página activa
     * continuar à vista; senão centra-se nela.
     */
    useEffect(() => {
        const nav = lista.current;
        if (!nav) return;
        const pagina = window.requestAnimationFrame(() => {
            try {
                const guardado = localStorage.getItem(CHAVE_DO_SCROLL);
                if (guardado !== null) nav.scrollTop = parseInt(guardado, 10);
            } catch {
                /* sem armazenamento, começa-se do topo */
            }
            const activa = nav.querySelector<HTMLElement>('[aria-current="page"]');
            if (activa) {
                const n = nav.getBoundingClientRect();
                const a = activa.getBoundingClientRect();
                if (a.top < n.top || a.bottom > n.bottom) {
                    nav.scrollTop += a.top - n.top - (n.height - a.height) / 2;
                }
            }
        });
        let relogio = 0;
        const aoRolar = () => {
            window.clearTimeout(relogio);
            relogio = window.setTimeout(() => {
                try { localStorage.setItem(CHAVE_DO_SCROLL, String(nav.scrollTop)); } catch { /* sem memória */ }
            }, 100);
        };
        nav.addEventListener('scroll', aoRolar, { passive: true });
        return () => { nav.removeEventListener('scroll', aoRolar); window.clearTimeout(relogio); window.cancelAnimationFrame(pagina); };
    }, []);

    /* O botão de encolher é outra peça, na barra do topo: fala por evento. */
    useEffect(() => {
        const alternar = () => porAberta((a) => !a);
        const redimensionar = () => {
            const m = window.innerWidth < 768;
            porMovel(m);
            if (window.innerWidth < LARGURA_TABLET) porAberta(false);
        };

        window.addEventListener('casca:alternar', alternar);
        window.addEventListener('resize', redimensionar);

        return () => {
            window.removeEventListener('casca:alternar', alternar);
            window.removeEventListener('resize', redimensionar);
        };
    }, []);

    /*
     * A escolha fica no aparelho E num cookie: o servidor lê o cookie e desenha
     * o lugar da barra já com a largura certa, sem script nenhum no <head> a
     * adivinhar antes do React chegar. O `data-casca` é para as outras peças
     * (o botão do topo) saberem o estado mesmo que montem depois desta.
     */
    useEffect(() => {
        document.documentElement.dataset.casca = aberta ? 'aberta' : 'fechada';
        if (movel) return;
        try {
            localStorage.setItem(CHAVE_DE_ABERTA, aberta ? '1' : '0');
        } catch {
            /* sem armazenamento, sem memória — a barra abre-se de novo */
        }
        document.cookie = `${COOKIE_DE_ABERTA}=${aberta ? '1' : '0'}; path=/; max-age=31536000; SameSite=Lax`;
    }, [aberta, movel]);

    const fechar = () => { if (movel) porAberta(false); };

    return (
        <>
            {/* Véu no telemóvel. */}
            {aberta && movel && <div onClick={() => porAberta(false)} className="animate-fade-in fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden" aria-hidden="true" />}

            <aside
                id="app-sidebar"
                data-aberta={aberta ? '1' : '0'}
                className={cls(
                    'flex flex-col overflow-hidden bg-gradient-to-b from-blue-900 to-blue-800 text-white shadow-2xl transition-[width,transform] duration-300',
                    cascata && 'first-load',
                    movel ? 'fixed inset-y-0 left-0 z-50' : '',
                    aberta ? 'w-64 translate-x-0' : movel ? 'w-0 -translate-x-full' : 'w-20',
                )}
            >
                {/* Logótipo */}
                <div className="logo-container flex items-center justify-between border-b border-blue-700 p-4">
                    <div className={cls('flex items-center justify-center', aberta && 'w-full')}>
                        {logo ? (
                            <img src={logo} alt={nome} style={{ maxHeight: '4rem', maxWidth: 200 }} className={cls('w-auto object-contain transition-[height,width] duration-300', aberta ? 'h-16' : 'h-12 w-12')} />
                        ) : (
                            <div className={cls('flex items-center justify-center rounded-lg bg-gradient-to-br from-yellow-400 to-orange-500 shadow-lg', aberta ? 'h-12 w-12' : 'h-10 w-10')}>
                                <i className={cls('fas fa-crown text-white', aberta ? 'text-2xl' : 'text-xl')} aria-hidden="true" />
                            </div>
                        )}
                    </div>
                    {movel && (
                        <button type="button" onClick={() => porAberta(false)} aria-label={t('Fechar o menu')} className="ml-2 p-1 text-blue-300 transition hover:text-white">
                            <i className="fas fa-times text-lg" aria-hidden="true" />
                        </button>
                    )}
                </div>

                {/* A procura no menu: são mais de cem ligações num tenant com tudo. */}
                {aberta && <ProcuraNoMenu termo={procura} porTermo={porProcura} />}

                {/* O menu */}
                <nav
                    ref={lista}
                    id="sidebar-menu"
                    aria-label={t('Menu principal')}
                    className="flex-1 overflow-y-auto overscroll-contain px-2 pb-4 pt-2 [scrollbar-color:rgba(255,255,255,.18)_transparent] [scrollbar-width:thin]"
                >
                    {procura.trim() !== '' ? (
                        <ResultadosDaProcura menu={menu} termo={procura} aoClicar={fechar} />
                    ) : (
                        <>
                            <Titulo aberta={aberta} primeiro>{t('Menu Principal')}</Titulo>

                            {menu.principal.map((l) => <LigacaoDoMenu key={l.url} l={l} nivel="topo" aberta={aberta} aoClicar={fechar} />)}

                            {menu.grupos.map((g) => (
                                g.simples
                                    ? <GrupoSimples key={g.chave} g={g} aberta={aberta} aoClicar={fechar} />
                                    : <GrupoDoMenu key={g.chave} g={g} aberta={aberta} aoClicar={fechar} />
                            ))}

                            {menu.superadmin.map((s) => (
                                <div key={s.titulo}>
                                    <Titulo aberta={aberta}>{s.titulo}</Titulo>
                                    {s.entradas.map((l) => <LigacaoDoMenu key={l.url} l={l} nivel="topo" aberta={aberta} aoClicar={fechar} />)}
                                </div>
                            ))}
                        </>
                    )}
                </nav>

                {/* FOX Friendly */}
                {menu.fox && (
                    <div className="border-t border-blue-700 px-4 py-3">
                        <div className="group relative flex cursor-help items-center justify-center" title={t('🦊 FOX Friendly Active! 3 meses grátis • Todos os módulos')}>
                            <span className="text-3xl transition-transform duration-300 group-hover:scale-125" style={{ animation: 'foxFloat 3s ease-in-out infinite' }}>🦊</span>
                        </div>
                    </div>
                )}

                {/* Suporte — o painel da plataforma não o tem: é ela que o dá. */}
                {menu.suporte ? (
                    <div className="mt-auto border-t border-white/10 px-2 py-2">
                        <a href={menu.suporte.url} onClick={fechar} title={menu.suporte.rotulo} className={cls('group flex items-center rounded-xl px-2 py-2 transition-all duration-200', !aberta && 'justify-center', menu.suporte.activo ? 'bg-white/15 ring-1 ring-white/15' : 'hover:bg-white/10')}>
                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-purple-500/20 transition-transform duration-200 group-hover:rotate-45">
                                <i className="fas fa-life-ring text-lg text-purple-300" aria-hidden="true" />
                            </span>
                            {aberta && <span className="ml-3 font-semibold text-white">{menu.suporte.rotulo}</span>}
                            {aberta && <span className="ml-auto rounded-full bg-purple-500 px-2 py-0.5 text-[10px] font-bold shadow-sm">{menu.suporte.extra}</span>}
                        </a>
                    </div>
                ) : <div className="mt-auto" />}

                {/* O utilizador */}
                <div className="relative border-t border-white/10 p-2">
                    <button type="button" onClick={() => porMenuDoUtilizador((v) => !v)} aria-expanded={menuDoUtilizador} title={menu.utilizador.nome} className={cls('flex w-full items-center rounded-xl p-2 text-left transition hover:bg-white/10', !aberta && 'justify-center')}>
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-indigo-600 shadow-lg ring-2 ring-white/20">
                            <i className="fas fa-user text-white" aria-hidden="true" />
                        </div>
                        {aberta && (
                            <div className="ml-3 min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">{menu.utilizador.nome}</p>
                                <p className="text-xs text-blue-300">{menu.utilizador.papel}</p>
                            </div>
                        )}
                        {aberta && <i className={cls('fas fa-chevron-up text-sm transition', !menuDoUtilizador && 'rotate-180')} aria-hidden="true" />}
                    </button>

                    {menuDoUtilizador && (
                        <div className="animate-scale-in absolute bottom-full left-4 right-4 mb-2 rounded-lg bg-white py-2 shadow-xl" onMouseLeave={() => porMenuDoUtilizador(false)}>
                            {menu.utilizador.ligacoes.map((l) => (
                                <a key={l.url} href={l.url} className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i className={cls('fas mr-2', l.icone, 'text-' + l.cor)} aria-hidden="true" /> {l.rotulo}
                                </a>
                            ))}
                            <div className="my-1 border-t border-gray-200" />
                            <a href={menu.utilizador.atualizacoes.url} className="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <span><i className="fas fa-rocket mr-2 text-indigo-600" aria-hidden="true" /> {menu.utilizador.atualizacoes.rotulo}</span>
                                <span className="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">{menu.utilizador.atualizacoes.versao}</span>
                            </a>
                            <div className="my-1 border-t border-gray-200" />

                            <form method="POST" action={menu.utilizador.sair}>
                                <input type="hidden" name="_token" value={csrf} />
                                <button type="submit" className="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-100">
                                    <i className="fas fa-sign-out-alt mr-2" aria-hidden="true" /> {t('Sair')}
                                </button>
                            </form>
                        </div>
                    )}
                </div>
            </aside>
        </>
    );
}

