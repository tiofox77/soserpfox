import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type PedidoDeEstabelecimentos, ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO_GRANDE, TOQUE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Paginas, Recado } from './comum';

type Estado = 'pending' | 'approved' | 'rejected';

const ESTADOS = (): Array<{ chave: Estado; rotulo: string; icone: string; cor: 'aviso' | 'bom' | 'perigo'; aceso: string }> => [
    { chave: 'pending', rotulo: t('Pendentes'), icone: 'fa-hourglass-half', cor: 'aviso', aceso: 'border-amber-400 bg-amber-50 ring-2 ring-amber-200' },
    { chave: 'approved', rotulo: t('Aprovados'), icone: 'fa-circle-check', cor: 'bom', aceso: 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200' },
    { chave: 'rejected', rotulo: t('Recusados'), icone: 'fa-circle-xmark', cor: 'perigo', aceso: 'border-red-400 bg-red-50 ring-2 ring-red-200' },
];

/**
 * OS PEDIDOS DE MAIS ESTABELECIMENTOS DO RESTAURANTE.
 *
 * Controla quantas unidades cada empresa pode operar sem contornar o plano.
 * A janela de análise mostra a quota que a empresa tem HOJE — o pedido guarda
 * a de quando foi feito, e as duas podem já não ser a mesma.
 */
export default function Estabelecimentos() {
    const fila = useQueryClient();
    const [filtros, porFiltros] = useState<{ estado: Estado; procura?: string; pagina: number }>({ estado: 'pending', pagina: 1 });
    const [recado, porRecado] = useState<string | null>(null);
    const [aAnalisar, porAAnalisar] = useState<PedidoDeEstabelecimentos | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'estabelecimentos', filtros],
        queryFn: () => ferramentas.estabelecimentos.ler(filtros),
        placeholderData: keepPreviousData,
    });

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <ErroDoEcra titulo={t('Não foi possível abrir os pedidos de estabelecimentos')} erro={lista.error} />;

    const d = lista.data;
    const estados = ESTADOS();

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Pedidos de estabelecimentos')} subtitulo={t('Controle quantas unidades cada empresa pode operar sem contornar o plano contratado.')} icone="fa-store" cor="laranja">
                <EstadoNaFaixa icone="fa-utensils">{t('Restaurante')}</EstadoNaFaixa>
                {d.contagens.pending > 0 && <EstadoNaFaixa icone="fa-bell">{t(':n por analisar', { n: d.contagens.pending })}</EstadoNaFaixa>}
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />

            <div className="grid gap-4 sm:grid-cols-3">
                {estados.map((e, i) => (
                    <button
                        key={e.chave}
                        type="button"
                        onClick={() => porFiltros((f) => ({ ...f, estado: e.chave, pagina: 1 }))}
                        aria-pressed={filtros.estado === e.chave}
                        className={cls('entra card-hover border bg-white p-5 text-left shadow-sm', RAIO_GRANDE, TRANSICAO, TOQUE, FOCO,
                            filtros.estado === e.chave ? e.aceso : 'border-slate-200 hover:border-slate-300')}
                        style={cascata(i)}
                    >
                        <span className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-slate-500">{e.rotulo}</span>
                            <i className={cls('fas icon-float text-xl', e.icone, { aviso: 'text-amber-500', bom: 'text-emerald-500', perigo: 'text-red-500' }[e.cor])} aria-hidden="true" />
                        </span>
                        <strong className="mt-1 block text-3xl font-black tabular-nums text-slate-900">{d.contagens[e.chave]}</strong>
                    </button>
                ))}
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <label className="block">
                    <Rotulo>{t('Procurar')}</Rotulo>
                    <span className="relative block">
                        <i className="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" className={cls(entrada, 'pl-9')} placeholder={t('Pesquisar empresa...')} value={filtros.procura ?? ''}
                            onChange={(ev) => porFiltros((f) => ({ ...f, procura: ev.target.value || undefined, pagina: 1 }))} />
                    </span>
                </label>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {d.pedidos.length === 0 ? <SemNada icone="fa-store-slash" frase={t('Nenhum pedido neste estado.')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">{t('Empresa')}</th>
                                    <th className="px-5 py-3">{t('Quota')}</th>
                                    <th className="px-5 py-3">{t('Pedido')}</th>
                                    <th className="px-5 py-3">{t('Motivo')}</th>
                                    <th className="px-5 py-3">{t('Estado')}</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.pedidos.map((p, i) => {
                                    const e = estados.find((x) => x.chave === p.estado) ?? estados[0]!;

                                    return (
                                        <tr key={p.id} className={cls('entra hover:bg-orange-50/40', TRANSICAO)} style={cascata(i)}>
                                            <td className="px-5 py-3">
                                                <b className="block text-slate-900">{p.empresa ?? '—'}</b>
                                                <span className="text-xs text-slate-500">{p.pedido_por ?? '—'} · {dataHora(p.pedido_em)}</span>
                                            </td>
                                            <td className="px-5 py-3 tabular-nums text-slate-700">{p.quota}</td>
                                            <td className="px-5 py-3 text-lg font-black tabular-nums text-orange-600">{p.pedido}</td>
                                            <td className="max-w-sm px-5 py-3 text-slate-500">
                                                {p.motivo || t('Sem observação')}
                                                {p.nota && <span className="mt-1 block text-xs text-slate-600"><i className="fas fa-user-shield mr-1" aria-hidden="true" />{p.nota}</span>}
                                            </td>
                                            <td className="px-5 py-3"><Etiqueta cor={e.cor} ponto>{p.estado === 'approved' ? t('Aprovado') : p.estado === 'rejected' ? t('Recusado') : t('Pendente')}</Etiqueta></td>
                                            <td className="px-5 py-3 text-right">
                                                {p.estado === 'pending' ? (
                                                    <Botao cor="aviso" tom="solida" altura="pequeno" icone="fa-magnifying-glass" onClick={() => porAAnalisar(p)}>{t('Analisar')}</Botao>
                                                ) : (
                                                    <span className="text-xs text-slate-500">{p.analisado_por ? `${p.analisado_por} · ` : ''}{dataHora(p.analisado_em)}</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="px-5 pb-4">
                    <Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={(p) => porFiltros((f) => ({ ...f, pagina: p }))} />
                </div>
            </section>

            {aAnalisar && (
                <Analisar
                    pedido={aAnalisar}
                    aoFechar={() => porAAnalisar(null)}
                    aoDecidir={(m) => { porAAnalisar(null); porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'estabelecimentos'] }); }}
                />
            )}
        </div>
    );
}

function Analisar({ pedido, aoFechar, aoDecidir }: { pedido: PedidoDeEstabelecimentos; aoFechar: () => void; aoDecidir: (m: string) => void }) {
    const [limite, porLimite] = useState(pedido.pedido);
    const [nota, porNota] = useState('');

    const aprovar = useMutation({ mutationFn: () => ferramentas.estabelecimentos.aprovar(pedido.id, limite, nota), onSuccess: (r) => aoDecidir(r.message) });
    const recusar = useMutation({ mutationFn: () => ferramentas.estabelecimentos.recusar(pedido.id, nota), onSuccess: (r) => aoDecidir(r.message) });

    const erro = aprovar.error ?? recusar.error;
    const erros = erro instanceof ErroDaApi ? erro.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Analisar pedido')}
            subtitulo={pedido.empresa ?? undefined}
            icone="fa-scale-balanced"
            cor="laranja"
            rodape={
                <div className="grid grid-cols-2 gap-3">
                    <Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={recusar.isPending} disabled={aprovar.isPending} onClick={() => recusar.mutate()}>{t('Recusar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aprovar.isPending} disabled={recusar.isPending} onClick={() => aprovar.mutate()}>{t('Aprovar quota')}</Botao>
                </div>
            }
        >
            <div className="space-y-4">
                <div className="grid grid-cols-3 gap-2 text-center">
                    <Numero rotulo={t('Quota no pedido')} valor={pedido.quota} />
                    <Numero rotulo={t('Quota hoje')} valor={pedido.quota_actual_da_empresa ?? '—'} />
                    <Numero rotulo={t('Pedido')} valor={pedido.pedido} destaque />
                </div>
                {pedido.motivo && <p className="rounded-xl bg-slate-50 p-3 text-sm text-slate-600"><i className="fas fa-quote-left mr-2 text-slate-400" aria-hidden="true" />{pedido.motivo}</p>}

                <AvisoDeErro erro={erros.review || erros.approvedLimit ? null : erro} />
                {erros.review && <p role="alert" className="text-sm font-semibold text-red-600">{erros.review[0]}</p>}

                <Campo etiqueta={t('Novo limite aprovado')} obrigatorio erro={erros.limite ?? erros.approvedLimit} ajuda={t('Entre 2 e 100, e acima da quota actual.')}>
                    <input type="number" min={2} max={100} className={entrada} value={limite} onChange={(e) => porLimite(Number(e.target.value))} />
                </Campo>
                <Campo etiqueta={t('Nota do administrador')} erro={erros.nota} ajuda={t('Obrigatória para recusar.')}>
                    <textarea rows={4} className={entrada} placeholder={t('Condição comercial ou motivo da decisão')} value={nota} onChange={(e) => porNota(e.target.value)} />
                </Campo>
            </div>
        </Modal>
    );
}

function Numero({ rotulo, valor, destaque = false }: { rotulo: string; valor: number | string; destaque?: boolean }) {
    return (
        <div className={cls('rounded-xl border p-3', destaque ? 'border-orange-200 bg-orange-50' : 'border-slate-200 bg-white')}>
            <span className="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">{rotulo}</span>
            <strong className={cls('block text-2xl font-black tabular-nums', destaque ? 'text-orange-600' : 'text-slate-800')}>{valor}</strong>
        </div>
    );
}
