import { useEffect, useState, type ReactNode } from 'react';

import { eLigacao, eSeparador, eSub, eTitulo, type Entrada, type Grupo, type Ligacao, type PropsDaCasca } from '@/api/casca';
import { cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A CASCA — a barra lateral com o menu, o suporte e o utilizador.
 *
 * O menu não se decide aqui: vem do `MenuDaCasca`, no servidor, já com as
 * permissões e o "está activo" resolvidos — os mesmos dados que o Blade de
 * sempre desenha. Este ecrã só sabe a forma: abrir e fechar grupos, encolher
 * a barra, o menu do utilizador.
 *
 * A BARRA DO TOPO fica no Blade: é lá que vivem o selector de empresa, o
 * contador da subscrição e as notificações (peças em React). O botão de
 * encolher, que está lá, fala com esta barra por um evento na janela.
 *
 * As ligações são ligações: cada uma abre a página, como o menu de sempre.
 */

const CHAVE_DE_ABERTA = 'casca:aberta';
const LARGURA_TABLET = 1024;

export default function Casca({ menu, logo, nome, csrf, voltar }: PropsDaCasca) {
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

    /* O botão de encolher está na barra do topo, em Blade: fala por evento. */
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

    useEffect(() => {
        if (movel) return;
        try {
            localStorage.setItem(CHAVE_DE_ABERTA, aberta ? '1' : '0');
        } catch {
            /* sem armazenamento, sem memória — a barra abre-se de novo */
        }
    }, [aberta, movel]);

    const fechar = () => { if (movel) porAberta(false); };

    return (
        <>
            {/* Véu no telemóvel. */}
            {aberta && movel && <div onClick={() => porAberta(false)} className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden" aria-hidden="true" />}

            <aside
                id="app-sidebar"
                data-aberta={aberta ? '1' : '0'}
                className={cls(
                    'flex flex-col overflow-hidden bg-gradient-to-b from-blue-900 to-blue-800 text-white shadow-2xl transition-[width,transform] duration-300',
                    movel ? 'fixed inset-y-0 left-0 z-50' : '',
                    aberta ? 'w-64 translate-x-0' : movel ? 'w-0 -translate-x-full' : 'w-20',
                )}
            >
                {/* Logótipo */}
                <div className="flex items-center justify-between border-b border-blue-700 p-4">
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

                {/* O menu */}
                <nav id="sidebar-menu" aria-label={t('Menu principal')} className="flex-1 overflow-y-auto py-4">
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
                </nav>

                {/* FOX Friendly */}
                {menu.fox && (
                    <div className="border-t border-blue-700 px-4 py-3">
                        <div className="group relative flex cursor-help items-center justify-center" title={t('🦊 FOX Friendly Active! 3 meses grátis • Todos os módulos')}>
                            <span className="text-3xl transition-transform duration-300 group-hover:scale-125" style={{ animation: 'foxFloat 3s ease-in-out infinite' }}>🦊</span>
                        </div>
                    </div>
                )}

                {/* Suporte */}
                <div className="mt-auto border-t border-blue-700 pt-4">
                    <a href={menu.suporte.url} onClick={fechar} className={cls('group flex items-center px-4 py-3 transition', menu.suporte.activo ? 'border-l-4 border-purple-400 bg-blue-700' : 'hover:bg-blue-700/50')}>
                        <i className="fas fa-life-ring text-2xl text-purple-400" aria-hidden="true" />
                        {aberta && <span className="ml-3 font-semibold text-white">{menu.suporte.rotulo}</span>}
                        {aberta && <span className="ml-auto rounded-full bg-purple-500 px-2 py-1 text-xs">{menu.suporte.extra}</span>}
                    </a>
                </div>

                {/* O utilizador */}
                <div className="relative border-t border-blue-700 p-4">
                    <button type="button" onClick={() => porMenuDoUtilizador((v) => !v)} aria-expanded={menuDoUtilizador} className="flex w-full items-center rounded-lg p-2 text-left transition hover:bg-blue-700/50">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-blue-600 shadow-lg">
                            <i className="fas fa-user text-white" aria-hidden="true" />
                        </div>
                        {aberta && (
                            <div className="ml-3 flex-1">
                                <p className="text-sm font-medium">{menu.utilizador.nome}</p>
                                <p className="text-xs text-blue-300">{menu.utilizador.papel}</p>
                            </div>
                        )}
                        {aberta && <i className={cls('fas fa-chevron-up text-sm transition', !menuDoUtilizador && 'rotate-180')} aria-hidden="true" />}
                    </button>

                    {menuDoUtilizador && (
                        <div className="absolute bottom-full left-4 right-4 mb-2 rounded-lg bg-white py-2 shadow-xl" onMouseLeave={() => porMenuDoUtilizador(false)}>
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
                            {/* Voltar à casca de sempre, enquanto as duas convivem. */}
                            <a href={voltar} className="block px-4 py-2 text-xs text-gray-500 hover:bg-gray-100">
                                <i className="fas fa-rotate-left mr-2" aria-hidden="true" /> {t('Ecrã de sempre')}
                            </a>
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

/* ─── As peças ──────────────────────────────────────────────────────────── */

function Titulo({ children, aberta, primeiro = false }: { children: ReactNode; aberta: boolean; primeiro?: boolean }) {
    return (
        <div className={cls('px-3 mb-2', !primeiro && 'mt-6')}>
            {aberta && <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-blue-300">{children}</p>}
        </div>
    );
}

type Nivel = 'topo' | 'sub' | 'subsub' | 'relatorio';

const FORMA: Record<Nivel, [string, string, string]> = {
    topo: ['px-4 py-3', 'w-6', 'ml-3'],
    sub: ['pl-8 pr-4 py-2.5', 'w-5 text-sm', 'ml-3 text-sm'],
    subsub: ['pl-4 pr-4 py-2.5', 'w-5 text-sm', 'ml-3 text-xs'],
    relatorio: ['pl-6 pr-4 py-2', 'w-5 text-xs', 'ml-3 text-xs'],
};

function LigacaoDoMenu({ l, nivel, aberta, aoClicar }: { l: Ligacao; nivel: Nivel; aberta: boolean; aoClicar: () => void }) {
    const [caixa, icone, rotulo] = FORMA[nivel];

    return (
        <a
            href={l.url}
            onClick={aoClicar}
            aria-current={l.activo ? 'page' : undefined}
            className={cls('flex items-center transition', caixa, l.activo ? `bg-blue-700 border-l-4 border-${l.barra}` : l.hover)}
        >
            <i className={cls(l.marca ? 'fab' : 'fas', l.icone, icone, 'text-' + l.cor)} aria-hidden="true" />
            {aberta && <span className={cls(rotulo, l.forte && 'font-semibold')}>{l.prefixo ? l.prefixo + ' ' : ''}{l.rotulo}</span>}
        </a>
    );
}

function Entradas({ entradas, nivel, aberta, aoClicar }: { entradas: Entrada[]; nivel: Nivel; aberta: boolean; aoClicar: () => void }) {
    return (
        <>
            {entradas.map((e, i) => {
                if (eSeparador(e)) return <div key={i} className="my-2 border-t border-blue-700/50" />;
                if (eTitulo(e)) return aberta ? <div key={i} className="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-blue-300/70">{e.titulo}</div> : null;
                if (eSub(e)) return <SubGrupo key={e.sub.chave} g={e.sub} aberta={aberta} aoClicar={aoClicar} />;
                if (eLigacao(e)) return <LigacaoDoMenu key={e.url} l={e} nivel={e.relatorio ? 'relatorio' : nivel} aberta={aberta} aoClicar={aoClicar} />;
                return null;
            })}
        </>
    );
}

function GrupoDoMenu({ g, aberta, aoClicar }: { g: Grupo; aberta: boolean; aoClicar: () => void }) {
    const [aberto, porAberto] = useState(g.aberto);

    return (
        <div className="mt-6">
            <button type="button" onClick={() => porAberto((v) => !v)} aria-expanded={aberto} className="group flex w-full items-center justify-between px-4 py-3 transition hover:bg-blue-700/50">
                <div className="flex items-center">
                    <i className={cls('fas', g.icone, 'w-6', 'text-' + g.cor)} aria-hidden="true" />
                    {aberta && <span className={cls('ml-3', !g.leve && 'font-semibold text-white')}>{g.rotulo}</span>}
                </div>
                {aberta && <i className={cls('fas text-xs text-blue-300 transition-transform duration-200', aberto ? 'fa-chevron-down' : 'fa-chevron-right')} aria-hidden="true" />}
            </button>
            {aberto && (
                <div className="bg-blue-900/30">
                    <Entradas entradas={g.entradas} nivel="sub" aberta={aberta} aoClicar={aoClicar} />
                </div>
            )}
        </div>
    );
}

function SubGrupo({ g, aberta, aoClicar }: { g: Grupo; aberta: boolean; aoClicar: () => void }) {
    const [aberto, porAberto] = useState(g.aberto);

    return (
        <div className="ml-8 border-l-2 border-blue-700/30">
            <button type="button" onClick={() => porAberto((v) => !v)} aria-expanded={aberto} className="group flex w-full items-center justify-between py-2.5 pr-4 transition hover:bg-blue-700/30">
                <div className="flex items-center">
                    <i className={cls('fas', g.icone, 'w-5 text-sm', 'text-' + g.cor)} aria-hidden="true" />
                    {aberta && <span className="ml-3 text-sm font-semibold">{g.prefixo ? g.prefixo + ' ' : ''}{g.rotulo}</span>}
                </div>
                {aberta && <i className={cls('fas text-xs text-blue-300 transition-transform duration-200', aberto ? 'fa-chevron-down' : 'fa-chevron-right')} aria-hidden="true" />}
            </button>
            {aberto && (
                <div className="bg-blue-900/20">
                    <Entradas entradas={g.entradas} nivel="subsub" aberta={aberta} aoClicar={aoClicar} />
                </div>
            )}
        </div>
    );
}

/** Uma ligação de topo com as suas dependentes, sem abrir e fechar (Notificações). */
function GrupoSimples({ g, aberta, aoClicar }: { g: Grupo; aberta: boolean; aoClicar: () => void }) {
    return (
        <div className="mt-6">
            <a href={g.url ?? '#'} onClick={aoClicar} className={cls('flex items-center px-4 py-3 transition', g.activo ? 'border-l-4 border-yellow-400 bg-blue-700' : 'hover:bg-blue-700/50')}>
                <i className={cls('fas', g.icone, 'w-5 text-xl', 'text-' + g.cor)} aria-hidden="true" />
                {aberta && <span className="ml-3 font-semibold text-white">{g.rotulo}</span>}
            </a>
            <Entradas entradas={g.entradas} nivel="sub" aberta={aberta} aoClicar={aoClicar} />
        </div>
    );
}
