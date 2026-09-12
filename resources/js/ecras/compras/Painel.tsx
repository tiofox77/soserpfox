import { useQuery } from '@tanstack/react-query';

import { compras } from '@/api/compras';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DAS COMPRAS — o que está parado à minha espera.
 *
 * A pergunta a que este ecrã responde NÃO é «quanto comprámos»: é «o que é que
 * está à espera de mim». Requisições por decidir, encomendas atrasadas,
 * mercadoria recebida ainda por facturar. Um painel de compras que só some
 * valores serve para o relatório do fim do mês e para mais nada.
 */
export default function PainelDasCompras() {
    const painel = useQuery({
        queryKey: ['compras', 'painel'],
        queryFn: () => compras.painel(),
        refetchInterval: 120_000,
    });

    if (painel.isPending) return <Carregando linhas={8} />;
    if (painel.isError) return <AvisoDeErro erro={painel.error} />;

    const p = painel.data;
    const r = p.resumo;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Painel das Compras')}
                subtitulo={t('O que está parado à minha espera')}
                icone="fa-cart-shopping"
                cor="bom"
                accoes={
                    <>
                        <a href="/compras/requisicoes" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-clipboard-list" aria-hidden="true" />
                            {t('Requisições')}
                        </a>
                        <a href="/compras/encomendas" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-truck" aria-hidden="true" />
                            {t('Encomendas')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    {r.por_decidir > 0 && (
                        <EstadoNaFaixa icone="fa-gavel">
                            {t(':n por decidir', { n: numero(r.por_decidir) })}
                        </EstadoNaFaixa>
                    )}
                    {r.atrasadas > 0 && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">
                            {t(':n encomendas atrasadas', { n: numero(r.atrasadas) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Por decidir')} valor={numero(r.por_decidir)}
                    icone="fa-gavel" tom={r.por_decidir > 0 ? 'ambar' : 'teal'}
                    nota={t(':n aprovadas por encomendar', { n: numero(r.por_encomendar) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Encomendas em curso')} valor={numero(r.em_curso)}
                    icone="fa-truck" tom="indigo"
                    nota={t(':v Kz encomendados', { v: kz(r.valor_em_curso, 0) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Atrasadas')} valor={numero(r.atrasadas)}
                    icone="fa-triangle-exclamation" tom={r.atrasadas > 0 ? 'vermelho' : 'verde'}
                    nota={t('Prometidas para trás e ainda por chegar')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Por facturar')} valor={numero(r.por_facturar)}
                    icone="fa-file-invoice" tom={r.por_facturar > 0 ? 'ambar' : 'teal'}
                    nota={t('Chegou e ainda não há factura do fornecedor')}
                />
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                {/* AS DECISÕES VÊM PRIMEIRO: é o que impede outra pessoa de
                    trabalhar enquanto ninguém carrega no botão. */}
                <Cartao
                    titulo={t('À espera de decisão')}
                    subtitulo={t('Requisições submetidas por aprovar ou recusar')}
                    icone="fa-gavel"
                    semPadding
                >
                    {p.a_decidir.length === 0 ? (
                        <div className="p-5">
                            <SemNada icone="fa-circle-check" titulo={t('Nada por decidir')}
                                frase={t('Quando alguém submeter uma requisição, ela aparece aqui.')} />
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.a_decidir.map((x, i) => (
                                <li key={x.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                    <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-amber-50 text-amber-600">
                                        <i className="fas fa-clipboard-list" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-mono text-sm font-semibold text-slate-800">{x.numero}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {[x.autor, t(':n linhas', { n: String(x.linhas) })]
                                                .filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                    {x.necessaria_em && (
                                        <Etiqueta cor="aviso" icone="fa-calendar-day">{x.necessaria_em}</Etiqueta>
                                    )}
                                    <a
                                        href={`/compras/requisicoes?requisicao=${x.id}`}
                                        className={cls('grid h-9 w-9 flex-none place-items-center border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-300 hover:text-emerald-600', RAIO, FOCO)}
                                        aria-label={t('Abrir a requisição')}
                                    >
                                        <i className="fas fa-arrow-right" aria-hidden="true" />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao
                    titulo={t('Encomendas atrasadas')}
                    subtitulo={t('A data prometida passou e a mercadoria não chegou')}
                    icone="fa-truck-fast"
                    semPadding
                >
                    {p.atrasadas.length === 0 ? (
                        <div className="p-5">
                            <SemNada icone="fa-circle-check" titulo={t('Nada atrasado')}
                                frase={t('Todas as encomendas em curso estão dentro da data prometida.')} />
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.atrasadas.map((x, i) => (
                                <li key={x.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                    <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-red-50 text-red-600">
                                        <i className="fas fa-truck" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-mono text-sm font-semibold text-slate-800">{x.numero}</p>
                                        <p className="truncate text-xs text-slate-500">{x.fornecedor ?? '—'}</p>
                                    </div>
                                    <Etiqueta cor="perigo" icone="fa-hourglass-end">
                                        {t(':n dias', { n: numero(x.dias) })}
                                    </Etiqueta>
                                    <span className="flex-none text-sm font-bold tabular-nums text-slate-900">
                                        {kz(x.total)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao
                    titulo={t('Gasto encomendado por mês')}
                    subtitulo={t('Os meses sem encomendas aparecem a zero')}
                    icone="fa-chart-column"
                >
                    <GraficoDeBarras
                        titulo={t('Encomendado por mês')}
                        dados={p.por_mes.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_mes.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao
                    titulo={t('A quem mais compramos')}
                    subtitulo={t('Este ano, por valor encomendado')}
                    icone="fa-ranking-star"
                >
                    <GraficoHorizontal
                        titulo={t('Compras por fornecedor')}
                        vazio={t('Ainda não há encomendas este ano.')}
                        dados={p.fornecedores.etiquetas.map((e, i) => ({
                            rotulo: e, valor: p.fornecedores.valores[i] ?? 0,
                        }))}
                    />
                </Cartao>
            </div>
        </div>
    );
}
