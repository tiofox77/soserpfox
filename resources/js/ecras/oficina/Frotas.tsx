import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { frotas, type FrotaPorFacturar } from '@/api/oficina';
import { Faixa } from '@/ecras/facturacao/faixa';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, kz } from '@/ui/tokens';

import { ChapaDaMatricula } from './ChapaDaMatricula';

/**
 * A FACTURAÇÃO DE FROTAS (15/09/2026, OF-16).
 *
 * À esquerda, as empresas com ordens por facturar e quanto vale cada uma; à
 * direita, as ordens da escolhida no período — marcam-se e sai UMA factura com
 * todas, cada linha com a matrícula e a OS à frente.
 */

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function Frotas() {
    const cache = useQueryClient();
    const lista = useQuery({ queryKey: ['oficina', 'frotas'], queryFn: frotas.lista });
    const [escolhido, porEscolhido] = useState<FrotaPorFacturar | null>(null);
    const [periodo, porPeriodo] = useState(() => {
        const hoje = new Date();
        return { de: iso(new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1)), ate: iso(hoje) };
    });
    const [marcadas, porMarcadas] = useState<number[]>([]);
    const [confirmar, porConfirmar] = useState(false);
    const [emitida, porEmitida] = useState<{ numero: string; total: number; morada: string } | null>(null);

    const ordens = useQuery({
        queryKey: ['oficina', 'frotas', escolhido?.id, periodo],
        queryFn: () => frotas.ordens(escolhido?.id as number, periodo.de, periodo.ate),
        enabled: Boolean(escolhido),
        placeholderData: keepPreviousData,
    });

    // Ao mudar de empresa ou de período, marcam-se todas as que podem entrar.
    useEffect(() => {
        porMarcadas((ordens.data?.data ?? []).filter((o) => o.pode).map((o) => o.id));
    }, [ordens.data]);

    const facturar = useMutation({
        mutationFn: () => frotas.facturar(escolhido?.id as number, marcadas),
        onSuccess: (r) => {
            porConfirmar(false);
            porEmitida(r.factura);
            void cache.invalidateQueries({ queryKey: ['oficina', 'frotas'] });
            void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'] });
        },
    });

    const valorMarcado = useMemo(() => (ordens.data?.data ?? []).filter((o) => marcadas.includes(o.id)).reduce((s, o) => s + o.total, 0), [ordens.data, marcadas]);

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    const d = lista.data;
    const valorTotal = d.data.reduce((s, c) => s + c.valor, 0);
    const podem = (ordens.data?.data ?? []).filter((o) => o.pode);

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Facturação de Frotas')} subtitulo={t('Uma factura por empresa com as ordens de todas as viaturas do período')} icone="fa-truck-fast" cor="bom" />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <CartaoNumero aspecto="claro" tom="verde" icone="fa-building" rotulo={t('Clientes com ordens por facturar')} valor={d.data.length} />
                <CartaoNumero aspecto="claro" tom="indigo" icone="fa-clipboard-list" rotulo={t('Ordens por facturar')} valor={d.data.reduce((s, c) => s + c.ordens, 0)} />
                <CartaoNumero aspecto="claro" tom="ambar" icone="fa-sack-dollar" rotulo={t('Valor por facturar')} valor={kz(valorTotal)} sufixo="Kz" className="col-span-2 lg:col-span-1" />
            </div>

            {d.data.length === 0 ? (
                <SemNada icone="fa-truck-fast" titulo={t('Nada por facturar')} frase={t('As ordens concluídas ou entregues de viaturas ligadas a um cliente aparecem aqui até serem facturadas.')} />
            ) : (
                <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
                    <ul className="space-y-2">
                        {d.data.map((c, i) => {
                            const activo = escolhido?.id === c.id;
                            return (
                                <li key={c.id} style={cascata(i)} className="entra">
                                    <button type="button" onClick={() => { porEscolhido(c); porEmitida(null); }} aria-pressed={activo}
                                        className={cls('group flex w-full items-center gap-3 border bg-white p-3 text-left shadow-sm', RAIO_GRANDE, TRANSICAO, FOCO, 'hover:-translate-y-0.5 hover:shadow-md',
                                            activo ? 'border-emerald-400 ring-2 ring-emerald-100' : 'border-slate-200')}>
                                        <span className={cls('grid h-10 w-10 flex-none place-items-center rounded-xl text-white shadow transition-transform duration-300 group-hover:scale-110', c.empresa ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : 'bg-gradient-to-br from-slate-400 to-slate-600')}>
                                            <i className={cls('fas', c.empresa ? 'fa-building' : 'fa-user')} aria-hidden="true" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-bold text-slate-900">{c.nome}</span>
                                            <span className="block text-xs text-slate-500">
                                                {tn(':n viatura|:n viaturas', c.viaturas, { n: c.viaturas })} · {tn(':n ordem|:n ordens', c.ordens, { n: c.ordens })}
                                                {c.desde && ` · ${t('desde :data', { data: data(c.desde) })}`}
                                            </span>
                                        </span>
                                        <span className="flex-none text-right text-sm font-extrabold tabular-nums text-emerald-700">{kz(c.valor)}</span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>

                    <section className={cls('border border-slate-200 bg-white shadow-sm', RAIO_GRANDE)}>
                        {!escolhido ? (
                            <div className="flex h-full min-h-[16rem] flex-col items-center justify-center gap-2 p-8 text-center text-slate-400">
                                <i className="fas fa-hand-pointer icon-float text-3xl" aria-hidden="true" />
                                <p className="text-sm">{t('Escolha um cliente para ver as ordens por facturar.')}</p>
                            </div>
                        ) : (
                            <>
                                <header className="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate text-base font-bold text-slate-900">{escolhido.nome}</h2>
                                        <p className="text-xs text-slate-500">{escolhido.nif ? t('NIF :nif', { nif: escolhido.nif }) : t('Sem NIF')}</p>
                                    </div>
                                    <label className="text-xs font-semibold text-slate-600">{t('De')}
                                        <input type="date" value={periodo.de} onChange={(e) => porPeriodo({ ...periodo, de: e.target.value })} className={cls(entrada, 'mt-1')} />
                                    </label>
                                    <label className="text-xs font-semibold text-slate-600">{t('Até')}
                                        <input type="date" value={periodo.ate} onChange={(e) => porPeriodo({ ...periodo, ate: e.target.value })} className={cls(entrada, 'mt-1')} />
                                    </label>
                                </header>

                                {emitida && (
                                    <div className={cls('animate-scale-in m-4 flex flex-wrap items-center gap-3 border border-emerald-200 bg-emerald-50 p-4', RAIO_GRANDE)}>
                                        <span className="grid h-11 w-11 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-lg text-white shadow"><i className="fas fa-check" aria-hidden="true" /></span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-bold text-emerald-900">{t('Factura :numero emitida', { numero: emitida.numero })}</span>
                                            <span className="block text-sm text-emerald-800">{kz(emitida.total)} Kz</span>
                                        </span>
                                        <a href={emitida.morada} className={cls('inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800 hover:underline', FOCO)}>{t('Abrir a factura')}<i className="fas fa-arrow-right text-xs" aria-hidden="true" /></a>
                                    </div>
                                )}

                                {ordens.isPending ? <div className="p-4"><Carregando linhas={4} /></div> : ordens.isError ? <div className="p-4"><AvisoDeErro erro={ordens.error} /></div> : ordens.data.data.length === 0 ? (
                                    <p className="p-8 text-center text-sm text-slate-400">{t('Não há ordens por facturar neste período.')}</p>
                                ) : (
                                    <>
                                        <div className="overflow-x-auto">
                                            <table className={cls('w-full text-sm transition-opacity', ordens.isFetching && 'opacity-60')}>
                                                <thead>
                                                    <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                        <th scope="col" className="px-3 py-2 text-left">
                                                            <input type="checkbox" aria-label={t('Marcar todas')} checked={podem.length > 0 && podem.every((o) => marcadas.includes(o.id))}
                                                                onChange={(e) => porMarcadas(e.target.checked ? podem.map((o) => o.id) : [])} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                                        </th>
                                                        <th scope="col" className="px-3 py-2 text-left">{t('Viatura')}</th>
                                                        <th scope="col" className="px-3 py-2 text-left">{t('Ordem')}</th>
                                                        <th scope="col" className="px-3 py-2 text-left">{t('Concluída')}</th>
                                                        <th scope="col" className="px-3 py-2 text-right">{t('Total')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {ordens.data.data.map((o) => (
                                                        <tr key={o.id} className={cls('transition-colors', o.pode ? 'hover:bg-emerald-50/40' : 'bg-slate-50 text-slate-400', marcadas.includes(o.id) && 'bg-emerald-50/60')}>
                                                            <td className="px-3 py-2">
                                                                <input type="checkbox" disabled={!o.pode} checked={marcadas.includes(o.id)} aria-label={t('Incluir :ordem', { ordem: o.numero })}
                                                                    onChange={() => porMarcadas(marcadas.includes(o.id) ? marcadas.filter((x) => x !== o.id) : [...marcadas, o.id])} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <div className="flex items-center gap-2">
                                                                    {o.matricula && <ChapaDaMatricula matricula={o.matricula} tamanho="pequeno" />}
                                                                    <span className="hidden truncate text-xs text-slate-500 xl:inline">{o.viatura}</span>
                                                                </div>
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <span className="font-mono text-xs font-semibold text-slate-700">{o.numero}</span>
                                                                <span className="block text-[11px] text-slate-400">{o.estado_rotulo} · {tn(':n linha|:n linhas', o.linhas, { n: o.linhas })}</span>
                                                                {o.motivo && <span className="block text-[11px] font-semibold text-amber-700"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{o.motivo}</span>}
                                                            </td>
                                                            <td className="px-3 py-2 text-xs tabular-nums">{data(o.concluida ?? o.entrada)}</td>
                                                            <td className="px-3 py-2 text-right font-bold tabular-nums text-slate-900">{kz(o.total)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        <footer className="sticky bottom-0 flex flex-wrap items-center gap-3 border-t border-slate-200 bg-white/95 p-4 backdrop-blur">
                                            <span className="text-sm text-slate-600">
                                                {tn(':n ordem marcada|:n ordens marcadas', marcadas.length, { n: marcadas.length })} · <b className="tabular-nums text-slate-900">{kz(valorMarcado)} Kz</b>
                                            </span>
                                            {d.pode_facturar ? (
                                                <Botao cor="bom" tom="solida" icone="fa-file-invoice" className="sm:ml-auto" disabled={marcadas.length === 0} onClick={() => porConfirmar(true)}>
                                                    {t('Emitir factura agrupada')}
                                                </Botao>
                                            ) : (
                                                <span className="text-xs text-slate-400 sm:ml-auto"><i className="fas fa-lock mr-1" aria-hidden="true" />{t('Emitir facturas é uma permissão da facturação.')}</span>
                                            )}
                                        </footer>
                                    </>
                                )}
                            </>
                        )}
                    </section>
                </div>
            )}

            {confirmar && escolhido && (
                <Modal aberto aoFechar={() => porConfirmar(false)} titulo={t('Emitir factura agrupada')} subtitulo={escolhido.nome} icone="fa-file-invoice" cor="bom"
                    rodape={<><Botao onClick={() => porConfirmar(false)}>{t('Cancelar')}</Botao><Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={facturar.isPending} onClick={() => facturar.mutate()}>{t('Emitir')}</Botao></>}>
                    <div className="space-y-3 text-sm text-slate-700">
                        <AvisoDeErro erro={facturar.error} />
                        <p>{tn('Vai sair uma factura fiscal com :n ordem de serviço, no valor de :valor Kz.|Vai sair uma factura fiscal com :n ordens de serviço, no valor de :valor Kz.', marcadas.length, { n: marcadas.length, valor: kz(valorMarcado) })}</p>
                        <p className="text-xs text-slate-500">{t('Cada linha leva à frente a matrícula e o número da ordem. O valor final (imposto, retenção) é o que a factura calcular.')}</p>
                    </div>
                </Modal>
            )}
        </div>
    );
}
