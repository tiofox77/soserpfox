import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { portal, type OrdemDoCliente } from '@/api/portalDoCliente';
import { t, tn } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data, dataHora } from '@/ui/tokens';

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
                                        <p className="font-mono text-lg font-bold text-gray-900">{v.matricula}</p>
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

                    {o.linhas.length > 0 && (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr><th className="px-4 py-2">{t('Serviço / peça')}</th><th className="px-4 py-2 text-right">{t('Qtd.')}</th><th className="px-4 py-2 text-right">{t('Valor')}</th></tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {o.linhas.map((l, n) => (
                                        <tr key={n}>
                                            <td className="px-4 py-2 text-gray-800"><i className={cls('fas mr-2 text-gray-400', l.tipo === 'part' ? 'fa-gear' : 'fa-screwdriver-wrench')} aria-hidden="true" />{l.nome}</td>
                                            <td className="px-4 py-2 text-right tabular-nums text-gray-600">{l.quantidade}</td>
                                            <td className="px-4 py-2 text-right tabular-nums font-semibold text-gray-900">{kwanzas(l.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot><tr><td colSpan={2} className="px-4 py-2 text-right font-semibold text-gray-600">{t('Total da ordem')}</td><td className="px-4 py-2 text-right text-base font-bold tabular-nums text-gray-900">{kwanzas(o.total)}</td></tr></tfoot>
                            </table>
                        </div>
                    )}

                    {o.fotos.length > 0 && (
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500"><i className="fas fa-camera mr-1.5" aria-hidden="true" />{tn(':n fotografia|:n fotografias', o.fotos.length, { n: o.fotos.length })}</p>
                            <div className="flex flex-wrap gap-2">
                                {o.fotos.map((foto, n) => (
                                    <a key={n} href={foto.url} target="_blank" rel="noreferrer" className={cls('group relative block h-24 w-24 overflow-hidden', RAIO, FOCO)}>
                                        <img src={foto.url} alt={foto.descricao ?? ''} loading="lazy" className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110" />
                                        <span className="absolute inset-x-0 bottom-0 bg-black/50 px-1 text-center text-[10px] text-white">
                                            {foto.tipo === 'photo_before' ? t('Antes') : foto.tipo === 'photo_after' ? t('Depois') : t('Dano')}
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
