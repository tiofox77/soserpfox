import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { irt } from '@/api/rh';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { entrada } from '@/ui/Campo';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O MAPA DE IRT — o imposto retido no mês, na forma em que se entrega.
 *
 * Não recalcula imposto nenhum: lê o que ficou retido no processamento da
 * folha. Se recalculasse, o mapa podia dizer um valor e os recibos que os
 * trabalhadores têm em casa dizerem outro.
 *
 * ABRE NO MÊS PASSADO. O IRT retido entrega-se depois de o mês fechar, por
 * isso é esse que se está a preparar quando se abre este ecrã.
 *
 * AS FOLHAS QUE FICARAM DE FORA APARECEM. Um rascunho por aprovar é a razão
 * nº1 de o mapa não bater com o que a contabilidade espera, e descobri-lo
 * depois de entregar é tarde — por isso o aviso é grande e está por cima da
 * tabela, não numa nota de rodapé.
 */

const MESES = 12;

export default function MapaDeIrt() {
    const anterior = new Date();
    anterior.setDate(1);
    anterior.setMonth(anterior.getMonth() - 1);

    const [ano, porAno] = useState(anterior.getFullYear());
    const [mes, porMes] = useState(anterior.getMonth() + 1);
    const [departamento, porDepartamento] = useState('');

    const q = useQuery({
        queryKey: ['rh', 'irt', ano, mes, departamento],
        queryFn: () => irt.ler(ano, mes, departamento || undefined),
        placeholderData: keepPreviousData,
    });

    const andar = (passo: number) => {
        const d = new Date(ano, mes - 1 + passo, 1);
        porAno(d.getFullYear());
        porMes(d.getMonth() + 1);
    };

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) return <Falhou erro={q.error} />;

    const d = q.data;
    const parametros = new URLSearchParams({ ano: String(ano), mes: String(mes) });

    if (departamento) parametros.set('departamento', departamento);

    const inteiro = (v: number) => v.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Mapa de IRT')}
                subtitulo={t('O imposto retido aos trabalhadores no mês — para declarar e pagar à AGT')}
                icone="fa-landmark"
                cor="ciano"
                accoes={
                    <>
                        <a href={`/hr/irt-map/print?${parametros}`} target="_blank" rel="noreferrer" className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i className="fas fa-print transition-transform duration-300 group-hover:-translate-y-0.5" aria-hidden="true" />
                            {t('Imprimir')}
                        </a>
                        <a href={`/hr/irt-map/csv?${parametros}`} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i className="fas fa-file-csv transition-transform duration-300 group-hover:-translate-y-0.5" aria-hidden="true" />
                            {t('CSV')}
                        </a>
                    </>
                }
            >
                <p className="mt-3 text-sm text-white/80">
                    <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                    {t('O mapa lê o que ficou retido no processamento — não recalcula nada.')}
                </p>
            </Faixa>

            <Cartao
                titulo={t('O período')}
                icone="fa-calendar-days"
                accoes={
                    <div className="flex items-center gap-2">
                        <Botao altura="pequeno" icone="fa-chevron-left" onClick={() => andar(-1)}>{t('Anterior')}</Botao>
                        <span className="min-w-[10rem] text-center text-sm font-bold capitalize text-slate-700">
                            {d.periodo.nome_do_mes} {d.periodo.ano}
                        </span>
                        <Botao altura="pequeno" icone="fa-chevron-right" onClick={() => andar(1)}>{t('Seguinte')}</Botao>
                    </div>
                }
            >
                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Ano')}</span>
                        <input type="number" min="2000" max="2100" value={ano} onChange={(e) => porAno(Number(e.target.value))}
                            className={cls(entrada, 'tabular-nums')} />
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Mês')}</span>
                        <select value={String(mes)} onChange={(e) => porMes(Number(e.target.value))} className={cls(entrada, 'tabular-nums')}>
                            {Array.from({ length: MESES }, (_, i) => i + 1).map((m) => (
                                <option key={m} value={m}>{String(m).padStart(2, '0')}</option>
                            ))}
                        </select>
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Departamento')}</span>
                        <select value={departamento} onChange={(e) => porDepartamento(e.target.value)} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {d.departamentos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </label>
                </div>
            </Cartao>

            {/* O AVISO QUE EVITA UMA ENTREGA ERRADA. */}
            {d.ignoradas.length > 0 && (
                <div role="alert" className={cls('animate-fade-in border border-amber-300 bg-amber-50 p-4 text-amber-900', RAIO)}>
                    <p className="font-bold">
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t(':n folha(s) deste mês ficaram de fora do mapa', { n: d.ignoradas.length })}
                    </p>
                    <p className="mt-1 text-sm">
                        {t('Só entram as folhas aprovadas ou pagas. Enquanto estas não forem aprovadas, o imposto delas não está aqui.')}
                    </p>
                    <ul className="mt-2 flex flex-wrap gap-2">
                        {d.ignoradas.map((f) => (
                            <li key={f.numero}>
                                <Etiqueta cor="aviso" icone="fa-file">{f.numero} · {t(f.estado === 'draft' ? 'Rascunho' : f.estado)}</Etiqueta>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', q.isFetching && 'opacity-70')}>
                <CartaoNumero aspecto="claro" rotulo={t('Trabalhadores')} tom="indigo" icone="fa-users"
                    nota={t(':n isento(s)', { n: inteiro(d.totais.isentos) })} valor={inteiro(d.totais.trabalhadores)} />
                <CartaoNumero aspecto="claro" rotulo={t('Matéria colectável')} tom="azul" icone="fa-scale-balanced"
                    sufixo="Kz" valor={kz(d.totais.base)} />
                <CartaoNumero aspecto="claro" rotulo={t('INSS retido')} tom="ambar" icone="fa-shield-halved"
                    sufixo="Kz" valor={kz(d.totais.inss)} />
                {/* O NÚMERO QUE SE ENTREGA. */}
                <CartaoNumero aspecto="claro" rotulo={t('IRT a entregar')} tom="verde" icone="fa-landmark"
                    sufixo="Kz" nota={d.periodo.etiqueta} valor={kz(d.totais.irt)} />
            </div>

            {d.linhas.length === 0 ? (
                <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                    <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                        <i className="fas fa-landmark text-4xl text-slate-300" aria-hidden="true" />
                    </div>
                    <p className="text-lg font-bold text-slate-800">{t('Nada retido neste mês')}</p>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                        {t('Ou não há folha aprovada deste mês, ou nenhum trabalhador atingiu o limite de incidência.')}
                    </p>
                </div>
            ) : (
                <Cartao titulo={t('Retenção por trabalhador')} icone="fa-list" semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[58rem] text-sm">
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <th className="px-4 py-3 font-bold">{t('Funcionário')}</th>
                                    <th className="px-4 py-3 font-bold">{t('NIF')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Segurança Social')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Bruto')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('INSS')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Matéria colectável')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Taxa efectiva')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('IRT retido')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.linhas.map((l, i) => (
                                    <tr key={l.employee_id} className="entra transition-colors hover:bg-cyan-50/50"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-semibold text-slate-900">{l.nome}</span>
                                            <span className="block font-mono text-xs text-slate-400">
                                                {l.numero}{l.departamento ? ` · ${l.departamento}` : ''}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-slate-600">{l.nif || '—'}</td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-slate-600">{l.seguranca || '—'}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-700">{kz(l.bruto)}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-500">{kz(l.inss)}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-700">{kz(l.base)}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {l.isento ? (
                                                <Etiqueta cor="neutra">{t('Isento')}</Etiqueta>
                                            ) : (
                                                <span className="tabular-nums text-slate-600">
                                                    {l.taxa.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })}%
                                                </span>
                                            )}
                                        </td>
                                        <td className={cls('px-4 py-2.5 text-right font-bold tabular-nums', l.isento ? 'text-slate-300' : 'text-cyan-800')}>
                                            {kz(l.irt)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-slate-200 bg-slate-50 font-bold">
                                <tr>
                                    <td className="px-4 py-3" colSpan={3}>
                                        {t(':n trabalhador(es), :t tributado(s)', { n: inteiro(d.totais.trabalhadores), t: inteiro(d.totais.tributados) })}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{kz(d.totais.bruto)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{kz(d.totais.inss)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{kz(d.totais.base)}</td>
                                    <td />
                                    <td className="px-4 py-3 text-right tabular-nums text-cyan-900">{kz(d.totais.irt)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {d.folhas.length > 0 && (
                        <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                            <i className="fas fa-file-invoice-dollar mr-1.5" aria-hidden="true" />
                            {t('Do que está nas folhas :lista.', { lista: d.folhas.map((f) => f.numero).join(', ') })}
                        </p>
                    )}
                </Cartao>
            )}
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6', FOCO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o mapa de IRT')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
