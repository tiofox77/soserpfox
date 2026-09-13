import { useMutation } from '@tanstack/react-query';
import { useEffect, useMemo, useState, type CSSProperties } from 'react';

import { ErroDaApi, criarApi } from '@/api/cliente';
import { t } from '@/i18n';
import { cls } from '@/ui/tokens';

import { kwanzasDaCarta, mensagemDoWhatsapp } from './mensagemDoWhatsapp';

type Prato = {
    id: number;
    nome: string;
    descricao: string | null;
    preco: number | null;
    imagem: string | null;
    categoria_id: number | null;
    categoria: string | null;
};

type Props = {
    slug: string;
    casa: {
        titulo: string | null;
        descricao: string | null;
        logo: string | null;
        logo_soserp: string;
        capa: string | null;
        cor: string;
        acento: string;
        escuro: boolean;
        mostra_precos: boolean;
        aceita_pedidos: boolean;
        whatsapp: string | null;
        destaques_titulo: string | null;
    };
    mesa: { codigo: string; rotulo: string; reconhecida: boolean } | null;
    categorias: { id: number; nome: string }[];
    pratos: Prato[];
    destaques: Prato[];
};

const publico = criarApi('/api/publico/restaurante');

/**
 * A CARTA DO RESTAURANTE, vista pelo cliente no telemóvel.
 *
 * A carta inteira chega no HTML: procurar e mudar de categoria é instantâneo,
 * mesmo com a rede fraca de uma esplanada — no Livewire cada letra escrita na
 * pesquisa era uma ida ao servidor. As escolhas guardam-se no telemóvel, e um
 * toque sem querer no «recarregar» já não esvazia o pedido.
 *
 * A COR É A DA CASA, e o tema (claro ou escuro) vale agora para a carta toda, e
 * não só para os destaques.
 */
export default function CartaOnline({ slug, casa, mesa, categorias, pratos, destaques }: Props) {
    const chave = `carta:${slug}:${mesa?.codigo ?? ''}`;
    const [pesquisa, porPesquisa] = useState('');
    const [categoria, porCategoria] = useState<number | null>(null);
    const [escolhas, porEscolhas] = useState<Record<number, number>>(() => ler(chave));
    const [nome, porNome] = useState('');
    const [telefone, porTelefone] = useState('');
    const [observacoes, porObservacoes] = useState('');
    const [enviado, porEnviado] = useState(false);

    useEffect(() => { guardar(chave, escolhas); }, [chave, escolhas]);

    const cor = casa.cor;
    const acento = casa.acento;
    const escuro = casa.escuro;
    const logoDaCasa = casa.logo || casa.logo_soserp;
    const podePedir = casa.whatsapp !== null || casa.aceita_pedidos;

    const porId = useMemo(() => new Map([...pratos, ...destaques].map((p) => [p.id, p])), [pratos, destaques]);

    const visiveis = useMemo(() => {
        const termo = pesquisa.trim().toLocaleLowerCase();

        return pratos.filter((p) =>
            (categoria === null || p.categoria_id === categoria)
            && (termo === '' || p.nome.toLocaleLowerCase().includes(termo) || (p.descricao ?? '').toLocaleLowerCase().includes(termo)));
    }, [pratos, pesquisa, categoria]);

    const linhas = Object.entries(escolhas)
        .map(([id, quantidade]) => ({ prato: porId.get(Number(id)), quantidade }))
        .filter((l): l is { prato: Prato; quantidade: number } => !!l.prato && l.quantidade > 0);
    const artigos = linhas.reduce((s, l) => s + l.quantidade, 0);
    const total = linhas.reduce((s, l) => s + (l.prato.preco ?? 0) * l.quantidade, 0);

    const escolher = (id: number) => porEscolhas((e) => ({ ...e, [id]: (e[id] ?? 0) + 1 }));
    const retirar = (id: number) => porEscolhas((e) => {
        const resta = (e[id] ?? 0) - 1;
        const novo = { ...e, [id]: resta };
        if (resta <= 0) delete novo[id];
        return novo;
    });

    const enviar = useMutation({
        mutationFn: () => publico.criar<{ message: string }>(`/${encodeURIComponent(slug)}/pedido`, {
            mesa: mesa?.codigo ?? null,
            escolhas: linhas.map((l) => ({ id: l.prato.id, quantidade: l.quantidade })),
            nome, telefone, observacoes,
        }),
        onSuccess: () => { porEscolhas({}); porObservacoes(''); porEnviado(true); },
    });
    const erroDoPedido = enviar.error instanceof ErroDaApi
        ? (enviar.error.erros.pedido?.[0] ?? Object.values(enviar.error.erros)[0]?.[0] ?? enviar.error.message)
        : enviar.error?.message;

    const linkDoWhatsapp = casa.whatsapp && linhas.length
        ? mensagemDoWhatsapp({
            numero: casa.whatsapp,
            casa: casa.titulo,
            mesa: mesa?.rotulo ?? null,
            linhas: linhas.map((l) => ({ nome: l.prato.nome, quantidade: l.quantidade, preco: l.prato.preco })),
            comPrecos: casa.mostra_precos,
        })
        : null;

    const fundo = escuro ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900';
    const cartao = escuro ? 'bg-slate-900 ring-white/10 border-slate-800' : 'bg-white ring-slate-200 border-slate-200';
    const textoForte = escuro ? 'text-slate-100' : 'text-slate-900';
    const textoSuave = escuro ? 'text-slate-400' : 'text-slate-500';
    const vazioImagem = escuro ? 'from-slate-800 to-slate-900' : 'from-slate-50 to-slate-100';

    return (
        <div className={cls('min-h-screen', fundo)}
            style={{ backgroundImage: `radial-gradient(circle at 8% 2%, ${cor}12 0, transparent 24rem), radial-gradient(circle at 95% 22%, ${acento}0d 0, transparent 22rem)` } as CSSProperties}>

            <header className="relative overflow-hidden text-white" style={{ background: `linear-gradient(135deg, #0f172a 0%, #172554 54%, ${cor} 145%)` }}>
                {/* A capa fica POR BAIXO de um véu escuro: a fotografia dá o ambiente, o texto continua a ler-se. */}
                {casa.capa && (
                    <>
                        <div className="absolute inset-0 scale-105 bg-cover bg-center animate-fade-in" style={{ backgroundImage: `url('${casa.capa}')` }} />
                        <div className="absolute inset-0" style={{ background: `linear-gradient(135deg, rgba(15,23,42,.88) 0%, rgba(23,37,84,.78) 54%, ${cor}cc 145%)` }} />
                    </>
                )}
                <div className="icon-float absolute -right-16 -top-20 h-64 w-64 rounded-full opacity-25" style={{ background: cor }} />
                <div className="absolute -bottom-24 -left-16 h-52 w-52 rounded-full border border-white/10" />
                <div className={cls('relative mx-auto max-w-5xl px-4 sm:px-6', casa.capa ? 'py-10 sm:py-16' : 'py-6 sm:py-9')}>
                    <div className="entra flex items-start gap-4 sm:items-center sm:gap-6">
                        <div className="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-3xl bg-white p-2 shadow-2xl ring-1 ring-white/30 sm:h-24 sm:w-24">
                            <img src={logoDaCasa} alt={casa.titulo || 'SOS ERP'} className="h-full w-full object-contain"
                                onError={(e) => { if (e.currentTarget.src !== casa.logo_soserp) e.currentTarget.src = casa.logo_soserp; }} />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-white/90 backdrop-blur">
                                <i className="fas fa-utensils" aria-hidden="true" /> {t('Menu digital')}
                            </div>
                            <h1 className="text-2xl font-black leading-tight sm:text-4xl">{casa.titulo || t('A nossa carta')}</h1>
                            {casa.descricao && <p className="mt-2 max-w-2xl text-sm leading-relaxed text-white/75 sm:text-base">{casa.descricao}</p>}
                        </div>
                    </div>

                    {/* A MESA do QR: mostrar que foi reconhecida poupa ao cliente a dúvida de a ter de dizer. */}
                    {mesa?.reconhecida && (
                        <div className="entra mt-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-semibold backdrop-blur" style={{ ['--i' as string]: 2 }}>
                            <i className="fas fa-location-dot animate-pulse" aria-hidden="true" />{mesa.rotulo}
                        </div>
                    )}
                    {/* Um QR antigo, de uma mesa removida: dizer, em vez de fingir que se sabe onde a pessoa está. */}
                    {mesa && !mesa.reconhecida && (
                        <div className="entra mt-5 inline-flex items-center gap-2 rounded-full bg-black/25 px-4 py-2 text-sm" style={{ ['--i' as string]: 2 }}>
                            <i className="fas fa-circle-question" aria-hidden="true" />{t('Mesa não reconhecida — diga-a ao fazer o pedido')}
                        </div>
                    )}

                    <div className="mt-5 flex items-center gap-2 text-[11px] text-white/60">
                        <span>{t('Menu disponibilizado por')}</span>
                        <span className="inline-flex items-center rounded-lg bg-white px-2 py-1"><img src={casa.logo_soserp} alt="SOS ERP" className="h-5 w-auto object-contain" /></span>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-4 py-5 pb-48 sm:px-6 sm:py-7">
                <label className={cls('mb-4 flex h-14 items-center gap-3 rounded-2xl border px-4 shadow-sm transition focus-within:border-transparent focus-within:ring-2', cartao)}
                    style={{ ['--tw-ring-color' as string]: `${cor}55` }}>
                    <i className={cls('fas fa-magnifying-glass shrink-0', textoSuave)} aria-hidden="true" />
                    <input type="search" value={pesquisa} onChange={(e) => porPesquisa(e.target.value)}
                        placeholder={t('Procurar na carta…')} aria-label={t('Procurar prato')}
                        className={cls('min-w-0 flex-1 border-0 bg-transparent py-0 text-sm font-medium placeholder:text-slate-400 focus:outline-none focus:ring-0', textoForte)} />
                    {pesquisa && (
                        <button type="button" onClick={() => porPesquisa('')} aria-label={t('Limpar')} className={cls('animate-scale-in', textoSuave)}>
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    )}
                </label>

                {categorias.length > 0 && (
                    <nav className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-2" aria-label={t('Categorias')}>
                        {[{ id: null as number | null, nome: t('Tudo') }, ...categorias].map((c) => {
                            const activa = categoria === c.id;
                            return (
                                <button key={c.id ?? 'tudo'} type="button" onClick={() => porCategoria(c.id)} aria-pressed={activa}
                                    className={cls('h-10 shrink-0 whitespace-nowrap rounded-full border px-4 text-xs font-bold shadow-sm transition-all duration-200 active:scale-95',
                                        activa ? 'border-transparent text-white shadow-md' : escuro ? 'border-slate-700 bg-slate-900 text-slate-300 hover:border-slate-500' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300')}
                                    style={activa ? { background: cor } : undefined}>
                                    {c.nome}
                                </button>
                            );
                        })}
                    </nav>
                )}

                {/* OS DESTAQUES: só na vista sem filtros — a meio de uma pesquisa são ruído. */}
                {destaques.length > 0 && categoria === null && pesquisa.trim() === '' && (
                    <section className="mb-2 mt-6">
                        <h2 className="mb-2 flex items-center gap-2 text-sm font-black uppercase tracking-widest" style={{ color: acento }}>
                            <i className="fas fa-star icon-float text-sm" aria-hidden="true" />{casa.destaques_titulo || t('Sugestões da casa')}
                        </h2>
                        <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {destaques.map((p, i) => (
                                <div key={p.id} className={cls('entra card-hover flex gap-3 overflow-hidden rounded-2xl p-3 shadow-sm ring-1', cartao)}
                                    style={{ borderLeft: `4px solid ${acento}`, ['--i' as string]: i } as CSSProperties}>
                                    {p.imagem && <img src={p.imagem} alt={p.nome} loading="lazy" className="h-16 w-16 shrink-0 rounded-xl object-cover" />}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-bold">{p.nome}</p>
                                        {p.descricao && <p className={cls('mt-0.5 line-clamp-2 text-xs', textoSuave)}>{p.descricao}</p>}
                                        <div className="mt-1.5 flex items-center justify-between gap-2">
                                            {p.preco !== null ? <span className="text-sm font-black" style={{ color: acento }}>{kwanzasDaCarta(p.preco)}</span> : <span />}
                                            {podePedir && (
                                                <button type="button" onClick={() => escolher(p.id)}
                                                    className="rounded-full px-3 py-1 text-xs font-bold text-white transition hover:opacity-90 active:scale-95" style={{ background: acento }}>
                                                    <i className="fas fa-plus mr-1" aria-hidden="true" />{t('Juntar')}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {visiveis.length === 0 ? (
                    <div className={cls('animate-fade-in my-8 rounded-3xl border border-dashed px-6 py-16 text-center', escuro ? 'border-slate-700 bg-slate-900/60' : 'border-slate-300 bg-white/70')}>
                        <div className={cls('mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl', escuro ? 'bg-slate-800' : 'bg-slate-100')}>
                            <i className="fas fa-utensils icon-float text-2xl text-slate-400" aria-hidden="true" />
                        </div>
                        <p className={cls('font-bold', escuro ? 'text-slate-200' : 'text-slate-700')}>{t('Nenhum prato encontrado')}</p>
                        <p className={cls('mt-1 text-sm', textoSuave)}>{t('Experimente outra categoria ou pesquisa.')}</p>
                    </div>
                ) : (
                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        {visiveis.map((p, i) => {
                            const quantidade = escolhas[p.id] ?? 0;
                            return (
                                <article key={p.id} style={{ ['--i' as string]: Math.min(i, 12), ['--tw-ring-color' as string]: cor } as CSSProperties}
                                    className={cls('entra group overflow-hidden rounded-3xl border shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg', cartao, quantidade > 0 && 'ring-2')}
                                >
                                    <div className={cls('relative h-44 overflow-hidden sm:h-48', p.imagem ? 'bg-slate-100' : `bg-gradient-to-br ${vazioImagem}`)}>
                                        <img src={p.imagem || casa.logo_soserp} alt={p.nome} loading="lazy"
                                            className={cls('h-full w-full transition duration-500 group-hover:scale-[1.03]', p.imagem ? 'object-cover' : 'object-contain p-10 opacity-75')}
                                            onError={(e) => { const img = e.currentTarget; if (img.src !== casa.logo_soserp) { img.src = casa.logo_soserp; img.className = 'h-full w-full object-contain p-10 opacity-75'; } }} />
                                        {p.categoria && (
                                            <span className="absolute left-3 top-3 max-w-[80%] truncate rounded-full bg-slate-950/70 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow backdrop-blur">{p.categoria}</span>
                                        )}
                                        {quantidade > 0 && (
                                            <span key={quantidade} className="animate-scale-in absolute right-3 top-3 grid h-9 min-w-9 place-items-center rounded-full px-2 text-sm font-black text-white shadow-lg" style={{ background: cor }}>
                                                {quantidade}
                                            </span>
                                        )}
                                    </div>

                                    <div className="p-4 sm:p-5">
                                        <p className={cls('text-base font-extrabold leading-snug', textoForte)}>{p.nome}</p>
                                        {p.descricao && <p className={cls('mt-1.5 line-clamp-2 min-h-9 text-xs leading-relaxed', textoSuave)}>{p.descricao}</p>}

                                        <div className={cls('mt-4 flex items-center justify-between gap-3 border-t pt-4', escuro ? 'border-slate-800' : 'border-slate-100')}>
                                            {p.preco !== null ? (
                                                <p className="text-lg font-black tabular-nums" style={{ color: cor }}>{kwanzasDaCarta(p.preco)} <span className="text-xs">Kz</span></p>
                                            ) : <span />}

                                            {podePedir && (
                                                <div className="flex shrink-0 items-center gap-2">
                                                    {quantidade > 0 && (
                                                        <>
                                                            <button type="button" onClick={() => retirar(p.id)} aria-label={t('Retirar uma unidade de :prato', { prato: p.nome })}
                                                                className={cls('animate-scale-in grid h-10 w-10 place-items-center rounded-xl border text-lg font-bold transition active:scale-90', escuro ? 'border-slate-700 bg-slate-800 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100')}>
                                                                &minus;
                                                            </button>
                                                            <span className={cls('w-6 text-center font-black tabular-nums', textoForte)}>{quantidade}</span>
                                                        </>
                                                    )}
                                                    <button type="button" onClick={() => escolher(p.id)} aria-label={t('Adicionar :prato', { prato: p.nome })}
                                                        className="grid h-10 w-10 place-items-center rounded-xl text-lg font-bold text-white shadow-md transition hover:brightness-95 active:scale-90" style={{ background: cor }}>
                                                        +
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}

                <div className="mt-10 flex items-center justify-center gap-2 text-xs text-slate-400">
                    <span>{t('Menu digital seguro por')}</span>
                    <img src={casa.logo_soserp} alt="SOS ERP" className="h-7 w-auto object-contain opacity-70" />
                </div>
            </main>

            {/* PEDIDO ENVIADO: diz o que vai acontecer a seguir — quem está sentado quer saber se alguém vai aparecer. */}
            {enviado && (
                <div className="animate-fade-in fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4" onClick={() => porEnviado(false)}>
                    <div role="dialog" aria-modal="true" aria-labelledby="pedido-enviado" className="animate-scale-in w-full max-w-sm rounded-3xl bg-white p-7 text-center shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full" style={{ background: `${cor}22` }}>
                            <i className="fas fa-check animate-scale-in text-2xl" style={{ color: cor }} aria-hidden="true" />
                        </div>
                        <h2 id="pedido-enviado" className="text-lg font-bold text-slate-800">{t('Pedido enviado')}</h2>
                        <p className="mt-2 text-sm text-slate-500">{t('Um empregado vai confirmar o pedido na sua mesa. Se precisar de alterar alguma coisa, diga-lhe.')}</p>
                        <button type="button" onClick={() => porEnviado(false)} autoFocus className="mt-5 w-full rounded-2xl py-3 font-bold text-white transition active:scale-[.98]" style={{ background: cor }}>
                            {t('Continuar a ver a carta')}
                        </button>
                    </div>
                </div>
            )}

            {linhas.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white text-slate-900 shadow-2xl" style={{ animation: 'entradaSuave .25s ease-out both' }}>
                    <div className="mx-auto max-w-5xl px-4 py-3 sm:px-6">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm" aria-live="polite">
                                <span className="text-slate-500">{t('Escolheu')}</span>
                                <strong className="ml-1 text-slate-800">{artigos === 1 ? t(':n artigo', { n: artigos }) : t(':n artigos', { n: artigos })}</strong>
                                {casa.mostra_precos && (
                                    <>
                                        <span className="mx-1 text-slate-400">·</span>
                                        <strong className="tabular-nums" style={{ color: cor }}>{kwanzasDaCarta(total)} Kz</strong>
                                    </>
                                )}
                            </p>
                            <button type="button" onClick={() => { porEscolhas({}); enviar.reset(); }} className="text-xs text-slate-400 underline">{t('Limpar')}</button>
                        </div>

                        {erroDoPedido && (
                            <p role="alert" className="animate-fade-in mb-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{erroDoPedido}</p>
                        )}

                        {/* PEDIR AQUI MESMO: só com os pedidos ligados E a mesa reconhecida. */}
                        {casa.aceita_pedidos && mesa?.reconhecida && (
                            <>
                                <div className="mb-2 grid gap-2 sm:grid-cols-3">
                                    <input value={nome} onChange={(e) => porNome(e.target.value)} placeholder={t('O seu nome (opcional)')} autoComplete="name" aria-label={t('O seu nome (opcional)')}
                                        className="rounded-xl border-slate-300 text-sm" />
                                    <input value={telefone} onChange={(e) => porTelefone(e.target.value)} placeholder={t('O seu telefone (opcional)')} type="tel" autoComplete="tel" aria-label={t('O seu telefone (opcional)')}
                                        className="rounded-xl border-slate-300 text-sm" />
                                    <input value={observacoes} onChange={(e) => porObservacoes(e.target.value)} placeholder={t('Alguma indicação? (opcional)')} aria-label={t('Alguma indicação? (opcional)')}
                                        className="rounded-xl border-slate-300 text-sm" />
                                </div>
                                <button type="button" onClick={() => enviar.mutate()} disabled={enviar.isPending}
                                    className="mb-2 flex w-full items-center justify-center gap-2 rounded-2xl py-3.5 font-bold text-white shadow-lg transition active:scale-[.98] disabled:opacity-60" style={{ background: cor }}>
                                    <i className={cls('fas', enviar.isPending ? 'fa-spinner fa-spin' : 'fa-paper-plane')} aria-hidden="true" />
                                    {enviar.isPending ? t('A enviar…') : t('Enviar pedido para a cozinha')}
                                </button>
                            </>
                        )}

                        {/* O pedido vai como MENSAGEM, com a mesa à cabeça: quem lança a comanda é quem está no restaurante. */}
                        {linkDoWhatsapp ? (
                            <a href={linkDoWhatsapp} target="_blank" rel="noopener noreferrer"
                                className="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-3.5 font-bold text-white shadow-lg transition hover:bg-emerald-700 active:scale-[.98]">
                                <i className="fab fa-whatsapp text-lg" aria-hidden="true" />{t('Enviar pedido por WhatsApp')}
                            </a>
                        ) : !(casa.aceita_pedidos && mesa?.reconhecida) && (
                            <p className="py-3 text-center text-xs text-slate-500">{t('Mostre esta lista ao empregado para fazer o pedido.')}</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function ler(chave: string): Record<number, number> {
    try {
        const guardado = JSON.parse(window.sessionStorage.getItem(chave) ?? '{}');
        return guardado && typeof guardado === 'object' ? guardado : {};
    } catch {
        return {};
    }
}

function guardar(chave: string, escolhas: Record<number, number>): void {
    try {
        if (Object.keys(escolhas).length) window.sessionStorage.setItem(chave, JSON.stringify(escolhas));
        else window.sessionStorage.removeItem(chave);
    } catch {
        // Sem armazenamento (janela privada): as escolhas vivem só nesta página.
    }
}
