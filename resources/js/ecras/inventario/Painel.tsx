import { useQuery } from '@tanstack/react-query';

import { inventario } from '@/api/compras';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeDuasSeries } from '@/ui/GraficoDeDuasSeries';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DO INVENTÁRIO — quanto vale, o que está errado, o que se perdeu.
 *
 *   1. VALOR — o stock a custo: o dinheiro parado nas prateleiras;
 *   2. ERRADO — os NEGATIVOS, que são impossíveis físicos: cada um é uma venda
 *      de coisa que o sistema diz não existir;
 *   3. PERDIDO — as quebras do mês;
 *   4. QUANDO — a última contagem por armazém, porque UM INVENTÁRIO NUNCA
 *      CONTADO É UM NÚMERO EM QUE NINGUÉM DEVE CONFIAR.
 */
export default function PainelDoInventario() {
    const painel = useQuery({
        queryKey: ['inventario', 'painel'],
        queryFn: () => inventario.painel(),
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
                titulo={t('Painel do Inventário')}
                subtitulo={t('Quanto vale, o que está errado, o que se perdeu')}
                icone="fa-warehouse"
                cor="teal"
                accoes={
                    <>
                        <a href="/inventario/movimentos" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-right-left" aria-hidden="true" />
                            {t('Movimentos')}
                        </a>
                        <a href="/inventario/contagem" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-clipboard-check" aria-hidden="true" />
                            {t('Contagem física')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-boxes-stacked">
                        {t(':n artigos geridos', { n: numero(r.artigos_geridos) })}
                    </EstadoNaFaixa>
                    {r.negativos > 0 && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">
                            {t(':n negativos', { n: numero(r.negativos) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Valor do stock')} valor={kz(r.valor)} sufixo="Kz"
                    icone="fa-sack-dollar" tom="teal"
                    nota={t('A custo, o que está parado nas prateleiras')}
                />
                {/*
                  * OS NEGATIVOS SÃO IMPOSSÍVEIS FÍSICOS. Cada um é uma venda de
                  * coisa que o sistema diz não existir — e um número que já
                  * mentiu uma vez continua a mentir até alguém o corrigir.
                  */}
                <CartaoNumero
                    aspecto="claro" rotulo={t('Negativos')} valor={numero(r.negativos)}
                    icone="fa-triangle-exclamation" tom={r.negativos > 0 ? 'vermelho' : 'verde'}
                    nota={t('Impossíveis físicos — vendeu-se o que não havia')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('A zero')} valor={numero(r.a_zero)}
                    icone="fa-box-open" tom={r.a_zero > 0 ? 'ambar' : 'verde'}
                    nota={t('Geridos e esgotados — o POS não os mostra')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Quebras do mês')} valor={kz(r.quebras_mes)} sufixo="Kz"
                    icone="fa-trash" tom="vermelho"
                    nota={t('O que se partiu, estragou ou desapareceu')}
                />
            </div>

            {p.negativos.length > 0 && (
                <Cartao
                    titulo={t('Os artigos em negativo')}
                    subtitulo={t('Do pior para o menos mau — cada um é uma correcção por fazer')}
                    icone="fa-triangle-exclamation"
                    semPadding
                >
                    <ul className="divide-y divide-slate-100">
                        {p.negativos.map((n, i) => (
                            <li key={n.nome + i} style={cascata(i)} className="entra flex items-center gap-3 px-5 py-2.5">
                                <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-red-50 text-red-600">
                                    <i className="fas fa-minus" aria-hidden="true" />
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm font-medium text-slate-800">{n.nome}</span>
                                <span className="flex-none text-sm font-bold tabular-nums text-red-600">
                                    {numero(n.quantidade)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Cartao>
            )}

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao
                    titulo={t('Onde está o dinheiro')}
                    subtitulo={t('Os artigos que mais valem, parados')}
                    icone="fa-coins"
                >
                    <GraficoHorizontal
                        titulo={t('Valor em stock por artigo')}
                        vazio={t('Ainda não há stock com custo.')}
                        dados={p.mais_valiosos.map((m) => ({ rotulo: m.nome, valor: m.valor }))}
                    />
                </Cartao>

                <Cartao
                    titulo={t('O pulso do armazém')}
                    subtitulo={t('Entradas contra saídas, nos últimos 30 dias')}
                    icone="fa-right-left"
                >
                    <GraficoDeDuasSeries
                        titulo={t('Movimentos por dia')}
                        primeira={t('Entradas')}
                        segunda={t('Saídas')}
                        dados={p.movimentos.etiquetas.map((e, i) => ({
                            rotulo: e,
                            a: p.movimentos.entradas[i] ?? 0,
                            b: p.movimentos.saidas[i] ?? 0,
                        }))}
                    />
                </Cartao>
            </div>

            {/*
              * A IDADE DA VERDADE. Um inventário contado há seis meses não é o
              * mesmo número que um contado ontem — e esta é a única secção do
              * painel que diz em que medida é que se pode confiar no resto.
              */}
            <Cartao
                titulo={t('A última contagem de cada armazém')}
                subtitulo={t('Um inventário nunca contado é um número em que ninguém deve confiar')}
                icone="fa-clipboard-check"
                semPadding
            >
                {p.contagens.length === 0 ? (
                    <div className="p-5">
                        <SemNada
                            icone="fa-clipboard-check"
                            titulo={t('Nunca se contou nada')}
                            frase={t('Uma contagem física compara a prateleira com o sistema — e é o que torna o valor do stock uma verdade e não uma estimativa.')}
                            accao={
                                <a href="/inventario/contagem" className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-cyan-600 px-4 text-sm font-semibold text-white shadow-md')}>
                                    <i className="fas fa-clipboard-check" aria-hidden="true" />
                                    {t('Contar agora')}
                                </a>
                            }
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {p.contagens.map((c, i) => (
                            <li key={c.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-teal-50 text-teal-600">
                                    <i className="fas fa-warehouse" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{c.armazem ?? '—'}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {t(':a acertos · :v Kz de diferença', {
                                            a: String(c.acertos), v: kz(c.custo, 0),
                                        })}
                                    </p>
                                </div>
                                <Etiqueta
                                    cor={(c.dias ?? 0) > 180 ? 'perigo' : (c.dias ?? 0) > 90 ? 'aviso' : 'bom'}
                                    icone="fa-calendar-day"
                                >
                                    {c.dias === null
                                        ? '—'
                                        : c.dias === 0
                                            ? t('Hoje')
                                            : t('Há :n dias', { n: String(c.dias) })}
                                </Etiqueta>
                            </li>
                        ))}
                    </ul>
                )}
            </Cartao>
        </div>
    );
}
