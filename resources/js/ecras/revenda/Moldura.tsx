import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '@/i18n';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

import { useFecharFora } from '../casca/comum';

/**
 * A MOLDURA DO PORTAL DO REVENDEDOR — a barra lateral e o cabeçalho (16/09/2026).
 *
 * Pedido: «um painel com sidebar e header, melhor e mais intuitivo». À
 * esquerda, quem está dentro (com o código à mão), o menu por secções com os
 * números que pedem acção e o link para partilhar; em cima, onde se está, a
 * acção principal (nova empresa), os pagamentos por fazer e o menu da conta.
 * No telemóvel a barra abre por cima, e fecha com o Escape ou ao tocar fora.
 */
type Ligacao = { url: string; icone: string; rotulo: string; activo: boolean; contador?: number | null; alerta?: boolean };
type Seccao = { titulo: string; ligacoes: Ligacao[] };

type Props = {
    logo: string | null;
    nomeApp: string;
    titulo: string;
    revendedor: { nome: string; empresa: string | null; codigo: string | null; link: string | null };
    seccoes: Seccao[];
    pagamentos: number;
    urls: { painel: string; nova: string; pagamentos: string; perfil: string; sair: string };
    csrf: string;
};

function iniciais(nome: string): string {
    return nome.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || 'R';
}

function Sair({ url, csrf, className, children }: { url: string; csrf: string; className: string; children: React.ReactNode }) {
    return (
        <form method="POST" action={url}>
            <input type="hidden" name="_token" value={csrf} />
            <button type="submit" className={className}>{children}</button>
        </form>
    );
}

function Copiar({ texto, rotulo }: { texto: string; rotulo: string }) {
    const [feito, porFeito] = useState(false);

    return (
        <button type="button" title={rotulo}
            onClick={() => void navigator.clipboard?.writeText(texto).then(() => { porFeito(true); window.setTimeout(() => porFeito(false), 1600); })}
            className={cls('inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO,
                feito ? 'bg-emerald-500 text-white' : 'bg-white/10 text-white hover:bg-white/20')}>
            <i className={cls('fas', feito ? 'fa-check' : 'fa-copy')} aria-hidden="true" />{feito ? t('Copiado') : rotulo}
        </button>
    );
}

export default function Moldura(p: Props) {
    const [aberta, porAberta] = useState(false);
    const [menu, porMenu] = useState(false);
    const conta = useRef<HTMLDivElement>(null);
    const fecharMenu = useCallback(() => porMenu(false), []);
    useFecharFora(conta, menu, fecharMenu);

    useEffect(() => {
        const tecla = (e: KeyboardEvent) => { if (e.key === 'Escape') porAberta(false); };
        window.addEventListener('keydown', tecla);
        return () => window.removeEventListener('keydown', tecla);
    }, []);

    const r = p.revendedor;
    const mensagem = r.link ? t('Conheça o SOSERP, o sistema de gestão feito para Angola: facturação certificada pela AGT, stock, POS e muito mais. Registe a sua empresa por aqui: :link', { link: r.link }) : '';

    return (
        <>
            {/* A BARRA LATERAL */}
            {aberta && <button type="button" aria-label={t('Fechar o menu')} onClick={() => porAberta(false)} className="animate-fade-in fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden" />}
            <aside aria-label={t('Menu do portal')}
                className={cls('fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-gradient-to-b from-slate-950 via-violet-950 to-slate-900 text-white shadow-2xl transition-transform duration-300 lg:translate-x-0 motion-reduce:transition-none',
                    aberta ? 'translate-x-0' : '-translate-x-full')}>
                <div className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5">
                    <a href={p.urls.painel} className={cls('flex min-w-0 items-center gap-3', RAIO, FOCO)}>
                        {p.logo
                            ? <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white p-1"><img src={p.logo} alt="" className="h-full w-full object-contain" /></span>
                            : <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-emerald-500 shadow-lg"><i className="fas fa-handshake icon-float" aria-hidden="true" /></span>}
                        <span className="min-w-0">
                            <span className="block truncate font-bold">{p.nomeApp}</span>
                            <span className="block text-[10px] font-bold uppercase tracking-widest text-violet-300">{t('Portal do Revendedor')}</span>
                        </span>
                    </a>
                    <button type="button" onClick={() => porAberta(false)} aria-label={t('Fechar o menu')} className={cls('grid h-9 w-9 place-items-center text-white/70 hover:bg-white/10 hover:text-white lg:hidden', RAIO, FOCO)}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto px-4 py-5">
                    {/* Quem está dentro, com o código à mão. */}
                    <div className="mb-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div className="flex items-center gap-3">
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-emerald-500 font-bold shadow ring-2 ring-white/20">{iniciais(r.nome)}</span>
                            <span className="min-w-0">
                                <span className="block truncate font-semibold">{r.nome}</span>
                                {r.empresa && <span className="block truncate text-xs text-white/60">{r.empresa}</span>}
                            </span>
                        </div>
                        {r.codigo && (
                            <div className="mt-3 flex items-center justify-between gap-2 rounded-xl bg-black/20 px-3 py-2">
                                <span className="min-w-0">
                                    <span className="block text-[10px] font-bold uppercase tracking-widest text-white/50">{t('Código')}</span>
                                    <span className="block font-mono text-lg font-black tracking-widest text-emerald-300">{r.codigo}</span>
                                </span>
                                <Copiar texto={r.codigo} rotulo={t('Copiar')} />
                            </div>
                        )}
                    </div>

                    <nav className="space-y-6">
                        {p.seccoes.map((s) => (
                            <div key={s.titulo}>
                                <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-white/40">{s.titulo}</p>
                                <ul className="space-y-1">
                                    {s.ligacoes.map((l) => (
                                        <li key={l.url}>
                                            <a href={l.url} aria-current={l.activo ? 'page' : undefined}
                                                className={cls('group relative flex items-center gap-3 px-3 py-2.5 text-sm font-medium', RAIO, TRANSICAO, FOCO,
                                                    l.activo ? 'bg-white/10 text-white shadow-inner' : 'text-white/70 hover:bg-white/5 hover:text-white')}>
                                                {l.activo && <span aria-hidden="true" className="absolute inset-y-2 left-0 w-1 rounded-r-full bg-gradient-to-b from-violet-400 to-emerald-400" />}
                                                <span className={cls('grid h-8 w-8 shrink-0 place-items-center rounded-lg transition-transform duration-200 group-hover:scale-110',
                                                    l.activo ? 'bg-gradient-to-br from-violet-500 to-emerald-500 text-white shadow' : 'bg-white/5 text-white/70')}>
                                                    <i className={cls('fas', l.icone)} aria-hidden="true" />
                                                </span>
                                                <span className="min-w-0 flex-1 truncate">{l.rotulo}</span>
                                                {l.contador ? (
                                                    <span className={cls('min-w-[1.5rem] rounded-full px-2 py-0.5 text-center text-xs font-bold',
                                                        l.alerta ? 'animate-pulse bg-amber-400 text-amber-950 motion-reduce:animate-none' : 'bg-white/10 text-white/80')}>{l.contador}</span>
                                                ) : null}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </nav>
                </div>

                {/* O link para partilhar, sempre à mão. */}
                {r.link && (
                    <div className="shrink-0 border-t border-white/10 p-4">
                        <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/40"><i className="fas fa-link mr-1" aria-hidden="true" />{t('O seu link')}</p>
                        <p className="truncate rounded-lg bg-black/25 px-2.5 py-1.5 font-mono text-xs text-white/80" title={r.link}>{r.link.replace(/^https?:\/\//, '')}</p>
                        <div className="mt-2 flex gap-2">
                            <Copiar texto={r.link} rotulo={t('Copiar')} />
                            <a href={`https://wa.me/?text=${encodeURIComponent(mensagem)}`} target="_blank" rel="noreferrer"
                                className={cls('inline-flex items-center gap-1.5 bg-[#25D366] px-2.5 py-1.5 text-xs font-semibold text-white hover:brightness-110', RAIO, TRANSICAO, FOCO)}>
                                <i className="fab fa-whatsapp" aria-hidden="true" />WhatsApp
                            </a>
                        </div>
                    </div>
                )}
            </aside>

            {/* O CABEÇALHO */}
            <header className="fixed inset-x-0 top-0 z-30 h-16 border-b border-slate-200/80 bg-white/85 backdrop-blur lg:pl-72">
                <div className="flex h-full items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button type="button" onClick={() => porAberta(true)} aria-label={t('Abrir o menu')} aria-expanded={aberta}
                        className={cls('grid h-10 w-10 place-items-center text-slate-600 hover:bg-slate-100 lg:hidden', RAIO, FOCO)}>
                        <i className="fas fa-bars text-lg" aria-hidden="true" />
                    </button>

                    <nav aria-label={t('Onde está')} className="min-w-0 flex-1">
                        <ol className="flex items-center gap-2 text-sm">
                            <li className="hidden sm:block"><a href={p.urls.painel} className="text-slate-500 hover:text-violet-700">{t('Portal do Revendedor')}</a></li>
                            <li className="hidden text-slate-300 sm:block" aria-hidden="true"><i className="fas fa-chevron-right text-[10px]" /></li>
                            <li className="truncate font-bold text-slate-900" aria-current="page">{p.titulo}</li>
                        </ol>
                    </nav>

                    <a href={p.urls.nova} className={cls('group hidden items-center gap-2 bg-gradient-to-r from-violet-600 to-emerald-600 px-4 py-2 text-sm font-bold text-white shadow hover:-translate-y-0.5 hover:shadow-lg sm:inline-flex', RAIO, TRANSICAO, FOCO)}>
                        <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />{t('Nova empresa')}
                    </a>

                    <a href={p.urls.pagamentos} title={t('Pagamentos por fazer')}
                        className={cls('relative grid h-10 w-10 place-items-center text-slate-600 hover:bg-slate-100 hover:text-violet-700', RAIO, TRANSICAO, FOCO)}>
                        <i className={cls('fas fa-file-invoice-dollar text-lg', p.pagamentos > 0 && 'icon-float')} aria-hidden="true" />
                        {p.pagamentos > 0 && (
                            <span className="absolute -right-0.5 -top-0.5 grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-white ring-2 ring-white">{p.pagamentos}</span>
                        )}
                        <span className="sr-only">{t(':n pagamentos por fazer', { n: p.pagamentos })}</span>
                    </a>

                    <div ref={conta} className="relative">
                        <button type="button" onClick={() => porMenu(!menu)} aria-expanded={menu} aria-label={t('Menu da conta')}
                            className={cls('flex items-center gap-2 p-1 pr-2 hover:bg-slate-100', RAIO, TRANSICAO, FOCO)}>
                            <span className="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-violet-600 to-emerald-600 text-sm font-bold text-white shadow">{iniciais(r.nome)}</span>
                            <i className={cls('fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200', menu && 'rotate-180')} aria-hidden="true" />
                        </button>
                        {menu && (
                            <div className="animate-scale-in absolute right-0 z-40 mt-2 w-60 origin-top-right overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/5">
                                <div className="border-b border-slate-100 px-4 py-3">
                                    <p className="truncate text-sm font-semibold text-slate-900">{r.nome}</p>
                                    {r.codigo && <p className="font-mono text-xs text-violet-700">{r.codigo}</p>}
                                </div>
                                <a href={p.urls.perfil} className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                                    <i className="fas fa-circle-user w-4 text-slate-400" aria-hidden="true" />{t('O meu perfil')}
                                </a>
                                <a href={p.urls.nova} className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 sm:hidden">
                                    <i className="fas fa-plus w-4 text-slate-400" aria-hidden="true" />{t('Nova empresa')}
                                </a>
                                <Sair url={p.urls.sair} csrf={p.csrf} className="flex w-full items-center gap-2 border-t border-slate-100 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50">
                                    <i className="fas fa-right-from-bracket w-4" aria-hidden="true" />{t('Sair')}
                                </Sair>
                            </div>
                        )}
                    </div>
                </div>
            </header>
        </>
    );
}
