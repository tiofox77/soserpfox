import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { painel, type NumerosDoPainel } from '@/api/painel';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { entrada } from '@/ui/Campo';
import { Etiqueta } from '@/ui/Etiqueta';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoDeDuasSeries } from '@/ui/GraficoDeDuasSeries';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { CARTAO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t, tPartes } from '@/i18n';
import { avisar } from '@/casca/avisos';

import { exportarCsvDoPainel, exportarPdfDoPainel } from './exportarPainel';

/**
 * O PAINEL DA FACTURAÇÃO.
 *
 * Não faz contas nenhumas: os números vêm do `PainelDaFacturacao`, a fonte
 * única destas somas. Era essa a condição para o painel e os relatórios não se
 * contradizerem.
 *
 * E a regra que esses números guardam: conta-se pelo SALDO, nunca pelo nome do
 * estado. Nomear os estados que contam foi o que partiu o painel de origem —
 * dizia 76 mil por cobrar quando havia 15 milhões.
 *
 * O PERÍODO É DO SERVIDOR, não deste ecrã. Aqui escolhe-se e pede-se outra
 * vez; os cartões, o gráfico e o título vêm todos da mesma resposta, e por
 * isso não podem discordar sobre o período que estão a mostrar — era isso que
 * o selector do painel de sempre garantia e a migração tinha perdido.
 */
export default function Painel() {
    const [periodo, porPeriodo] = useState<string | undefined>(undefined);

    const numeros = useQuery({
        queryKey: ['painel', periodo ?? 'omissao'],
        queryFn: () => painel.numeros(periodo),
        staleTime: 60_000,
        // Trocar de período não deve apagar o painel inteiro: os números
        // anteriores ficam à vista até os novos chegarem.
        placeholderData: keepPreviousData,
    });

    if (numeros.isPending) {
        return <Carregando linhas={8} />;
    }

    if (numeros.isError) {
        return <Falhou erro={numeros.error} />;
    }

    const d = numeros.data;

    /*
     * O RÓTULO DO PRIMEIRO CARTÃO SEGUE O PERÍODO.
     *
     * O mês tem frase própria — «Faturação do Mês», que o dicionário conhece
     * nas três línguas e que se lê melhor do que a forma genérica. Os outros
     * períodos usam a forma com o rótulo por dentro, que o servidor manda já
     * traduzido. O que não pode acontecer é o cartão dizer «do Mês» com um ano
     * inteiro somado lá dentro.
     */
    const rotuloDoFacturado = d.periodo.valor === 'month'
        ? t('Faturação do Mês')
        : t('Faturação :ano', { ano: d.periodo.rotulo });

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-slate-500">
                    {t('Visão geral do módulo de faturação - :periodo', { periodo: d.periodo.rotulo })}
                </p>
                <select
                    aria-label={t('Período')}
                    data-periodo
                    value={d.periodo.valor}
                    onChange={(ev) => porPeriodo(ev.target.value)}
                    className={cls(entrada, 'sm:w-56')}
                >
                    {/* Os rótulos vêm traduzidos do servidor: uma segunda lista
                        de períodos aqui acabaria a divergir da dos relatórios. */}
                    {d.periodo.opcoes.map((o) => (
                        <option key={o.valor} value={o.valor}>
                            {o.rotulo}
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Numero
                    rotulo={rotuloDoFacturado}
                    valor={d.stats.total_invoiced}
                    variacao={d.stats.growth}
                    comparacao={d.periodo.rotulo_anterior}
                />
                <Numero
                    rotulo={t('Recebimentos')}
                    valor={d.stats.total_received}
                    nota={t('Pagamentos recebidos')}
                    cor="bom"
                />
                {/* O que se deve não é uma grandeza de período: estes dois são o
                    retrato de hoje, e a lista lá em baixo segue-os. */}
                <Numero
                    rotulo={t('Valores Pendentes')}
                    valor={d.stats.total_pending}
                    nota={t('Aguardando pagamento')}
                    cor="aviso"
                />
                <Numero
                    rotulo={t('Valores Vencidos')}
                    valor={d.stats.total_overdue}
                    nota={t('Requer atenção')}
                    cor="perigo"
                />
            </div>

            {/* O PERÍODO ANTERIOR EQUIVALENTE, e o ano até ao mesmo dia.
                Comparar um ano a meio com um ano inteiro daria sempre uma queda
                que não existe. */}
            <Cartao
                titulo={t('Evolução de Vendas - :periodo', { periodo: d.periodo.rotulo })}
                accoes={<Exportar />}
            >
                <div className="mb-5 flex flex-wrap items-baseline gap-x-6 gap-y-2">
                    <div>
                        <span className="text-2xl font-bold tabular-nums text-slate-900">
                            {kz(d.stats.total_invoiced)}
                        </span>
                        <span className="ml-1 text-sm text-slate-500">Kz · {d.periodo.rotulo}</span>
                    </div>
                    <div className="text-sm text-slate-500">
                        {d.periodo.rotulo_anterior}:{' '}
                        <span className="tabular-nums">{kz(d.stats.total_invoiced_previous)}</span>
                    </div>
                    <Variacao valor={d.stats.growth} />
                </div>

                <GraficoDeBarras dados={d.serie} titulo={t('Vendas (AOA)')} />
            </Cartao>

            {/* OS TRÊS GRÁFICOS DO PERÍODO — os que o painel em Blade tinha por
                baixo dos cartões e a migração deixou para trás. Vêm do mesmo
                serviço que desenha o ecrã de gráficos, para os dois nunca
                divergirem. */}
            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao
                    titulo={t('Como Recebemos')}
                    icone="fa-money-check-dollar"
                    subtitulo={t('Recebimentos por forma de pagamento')}
                >
                    <GraficoHorizontal
                        dados={d.graficos.meios_de_pagamento}
                        titulo={t('Como Recebemos')}
                        vazio={t('Sem recebimentos no período.')}
                    />
                </Cartao>

                <Cartao titulo={t('Top Produtos')} icone="fa-star" subtitulo={t('Por valor vendido')}>
                    <GraficoHorizontal
                        dados={d.graficos.top_produtos}
                        titulo={t('Top Produtos')}
                        vazio={t('Sem vendas no período.')}
                    />
                </Cartao>
            </div>

            <Cartao
                titulo={t('Vendas vs. Compras')}
                icone="fa-scale-balanced"
                subtitulo={t('A folga entre o que entra e o que sai')}
                accoes={
                    <a
                        href="/invoicing/reports/charts"
                        className="group inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:text-indigo-800"
                    >
                        {t('Ver todos os gráficos')}
                        <i
                            className="fas fa-arrow-right transition-transform duration-300 group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </a>
                }
            >
                <GraficoDeDuasSeries
                    dados={d.graficos.vendas_contra_compras.map((p) => ({
                        rotulo: p.rotulo,
                        a: p.vendas,
                        b: p.compras,
                    }))}
                    titulo={t('Vendas vs. Compras')}
                    primeira={t('Vendas')}
                    segunda={t('Compras')}
                />

                <Folga pontos={d.graficos.vendas_contra_compras} />
            </Cartao>

            {/* A COMPARAÇÃO ANO A ANO. Os dois anos já vinham na resposta e
                ninguém os desenhava — o painel de sempre fechava o mês com
                este confronto, e é ele que diz se o ano está a correr melhor. */}
            <ComparacaoAnoAAno esteAno={d.por_mes} anoPassado={d.por_mes_ano_passado} />

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Estado das Faturas')}>
                    {/* Não se sobrepõem: uma factura cai numa caixa e numa só. */}
                    <p className="mb-3 text-xs text-slate-500">{t('Vencidas contadas à parte')}</p>
                    <div className="grid grid-cols-2 gap-3">
                        <Caixa rotulo={t('Pagas')} valor={d.estado_das_facturas.paid} cor="bom" />
                        <Caixa rotulo={t('Pendentes')} valor={d.estado_das_facturas.pending} cor="primaria" />
                        <Caixa
                            rotulo={t('Parc. Pagas')}
                            valor={d.estado_das_facturas.partially_paid}
                            cor="aviso"
                        />
                        <Caixa rotulo={t('Vencidas')} valor={d.estado_das_facturas.overdue} cor="perigo" />
                    </div>

                    <div className="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        <Conta rotulo={t('Faturas')} valor={d.documentos.invoices} />
                        <Conta rotulo={t('Recibos')} valor={d.documentos.receipts} />
                        <Conta rotulo={t('Notas Crédito')} valor={d.documentos.credit_notes} />
                        <Conta rotulo={t('Notas Débito')} valor={d.documentos.debit_notes} />
                        <Conta rotulo={t('Adiantamentos')} valor={d.documentos.advances} />
                    </div>
                </Cartao>

                <Cartao titulo={t('Top 5 Clientes')}>
                    {d.melhores_clientes.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">
                            {t('Sem dados de clientes')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {d.melhores_clientes.map((c, i) => (
                                <li key={i} className="flex items-center justify-between gap-4 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {c.cliente}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {t(':quantos documento(s)', { quantos: c.documentos })}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-sm font-bold tabular-nums text-slate-900">
                                        {kz(c.total)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            <Cartao titulo={t('Faturas Pendentes')} semPadding>
                {d.por_cobrar.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-slate-400">
                        {t('Nenhuma fatura pendente')}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">{t('Número')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Cliente')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Vencimento')}</th>
                                    <th className="px-4 py-3 text-right font-semibold">{t('Saldo')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.por_cobrar.map((f) => (
                                    <tr key={f.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <a
                                                href={`/invoicing/sales/invoices/${f.id}/preview`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="font-semibold text-indigo-700 hover:underline"
                                            >
                                                {f.numero}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{f.cliente}</td>
                                        <td className="px-4 py-3">
                                            <span className="tabular-nums text-slate-600">
                                                {data(f.vencimento)}
                                            </span>
                                            {f.vencida && (
                                                <Etiqueta cor="perigo">
                                                    <span className="ml-2">{t('Vencida')}</span>
                                                </Etiqueta>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                                            {kz(f.saldo)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>

            <ActividadesRecentes linhas={d.actividades} />

            <TextosDaExportacao numeros={d} />
        </div>
    );
}

/* ─── As peças que faltavam ao painel ─────────────────────────────────── */

/**
 * A FOLGA, POR EXTENSO.
 *
 * O gráfico mostra as duas barras; isto diz o que elas somam e a diferença
 * entre elas — que é o número que se procura quando se abre este cartão. Uma
 * folga negativa não é um erro (comprou-se para stock), mas assinala-se.
 */
function Folga({ pontos }: { pontos: NumerosDoPainel['graficos']['vendas_contra_compras'] }) {
    if (pontos.length === 0) {
        return null;
    }

    const vendas = pontos.reduce((s, p) => s + p.vendas, 0);
    const compras = pontos.reduce((s, p) => s + p.compras, 0);
    const folga = vendas - compras;

    return (
        <div className="mt-4 flex flex-wrap items-baseline gap-x-6 gap-y-2 border-t border-slate-100 pt-4 text-sm">
            <span className="text-slate-500">
                {t('Vendas')}: <strong className="tabular-nums text-slate-800">{kz(vendas)}</strong>
            </span>
            <span className="text-slate-500">
                {t('Compras')}: <strong className="tabular-nums text-slate-800">{kz(compras)}</strong>
            </span>
            <span
                className={cls(
                    'flex items-center gap-1.5 font-bold tabular-nums',
                    folga >= 0 ? 'text-emerald-600' : 'text-red-600',
                )}
            >
                <i className={`fas ${folga >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}`} aria-hidden="true" />
                {t('Folga')}: {kz(folga)}
            </span>
        </div>
    );
}

/**
 * O ANO A ANO — este ano contra o passado, mês a mês.
 *
 * Os dois anos já vinham na resposta desde o primeiro dia e nenhum ecrã os
 * desenhava. O crescimento tem TRÊS estados e não dois: zero não é uma queda, e
 * pintá-lo de vermelho fazia parecer mau um mês igual ao do ano passado.
 */
function ComparacaoAnoAAno({
    esteAno,
    anoPassado,
}: {
    esteAno: NumerosDoPainel['por_mes'];
    anoPassado: NumerosDoPainel['por_mes_ano_passado'];
}) {
    const total = esteAno.reduce((s, m) => s + m.valor, 0);
    const totalPassado = anoPassado.reduce((s, m) => s + m.valor, 0);

    // Sem base de comparação não há percentagem que signifique alguma coisa:
    // «+∞%» sobre zero não se escreve.
    const crescimento = totalPassado > 0 ? ((total - totalPassado) / totalPassado) * 100 : null;

    const pontos = esteAno.map((m, i) => ({
        rotulo: m.rotulo,
        a: m.valor,
        b: anoPassado[i]?.valor ?? 0,
    }));

    const anoActual = new Date().getFullYear();

    return (
        <Cartao
            titulo={t('Comparação Ano a Ano')}
            icone="fa-calendar-days"
            subtitulo={t('Mês a mês, este ano contra o passado')}
        >
            <div className="mb-4 flex flex-wrap items-baseline gap-x-6 gap-y-2 text-sm">
                <span className="text-slate-500">
                    {anoActual}: <strong className="tabular-nums text-slate-800">{kz(total)}</strong>
                </span>
                <span className="text-slate-500">
                    {anoActual - 1}: <strong className="tabular-nums text-slate-800">{kz(totalPassado)}</strong>
                </span>
                {crescimento === null ? (
                    <span className="text-xs text-slate-400">{t('Sem ano anterior para comparar')}</span>
                ) : (
                    <span className="flex items-center gap-1.5 text-sm font-bold">
                        <span className="text-slate-500">{t('Crescimento')}:</span>
                        <Variacao valor={crescimento} />
                    </span>
                )}
            </div>

            <GraficoDeDuasSeries
                dados={pontos}
                titulo={t('Comparação Ano a Ano')}
                primeira={String(anoActual)}
                segunda={String(anoActual - 1)}
            />
        </Cartao>
    );
}

/**
 * AS ACTIVIDADES RECENTES — as últimas facturas criadas.
 *
 * É com esta lista que o painel de sempre fechava, e é ela que responde a «o
 * que é que se andou a fazer aqui hoje». O serviço já as contava; a API é que
 * não as entregava.
 */
function ActividadesRecentes({ linhas }: { linhas: NumerosDoPainel['actividades'] }) {
    return (
        <Cartao titulo={t('Atividades Recentes')} icone="fa-clock-rotate-left">
            {linhas.length === 0 ? (
                <div className="py-10 text-center">
                    <div className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-slate-100">
                        <i className="fas fa-inbox text-3xl text-slate-300" aria-hidden="true" />
                    </div>
                    <p className="text-sm text-slate-400">{t('Sem atividades recentes')}</p>
                </div>
            ) : (
                <ul className="max-h-96 space-y-2 overflow-y-auto">
                    {linhas.map((a, i) => (
                        <li
                            key={a.id}
                            className="entra flex items-center gap-3 rounded-xl bg-slate-50 p-3 transition-all duration-200 hover:bg-indigo-50/70"
                            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                        >
                            <span
                                className="grid h-10 w-10 flex-none place-items-center rounded-full bg-indigo-100 text-indigo-600"
                                aria-hidden="true"
                            >
                                <i className="fas fa-file-invoice" />
                            </span>

                            <div className="min-w-0 flex-1">
                                {/* A ordem «Fatura X criada» não se mantém noutras
                                    línguas: a frase viaja inteira e o número é
                                    substituído dentro dela, nunca colado de fora. */}
                                <p className="text-sm text-slate-800">
                                    {tPartes('Fatura :numero criada', {
                                        numero: (
                                            <a
                                                key="n"
                                                href={`/invoicing/sales/invoices/${a.id}/preview`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="font-bold text-indigo-700 hover:underline"
                                            >
                                                {a.numero}
                                            </a>
                                        ),
                                    })}
                                </p>
                                <p className="truncate text-xs text-slate-500">
                                    {t('Cliente: :nome', { nome: a.cliente })}
                                    {a.quando && ` • ${a.quando}`}
                                </p>
                            </div>

                            <Etiqueta cor={corDoEstado(a.cor)}>{a.estado}</Etiqueta>
                        </li>
                    ))}
                </ul>
            )}
        </Cartao>
    );
}

/**
 * A cor do estado, do servidor para a paleta das etiquetas.
 *
 * O modelo devolve nomes de cor do Tailwind («yellow», «indigo») porque era
 * assim que o Blade os interpolava numa classe. Aqui traduz-se para os tons da
 * `Etiqueta` — interpolar uma classe em Tailwind não funciona sem build, e um
 * tom desconhecido cai no neutro em vez de sair sem cor nenhuma.
 */
function corDoEstado(cor: string): 'bom' | 'aviso' | 'perigo' | 'primaria' | 'neutra' {
    switch (cor) {
        case 'green':
            return 'bom';
        case 'yellow':
            return 'aviso';
        case 'red':
            return 'perigo';
        case 'blue':
        case 'indigo':
        case 'purple':
            return 'primaria';
        default:
            return 'neutra';
    }
}

/* ─── Exportar ────────────────────────────────────────────────────────── */

/**
 * OS DOIS BOTÕES QUE O PAINEL EM BLADE TINHA, e que a migração perdeu.
 *
 * O PDF e o CSV vivem em `exportarPainel.ts` (eram o `painel-facturacao.js`
 * do layout). Uma falha a gerar o PDF diz-se num aviso, em vez de morrer calada.
 */
function Exportar() {
    return (
        <>
            <Botao
                cor="perigo"
                altura="pequeno"
                icone="fa-file-pdf"
                onClick={() => void exportarPdfDoPainel().catch(() => avisar(t('Não foi possível gerar o PDF.'), 'erro'))}
            >
                {t('Exportar PDF')}
            </Botao>
            {/* «Excel» fica fora do t(): é nome de produto, como FOX Friendly.
                Escreve-se igual nas três línguas. */}
            <Botao cor="bom" altura="pequeno" icone="fa-file-excel" onClick={exportarCsvDoPainel}>
                Excel
            </Botao>
        </>
    );
}

/**
 * A MESA POSTA PARA O EXPORTADOR: os mesmos nós, com os mesmos nomes.
 *
 * O exportador (`exportarPainel.ts`) lê tudo do DOM, como lia o antigo
 * `painel-facturacao.js`: o ecrã escreve os nós e ele encontra-os.
 *
 *   `textosPainel` → a etiqueta do `Intl`, as frases já traduzidas e os
 *                    valores (formatados para o PDF, CRUS para o CSV).
 *   `dadosVendas`  → as linhas do CSV, uma por mês.
 *
 * O `dadosPainel` de propósito NÃO se escreve: era só para as roscas em
 * `<canvas>` que este ecrã não tem.
 *
 * OS VALORES DO CSV NÃO LEVAM SEPARADOR DE MILHARES. Um `1.234,56` dentro de
 * um ficheiro separado por vírgulas abre na folha de cálculo com uma coluna a
 * mais e o valor partido em dois — daí o `toFixed`, que escreve sempre
 * `1234.56` seja qual for a língua.
 */
function TextosDaExportacao({ numeros }: { numeros: NumerosDoPainel }) {
    const s = numeros.stats;
    const cru = (v: number) => (Number.isFinite(v) ? v : 0).toFixed(2);

    const textos = {
        intl: etiquetaIntl(),
        t: {
            vendasAoa: t('Vendas (AOA)'),
            vendas: t('Vendas'),
            compras: t('Compras'),
            titulo: t('Dashboard de Faturação'),
            // Sem substituição: o `:data` é o exportador que o preenche, com
            // a data do dia em que se carregou no botão.
            geradoEm: t('Gerado em: :data'),
            // O mesmo rótulo do cartão: o PDF exporta o período que está no
            // ecrã, e não um mês fixo que já não é o que se está a ver.
            facturado: numeros.periodo.valor === 'month'
                ? t('Faturação do Mês')
                : t('Faturação :ano', { ano: numeros.periodo.rotulo }),
            recebido: t('Recebimentos'),
            pendente: t('Valores Pendentes'),
            vencido: t('Valores Vencidos'),
            data: t('Data'),
            valorAoa: t('Valor (AOA)'),
            estatisticas: t('Estatísticas'),
        },
        valores: {
            facturado: kz(s.total_invoiced),
            recebido: kz(s.total_received),
            pendente: kz(s.total_pending),
            vencido: kz(s.total_overdue),
            facturadoCru: cru(s.total_invoiced),
            recebidoCru: cru(s.total_received),
            pendenteCru: cru(s.total_pending),
            vencidoCru: cru(s.total_overdue),
        },
    };

    /*
     * A MESMA LINHA QUE ESTÁ NO GRÁFICO, na forma que o exportador já sabe
     * ler: uma data e um total. Exportar o ano enquanto o ecrã mostra a semana
     * seria dar à folha de cálculo números que ninguém pediu.
     *
     * A data vem do servidor — é ele que sabe se o balde é um dia ou um mês. A
     * hora vai escrita para a data ser lida na zona de quem está a olhar: sem
     * ela, `2026-01-01` é meia-noite em UTC e a Ocidente do meridiano lê-se 31
     * de Dezembro.
     */
    const linhas = numeros.serie.map((m) => ({
        date: `${m.data}T00:00:00`,
        total: m.valor,
        rotulo: m.rotulo,
    }));

    return (
        <>
            <script type="application/json" id="textosPainel" dangerouslySetInnerHTML={emJson(textos)} />
            <script type="application/json" id="dadosVendas" dangerouslySetInnerHTML={emJson(linhas)} />
        </>
    );
}

/** JSON dentro de `<script>`: um `</script>` no meio dos dados fechava a etiqueta. */
function emJson(valor: unknown): { __html: string } {
    return { __html: JSON.stringify(valor).replace(/</g, '\\u003c') };
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

/**
 * OS QUATRO NÚMEROS DO TOPO — os cartões de gradiente de sempre.
 *
 * Eram assim no painel em Blade (`bg-gradient-to-br from-…-500 to-…-600`, com
 * o ícone num círculo translúcido) e foi o que mais se notou a faltar quando
 * o ecrã passou a React: quatro caixas brancas dizem os mesmos números e
 * parecem outro produto. A cor segue o significado — recebido é verde,
 * pendente é âmbar, vencido é vermelho — e leva ícone, porque a cor sozinha
 * não serve a quem não a distingue.
 */
function Numero({
    rotulo,
    valor,
    variacao,
    comparacao,
    nota,
    cor = 'primaria',
}: {
    rotulo: string;
    valor: number;
    variacao?: number;
    comparacao?: string;
    nota?: string;
    cor?: 'primaria' | 'bom' | 'aviso' | 'perigo';
}) {
    const tons = { primaria: 'indigo', bom: 'verde', aviso: 'ambar', perigo: 'vermelho' } as const;
    const icones = {
        primaria: 'fa-file-invoice-dollar',
        bom: 'fa-hand-holding-dollar',
        aviso: 'fa-clock',
        perigo: 'fa-triangle-exclamation',
    } as const;

    return (
        <CartaoNumero
            rotulo={rotulo}
            valor={kz(valor)}
            sufixo="Kz"
            tom={tons[cor]}
            icone={icones[cor]}
            nota={
                variacao !== undefined ? (
                    <span className="flex items-center gap-1.5">
                        <Variacao valor={variacao} claro />
                        {/* Contra o quê: o rótulo do período anterior vem do
                            servidor, já na língua de quem está a olhar. */}
                        {comparacao && <span className="text-white/60">· {comparacao}</span>}
                    </span>
                ) : (
                    nota
                )
            }
        />
    );
}

/**
 * A VARIAÇÃO É SÓ O NÚMERO, e quem diz contra o quê é o rótulo ao lado.
 *
 * Tinha a frase inteira no dicionário — «:pct% vs mês anterior» — porque
 * partida em dois pedaços obrigava o tradutor a adivinhar a ordem das
 * palavras. Deixou de servir quando o período passou a ser escolhido: a mesma
 * frase dizia «vs mês anterior» com uma semana ou um ano à frente. O rótulo do
 * período anterior vem agora do servidor, que é quem sabe qual é.
 */
function Variacao({ valor, claro = false }: { valor: number; claro?: boolean }) {
    // Zero não é subida nem descida, e pintá-lo de verde seria dizer que sim.
    if (Math.abs(valor) < 0.05) {
        return <span className={cls('text-xs font-semibold', claro ? 'text-white/70' : 'text-slate-400')}>{t('Sem alteração')}</span>;
    }

    const sobe = valor > 0;
    const pct = Math.abs(valor).toFixed(1);

    return (
        <span
            className={cls(
                'text-xs font-semibold tabular-nums',
                claro
                    ? 'rounded-full bg-white/20 px-1.5 py-0.5 text-white'
                    : sobe ? 'text-emerald-600' : 'text-red-600',
            )}
        >
            <i className={`fas fa-arrow-${sobe ? 'up' : 'down'} mr-1`} aria-hidden="true" />
            {pct}%
        </span>
    );
}

function Caixa({
    rotulo,
    valor,
    cor,
}: {
    rotulo: string;
    valor: number;
    cor: 'primaria' | 'bom' | 'aviso' | 'perigo';
}) {
    const fundo = {
        primaria: 'bg-indigo-50 text-indigo-800',
        bom: 'bg-emerald-50 text-emerald-800',
        aviso: 'bg-amber-50 text-amber-800',
        perigo: 'bg-red-50 text-red-800',
    } as const;

    return (
        <div className={cls('px-4 py-3', RAIO, fundo[cor])}>
            <p className="text-2xl font-bold tabular-nums">{valor}</p>
            <p className="text-xs font-medium">{rotulo}</p>
        </div>
    );
}

function Conta({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <span>
            <strong className="tabular-nums text-slate-900">{valor}</strong>{' '}
            <span className="text-slate-500">{rotulo}</span>
        </span>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">{t('A sessão expirou')}</h2>
                <p className="mb-4 text-sm text-amber-900">{t('Entre outra vez para continuar.')}</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    {t('Voltar a entrar')}
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível carregar o painel')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
