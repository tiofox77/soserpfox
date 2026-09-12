import { useQuery } from '@tanstack/react-query';

import { projetos, type LinhaDoPainel } from '@/api/projetos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DOS PROJETOS — o que está a fugir.
 *
 * Três coisas fogem num projeto, e são estas três:
 *
 *  · o ORÇAMENTO que está a ser ultrapassado — e a barra diz de relance quais;
 *  · o PRAZO das tarefas que já passou;
 *  · e as HORAS TRABALHADAS QUE NINGUÉM FACTUROU: dinheiro que a casa já
 *    gastou a fazer e nunca cobrou. É o cartão que mais vezes surpreende.
 */
export default function PainelDosProjetos() {
    const painel = useQuery({
        queryKey: ['projetos', 'painel'],
        queryFn: () => projetos.painel(),
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
                titulo={t('Painel dos Projetos')}
                subtitulo={t('O orçamento, o prazo e o que falta cobrar')}
                icone="fa-diagram-project"
                cor="roxo"
                accoes={
                    <>
                        <a href="/projetos/lista" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-folder-open" aria-hidden="true" />
                            {t('Projetos')}
                        </a>
                        <a href="/projetos/timesheet" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-clock" aria-hidden="true" />
                            {t('Folha de horas')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-list-check">
                        {t(':n tarefas abertas', { n: numero(r.tarefas_abertas) })}
                    </EstadoNaFaixa>
                    {r.tarefas_atrasadas > 0 && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">
                            {t(':n atrasadas', { n: numero(r.tarefas_atrasadas) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Projetos activos')} valor={numero(r.activos)}
                    icone="fa-diagram-project" tom="roxo"
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Horas no mês')} valor={numero(r.horas_mes)} sufixo="h"
                    icone="fa-clock" tom="indigo"
                />
                {/*
                  * O CARTÃO QUE SURPREENDE: horas trabalhadas, facturáveis, com
                  * preço, e sem factura nenhuma. É dinheiro que a casa já gastou
                  * a fazer e nunca cobrou.
                  */}
                <CartaoNumero
                    aspecto="claro" rotulo={t('Por facturar')} valor={kz(r.valor_por_facturar)} sufixo="Kz"
                    icone="fa-file-invoice" tom={r.valor_por_facturar > 0 ? 'ambar' : 'teal'}
                    nota={t(':n horas trabalhadas e não cobradas', { n: numero(r.horas_por_facturar) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Tarefas atrasadas')} valor={numero(r.tarefas_atrasadas)}
                    icone="fa-triangle-exclamation" tom={r.tarefas_atrasadas > 0 ? 'vermelho' : 'verde'}
                    nota={t('De :n abertas', { n: numero(r.tarefas_abertas) })}
                />
            </div>

            {/* OS ESTOUROS VÊM PRIMEIRO: é a única coisa desta página que já
                custou dinheiro e continua a custar. */}
            {p.estouros.length > 0 && (
                <Cartao
                    titulo={t('Projetos acima do orçamento')}
                    subtitulo={t('Já passaram o tecto — cada hora a mais sai do lucro')}
                    icone="fa-fire"
                    semPadding
                >
                    <ul className="divide-y divide-slate-100">
                        {p.estouros.map((x, i) => <LinhaDoProjeto key={x.id} projeto={x} posicao={i} />)}
                    </ul>
                </Cartao>
            )}

            <Cartao
                titulo={t('Os projetos a andar')}
                subtitulo={t('Com o consumo do orçamento à vista')}
                icone="fa-folder-open"
                semPadding
            >
                {p.activos.length === 0 ? (
                    <div className="p-5">
                        <SemNada
                            icone="fa-folder-plus"
                            titulo={t('Nenhum projeto a andar')}
                            frase={t('Um projeto guarda o orçamento e o preço/hora — e é contra ele que as horas se lançam.')}
                            accao={
                                <a href="/projetos/lista" className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 px-4 text-sm font-semibold text-white shadow-md', FOCO)}>
                                    <i className="fas fa-plus" aria-hidden="true" />
                                    {t('Criar projeto')}
                                </a>
                            }
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {p.activos.map((x, i) => <LinhaDoProjeto key={x.id} projeto={x} posicao={i} />)}
                    </ul>
                )}
            </Cartao>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao
                    titulo={t('As tarefas com o prazo passado')}
                    subtitulo={t('Da mais antiga para a mais recente')}
                    icone="fa-triangle-exclamation"
                    semPadding
                >
                    {p.atrasadas.length === 0 ? (
                        <div className="p-5">
                            <SemNada icone="fa-circle-check" titulo={t('Nada atrasado')}
                                frase={t('Todas as tarefas com prazo estão dentro dele.')} />
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.atrasadas.map((x, i) => (
                                <li key={x.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                    <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-red-50 text-red-600">
                                        <i className="fas fa-clock" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">{x.titulo}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {[x.projeto, x.responsavel].filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                    <Etiqueta cor="perigo" icone="fa-hourglass-end">
                                        {t(':n dias', { n: numero(x.dias) })}
                                    </Etiqueta>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao
                    titulo={t('Horas dos últimos 6 meses')}
                    subtitulo={t('Os meses sem horas aparecem a zero')}
                    icone="fa-chart-column"
                >
                    <GraficoDeBarras
                        titulo={t('Horas por mês')}
                        dados={p.horas_por_mes.etiquetas.map((e, i) => ({
                            rotulo: e, valor: p.horas_por_mes.valores[i] ?? 0,
                        }))}
                    />
                </Cartao>
            </div>
        </div>
    );
}

/**
 * Uma linha com a BARRA DO ORÇAMENTO.
 *
 * A percentagem sozinha é um número; a barra diz de relance quais os projetos
 * que estão a fugir. E SEM ORÇAMENTO NÃO HÁ BARRA — «0%» seria mentira, e uma
 * barra vazia num projeto sem tecto engana mais do que não mostrar nada.
 */
function LinhaDoProjeto({ projeto, posicao }: { projeto: LinhaDoPainel; posicao: number }) {
    const p = projeto.percentagem;

    return (
        <li style={cascata(posicao)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
            <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-purple-50 text-purple-600">
                <i className="fas fa-folder" aria-hidden="true" />
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-slate-800">{projeto.nome}</p>
                <p className="truncate text-xs text-slate-500">
                    {[projeto.codigo, projeto.cliente].filter(Boolean).join(' · ')}
                </p>
            </div>

            <div className="w-36 flex-none">
                {p === null ? (
                    <p className="text-xs italic text-slate-400">{t('Sem orçamento definido')}</p>
                ) : (
                    <>
                        <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                className={cls(
                                    'h-full rounded-full transition-all duration-500',
                                    p > 100 ? 'bg-red-500' : p > 80 ? 'bg-amber-500' : 'bg-emerald-500',
                                )}
                                style={{ width: `${Math.min(100, Math.max(2, p))}%` }}
                            />
                        </div>
                        <p className="mt-1 text-[10px] font-semibold tabular-nums text-slate-500">
                            {t(':n% de :v Kz', { n: String(p), v: kz(projeto.orcamento, 0) })}
                        </p>
                    </>
                )}
            </div>

            <span className="flex-none text-right">
                <span className="block text-sm font-bold tabular-nums text-slate-900">{kz(projeto.gasto)}</span>
                <span className="block text-[10px] text-slate-500 tabular-nums">
                    {t(':n h', { n: String(projeto.horas) })}
                </span>
            </span>

            <a
                href={`/projetos/lista?projeto=${projeto.id}`}
                className={cls('grid h-9 w-9 flex-none place-items-center border border-slate-200 bg-white text-slate-600 transition hover:border-purple-300 hover:text-purple-600', RAIO, FOCO)}
                aria-label={t('Abrir o projeto')}
            >
                <i className="fas fa-arrow-right" aria-hidden="true" />
            </a>
        </li>
    );
}
