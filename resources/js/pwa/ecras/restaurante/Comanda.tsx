import { useEffect, useMemo, useRef, useState } from 'react';

import { t, tn } from '@/i18n';

import { dinheiro, useAccao, useBaseViva } from '../../ganchos';
import type { Registo } from '../../motor/base';
import { restaurante } from '../../motor/restaurante';
import { numero } from '../../motor/util';
import { CrachaDoTurno, type ControloDoTurno } from '../../turno/Turno';
import { avisar } from '../../ui/Dialogos';
import { mensagemDe, nomeDaMesa, type Recibo } from './comum';
import { FolhaReceber } from './Receber';

/**
 * O ícone do PWA, e não o logótipo da empresa — mesma razão do POS de balcão:
 * o logótipo do tenant não está pré-guardado pelo service worker e offline
 * daria um quadrado partido em cada cartão.
 */
const LOGO_DO_POS = '/pwa/icon-192x192.png';

/** Quantos pratos se desenham de cada vez (e quantos mais o «Mostrar mais» junta). */
const PASSO = 40;

/**
 * VISTA: A COMANDA
 *
 * Mesma geometria do POS de balcão, e pela mesma razão: `dvh` para o cromado
 * do browser no telemóvel, e a barra de pesquisa `lg:static` porque a partir de
 * lg a coluna dos pratos já tem o seu próprio scroll — sticky ali tapava a
 * primeira fila de cartões.
 */
export function Comanda({
    uuid,
    c,
    todasAsMesas,
    definicoes,
    voltarSala,
    aoFechar,
}: {
    uuid: string;
    c: ControloDoTurno;
    todasAsMesas: Registo[];
    definicoes: Registo;
    voltarSala: () => void;
    aoFechar: (recibo: Recibo) => void;
}) {
    // A comanda vem da base viva: o que o motor grava (juntar, mandar à
    // cozinha, o número CMD- que a sincronização traz) aparece sem se reler à
    // mão. Guarda-se o identificador com o registo para nunca mostrar, por um
    // instante, a comanda ANTERIOR ao trocar de mesa.
    const lida = useBaseViva<{ uuid: string; comanda: Registo | null } | null>(
        async () => ({ uuid, comanda: (await restaurante.comanda(uuid)) ?? null }),
        [uuid],
        null,
    );
    const carregada = lida !== null && lida.uuid === uuid;
    const comanda = carregada ? lida.comanda : null;

    const pratos = useBaseViva<Registo[] | null>(() => restaurante.pratos(), [], null);

    const [pesquisa, setPesquisa] = useState('');
    const [categoria, setCategoria] = useState<string | null>(null);
    const [limite, setLimite] = useState(PASSO);
    const [receber, setReceber] = useState(false);

    // Uma comanda que desapareceu do aparelho (descartada noutro separador,
    // apagada por uma cópia reposta) não deixa o empregado a olhar para o vazio.
    useEffect(() => {
        if (carregada && !comanda) voltarSala();
    }, [carregada, comanda, voltarSala]);

    // Pesquisa ou categoria nova começa do princípio da lista.
    useEffect(() => { setLimite(PASSO); }, [pesquisa, categoria]);

    const itens: Registo[] = comanda?.items || [];

    const categorias = useMemo(
        () => [...new Set((pratos ?? []).map((p) => p.category).filter((x): x is string => !!x))].sort(),
        [pratos],
    );

    /**
     * Derivado, e não uma lista guardada que alguém tem de se lembrar de
     * refazer: escrever na pesquisa muda o ecrã sozinho. A versão anterior era
     * uma propriedade actualizada à mão e bastava esquecer uma chamada para a
     * grelha ficar a mostrar o resultado da pesquisa anterior.
     */
    const pratosFiltrados = useMemo(() => {
        const termo = pesquisa.toLowerCase().trim();

        return (pratos ?? []).filter((p) => {
            if (categoria && p.category !== categoria) return false;
            if (!termo) return true;

            return String(p.name || '').toLowerCase().includes(termo)
                || String(p.sku || '').toLowerCase().includes(termo);
        });
    }, [pratos, pesquisa, categoria]);

    const pratosVisiveis = pratosFiltrados.slice(0, limite);

    const quantidades = useMemo(() => {
        const q = new Map<unknown, number>();
        for (const i of itens) q.set(i.product_id, (q.get(i.product_id) ?? 0) + numero(i.quantity));

        return q;
    }, [itens]);

    const temPorEnviar = itens.some((i) => !i.enviado);

    /*
     * AS MEXIDAS NA CONTA VÃO EM FILA, uma de cada vez.
     *
     * O motor lê a comanda, mexe e grava. Dois toques rápidos no mesmo prato
     * liam os dois a comanda ANTES de qualquer um gravar — e o segundo gravava
     * por cima do primeiro: o empregado tocava duas vezes e a conta dizia 1.
     * Em fila, cada toque lê o que o anterior já gravou.
     */
    const serie = useRef<Promise<unknown>>(Promise.resolve());
    const emSerie = (fn: () => Promise<unknown>) => {
        serie.current = serie.current
            .then(fn)
            .catch((e) => avisar(mensagemDe(e), 'erro'));
    };

    const juntar = (p: Registo) => emSerie(() => restaurante.juntar(uuid, {
        product_id: p.id,
        product_name: p.name,
        quantity: 1,
        unit_price: p.price,
        tax_rate: p.tax_rate,
    }));

    const alterar = (i: Registo, delta: number) => emSerie(() => restaurante.alterarQuantidade(uuid, i.local_uuid, delta));
    const remover = (i: Registo) => emSerie(() => restaurante.removerArtigo(uuid, i.local_uuid));

    const [enviarCozinha, aEnviar] = useAccao(async () => {
        // Espera pelas mexidas que ainda estão na fila: mandar à cozinha antes
        // de o último prato ser gravado deixava-o de fora do pedido.
        await serie.current;
        try {
            // Sem aviso de sucesso: as linhas passam a violeta «na cozinha» e é isso que se lê.
            await restaurante.mandarParaCozinha(uuid);
        } catch (e) {
            avisar(mensagemDe(e), 'erro');
        }
    });

    const abrirReceber = async () => {
        await serie.current;
        setReceber(true);
    };

    // No telemóvel a conta fica por baixo da grelha de pratos: uma barra
    // flutuante com o total leva lá num toque, e some quando a conta já se vê.
    const refDaConta = useRef<HTMLDivElement>(null);
    const [contaVisivel, setContaVisivel] = useState(true);

    useEffect(() => {
        const alvo = refDaConta.current;
        if (!alvo || typeof IntersectionObserver === 'undefined') return;

        const obs = new IntersectionObserver(([e]) => setContaVisivel(!!e?.isIntersecting), { threshold: 0.05 });
        obs.observe(alvo);

        return () => obs.disconnect();
    }, [carregada]);

    const pessoas = Number(comanda?.guest_count) || 1;
    const semPratosNaEscolha = pratos !== null && !pratosFiltrados.length;

    return (
        <section className="pb-16 lg:pb-0">
            <div className="lg:grid lg:grid-cols-12 lg:h-[calc(100dvh-137px)]">

                {/* ---------- PRATOS ---------- */}
                <div className="lg:col-span-7 xl:col-span-8 bg-slate-100 lg:h-full lg:flex lg:flex-col lg:overflow-hidden">

                    <div className="sticky top-[var(--pwa-topo,60px)] lg:static z-30 bg-slate-100/95 backdrop-blur px-3 pt-3 pb-2 space-y-2 border-b border-slate-200">
                        <div className="flex gap-2 items-center">
                            <button type="button" onClick={voltarSala} title={t('Voltar à sala')} aria-label={t('Voltar à sala')}
                                    className="pwa-toque shrink-0 h-12 w-12 rounded-2xl bg-white shadow-sm text-slate-600 hover:text-orange-600 hover:shadow transition">
                                <i className="fas fa-arrow-left" aria-hidden="true" />
                            </button>
                            <div className="flex-1 min-w-0 flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12 border-2 border-transparent focus-within:border-orange-300 transition">
                                <i className="fas fa-magnifying-glass text-gray-400" aria-hidden="true" />
                                <input id="restaurante-pesquisa" name="pesquisa_prato" type="search" inputMode="search"
                                       aria-label={t('Procurar prato…')} placeholder={t('Procurar prato…')}
                                       value={pesquisa} onChange={(e) => setPesquisa(e.target.value)}
                                       className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none border-0 p-0" />
                                {pesquisa && (
                                    <button type="button" onClick={() => setPesquisa('')} aria-label={t('Limpar')}
                                            className="pwa-aparece text-gray-400 hover:text-gray-600 text-xl leading-none px-1">&times;</button>
                                )}
                            </div>
                        </div>

                        <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 no-scrollbar" role="group" aria-label={t('Categorias')}>
                            <button type="button" onClick={() => setCategoria(null)} aria-pressed={!categoria}
                                    className={`pwa-toque shrink-0 px-4 h-9 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap transition-colors ${!categoria ? 'bg-gradient-to-r from-orange-500 to-orange-600 text-white' : 'bg-white text-slate-600 hover:bg-orange-50'}`}>
                                {t('Todos')}
                            </button>
                            {categorias.map((cat) => (
                                <button key={cat} type="button" onClick={() => setCategoria(cat)} aria-pressed={categoria === cat}
                                        className={`pwa-toque shrink-0 px-4 h-9 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap transition-colors ${categoria === cat ? 'bg-gradient-to-r from-orange-500 to-orange-600 text-white' : 'bg-white text-slate-600 hover:bg-orange-50'}`}>
                                    {cat}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="px-3 py-3 lg:flex-1 lg:overflow-y-auto">
                        {pratos === null && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2.5" aria-hidden="true">
                                {[0, 1, 2, 3, 4, 5, 6, 7].map((i) => <div key={i} className="h-40 rounded-2xl bg-slate-200/70 animate-pulse" />)}
                            </div>
                        )}

                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2.5">
                            {pratosVisiveis.map((p) => {
                                const q = quantidades.get(p.id) ?? 0;

                                return (
                                    <button key={p.id} type="button" onClick={() => juntar(p)}
                                            className="group bg-white rounded-2xl shadow-sm p-2 flex flex-col text-left border-2 border-transparent hover:border-orange-300 hover:shadow-md hover:-translate-y-0.5 active:scale-[.97] transition">
                                        <div className="relative w-full h-20 sm:h-24 rounded-xl bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-100 flex items-center justify-center mb-2 overflow-hidden">
                                            <img src={LOGO_DO_POS} alt="" draggable={false}
                                                 className="h-8 sm:h-10 w-auto object-contain opacity-25 select-none pointer-events-none transition-transform duration-300 group-hover:scale-110" />
                                            <span className="absolute bottom-1 left-1 w-5 h-5 rounded-full bg-white/85 flex items-center justify-center shadow-sm">
                                                <i className="fas fa-bowl-food text-[10px] text-orange-500" aria-hidden="true" />
                                            </span>
                                            <span className="absolute top-1 right-1 w-6 h-6 rounded-full bg-orange-600 text-white flex items-center justify-center shadow opacity-0 group-hover:opacity-100 transition-opacity" aria-hidden="true">
                                                <i className="fas fa-plus text-[10px]" />
                                            </span>
                                            {q > 0 && (
                                                // A chave pela quantidade: o crachá volta a crescer a cada toque — o empregado vê que o toque contou.
                                                <span key={q} className="pwa-cresce absolute top-1 right-1 bg-emerald-600 text-white text-[11px] font-bold min-w-[24px] h-6 px-1 rounded-full flex items-center justify-center shadow">
                                                    {q}
                                                </span>
                                            )}
                                        </div>
                                        <p className="font-semibold text-[13px] leading-tight line-clamp-2 mb-0.5 text-slate-800">{p.name}</p>
                                        {p.category && <p className="text-[10px] text-gray-400 truncate">{p.category}</p>}
                                        <div className="mt-auto pt-1.5">
                                            <span className="font-bold text-orange-700 text-sm whitespace-nowrap tabular-nums">{dinheiro(p.price)}</span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {pratosFiltrados.length > limite && (
                            <div className="mt-4 text-center">
                                <button type="button" onClick={() => setLimite((l) => l + PASSO)}
                                        className="pwa-toque bg-white border border-gray-200 hover:border-orange-300 text-gray-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm">
                                    <i className="fas fa-chevron-down mr-1" aria-hidden="true" />{t('Mostrar mais')}
                                </button>
                            </div>
                        )}

                        {semPratosNaEscolha && (
                            <div className="pwa-entra text-center py-16 text-gray-400">
                                <i className="fas fa-bowl-food text-5xl mb-3 block opacity-40 pwa-flutua" aria-hidden="true" />
                                <p className="text-sm font-medium">{pesquisa || categoria ? t('Nenhum prato encontrado') : t('Sem pratos sincronizados')}</p>
                                {/* Quando a empresa exige ficha técnica, um menu vazio
                                    quase nunca é falta de sincronização: são pratos
                                    sem ficha. Dizê-lo poupa uma chamada ao suporte. */}
                                {!!definicoes.require_recipe_for_products && (
                                    <p className="text-[11px] mt-2 max-w-xs mx-auto">{t('Esta empresa só vende pratos com ficha técnica activa.')}</p>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* ---------- A CONTA ---------- */}
                <div ref={refDaConta} className="lg:col-span-5 xl:col-span-4 bg-white lg:h-full lg:flex lg:flex-col lg:border-l border-slate-200">

                    <div className="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-800 text-white">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-orange-300">
                                    <i className={`fas ${comanda?.table_id ? 'fa-chair' : 'fa-bag-shopping'} mr-1`} aria-hidden="true" />
                                    {comanda?.table_id ? nomeDaMesa(comanda.table_id, todasAsMesas) : t('Venda ao balcão')}
                                </p>
                                <h2 className="text-lg font-black truncate">{comanda?._server_number || t('Comanda por sincronizar')}</h2>
                                <div className="flex flex-wrap items-center gap-2 mt-0.5">
                                    <p className="text-[11px] opacity-70">
                                        <i className="fas fa-user-group mr-1" aria-hidden="true" />{tn(':n pessoa|:n pessoas', pessoas, { n: pessoas })}
                                    </p>
                                    <CrachaDoTurno c={c} className="!py-0.5" />
                                </div>
                            </div>
                            <button type="button" onClick={voltarSala} aria-label={t('Voltar à sala')} title={t('Voltar à sala')}
                                    className="pwa-toque shrink-0 h-9 w-9 rounded-xl bg-white/10 hover:bg-white/20 transition-colors">
                                <i className="fas fa-xmark" aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <div className="lg:flex-1 lg:overflow-y-auto divide-y divide-slate-100">
                        {itens.map((i) => (
                            <div key={i.local_uuid} className="pwa-entra px-3 py-2.5 flex items-center gap-2">
                                <div className="shrink-0">
                                    {!i.enviado ? (
                                        <div className="flex items-center rounded-xl border border-orange-200 bg-orange-50 overflow-hidden">
                                            <button type="button" onClick={() => alterar(i, -1)} aria-label={t('Diminuir')}
                                                    className="h-9 w-8 font-black text-orange-700 hover:bg-orange-100 active:bg-orange-200 transition-colors">&minus;</button>
                                            <span className="px-2 text-sm font-black text-slate-800 tabular-nums">{i.quantity}</span>
                                            <button type="button" onClick={() => alterar(i, 1)} aria-label={t('Aumentar')}
                                                    className="h-9 w-8 font-black text-orange-700 hover:bg-orange-100 active:bg-orange-200 transition-colors">+</button>
                                        </div>
                                    ) : (
                                        // Já foi para a cozinha: a quantidade fixa-se.
                                        // Foi cozinhado — mexer aqui seria mentir ao
                                        // stock e à cozinha, e o servidor recusa na mesma.
                                        <div className="h-9 min-w-[46px] px-2 rounded-xl bg-violet-50 border border-violet-200 flex items-center justify-center">
                                            <span className="text-sm font-black text-violet-700 tabular-nums">{i.quantity}×</span>
                                        </div>
                                    )}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold text-slate-800 truncate">{i.product_name}</p>
                                    <p className="text-[11px] text-slate-400">
                                        <span className="tabular-nums">{dinheiro(i.unit_price)}</span> Kz
                                        {i.enviado && (
                                            <span className="text-violet-600 font-bold ml-1">
                                                · <i className="fas fa-fire-burner mr-0.5" aria-hidden="true" />{t('na cozinha')}
                                            </span>
                                        )}
                                    </p>
                                    {i.notes && <p className="text-[11px] text-amber-600 italic truncate"><i className="fas fa-note-sticky mr-1" aria-hidden="true" />{i.notes}</p>}
                                </div>

                                <div className="text-right shrink-0">
                                    <b className="block text-sm text-slate-800 tabular-nums">{dinheiro(numero(i.quantity) * numero(i.unit_price))}</b>
                                    {!i.enviado && (
                                        <button type="button" onClick={() => remover(i)} aria-label={t('Remover')} title={t('Remover')}
                                                className="pwa-toque text-red-400 hover:text-red-600 text-xs mt-0.5 px-1">
                                            <i className="fas fa-trash" aria-hidden="true" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}

                        {carregada && !itens.length && (
                            <div className="pwa-entra text-center py-16 text-slate-400">
                                <i className="fas fa-utensils text-5xl mb-3 block opacity-30 pwa-flutua" aria-hidden="true" />
                                <p className="text-sm font-medium">{t('Comanda vazia')}</p>
                                <p className="text-xs mt-1">{t('Toque num prato para o juntar.')}</p>
                            </div>
                        )}
                    </div>

                    <div className="border-t border-slate-200 p-3 space-y-2 bg-slate-50">
                        <div className="flex justify-between text-xs text-slate-500">
                            <span>{t('Base')}</span>
                            <span className="tabular-nums">{dinheiro(comanda?.subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-xs text-slate-500">
                            <span>{t('Imposto')}</span>
                            <span className="tabular-nums">{dinheiro(comanda?.tax)}</span>
                        </div>
                        <div className="flex justify-between items-baseline">
                            <span className="text-sm font-bold text-slate-700">{t('Total')}</span>
                            <span key={String(comanda?.total ?? 0)} className="pwa-aparece text-2xl font-black text-orange-600 tabular-nums">{dinheiro(comanda?.total)} Kz</span>
                        </div>

                        {temPorEnviar && (
                            <button type="button" onClick={() => void enviarCozinha()} disabled={aEnviar}
                                    className="pwa-toque pwa-entra w-full rounded-2xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white p-3 font-black shadow-lg shadow-violet-600/20 disabled:opacity-60">
                                <i className={`fas ${aEnviar ? 'fa-spinner fa-spin' : 'fa-fire-burner'} mr-2`} aria-hidden="true" />
                                {definicoes.use_kitchen_workflow === false ? t('Confirmar pedido') : t('Enviar à cozinha')}
                            </button>
                        )}

                        <button type="button" onClick={() => void abrirReceber()} disabled={!itens.length}
                                className="pwa-toque w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 text-lg font-black shadow-lg shadow-emerald-600/20 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i className="fas fa-cash-register mr-2" aria-hidden="true" />{t('Receber e faturar')}
                        </button>
                    </div>
                </div>
            </div>

            {/* A barra da conta no telemóvel. Por cima do menu de baixo, nunca por baixo dele. */}
            {!contaVisivel && itens.length > 0 && (
                <button type="button" onClick={() => refDaConta.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                        style={{ bottom: 'calc(76px + env(safe-area-inset-bottom))' }}
                        className="lg:hidden pwa-aparece fixed inset-x-3 z-30 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 text-white shadow-2xl px-4 py-3 flex items-center gap-3">
                    <span className="w-9 h-9 shrink-0 rounded-xl bg-orange-500 flex items-center justify-center font-black text-sm tabular-nums">
                        {itens.reduce((s, i) => s + numero(i.quantity), 0)}
                    </span>
                    <span className="flex-1 text-left text-sm font-bold">
                        {t('Ver a conta')}
                        {temPorEnviar && <i className="fas fa-fire-burner ml-2 text-violet-300 animate-pulse" aria-hidden="true" />}
                    </span>
                    <span className="text-lg font-black text-orange-300 tabular-nums">{dinheiro(comanda?.total)} Kz</span>
                    <i className="fas fa-chevron-down text-xs opacity-70" aria-hidden="true" />
                </button>
            )}

            {receber && comanda && (
                <FolhaReceber comanda={comanda} aoFechar={() => setReceber(false)} aoFechada={aoFechar} />
            )}
        </section>
    );
}
