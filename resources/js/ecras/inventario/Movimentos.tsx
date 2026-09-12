import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import { inventario } from '@/api/compras';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS MOVIMENTOS DE STOCK — a resposta a «porque é que mudou?».
 *
 * SÓ LÊ, e é de propósito: não há um único botão que escreva. Tudo o que mexe
 * no stock desta casa passa por aqui — vendas, compras, transferências,
 * quebras, contagens — e o stock corrige-se onde ele é feito, nunca num ecrã
 * de consulta.
 *
 * A ORIGEM TEM NOME DE GENTE. `restaurant_waste` e `quebra_anulada` são código
 * de máquina, e quem lê a lista não tem de saber o que isso quer dizer.
 */

const COR_DO_TIPO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    in: 'bom',
    out: 'perigo',
    transfer: 'primaria',
    adjustment: 'aviso',
};

const ICONE_DO_TIPO: Record<string, string> = {
    in: 'fa-arrow-down',
    out: 'fa-arrow-up',
    transfer: 'fa-right-left',
    adjustment: 'fa-sliders',
};

const HOJE = () => new Date().toISOString().slice(0, 10);
const HA_30_DIAS = () => {
    const d = new Date();

    d.setDate(d.getDate() - 30);

    return d.toISOString().slice(0, 10);
};

export default function Movimentos() {
    const [filtros, porFiltros] = useState<{
        procura?: string; tipo?: string; armazem?: number | '';
        de: string; ate: string; por_pagina?: number; page?: number;
    }>({ de: HA_30_DIAS(), ate: HOJE(), tipo: 'todos', por_pagina: 30, page: 1 });

    const lista = useQuery({
        queryKey: ['inventario', 'movimentos', filtros],
        queryFn: () => inventario.movimentos(filtros),
    });

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    const { data, meta, resumo, opcoes } = lista.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Movimentos de Stock')}
                subtitulo={t('Porque é que mudou — tudo o que mexe no stock passa por aqui')}
                icone="fa-right-left"
                cor="teal"
                accoes={
                    <>
                        <a href="/inventario/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chart-pie" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                        <a href="/inventario/contagem" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-clipboard-check" aria-hidden="true" />
                            {t('Contagem física')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-right-left">
                        {t(':n movimentos no período', { n: numero(resumo.movimentos) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero aspecto="claro" rotulo={t('Movimentos')} valor={numero(resumo.movimentos)}
                    icone="fa-right-left" tom="teal" />
                <CartaoNumero aspecto="claro" rotulo={t('Entradas')} valor={numero(resumo.entradas)}
                    icone="fa-arrow-down" tom="verde" />
                <CartaoNumero aspecto="claro" rotulo={t('Saídas')} valor={numero(resumo.saidas)}
                    icone="fa-arrow-up" tom="vermelho" />
            </div>

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Nome ou código do artigo…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('De')} className="w-40">
                    <input type="date" value={filtros.de}
                        onChange={(e) => porFiltros({ ...filtros, de: e.target.value, page: 1 })}
                        className={entrada} />
                </Campo>

                <Campo etiqueta={t('Até')} className="w-40">
                    <input type="date" value={filtros.ate}
                        onChange={(e) => porFiltros({ ...filtros, ate: e.target.value, page: 1 })}
                        className={entrada} />
                </Campo>

                <Campo etiqueta={t('Tipo')} className="w-40">
                    <select value={filtros.tipo ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, tipo: e.target.value, page: 1 })}
                        className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        {opcoes.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Armazém')} className="w-48">
                    <select value={filtros.armazem ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, armazem: e.target.value ? Number(e.target.value) : '', page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {opcoes.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {data.length === 0 ? (
                <SemNada
                    icone="fa-right-left"
                    titulo={t('Nenhum movimento no período')}
                    frase={t('Alargue as datas ou limpe os filtros. Tudo o que mexe no stock deixa rasto aqui.')}
                />
            ) : (
                <div className={cls('overflow-x-auto', CARTAO)}>
                    <table className="w-full min-w-[56rem] text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left">{t('Quando')}</th>
                                <th className="px-4 py-3 text-left">{t('Artigo')}</th>
                                <th className="px-4 py-3 text-left">{t('Tipo')}</th>
                                <th className="px-4 py-3 text-right">{t('Quantidade')}</th>
                                <th className="px-4 py-3 text-left">{t('Armazém')}</th>
                                <th className="px-4 py-3 text-left">{t('Origem')}</th>
                                <th className="px-4 py-3 text-left">{t('Quem')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.map((m, i) => (
                                <tr key={m.id} style={cascata(Math.min(i, 15))} className="entra transition hover:bg-slate-50/70">
                                    <td className="px-4 py-2.5 tabular-nums text-slate-600">{m.quando}</td>
                                    <td className="px-4 py-2.5">
                                        <p className="font-medium text-slate-800">{m.artigo ?? '—'}</p>
                                        {m.codigo && <p className="font-mono text-xs text-slate-400">{m.codigo}</p>}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <Etiqueta cor={COR_DO_TIPO[m.tipo] ?? 'neutra'} icone={ICONE_DO_TIPO[m.tipo]}>
                                            {m.tipo_rotulo}
                                        </Etiqueta>
                                    </td>
                                    <td className={cls('px-4 py-2.5 text-right font-bold tabular-nums',
                                        m.tipo === 'out' ? 'text-red-600' : m.tipo === 'in' ? 'text-emerald-600' : 'text-slate-700')}>
                                        {numero(m.quantidade)} {m.unidade}
                                    </td>
                                    <td className="px-4 py-2.5 text-slate-600">{m.armazem ?? '—'}</td>
                                    <td className="px-4 py-2.5">
                                        <span className="text-slate-600">{m.origem_rotulo}</span>
                                        {m.referencia && (
                                            <span className="ml-1.5 font-mono text-xs text-slate-400">#{m.referencia}</span>
                                        )}
                                        {m.notas && <p className="truncate text-xs text-slate-400">{m.notas}</p>}
                                    </td>
                                    <td className="px-4 py-2.5 text-slate-600">{m.quem ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {meta.last_page > 1 && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-slate-500">
                        {t('A mostrar :de a :ate de :total', {
                            de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                        })}
                    </p>
                    <div className="flex items-center gap-2">
                        <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
                        <Botao altura="pequeno" icone="fa-chevron-left" disabled={meta.current_page <= 1}
                            onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                            aria-label={t('Página anterior')} />
                        <span className="text-xs font-semibold tabular-nums text-slate-600">
                            {meta.current_page}/{meta.last_page}
                        </span>
                        <Botao altura="pequeno" icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page}
                            onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                            aria-label={t('Página seguinte')} />
                    </div>
                </div>
            )}

            <p className={cls('px-4 py-3 text-xs text-slate-500', CARTAO)}>
                <i className="fas fa-circle-info mr-2 text-slate-400" aria-hidden="true" />
                {t('Este ecrã só lê. O stock corrige-se onde ele é feito — na contagem física, na recepção, na quebra.')}
            </p>
        </div>
    );
}
