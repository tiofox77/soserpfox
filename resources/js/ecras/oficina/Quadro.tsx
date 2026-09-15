import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type DragEvent } from 'react';

import { ordens, quadroDaOficina, type CartaoDoQuadro, type QuadroDaOficina } from '@/api/oficina';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, haQuanto, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { ChapaDaMatricula } from './ChapaDaMatricula';
import { FichaDaOrdemModal } from './OrdensDeServico';

/**
 * O QUADRO DE TRABALHO DA OFICINA (15/09/2026, OF-04).
 *
 * A parede da oficina no ecrã: uma coluna por estado, um cartão por carro.
 * Arrasta-se o cartão para mudar o estado (pela porta única, que desconta ou
 * devolve as peças), escolhe-se o mecânico no próprio cartão, e carregar no
 * cartão abre a ordem inteira. Refresca-se sozinho a cada minuto, para servir
 * numa televisão pendurada na oficina.
 */

const COR_DA_COLUNA: Record<string, { topo: string; ponto: string; fundo: string }> = {
    pending: { topo: 'border-slate-400', ponto: 'bg-slate-400', fundo: 'bg-slate-100/70' },
    scheduled: { topo: 'border-indigo-500', ponto: 'bg-indigo-500', fundo: 'bg-indigo-50/60' },
    in_progress: { topo: 'border-blue-500', ponto: 'bg-blue-500', fundo: 'bg-blue-50/60' },
    waiting_parts: { topo: 'border-amber-500', ponto: 'bg-amber-500', fundo: 'bg-amber-50/60' },
    completed: { topo: 'border-emerald-500', ponto: 'bg-emerald-500', fundo: 'bg-emerald-50/60' },
    delivered: { topo: 'border-teal-500', ponto: 'bg-teal-500', fundo: 'bg-teal-50/50' },
};

const COR_DA_PRIORIDADE: Record<string, string> = {
    urgent: 'bg-red-600', high: 'bg-orange-500', normal: 'bg-blue-400', low: 'bg-slate-300',
};

/** Mudar para estes estados mexe no stock: pergunta-se antes. */
const PEDEM_CONFIRMACAO = ['completed', 'delivered'];

const iniciais = (nome: string | null) => (nome ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || '?';

export default function Quadro() {
    const cache = useQueryClient();
    const [, porRecado] = useRecadoNoCanto('');
    const [procura, porProcura] = useState('');
    const [mecanico, porMecanico] = useState('');
    const [arrastado, porArrastado] = useState<CartaoDoQuadro | null>(null);
    const [sobre, porSobre] = useState<string | null>(null);
    const [aConfirmar, porAConfirmar] = useState<{ cartao: CartaoDoQuadro; estado: string; rotulo: string } | null>(null);
    const [aVer, porAVer] = useState<number | null>(null);
    const [falhas, porFalhas] = useState<string[]>([]);

    const chave = ['oficina', 'quadro', mecanico, procura];
    const q = useQuery({
        queryKey: chave,
        queryFn: () => quadroDaOficina.ler({ mecanico: mecanico || undefined, procura: procura || undefined }),
        placeholderData: keepPreviousData,
        refetchInterval: 60_000,
    });
    const opcoes = useQuery({ queryKey: ['oficina', 'ordens', 'opcoes'], queryFn: ordens.opcoes, staleTime: 5 * 60_000 });

    const refazer = () => {
        void cache.invalidateQueries({ queryKey: ['oficina', 'quadro'] });
        void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'] });
    };

    const mover = useMutation({
        mutationFn: ({ cartao, estado }: { cartao: CartaoDoQuadro; estado: string }) => ordens.estado(cartao.id, estado),
        // O cartão muda de coluna já; se o servidor recusar, volta ao sítio no refresco.
        onMutate: ({ cartao, estado }) => {
            cache.setQueryData<QuadroDaOficina>(chave, (d) => d && ({
                ...d,
                colunas: d.colunas.map((c) => ({
                    ...c,
                    cartoes: c.estado === estado ? [...c.cartoes.filter((x) => x.id !== cartao.id), cartao] : c.cartoes.filter((x) => x.id !== cartao.id),
                })),
            }));
        },
        onSuccess: (r) => { porFalhas(r.falhas ?? []); },
        onSettled: refazer,
    });

    const atribuir = useMutation({
        mutationFn: ({ cartao, mecanicoId }: { cartao: CartaoDoQuadro; mecanicoId: string }) => quadroDaOficina.mecanico(cartao.id, mecanicoId),
        onSettled: refazer,
    });

    const pedirMudanca = (cartao: CartaoDoQuadro, estado: string) => {
        const coluna = q.data?.colunas.find((c) => c.estado === estado);
        if (!coluna || q.data?.colunas.find((c) => c.cartoes.some((x) => x.id === cartao.id))?.estado === estado) return;
        if (PEDEM_CONFIRMACAO.includes(estado)) {
            porAConfirmar({ cartao, estado, rotulo: coluna.rotulo });
            return;
        }
        mover.mutate({ cartao, estado });
    };

    const soltar = (e: DragEvent<HTMLElement>, estado: string) => {
        e.preventDefault();
        porSobre(null);
        if (arrastado) pedirMudanca(arrastado, estado);
        porArrastado(null);
    };

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const total = d.colunas.reduce((s, c) => s + c.cartoes.length, 0);

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Quadro de Trabalho')}
                subtitulo={t('Os carros na oficina, por estado. Arraste um cartão para o mudar de coluna.')}
                icone="fa-table-columns"
                cor="roxo"
                accoes={
                    <a href="/workshop/work-orders" className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-list transition-transform duration-300 group-hover:scale-110" aria-hidden="true" />{t('Lista de ordens')}
                    </a>
                }
            >
                <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                    <span className={cls('h-2 w-2 rounded-full bg-emerald-300', q.isFetching && 'animate-ping')} aria-hidden="true" />
                    {tn(':n carro no quadro|:n carros no quadro', total, { n: total })}
                </span>
            </Faixa>

            <div className="flex flex-wrap items-center gap-2">
                <label className="relative min-w-0 flex-1 basis-60">
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Matrícula, TAG#, WO#, nº da ordem ou dono')} aria-label={t('Procurar')}
                        className={cls('w-full border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm', RAIO, FOCO)} />
                </label>
                <select value={mecanico} onChange={(e) => porMecanico(e.target.value)} aria-label={t('Mecânico')}
                    className={cls('border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)}>
                    <option value="">{t('Todos os mecânicos')}</option>
                    <option value="sem">{t('Sem mecânico')}</option>
                    {d.mecanicos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                </select>
            </div>

            {falhas.length > 0 && (
                <div role="alert" className={cls('border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                    <p className="font-semibold"><i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />{t('Peças que não saíram do stock')}</p>
                    <ul className="mt-1 list-disc pl-5">{falhas.map((f) => <li key={f}>{f}</li>)}</ul>
                </div>
            )}

            {/* AS COLUNAS — deslizam para o lado num ecrã estreito. */}
            <div className="-mx-1 overflow-x-auto pb-3">
                <div className="flex min-w-max gap-3 px-1">
                    {d.colunas.map((c) => {
                        const cor = COR_DA_COLUNA[c.estado] ?? COR_DA_COLUNA.pending!;
                        const alvo = sobre === c.estado && arrastado !== null;

                        return (
                            <section key={c.estado} aria-label={c.rotulo}
                                onDragOver={(e) => { if (d.pode_editar && arrastado) { e.preventDefault(); porSobre(c.estado); } }}
                                onDragLeave={() => porSobre((s) => (s === c.estado ? null : s))}
                                onDrop={(e) => soltar(e, c.estado)}
                                className={cls('flex w-72 flex-none flex-col border-t-4', RAIO_GRANDE, TRANSICAO, cor.topo, cor.fundo, alvo && 'scale-[1.01] ring-2 ring-indigo-400 ring-offset-2')}>
                                <header className="flex items-center gap-2 px-3 py-2.5">
                                    <span className={cls('h-2.5 w-2.5 rounded-full', cor.ponto)} aria-hidden="true" />
                                    <h2 className="text-sm font-bold text-slate-800">{c.rotulo}</h2>
                                    <span className="ml-auto rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums text-slate-600 shadow-sm">{c.cartoes.length}</span>
                                </header>
                                <ul className="flex max-h-[calc(100dvh-20rem)] min-h-[8rem] flex-col gap-2 overflow-y-auto px-2 pb-2">
                                    {c.cartoes.length === 0 && (
                                        <li className={cls('grid flex-1 place-items-center border-2 border-dashed border-slate-200 p-4 text-center text-xs text-slate-400', RAIO)}>
                                            {alvo ? t('Largue aqui') : t('Nenhum carro')}
                                        </li>
                                    )}
                                    {c.cartoes.map((k, i) => (
                                        <Cartao key={k.id} k={k} i={i} d={d} estado={c.estado}
                                            aArrastar={arrastado?.id === k.id}
                                            aoArrastar={() => porArrastado(k)} aoLargar={() => { porArrastado(null); porSobre(null); }}
                                            aoAbrir={() => porAVer(k.id)}
                                            aoMover={(estado) => pedirMudanca(k, estado)}
                                            aoAtribuir={(mecanicoId) => atribuir.mutate({ cartao: k, mecanicoId })} />
                                    ))}
                                </ul>
                            </section>
                        );
                    })}
                </div>
            </div>

            <Modal aberto={aConfirmar !== null} aoFechar={() => porAConfirmar(null)} titulo={t('Passar a «:estado»?', { estado: aConfirmar?.rotulo ?? '' })}
                subtitulo={aConfirmar ? `${aConfirmar.cartao.numero} · ${aConfirmar.cartao.matricula ?? ''}` : undefined} icone="fa-boxes-stacked" cor="bom" largura="sm"
                rodape={<><Botao onClick={() => porAConfirmar(null)}>{t('Cancelar')}</Botao><Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={mover.isPending}
                    onClick={() => { if (aConfirmar) mover.mutate({ cartao: aConfirmar.cartao, estado: aConfirmar.estado }); porAConfirmar(null); }}>{t('Confirmar')}</Botao></>}>
                <p className="text-sm text-slate-600">{t('As peças da ordem saem do stock nesta mudança.')}</p>
            </Modal>

            {aVer !== null && opcoes.data && (
                <FichaDaOrdemModal
                    id={aVer}
                    o={opcoes.data}
                    aoFechar={() => { porAVer(null); refazer(); }}
                    aoMudar={(m, f) => { refazer(); porRecado(m); porFalhas(f ?? []); }}
                    mudarEstado={(id, estado) => {
                        const cartao = d.colunas.flatMap((c) => c.cartoes).find((x) => x.id === id);
                        if (cartao) mover.mutate({ cartao, estado });
                    }}
                    aMudarEstado={mover.isPending}
                />
            )}
        </div>
    );
}

function Cartao({ k, i, d, estado, aArrastar, aoArrastar, aoLargar, aoAbrir, aoMover, aoAtribuir }: {
    k: CartaoDoQuadro; i: number; d: QuadroDaOficina; estado: string; aArrastar: boolean;
    aoArrastar: () => void; aoLargar: () => void; aoAbrir: () => void; aoMover: (estado: string) => void; aoAtribuir: (mecanicoId: string) => void;
}) {
    return (
        <li style={cascata(i)} draggable={d.pode_editar}
            onDragStart={(e) => { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(k.id)); aoArrastar(); }}
            onDragEnd={aoLargar}
            className={cls('entra group relative overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO, TRANSICAO,
                d.pode_editar && 'cursor-grab active:cursor-grabbing', 'hover:-translate-y-0.5 hover:shadow-lg', aArrastar && 'rotate-2 opacity-50')}>
            <span className={cls('absolute inset-y-0 left-0 w-1', COR_DA_PRIORIDADE[k.prioridade] ?? 'bg-slate-300')} aria-hidden="true" />
            <button type="button" onClick={aoAbrir} className={cls('block w-full p-3 pl-4 text-left', FOCO)} aria-label={t('Abrir :ordem', { ordem: k.numero })}>
                <span className="flex items-start justify-between gap-2">
                    {k.matricula ? <ChapaDaMatricula matricula={k.matricula} tamanho="pequeno" /> : <span className="text-xs text-slate-400">—</span>}
                    <span className="flex flex-wrap justify-end gap-1">
                        {k.tag && <span className="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-amber-900"><i className="fas fa-key text-[9px]" aria-hidden="true" />{k.tag}</span>}
                    </span>
                </span>
                <span className="mt-2 block truncate text-sm font-semibold text-slate-900">{k.viatura}</span>
                <span className="block truncate text-xs text-slate-500">{k.dono}</span>
                <span className="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                    <span className="font-mono font-semibold text-slate-600">{k.numero}</span>
                    {k.wo && <span className="font-mono text-purple-700">WO# {k.wo}</span>}
                    <span className="text-slate-400">· {haQuanto(k.entrada)}</span>
                </span>
                <span className="mt-2 flex flex-wrap gap-1">
                    {(k.prioridade === 'urgent' || k.prioridade === 'high') && (
                        <span className={cls('inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold text-white', COR_DA_PRIORIDADE[k.prioridade])}>
                            <i className="fas fa-bolt" aria-hidden="true" />{k.prioridade_rotulo}
                        </span>
                    )}
                    {k.atrasada && <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700 ring-1 ring-inset ring-red-200"><i className="fas fa-clock" aria-hidden="true" />{t('Atrasada')}</span>}
                    {k.a_espera > 0 && <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-900"><i className="fas fa-hand" aria-hidden="true" />{tn(':n à espera do cliente|:n à espera do cliente', k.a_espera, { n: k.a_espera })}</span>}
                    {k.checkin
                        ? <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700" title={k.checkin === 'assinado' ? t('Check-in assinado') : t('Check-in feito')}><i className={cls('fas', k.checkin === 'assinado' ? 'fa-signature' : 'fa-clipboard-check')} aria-hidden="true" />{t('Check-in')}</span>
                        : estado !== 'delivered' && <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500"><i className="fas fa-clipboard" aria-hidden="true" />{t('Sem check-in')}</span>}
                    {k.facturada && <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700"><i className="fas fa-file-invoice" aria-hidden="true" />{t('Facturada')}</span>}
                </span>
            </button>
            <div className="flex items-center gap-2 border-t border-slate-100 px-3 py-2 pl-4">
                <span className={cls('grid h-7 w-7 flex-none place-items-center rounded-full text-[10px] font-bold', k.mecanico ? 'bg-gradient-to-br from-orange-400 to-red-500 text-white' : 'bg-slate-100 text-slate-400')} aria-hidden="true">
                    {k.mecanico ? iniciais(k.mecanico) : <i className="fas fa-user-plus" />}
                </span>
                {d.pode_editar ? (
                    <select value={k.mecanico_id ? String(k.mecanico_id) : ''} onChange={(e) => aoAtribuir(e.target.value)} aria-label={t('Mecânico de :ordem', { ordem: k.numero })}
                        className={cls('min-w-0 flex-1 truncate border-0 bg-transparent py-0.5 pl-0 text-xs font-medium text-slate-700 focus:ring-0', FOCO)}>
                        <option value="">{t('Por atribuir')}</option>
                        {d.mecanicos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                    </select>
                ) : <span className="min-w-0 flex-1 truncate text-xs text-slate-600">{k.mecanico ?? t('Por atribuir')}</span>}
                <span className="text-xs font-bold tabular-nums text-slate-700">{kz(k.total, 0)}</span>
                {/* No telemóvel não há arrastar: muda-se o estado por aqui. */}
                {d.pode_editar && (
                    <span className="relative grid h-7 w-7 flex-none place-items-center rounded-lg bg-slate-100 text-xs text-slate-500 sm:hidden">
                        <i className="fas fa-right-left" aria-hidden="true" />
                        <select value={estado} onChange={(e) => aoMover(e.target.value)} aria-label={t('Mudar o estado de :ordem', { ordem: k.numero })}
                            className="absolute inset-0 cursor-pointer opacity-0">
                            {d.colunas.map((c) => <option key={c.estado} value={c.estado}>{c.rotulo}</option>)}
                        </select>
                    </span>
                )}
            </div>
        </li>
    );
}
