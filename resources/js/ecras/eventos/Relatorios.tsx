import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import { eventos } from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';
import { cor } from './Painel';

/**
 * OS RELATÓRIOS DOS EVENTOS.
 *
 * O ECRÃ ANTIGO TINHA DOIS BOTÕES QUE NÃO FAZIAM NADA: «PDF» e «Excel»
 * chamavam métodos que só diziam «ainda não existe». Ficou o CSV, que funciona
 * e abre em qualquer folha de cálculo — um botão que avisa que não faz nada
 * continua a ser um botão a mais. Para PDF, imprime-se a página.
 */

const HOJE = () => new Date().toISOString().slice(0, 10);
const HA_UM_ANO = () => {
    const d = new Date();

    d.setMonth(d.getMonth() - 12);

    return d.toISOString().slice(0, 10);
};

export default function Relatorios() {
    const [filtros, porFiltros] = useState<{
        de: string; ate: string; cliente?: number | ''; tipo?: number | ''; estado?: string;
    }>({ de: HA_UM_ANO(), ate: HOJE() });

    const relatorio = useQuery({
        queryKey: ['eventos', 'relatorios', filtros],
        queryFn: () => eventos.relatorios(filtros),
    });

    if (relatorio.isPending) return <Carregando linhas={8} />;
    if (relatorio.isError) return <AvisoDeErro erro={relatorio.error} />;

    const r = relatorio.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const csv = new URLSearchParams(
        Object.entries(filtros).reduce<Record<string, string>>((acc, [k, v]) => {
            if (v !== '' && v !== undefined && v !== null) acc[k] = String(v);

            return acc;
        }, {}),
    );

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Relatórios de Eventos')}
                subtitulo={t('O que se fez, para quem, e quanto rendeu')}
                icone="fa-chart-bar"
                cor="roxo"
                accoes={
                    <>
                        <a
                            href={`/api/v1/invoicing/react/eventos/relatorios/csv?${csv.toString()}`}
                            className={ACCAO_DA_FAIXA}
                        >
                            <i className="fas fa-file-csv" aria-hidden="true" />
                            {t('Descarregar CSV')}
                        </a>
                        <a href="/events/calendar" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-calendar-days" aria-hidden="true" />
                            {t('Agenda')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-calendar-check">
                        {t(':n eventos no período', { n: numero(r.resumo.eventos) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('De')} className="w-40">
                    <input type="date" value={filtros.de}
                        onChange={(e) => porFiltros({ ...filtros, de: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Até')} className="w-40">
                    <input type="date" value={filtros.ate}
                        onChange={(e) => porFiltros({ ...filtros, ate: e.target.value })} className={entrada} />
                </Campo>

                <Campo etiqueta={t('Cliente')} className="w-52">
                    <select value={filtros.cliente ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, cliente: e.target.value ? Number(e.target.value) : '' })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {r.opcoes.clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Tipo')} className="w-48">
                    <select value={filtros.tipo ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, tipo: e.target.value ? Number(e.target.value) : '' })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {r.opcoes.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-44">
                    <select value={filtros.estado ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {r.opcoes.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </Campo>

                <Botao icone="fa-rotate-left" onClick={() => porFiltros({ de: HA_UM_ANO(), ate: HOJE() })}>
                    {t('Limpar')}
                </Botao>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Eventos')} valor={numero(r.resumo.eventos)}
                    icone="fa-calendar-check" tom="roxo"
                    nota={t(':c concluídos · :x cancelados', {
                        c: numero(r.resumo.concluidos), x: numero(r.resumo.cancelados),
                    })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Valor')} valor={kz(r.resumo.valor)} sufixo="Kz"
                    icone="fa-sack-dollar" tom="verde"
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Valor médio')} valor={kz(r.resumo.valor_medio)} sufixo="Kz"
                    icone="fa-scale-balanced" tom="indigo"
                    nota={t('Por evento do período')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Pessoas')} valor={numero(r.resumo.pessoas)}
                    icone="fa-users" tom="ambar"
                    nota={t('Somando o que cada evento esperava')}
                />
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao titulo={t('Eventos por mês')} icone="fa-chart-column" subtitulo={t('Dentro do período escolhido')}>
                    <GraficoDeBarras
                        titulo={t('Eventos por mês')}
                        dados={r.por_mes.etiquetas.map((e, i) => ({ rotulo: e, valor: r.por_mes.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Os clientes que mais encomendam')} icone="fa-ranking-star">
                    <GraficoHorizontal
                        titulo={t('Eventos por cliente')}
                        unidade=""
                        vazio={t('Nenhum evento no período.')}
                        dados={r.por_cliente.etiquetas.map((e, i) => ({ rotulo: e, valor: r.por_cliente.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Que tipo de eventos se faz')} icone="fa-tags">
                    <GraficoHorizontal
                        titulo={t('Eventos por tipo')}
                        unidade=""
                        vazio={t('Nenhum evento no período.')}
                        dados={r.por_tipo.etiquetas.map((e, i) => ({ rotulo: e, valor: r.por_tipo.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Como acabaram')} icone="fa-chart-pie" subtitulo={t('Os cancelados dizem tanto como os concluídos')}>
                    <GraficoHorizontal
                        titulo={t('Eventos por estado')}
                        unidade=""
                        vazio={t('Nenhum evento no período.')}
                        dados={r.por_estado.etiquetas.map((e, i) => ({ rotulo: e, valor: r.por_estado.valores[i] ?? 0 }))}
                    />
                </Cartao>
            </div>

            <Cartao
                titulo={t('Evento a evento')}
                subtitulo={t('Os 200 mais recentes do período — o CSV leva todos')}
                icone="fa-list"
                semPadding
            >
                {r.data.length === 0 ? (
                    <div className="p-5">
                        <SemNada icone="fa-calendar-xmark" titulo={t('Nenhum evento no período')}
                            frase={t('Alargue as datas ou limpe os filtros.')} />
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[52rem] text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-left">{t('Número')}</th>
                                    <th className="px-4 py-3 text-left">{t('Evento')}</th>
                                    <th className="px-4 py-3 text-left">{t('Cliente')}</th>
                                    <th className="px-4 py-3 text-left">{t('Local')}</th>
                                    <th className="px-4 py-3 text-left">{t('Início')}</th>
                                    <th className="px-4 py-3 text-left">{t('Estado')}</th>
                                    <th className="px-4 py-3 text-right">{t('Pessoas')}</th>
                                    <th className="px-4 py-3 text-right">{t('Valor')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {r.data.map((e, i) => (
                                    <tr key={e.id} style={cascata(Math.min(i, 15))} className="entra transition hover:bg-slate-50/70">
                                        <td className="px-4 py-2.5 font-mono text-xs text-slate-500">{e.numero}</td>
                                        <td className="px-4 py-2.5 font-medium text-slate-800">
                                            {e.tipo_icone && <span className="mr-1.5">{e.tipo_icone}</span>}
                                            {e.nome}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">{e.cliente ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-slate-600">{e.local ?? '—'}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-slate-600">{e.inicio ?? '—'}</td>
                                        <td className="px-4 py-2.5">
                                            <Etiqueta cor={cor(e.estado)}>{e.estado_rotulo}</Etiqueta>
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{e.pessoas || '—'}</td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">{kz(e.valor)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>

            <p className={cls('px-4 py-3 text-xs text-slate-500', CARTAO)}>
                <i className="fas fa-circle-info mr-2 text-slate-400" aria-hidden="true" />
                {t('Para PDF, imprima a página — o CSV abre no Excel e traz todos os eventos do período.')}
            </p>
        </div>
    );
}
