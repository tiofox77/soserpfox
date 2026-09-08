import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { LinhaDeCategoria, LinhaEmAberto, RelatorioDeTesouraria, TipoDeRelatorio } from '@/api/tesouraria';
import { relatoriosDaTesouraria } from '@/api/tesouraria';
import { t } from '@/i18n';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * OS RELATÓRIOS FINANCEIROS.
 *
 * AS CONTAS NÃO ESTÃO AQUI e nunca estiveram: vivem na
 * `RelatoriosDeTesouraria`, a mesma classe que gera o PDF e o Excel. Um
 * relatório que dá números diferentes no ecrã e no ficheiro é pior do que não
 * existir — alguém imprime, leva a uma reunião, e descobre em público que não
 * bate certo. Este ecrã escolhe o período e desenha o que vier.
 *
 * MEXER NAS DATAS À MÃO PASSA O PERÍODO A PERSONALIZADO. Sem isso, o ecrã
 * continuava a dizer «Este mês» com datas que já não eram as do mês — e a
 * etiqueta do relatório impresso mentia.
 */
const PERIODOS = [
    { valor: 'today', rotulo: 'Hoje' },
    { valor: 'week', rotulo: 'Esta semana' },
    { valor: 'month', rotulo: 'Este mês' },
    { valor: 'year', rotulo: 'Este ano' },
    { valor: 'custom', rotulo: 'Personalizado' },
] as const;

export default function Relatorios() {
    const [f, porF] = useState<{ tipo: TipoDeRelatorio; periodo: string; de?: string; ate?: string }>({
        tipo: 'cash_flow',
        periodo: 'month',
    });

    const r = useQuery({
        queryKey: ['tesouraria', 'relatorios', f],
        queryFn: () => relatoriosDaTesouraria.ler(f),
        placeholderData: keepPreviousData,
    });

    if (r.isPending) return <Carregando linhas={10} />;

    if (r.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios')}</h2>
                <p className="text-sm text-red-800">
                    {r.error instanceof ErroDaApi ? r.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const dados = r.data;

    /** Mexer numa data à mão passa o período a personalizado. */
    const mudarData = (campo: 'de' | 'ate', valor: string) =>
        porF((a) => ({ ...a, [campo]: valor, periodo: 'custom' }));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Relatórios Financeiros')}
                subtitulo={dados.titulo}
                icone="fa-chart-pie"
                cor="teal"
                accoes={
                    <>
                        {/* AS MORADAS VÊM DO SERVIDOR: o ecrã não sabe compor
                            o URL de um relatório, e assim não pode enganar-se. */}
                        <a href={dados.descargas.pdf} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-file-pdf" aria-hidden="true" />
                            {t('PDF')}
                        </a>
                        <a href={dados.descargas.excel} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-file-excel" aria-hidden="true" />
                            {t('Excel')}
                        </a>
                    </>
                }
            />

            <div className={cls(CARTAO, 'p-4')}>
                {/* OS QUATRO RELATÓRIOS. A lista vem do servidor — está na
                    mesma classe que faz as contas. */}
                <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('Relatório')}>
                    {dados.tipos.map((x) => (
                        <button
                            key={x.valor}
                            type="button"
                            role="tab"
                            aria-selected={dados.tipo === x.valor}
                            onClick={() => porF((a) => ({ ...a, tipo: x.valor }))}
                            className={cls(
                                'px-4 py-2 text-sm font-semibold transition-all duration-200',
                                RAIO,
                                FOCO,
                                dados.tipo === x.valor
                                    ? 'bg-gradient-to-r from-teal-600 to-cyan-600 text-white shadow-md'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                            )}
                        >
                            {x.rotulo}
                        </button>
                    ))}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Campo etiqueta={t('Período')}>
                        <select
                            value={f.periodo}
                            onChange={(e) => porF((a) => ({ ...a, periodo: e.target.value }))}
                            className={entrada}
                        >
                            {PERIODOS.map((x) => <option key={x.valor} value={x.valor}>{t(x.rotulo)}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('De')}>
                        <input type="date" value={f.de ?? dados.de} max={f.ate ?? dados.ate}
                            onChange={(e) => mudarData('de', e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Até')}>
                        <input type="date" value={f.ate ?? dados.ate} min={f.de ?? dados.de}
                            onChange={(e) => mudarData('ate', e.target.value)} className={entrada} />
                    </Campo>
                </div>
            </div>

            {dados.tipo === 'cash_flow' && <FluxoDeCaixa d={dados} />}
            {dados.tipo === 'dre' && <Resultados d={dados} />}
            {dados.tipo === 'receivables' && <EmAberto d={dados} lado="receivables" />}
            {dados.tipo === 'payables' && <EmAberto d={dados} lado="payables" />}
        </div>
    );
}

/* ─── Fluxo de caixa ──────────────────────────────────────────────────── */

function FluxoDeCaixa({ d }: { d: RelatorioDeTesouraria }) {
    const x = d.dados;

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Saldo inicial')} valor={kz(x.initialBalance ?? 0)} sufixo="Kz" icone="fa-flag" tom="cinza" aspecto="claro" />
                <CartaoNumero rotulo={t('Entradas')} valor={kz(x.totalIncome ?? 0)} sufixo="Kz" icone="fa-arrow-down" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('Saídas')} valor={kz(x.totalExpense ?? 0)} sufixo="Kz" icone="fa-arrow-up" tom="vermelho" aspecto="claro" />
                <CartaoNumero
                    rotulo={t('Saldo final')}
                    valor={kz(x.finalBalance ?? 0)}
                    sufixo="Kz"
                    icone="fa-flag-checkered"
                    tom={(x.finalBalance ?? 0) < 0 ? 'vermelho' : 'teal'}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('De onde veio')} icone="fa-arrow-down">
                    <PorCategoria linhas={x.incomeByCategory ?? []} total={x.totalIncome ?? 0} tom="verde" />
                </Cartao>
                <Cartao titulo={t('Para onde foi')} icone="fa-arrow-up">
                    <PorCategoria linhas={x.expenseByCategory ?? []} total={x.totalExpense ?? 0} tom="vermelho" />
                </Cartao>
            </div>
        </div>
    );
}

/* ─── Demonstração de resultados ──────────────────────────────────────── */

function Resultados({ d }: { d: RelatorioDeTesouraria }) {
    const x = d.dados;
    const lucro = x.netProfit ?? 0;

    return (
        <div className="space-y-4">
            <div className={cls(CARTAO, 'overflow-hidden')}>
                <table className="min-w-full text-sm">
                    <tbody className="divide-y divide-slate-100">
                        <Conta rotulo={t('Receita bruta')} valor={x.grossRevenue ?? 0} />
                        <Conta rotulo={t('Deduções (devoluções e descontos)')} valor={-(x.deductions ?? 0)} suave />
                        <Conta rotulo={t('Receita líquida')} valor={x.netRevenue ?? 0} forte />
                        <Conta rotulo={t('Custos operacionais (compras)')} valor={-(x.operationalCosts ?? 0)} />
                        <Conta rotulo={t('Lucro bruto')} valor={x.grossProfit ?? 0} forte />
                        <Conta rotulo={t('Despesas')} valor={-(x.totalExpenses ?? 0)} />
                        <Conta rotulo={t('Resultado operacional')} valor={x.operationalProfit ?? 0} forte />
                        <Conta rotulo={t('Resultado do período')} valor={lucro} destaque />
                    </tbody>
                </table>
            </div>

            {/* O QUE ESTE MAPA NÃO SABE. Dizê-lo é o que o distingue de um
                número inventado — as deduções ainda não contam as notas de
                crédito, e não há imposto. */}
            <div className={cls('flex items-start gap-3 border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800', RAIO)}>
                <i className="fas fa-circle-info mt-0.5" aria-hidden="true" />
                <span>
                    {t('Aproximação: as deduções ainda não contam as notas de crédito e o resultado não tem impostos deduzidos.')}
                </span>
            </div>

            <Cartao titulo={t('Despesas por categoria')} icone="fa-chart-pie">
                <PorCategoria linhas={x.expensesByCategory ?? []} total={x.totalExpenses ?? 0} tom="vermelho" />
            </Cartao>
        </div>
    );
}

/* ─── Contas a receber e a pagar ──────────────────────────────────────── */

function EmAberto({ d, lado }: { d: RelatorioDeTesouraria; lado: 'receivables' | 'payables' }) {
    const x = d.dados;
    const receber = lado === 'receivables';
    const linhas: LinhaEmAberto[] = (receber ? x.receivables : x.payables) ?? [];
    const total = (receber ? x.totalReceivables : x.totalPayables) ?? 0;
    const vencido = x.totalOverdue ?? 0;

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero
                    rotulo={receber ? t('Por receber') : t('Por pagar')}
                    valor={kz(total)}
                    sufixo="Kz"
                    icone={receber ? 'fa-hand-holding-dollar' : 'fa-money-bill-wave'}
                    tom={receber ? 'ambar' : 'vermelho'}
                />
                <CartaoNumero rotulo={t('Já vencido')} valor={kz(vencido)} sufixo="Kz" icone="fa-clock" tom={vencido > 0 ? 'vermelho' : 'cinza'} aspecto="claro" />
                <CartaoNumero rotulo={t('Documentos')} valor={linhas.length.toLocaleString('pt-PT')} icone="fa-file-lines" tom="cinza" aspecto="claro" />
            </div>

            <div className={cls(CARTAO, 'overflow-hidden')}>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-gradient-to-r from-teal-50 to-cyan-50">
                            <tr>
                                {[t('Documento'), receber ? t('Cliente') : t('Fornecedor'), t('Data'), t('Vencimento')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-teal-700">{c}</th>
                                ))}
                                {[t('Total'), t('Pago'), t('Saldo')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-teal-700">{c}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={7}>
                                        <SemNada
                                            icone="fa-circle-check"
                                            titulo={receber ? t('Nada por receber neste período') : t('Nada por pagar neste período')}
                                        />
                                    </td>
                                </tr>
                            )}

                            {linhas.map((l, i) => (
                                <tr key={`${l.invoice_number}-${i}`} style={cascata(i)} className="entra transition-colors hover:bg-teal-50/60">
                                    <td className="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{l.invoice_number}</td>
                                    <td className="max-w-xs truncate px-4 py-3 text-slate-700">{(receber ? l.client : l.supplier) ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{data(l.invoice_date)}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        {l.due_date ? (
                                            l.overdue
                                                ? <Etiqueta cor="perigo" icone="fa-clock">{data(l.due_date)}</Etiqueta>
                                                : <span className="text-slate-600">{data(l.due_date)}</span>
                                        ) : <span className="text-slate-300">—</span>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-600">{kz(l.total)}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-emerald-600">{kz(l.paid)}</td>
                                    <td className={cls('whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums',
                                        l.overdue ? 'text-red-600' : 'text-slate-900')}>
                                        {kz(l.balance)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

/** `2026-09-08` ou uma data ISO completa → `08/09/2026`. */
function data(iso: string | null): string {
    if (!iso) return '—';

    const d = new Date(iso);

    return Number.isNaN(d.getTime())
        ? iso
        : `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

const TONS = { verde: 'bg-emerald-500', vermelho: 'bg-red-500' } as const;

/**
 * As categorias com a parte de cada uma no total.
 *
 * O ecrã de sempre dava só o valor. A barra diz de relance qual é a grande —
 * que é a pergunta que se faz a um mapa de despesas.
 */
function PorCategoria({
    linhas,
    total,
    tom,
}: {
    linhas: LinhaDeCategoria[];
    total: number;
    tom: keyof typeof TONS;
}) {
    if (linhas.length === 0) {
        return <p className="py-6 text-center text-sm text-slate-400">{t('Nada neste período.')}</p>;
    }

    return (
        <ul className="space-y-3">
            {linhas.map((l, i) => {
                const parte = total > 0 ? (l.valor / total) * 100 : 0;

                return (
                    <li key={l.codigo ?? i} style={cascata(i)} className="entra">
                        <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span className="min-w-0 truncate text-slate-700">{l.rotulo}</span>
                            <span className="shrink-0 font-semibold tabular-nums text-slate-900">
                                {kz(l.valor)}
                                <span className="ml-2 text-xs font-normal text-slate-400">{parte.toFixed(0)}%</span>
                            </span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div className={cls('h-full rounded-full transition-all duration-500', TONS[tom])} style={{ width: `${parte}%` }} />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/** Uma linha da demonstração de resultados. */
function Conta({
    rotulo,
    valor,
    forte = false,
    destaque = false,
    suave = false,
}: {
    rotulo: string;
    valor: number;
    /** Um subtotal: fundo cinzento e negrito. */
    forte?: boolean;
    /** A linha final: a cor diz se houve lucro ou prejuízo. */
    destaque?: boolean;
    /** Uma linha secundária, mais apagada. */
    suave?: boolean;
}) {
    return (
        <tr className={cls(destaque && 'bg-slate-900 text-white', forte && !destaque && 'bg-slate-50')}>
            <th scope="row" className={cls('px-5 py-3 text-left font-normal', (forte || destaque) && 'font-bold', suave && 'text-slate-400')}>
                {rotulo}
            </th>
            <td
                className={cls(
                    'px-5 py-3 text-right tabular-nums',
                    destaque
                        ? cls('text-lg font-bold', valor < 0 ? 'text-red-300' : 'text-emerald-300')
                        : forte
                            ? 'font-bold text-slate-900'
                            : valor < 0 ? 'text-red-600' : 'text-slate-700',
                    suave && !destaque && 'text-slate-400',
                )}
            >
                {kz(valor)} <span className="text-xs font-normal opacity-60">Kz</span>
            </td>
        </tr>
    );
}
