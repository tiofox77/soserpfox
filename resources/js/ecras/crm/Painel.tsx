import { useQuery } from '@tanstack/react-query';

import { crm } from '@/api/crm';
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
 * O PAINEL DO CRM — as quatro perguntas de segunda-feira de manhã.
 *
 *   1. quanto vale o funil aberto (e o PONDERADO, que é o honesto)?
 *   2. quanto se ganhou este mês, e a taxa de conversão?
 *   3. o que está ATRASADO — tarefas com prazo estourado?
 *   4. de onde vêm os leads (onde vale a pena gastar)?
 *
 * GANHO NÃO É COBRADO, e é a distinção que o cartão do meio faz: o funil sabia
 * dizer quanto se fechou e nunca quanto virou documento — que é a pergunta que
 * paga as contas.
 */
export default function PainelDoCrm() {
    const painel = useQuery({
        queryKey: ['crm', 'painel'],
        queryFn: () => crm.painel(),
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
                titulo={t('Painel do CRM')}
                subtitulo={t('O que está em jogo, e o que já fechou')}
                icone="fa-bullseye"
                cor="ciano"
                accoes={
                    <>
                        <a href="/crm/leads" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-user-plus" aria-hidden="true" />
                            {t('Leads')}
                        </a>
                        <a href="/crm/funil-vendas" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-filter" aria-hidden="true" />
                            {t('Funil')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-user-clock">
                        {t(':n leads por trabalhar', { n: numero(r.leads_abertos) })}
                    </EstadoNaFaixa>
                    {r.taxa !== null && (
                        <EstadoNaFaixa icone="fa-percent">
                            {t('Conversão: :n%', { n: String(r.taxa) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Funil aberto')} valor={kz(r.funil_valor)} sufixo="Kz"
                    icone="fa-filter" tom="indigo"
                    nota={t(':n negócios · ponderado :p Kz', {
                        n: numero(r.funil_contagem), p: kz(r.funil_ponderado, 0),
                    })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Ganho no mês')} valor={kz(r.ganho_mes)} sufixo="Kz"
                    icone="fa-trophy" tom="verde"
                    nota={t(':n negócios fechados', { n: numero(r.ganhas_mes) })}
                />
                {/*
                  * GANHO NÃO É COBRADO. Este cartão é a diferença entre o que se
                  * fechou e o que virou documento — e o número de baixo diz
                  * quantos negócios ganhos ainda não foram facturados.
                  */}
                <CartaoNumero
                    aspecto="claro" rotulo={t('Já facturado')} valor={kz(r.facturado_mes)} sufixo="Kz"
                    icone="fa-file-invoice" tom={r.por_facturar_mes > 0 ? 'ambar' : 'teal'}
                    nota={r.por_facturar_mes > 0
                        ? t(':n ganhos por facturar', { n: numero(r.por_facturar_mes) })
                        : t('Tudo o que se ganhou está facturado')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Leads novos no mês')} valor={numero(r.leads_novos_mes)}
                    icone="fa-user-plus" tom="roxo"
                    nota={t(':n ainda em jogo', { n: numero(r.leads_abertos) })}
                />
            </div>

            {/*
              * AS TAREFAS ATRASADAS VÊM PRIMEIRO: são a razão de abrir este ecrã,
              * e não um bloco no fundo da página que ninguém desce para ver.
              */}
            <Cartao
                titulo={t('O que está por fazer')}
                subtitulo={t('Por prazo — as atrasadas à cabeça')}
                icone="fa-list-check"
                semPadding
            >
                {p.tarefas.length === 0 ? (
                    <div className="p-5">
                        <SemNada
                            icone="fa-circle-check"
                            titulo={t('Nada por fazer')}
                            frase={t('Marque uma tarefa com prazo num lead ou numa oportunidade e ela aparece aqui.')}
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {p.tarefas.map((tarefa, i) => (
                            <li key={tarefa.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                <span className={cls(
                                    'grid h-9 w-9 flex-none place-items-center rounded-xl',
                                    tarefa.atrasada ? 'bg-red-50 text-red-600' : 'bg-cyan-50 text-cyan-600',
                                )}>
                                    <i className="fas fa-clock" aria-hidden="true" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{tarefa.assunto}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {[tarefa.tipo_rotulo, tarefa.lead, tarefa.oportunidade]
                                            .filter(Boolean).join(' · ')}
                                    </p>
                                </div>

                                <Etiqueta cor={tarefa.atrasada ? 'perigo' : 'neutra'} icone="fa-calendar-day">
                                    {tarefa.prazo ?? '—'}
                                </Etiqueta>
                            </li>
                        ))}
                    </ul>
                )}
            </Cartao>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao
                    titulo={t('O funil por etapa')}
                    subtitulo={t('Só o que está aberto')}
                    icone="fa-filter"
                >
                    <GraficoHorizontal
                        titulo={t('Valor por etapa')}
                        vazio={t('Nenhum negócio em aberto.')}
                        dados={p.por_etapa.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_etapa.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao
                    titulo={t('Ganhos dos últimos 6 meses')}
                    subtitulo={t('A linha que diz se está a melhorar')}
                    icone="fa-chart-line"
                >
                    <GraficoDeBarras
                        titulo={t('Ganho por mês')}
                        dados={p.ganhos_por_mes.etiquetas.map((e, i) => ({
                            rotulo: e, valor: p.ganhos_por_mes.valores[i] ?? 0,
                        }))}
                    />
                </Cartao>

                <Cartao
                    titulo={t('De onde vêm os leads')}
                    subtitulo={t('Últimos 90 dias — onde vale a pena gastar')}
                    icone="fa-signs-post"
                >
                    <GraficoHorizontal
                        titulo={t('Leads por origem')}
                        unidade=""
                        vazio={t('Ainda não há leads.')}
                        dados={p.por_origem.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_origem.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Os últimos negócios')} icone="fa-clock-rotate-left" semPadding>
                    {p.ultimas.length === 0 ? (
                        <div className="p-5">
                            <SemNada
                                icone="fa-handshake"
                                titulo={t('Ainda não há negócios')}
                                frase={t('Um lead qualificado converte-se em cliente e abre a primeira oportunidade.')}
                                accao={
                                    <a href="/crm/leads" className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 px-4 text-sm font-semibold text-white shadow-md', FOCO)}>
                                        <i className="fas fa-user-plus" aria-hidden="true" />
                                        {t('Leads')}
                                    </a>
                                }
                            />
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.ultimas.map((o, i) => (
                                <li key={o.id} style={cascata(i)} className="entra flex items-center gap-3 px-5 py-2.5">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-800">{o.titulo}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {[o.cliente, o.etapa].filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                    <span className={cls('flex-none text-sm font-bold tabular-nums text-slate-900', RAIO)}>
                                        {kz(o.valor)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>
        </div>
    );
}
