import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { relatorios } from '@/api/relatorios';
import { ErroDaApi } from '@/api/cliente';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { CartaoDoMapa, valor } from './Relatorio';
import { ACCAO_DA_FAIXA, Faixa, SemNada, cascata } from './faixa';

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
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os gráficos')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const { esquema: e, dados, atalhos } = q.data;
    const g = (dados.g ?? {}) as Record<string, unknown>;
    const intervalo = dados.intervalo;
    // O mesmo teste do ecrã de sempre: zero documentos, nada para desenhar.
    const semNumeros = Number(valor(dados, 'g.resumo.documentos') ?? 0) === 0;

    const paineis: Array<{ titulo: string; dados: Array<{ rotulo: string; valor: number }> }> = [
        { titulo: t('Evolução das vendas'), dados: serie(g.evolucao) },
        { titulo: t('Vendas'), dados: serie(g.vendasCompras, 'vendas') },
        { titulo: t('Compras'), dados: serie(g.vendasCompras, 'compras') },
        { titulo: t('Facturado'), dados: serie(g.cobranca, 'facturado') },
        { titulo: t('Recebido'), dados: serie(g.cobranca, 'recebido') },
        { titulo: t('Top clientes'), dados: serie(g.topClientes) },
        { titulo: t('Top produtos'), dados: serie(g.topProdutos) },
        { titulo: t('Vendas por vendedor'), dados: serie(g.vendedores) },
        { titulo: t('Estado das facturas'), dados: serie(g.estados) },
        { titulo: t('Recebimentos por meio'), dados: serie(g.meiosPagamento) },
        { titulo: t('Vendas por dia da semana'), dados: serie(g.diasDaSemana) },
        { titulo: t('IVA liquidado'), dados: serie(g.iva, 'liquidado') },
        { titulo: t('IVA suportado'), dados: serie(g.iva, 'suportado') },
    ];

    return (
        <div className={cls('space-y-4', q.isFetching && 'opacity-70')} data-relatorio="charts">
            <Faixa
                icone="fa-chart-line"
                titulo={e.titulo}
                subtitulo={e.descricao ?? t('A facturação vista de relance')}
                accoes={
                    <>
                        <a href="/invoicing/reports" className={ACCAO_DA_FAIXA}><i className="fas fa-arrow-left" aria-hidden="true" />{t('Todos os relatórios')}</a>
                        <button type="button" onClick={() => window.print()} className={ACCAO_DA_FAIXA}><i className="fas fa-print" aria-hidden="true" />{t('Imprimir')}</button>
                    </>
                }
            />

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Campo etiqueta={t('Período')}>
                        <select value={filtros.period ?? 'year'} onChange={(ev) => porFiltros({ period: ev.target.value })} className={entrada}>
                            {atalhos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('De')}><input type="date" value={filtros.dateFrom ?? intervalo?.de ?? ''} onChange={(ev) => porFiltros((f) => ({ ...f, period: 'custom', dateFrom: ev.target.value, dateTo: f.dateTo ?? intervalo?.ate ?? '' }))} className={entrada} /></Campo>
                    <Campo etiqueta={t('Até')}><input type="date" value={filtros.dateTo ?? intervalo?.ate ?? ''} onChange={(ev) => porFiltros((f) => ({ ...f, period: 'custom', dateTo: ev.target.value, dateFrom: f.dateFrom ?? intervalo?.de ?? '' }))} className={entrada} /></Campo>
                </div>
            </Cartao>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-cartoes>
                {e.cartoes.map((c, i) => (
                    <div key={c.chave} className="entra" style={cascata(i)}>
                        <CartaoDoMapa rotulo={c.rotulo} cor={c.cor} formato={c.formato} valor={valor(dados, c.chave)} />
                    </div>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-2" data-graficos>
                {/* Sem um único documento no período não há série nenhuma para
                    desenhar: treze caixas com um eixo vazio não dizem isso —
                    dizem que a página está avariada. */}
                {semNumeros ? (
                    <div className={cls('bg-white shadow-sm lg:col-span-2', RAIO)}>
                        <SemNada
                            icone="fa-chart-simple"
                            titulo={t('Sem facturação neste período')}
                            frase={t('Escolha outro intervalo de datas acima.')}
                        />
                    </div>
                ) : (
                    paineis.map((p, i) => (
                        <div key={p.titulo} className="entra" style={cascata(i)}>
                            <Cartao titulo={p.titulo} icone="fa-chart-column">
                                <GraficoDeBarras dados={p.dados} titulo={p.titulo} />
                            </Cartao>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
