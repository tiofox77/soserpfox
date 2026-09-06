import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { relatorios } from '@/api/relatorios';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { RAIO, cls } from '@/ui/tokens';
import { formatar, valor } from './Relatorio';

/**
 * O RELATÓRIO EM GRÁFICOS: a mesma facturação dos outros mapas, vista de
 * relance. Os números vêm do mesmo serviço do ecrã de sempre; aqui só se
 * escolhe o período e desenham-se as séries.
 */
type Serie = { rotulos?: string[]; valores?: number[]; [k: string]: unknown };

const serie = (s: unknown, chave = 'valores'): Array<{ rotulo: string; valor: number }> => {
    const x = (s ?? {}) as Serie;
    const rotulos = Array.isArray(x.rotulos) ? x.rotulos : [];
    const valores = Array.isArray(x[chave]) ? (x[chave] as unknown[]) : [];
    return rotulos.map((r, i) => ({ rotulo: String(r), valor: Number(valores[i] ?? 0) }));
};

export default function Graficos() {
    const [filtros, porFiltros] = useState<Record<string, string>>({});
    const q = useQuery({ queryKey: ['relatorio', 'charts', filtros], queryFn: () => relatorios.ler('charts', filtros), placeholderData: keepPreviousData });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir os gráficos</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const { esquema: e, dados, atalhos } = q.data;
    const g = (dados.g ?? {}) as Record<string, unknown>;
    const intervalo = dados.intervalo;

    const paineis: Array<{ titulo: string; dados: Array<{ rotulo: string; valor: number }> }> = [
        { titulo: 'Evolução das vendas', dados: serie(g.evolucao) },
        { titulo: 'Vendas', dados: serie(g.vendasCompras, 'vendas') },
        { titulo: 'Compras', dados: serie(g.vendasCompras, 'compras') },
        { titulo: 'Facturado', dados: serie(g.cobranca, 'facturado') },
        { titulo: 'Recebido', dados: serie(g.cobranca, 'recebido') },
        { titulo: 'Top clientes', dados: serie(g.topClientes) },
        { titulo: 'Top produtos', dados: serie(g.topProdutos) },
        { titulo: 'Vendas por vendedor', dados: serie(g.vendedores) },
        { titulo: 'Estado das facturas', dados: serie(g.estados) },
        { titulo: 'Recebimentos por meio', dados: serie(g.meiosPagamento) },
        { titulo: 'Vendas por dia da semana', dados: serie(g.diasDaSemana) },
        { titulo: 'IVA liquidado', dados: serie(g.iva, 'liquidado') },
        { titulo: 'IVA suportado', dados: serie(g.iva, 'suportado') },
    ];

    return (
        <div className={cls('space-y-4', q.isFetching && 'opacity-70')} data-relatorio="charts">
            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-chart-area text-slate-400" aria-hidden="true" />{e.titulo}</span>}
                accoes={<span className="flex gap-2"><a href="/invoicing/reports/novo-ecra" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-arrow-left" aria-hidden="true" />Todos os relatórios</a><Botao icone="fa-print" onClick={() => window.print()}>Imprimir</Botao></span>}
            >
                <div className="grid gap-3 sm:grid-cols-3">
                    <Campo etiqueta="Período">
                        <select value={filtros.period ?? 'year'} onChange={(ev) => porFiltros({ period: ev.target.value })} className={entrada}>
                            {atalhos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta="De"><input type="date" value={filtros.dateFrom ?? intervalo?.de ?? ''} onChange={(ev) => porFiltros((f) => ({ ...f, period: 'custom', dateFrom: ev.target.value, dateTo: f.dateTo ?? intervalo?.ate ?? '' }))} className={entrada} /></Campo>
                    <Campo etiqueta="Até"><input type="date" value={filtros.dateTo ?? intervalo?.ate ?? ''} onChange={(ev) => porFiltros((f) => ({ ...f, period: 'custom', dateTo: ev.target.value, dateFrom: f.dateFrom ?? intervalo?.de ?? '' }))} className={entrada} /></Campo>
                </div>
            </Cartao>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-cartoes>
                {e.cartoes.map((c) => (
                    <div key={c.chave} className={cls('border-l-4 border-indigo-500 bg-white p-4 shadow-sm', RAIO)}>
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{c.rotulo}</p>
                        <p className="mt-1 text-xl font-bold tabular-nums text-slate-900">{formatar(valor(dados, c.chave), c.formato ?? 'dinheiro')}</p>
                    </div>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-2" data-graficos>
                {paineis.map((p) => (
                    <Cartao key={p.titulo} titulo={p.titulo}>
                        <GraficoDeBarras dados={p.dados} titulo={p.titulo} />
                    </Cartao>
                ))}
            </div>
        </div>
    );
}
