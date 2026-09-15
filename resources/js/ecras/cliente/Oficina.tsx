import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { portal, type OrdemDoCliente } from '@/api/portalDoCliente';
import { t, tn } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data, dataHora } from '@/ui/tokens';

import { ChapaDaMatricula } from '../oficina/ChapaDaMatricula';
import { Cabecalho, Numero, kwanzas } from './comum';

/**
 * A OFICINA NO PORTAL — o carro, o estado, a folha de obra e a factura a pagar.
 *
 * Pedido de 15/09/2026. O dono do carro quer saber três coisas, por esta ordem:
 * EM QUE PONTO ESTÁ o carro (a linha das etapas), O QUE SE FEZ (a folha de
 * obra) e QUANTO FALTA PAGAR (a factura, com o PDF e o IBAN para transferir).
 * As ordens em curso vêm primeiro; o histórico fica por baixo.
 */
const COR: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso', scheduled: 'primaria', in_progress: 'primaria', waiting_parts: 'aviso', completed: 'bom', delivered: 'bom', cancelled: 'perigo',
};

export default function Oficina() {
    const pedido = useQuery({ queryKey: ['portal', 'oficina'], queryFn: portal.oficina, refetchInterval: 60_000 });

    if (pedido.isPending) return <Carregando linhas={6} />;
    if (pedido.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível abrir a oficina.')}</p>;

    const d = pedido.data;
    const emCurso = d.ordens.filter((o) => !['delivered', 'cancelled'].includes(o.estado));
    const historico = d.ordens.filter((o) => ['delivered', 'cancelled'].includes(o.estado));

    return (
        <div>
            <Cabecalho titulo={t('A Minha Oficina')} subtitulo={t('O estado do seu carro, as folhas de obra e as facturas')} icone="fa-car" gradiente="from-orange-500 to-red-600" />

            <div className="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <Numero i={0} cor="azul" rotulo={t('Viaturas')} valor={d.resumo.viaturas} nota={t('Registadas em seu nome')} icone="fa-car" />
                <Numero i={1} cor="laranja" rotulo={t('Na oficina')} valor={d.resumo.na_oficina} nota={t('Ordens por entregar')} icone="fa-screwdriver-wrench" />
                <Numero i={2} cor="verde" rotulo={t('Prontas')} valor={d.resumo.prontas} nota={t('Pode vir levantar')} icone="fa-circle-check" />
                <Numero i={3} cor={d.resumo.por_pagar > 0 ? 'vermelho' : 'cinza'} rotulo={t('Por pagar')} valor={<span className="text-2xl">{kwanzas(d.resumo.por_pagar)}</span>}
                    nota={d.resumo.por_pagar > 0 ? t('Nas facturas da oficina') : t('Nada em falta')} icone="fa-hand-holding-dollar" />
            </div>

            {d.viaturas.length === 0 ? (
                <section className={cls(CARTAO, 'mb-6')}>
                    <SemNada icone="fa-car" titulo={t('Sem viaturas')} frase={t('Ainda não há nenhuma viatura registada em seu nome nesta oficina.')} />
                </section>
            ) : (
                <section className="mb-8">
                    <h2 className="mb-3 text-lg font-bold text-gray-900"><i className="fas fa-car-side icon-float mr-2 text-orange-500" aria-hidden="true" />{t('As minhas viaturas')}</h2>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {d.viaturas.map((v, i) => (
                            <article key={v.id} style={cascata(i)} className={cls('entra card-hover border border-gray-100 bg-white p-5 shadow-lg', 'rounded-2xl')}>
                                <div className="flex flex-wrap items-start gap-3">
                                    <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-xl text-white shadow">
                                        <i className="fas fa-car icon-float" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1 basis-40">
                                        <ChapaDaMatricula matricula={v.matricula} className="mb-1.5" />
                                        <p className="truncate text-sm text-gray-600">{[v.viatura, v.ano, v.cor].filter(Boolean).join(' · ')}</p>
                                        {v.km > 0 && <p className="text-xs text-gray-500">{t(':km km', { km: v.km.toLocaleString() })}</p>}
                                    </div>
                                    {v.na_oficina && <Etiqueta cor="aviso" icone="fa-screwdriver-wrench">{t('Na oficina')}</Etiqueta>}
                                </div>
                                {v.documentos.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-1.5 border-t border-gray-100 pt-3">
                                        {v.documentos.map((doc) => (
                                            <Etiqueta key={doc.nome} cor={doc.dias < 0 ? 'perigo' : doc.dias <= 30 ? 'aviso' : 'bom'}
                                                icone={doc.dias < 0 ? 'fa-triangle-exclamation' : doc.dias <= 30 ? 'fa-hourglass-half' : 'fa-check'}>
                                                {doc.dias < 0 ? t(':doc caducou', { doc: doc.nome }) : t(':doc até :data', { doc: doc.nome, data: data(doc.ate) })}
                                            </Etiqueta>
                                        ))}
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                </section>
            )}

            <section className="mb-8">
                <h2 className="mb-3 text-lg font-bold text-gray-900"><i className="fas fa-screwdriver-wrench icon-float mr-2 text-orange-500" aria-hidden="true" />{t('Em curso')}</h2>
                {emCurso.length === 0
                    ? <div className={CARTAO}><SemNada icone="fa-circle-check" frase={t('Nenhuma viatura sua está na oficina neste momento.')} /></div>
                    : <div className="space-y-4">{emCurso.map((o, i) => <Folha key={o.id} o={o} i={i} aberta />)}</div>}
            </section>

            {historico.length > 0 && (
                <section className="mb-8">
                    <h2 className="mb-3 text-lg font-bold text-gray-900"><i className="fas fa-clock-rotate-left mr-2 text-gray-500" aria-hidden="true" />{t('Histórico')}</h2>
                    <div className="space-y-3">{historico.map((o, i) => <Folha key={o.id} o={o} i={i} />)}</div>
                </section>
            )}

            {d.contas.length > 0 && d.resumo.por_pagar > 0 && (
                <section className={cls(CARTAO, 'entra p-6')}>
                    <h2 className="mb-1 text-lg font-bold text-gray-900"><i className="fas fa-building-columns mr-2 text-blue-600" aria-hidden="true" />{t('Pagar por transferência')}</h2>
                    <p className="mb-4 text-sm text-gray-600">{t('Indique o número da factura no descritivo da transferência.')}</p>
                    <div className="grid gap-3 md:grid-cols-2">
                        {d.contas.map((c, i) => (
                            <div key={i} className={cls('border border-blue-100 bg-blue-50/60 p-4', RAIO)}>
                                <p className="font-semibold text-gray-900">{c.banco ?? '—'}</p>
                                {c.conta && <p className="text-sm text-gray-600">{t('Conta')}: <span className="font-mono">{c.conta}</span></p>}
                                {c.iban && <p className="break-all text-sm text-gray-600">IBAN: <span className="select-all font-mono font-semibold text-gray-900">{c.iban}</span></p>}
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

function Folha({ o, i, aberta = false }: { o: OrdemDoCliente; i: number; aberta?: boolean }) {
    const [ver, porVer] = useState(aberta);
    const f = o.factura;

    return (
        <article style={cascata(i)} className={cls('entra overflow-hidden border border-gray-100 bg-white shadow-lg', 'rounded-2xl', TRANSICAO, 'hover:shadow-xl')}>
            <button type="button" onClick={() => porVer(!ver)} aria-expanded={ver}
                className={cls('flex w-full flex-wrap items-center gap-3 p-5 text-left', FOCO)}>
                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-purple-600 to-pink-600 text-white shadow">
                    <i className="fas fa-clipboard-list" aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1 basis-48">
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">{t('Folha de obra')}</span>
                        <span className="font-mono font-bold text-gray-900">{o.numero}</span>
                        <Etiqueta cor={COR[o.estado] ?? 'neutra'} ponto>{o.estado_rotulo}</Etiqueta>
                    </span>
                    <span className="block text-sm text-gray-600">{[o.matricula, o.viatura].filter(Boolean).join(' · ')} · {t('entrou a :data', { data: data(o.entrada) })}</span>
                </span>
                {f && f.falta > 0 && (
                    <Etiqueta cor={f.vencida ? 'perigo' : 'aviso'} icone={f.vencida ? 'fa-triangle-exclamation' : 'fa-hand-holding-dollar'}>
                        {t('Falta pagar :v', { v: kwanzas(f.falta) })}
                    </Etiqueta>
                )}
                <i className={cls('fas fa-chevron-down text-gray-400 transition-transform duration-300', ver && 'rotate-180')} aria-hidden="true" />
            </button>

            {ver && (
                <div className="animate-fade-in space-y-5 border-t border-gray-100 p-5">
                    {/* A LINHA DAS ETAPAS — onde está o carro agora. */}
                    {!o.cancelada && (
                        <ol className="grid grid-cols-2 gap-2 sm:flex sm:items-start sm:gap-0">
                            {o.etapas.map((e, n) => (
                                <li key={e.chave} className="relative flex items-start gap-2 sm:flex-1 sm:flex-col sm:items-center sm:text-center">
                                    {n > 0 && <span aria-hidden="true" className={cls('absolute right-1/2 top-4 hidden h-0.5 w-full sm:block', e.feita ? 'bg-emerald-400' : 'bg-gray-200')} />}
                                    <span className={cls('relative z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm shadow',
                                        e.actual ? 'bg-gradient-to-br from-orange-500 to-red-600 text-white ring-4 ring-orange-100' : e.feita ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400')}>
                                        <i className={cls('fas', e.feita && !e.actual ? 'fa-check' : e.actual ? 'fa-screwdriver-wrench' : 'fa-circle text-[6px]')} aria-hidden="true" />
                                    </span>
                                    <span className="min-w-0 sm:mt-1.5">
                                        <span className={cls('block text-xs font-semibold', e.actual ? 'text-orange-700' : e.feita ? 'text-gray-800' : 'text-gray-400')}>{e.rotulo}</span>
                                        {e.quando && <span className="block text-[11px] text-gray-500">{dataHora(e.quando)}</span>}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                        {[
                            [t('Problema relatado'), o.problema, 'fa-comment-dots'],
                            [t('Trabalho realizado'), o.trabalho, 'fa-screwdriver-wrench'],
                            [t('Recomendações'), o.recomendacoes, 'fa-lightbulb'],
                        ].filter(([, texto]) => texto).map(([titulo, texto, icone]) => (
                            <div key={titulo} className={cls('bg-gray-50 p-4', RAIO)}>
                                <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500"><i className={cls('fas mr-1.5', icone)} aria-hidden="true" />{titulo}</p>
                                <p className="whitespace-pre-line text-sm text-gray-800">{texto}</p>
                            </div>
                        ))}
                    </div>

                    {/* OF-14: a avaliação do serviço — o convite, ou as estrelas que já deu. */}
                    {o.avaliar && (
                        <a href={o.avaliar} className={cls('group flex flex-wrap items-center gap-3 border border-amber-300 bg-gradient-to-r from-amber-50 to-yellow-50 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md', RAIO, FOCO)}>
                            <span className="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-lg text-white shadow transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110">
                                <i className="fas fa-star" aria-hidden="true" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block font-semibold text-gray-900">{t('Como correu o serviço?')}</span>
                                <span className="block text-sm text-gray-600">{t('Dê a sua nota em 30 segundos — ajuda a oficina a fazer melhor.')}</span>
                            </span>
                            <span className="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-2 text-sm font-bold text-white">{t('Avaliar')}<i className="fas fa-arrow-right transition-transform group-hover:translate-x-1" aria-hidden="true" /></span>
                        </a>
                    )}
                    {o.avaliacao ? (
                        <p className="flex items-center gap-2 text-sm text-gray-600">
                            {t('A sua avaliação:')}
                            <span className="inline-flex gap-0.5">{[1, 2, 3, 4, 5].map((e) => <i key={e} className={cls('fas fa-star', e <= (o.avaliacao ?? 0) ? 'text-amber-400' : 'text-gray-200')} aria-hidden="true" />)}</span>
                        </p>
                    ) : null}

                    {/* OF-03: o orçamento à espera da decisão do cliente. */}
                    {o.aprovar && (
                        <a href={o.aprovar} className={cls('group flex flex-wrap items-center gap-3 border border-amber-300 bg-gradient-to-r from-amber-50 to-orange-50 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md', RAIO, FOCO)}>
                            <span className="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-lg text-white shadow transition-transform duration-300 group-hover:scale-110">
                                <i className="fas fa-hand" aria-hidden="true" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block font-semibold text-gray-900">{t('O orçamento está à espera da sua aprovação')}</span>
                                <span className="block text-sm text-gray-600">{t('Veja o que a oficina propõe, aprove ou recuse cada trabalho e assine.')}</span>
                            </span>
                            <span className="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-2 text-sm font-bold text-white">{t('Decidir agora')}<i className="fas fa-arrow-right transition-transform group-hover:translate-x-1" aria-hidden="true" /></span>
                        </a>
                    )}

                    {o.linhas.length > 0 && (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr><th className="px-4 py-2">{t('Serviço / peça')}</th><th className="px-4 py-2 text-right">{t('Qtd.')}</th><th className="px-4 py-2 text-right">{t('Valor')}</th></tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {o.linhas.map((l, n) => (
                                        <tr key={n} className={cls(l.aprovacao === 'pending' && 'bg-amber-50', l.aprovacao === 'declined' && 'text-gray-400')}>
                                            <td className="px-4 py-2 text-gray-800">
                                                <i className={cls('fas mr-2 text-gray-400', l.tipo === 'part' ? 'fa-gear' : 'fa-screwdriver-wrench')} aria-hidden="true" />
                                                <span className={cls(l.aprovacao === 'declined' && 'text-gray-400 line-through')}>{l.nome}</span>
                                                {l.aprovacao === 'pending' && <span className="ml-2 rounded-full bg-amber-200 px-2 py-0.5 text-[11px] font-bold text-amber-900">{t('À sua espera')}</span>}
                                                {l.aprovacao === 'declined' && <span className="ml-2 text-[11px] font-semibold">{t('Recusada')}</span>}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums text-gray-600">{l.quantidade}</td>
                                            <td className="px-4 py-2 text-right tabular-nums font-semibold text-gray-900">{kwanzas(l.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot><tr><td colSpan={2} className="px-4 py-2 text-right font-semibold text-gray-600">{t('Total da ordem')}</td><td className="px-4 py-2 text-right text-base font-bold tabular-nums text-gray-900">{kwanzas(o.total)}</td></tr></tfoot>
                            </table>
                        </div>
                    )}

                    {o.inspeccoes.map((ins) => <InspeccaoNoPortal key={ins.id} ins={ins} />)}

                    {o.fotos.length > 0 && (
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500"><i className="fas fa-camera mr-1.5" aria-hidden="true" />{tn(':n fotografia|:n fotografias', o.fotos.length, { n: o.fotos.length })}</p>
                            <div className="flex flex-wrap gap-2">
                                {o.fotos.map((foto, n) => (
                                    <a key={n} href={foto.url} target="_blank" rel="noreferrer" className={cls('group relative block h-24 w-24 overflow-hidden', RAIO, FOCO)}>
                                        <img src={foto.url} alt={foto.descricao ?? ''} title={[foto.servico, foto.zona, foto.descricao].filter(Boolean).join(' · ')} loading="lazy" className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110" />
                                        <span className="absolute inset-x-0 bottom-0 bg-black/50 px-1 text-center text-[10px] text-white">
                                            {foto.tipo === 'photo_before' ? t('Antes') : foto.tipo === 'photo_after' ? t('Depois') : foto.tipo === 'photo_during' ? t('Durante') : t('Dano')}
                                        </span>
                                    </a>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3 border-t border-dashed border-gray-200 pt-4">
                        {o.garantia_ate && <Etiqueta cor="primaria" icone="fa-shield-halved">{t('Garantia até :data', { data: data(o.garantia_ate) })}</Etiqueta>}
                        {f ? (
                            <div className={cls('ml-auto flex flex-wrap items-center gap-3 border p-3', RAIO, f.falta > 0 ? (f.vencida ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50') : 'border-emerald-200 bg-emerald-50')}>
                                <i className={cls('fas fa-file-invoice text-xl', f.falta > 0 ? 'text-amber-600' : 'text-emerald-600')} aria-hidden="true" />
                                <span className="text-sm">
                                    <span className="block font-semibold text-gray-900">{t('Factura :n', { n: f.numero })}</span>
                                    <span className="block text-gray-600">
                                        {f.falta > 0
                                            ? t('Falta :v · vence a :data', { v: kwanzas(f.falta), data: data(f.vencimento) })
                                            : t('Paga · :v', { v: kwanzas(f.total) })}
                                    </span>
                                </span>
                                <a href={f.pdf} target="_blank" rel="noreferrer"
                                    className={cls('inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-md hover:-translate-y-0.5 hover:shadow-lg', RAIO, TRANSICAO, FOCO)}>
                                    <i className="fas fa-file-pdf" aria-hidden="true" />{t('Ver PDF')}
                                </a>
                            </div>
                        ) : (
                            <span className="ml-auto text-sm text-gray-500"><i className="fas fa-hourglass-half mr-1.5" aria-hidden="true" />{t('A factura sai quando o trabalho estiver concluído.')}</span>
                        )}
                    </div>
                </div>
            )}
        </article>
    );
}

/**
 * A INSPECÇÃO NO PORTAL (OF-02) — o semáforo que a oficina deu ao carro.
 *
 * O que precisa de atenção vem primeiro e aberto; os pontos OK ficam contados
 * e escondidos atrás de um botão, para o cliente não ler trinta linhas verdes.
 */
const COR_DO_PONTO: Record<string, { ponto: string; fundo: string; nome: string }> = {
    urgente: { ponto: 'bg-red-600', fundo: 'border-red-200 bg-red-50', nome: 'Urgente' },
    atencao: { ponto: 'bg-amber-400', fundo: 'border-amber-200 bg-amber-50', nome: 'Atenção' },
    ok: { ponto: 'bg-emerald-500', fundo: 'border-emerald-100 bg-emerald-50/50', nome: 'OK' },
    na: { ponto: 'bg-gray-300', fundo: 'border-gray-100 bg-gray-50', nome: 'Não se aplica' },
};

function InspeccaoNoPortal({ ins }: { ins: OrdemDoCliente['inspeccoes'][number] }) {
    const [verTudo, porVerTudo] = useState(false);
    const problemas = ins.pontos.filter((p) => p.estado === 'urgente' || p.estado === 'atencao')
        .sort((a, b) => (a.estado === 'urgente' ? 0 : 1) - (b.estado === 'urgente' ? 0 : 1));
    const restantes = ins.pontos.filter((p) => p.estado !== 'urgente' && p.estado !== 'atencao');

    const linha = (p: OrdemDoCliente['inspeccoes'][number]['pontos'][number], n: number) => {
        const cor = (COR_DO_PONTO[p.estado ?? 'na'] ?? COR_DO_PONTO.na) as { ponto: string; fundo: string; nome: string };
        return (
            <li key={`${p.seccao}-${p.ponto}-${n}`} className={cls('flex items-start gap-3 border p-2.5', RAIO, cor.fundo)}>
                <span className={cls('mt-1 h-3 w-3 flex-none rounded-full', cor.ponto)} aria-hidden="true" />
                <span className="min-w-0 flex-1 text-sm">
                    <span className="block font-semibold text-gray-900">{p.ponto} <span className="font-normal text-gray-500">· {p.seccao}</span></span>
                    <span className="block text-xs font-semibold text-gray-600">{t(cor.nome)}{p.nota ? ` — ${p.nota}` : ''}</span>
                </span>
                {p.foto && (
                    <a href={p.foto} target="_blank" rel="noreferrer" className={cls('block h-12 w-12 flex-none overflow-hidden', RAIO, FOCO)}>
                        <img src={p.foto} alt={p.ponto} loading="lazy" className="h-full w-full object-cover" />
                    </a>
                )}
            </li>
        );
    };

    return (
        <div className={cls('border border-gray-100 bg-white p-4', RAIO)}>
            <p className="mb-3 flex flex-wrap items-center gap-2">
                <i className="fas fa-list-check text-emerald-600" aria-hidden="true" />
                <span className="font-semibold text-gray-900">{t('Inspecção: :nome', { nome: ins.nome })}</span>
                <span className="text-xs text-gray-500">{dataHora(ins.concluida_em)}</span>
                <span className="ml-auto flex flex-wrap gap-1.5 text-xs font-bold">
                    {ins.contas.urgente > 0 && <span className="rounded-full bg-red-100 px-2 py-0.5 text-red-700">{tn(':n urgente|:n urgentes', ins.contas.urgente, { n: ins.contas.urgente })}</span>}
                    {ins.contas.atencao > 0 && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">{tn(':n a vigiar|:n a vigiar', ins.contas.atencao, { n: ins.contas.atencao })}</span>}
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">{tn(':n OK|:n OK', ins.contas.ok, { n: ins.contas.ok })}</span>
                </span>
            </p>
            {problemas.length > 0
                ? <ul className="space-y-2">{problemas.map(linha)}</ul>
                : <p className="text-sm text-emerald-700"><i className="fas fa-circle-check mr-1.5" aria-hidden="true" />{t('Tudo em ordem nos pontos inspeccionados.')}</p>}
            {restantes.length > 0 && (
                <>
                    <button type="button" onClick={() => porVerTudo(!verTudo)} className={cls('mt-3 text-xs font-semibold text-gray-600 hover:text-gray-900', FOCO, RAIO)}>
                        <i className={cls('fas mr-1', verTudo ? 'fa-chevron-up' : 'fa-chevron-down')} aria-hidden="true" />
                        {verTudo ? t('Esconder os restantes pontos') : tn('Ver o :n ponto restante|Ver os :n pontos restantes', restantes.length, { n: restantes.length })}
                    </button>
                    {verTudo && <ul className="animate-fade-in mt-2 grid gap-2 sm:grid-cols-2">{restantes.map(linha)}</ul>}
                </>
            )}
        </div>
    );
}
