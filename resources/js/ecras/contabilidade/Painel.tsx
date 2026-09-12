import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * O PAINEL DA CONTABILIDADE.
 *
 * Em cima estão os SALDOS, não as contagens de contas. «Total Ativo: 42» eram
 * quarenta e duas CONTAS de activo — um número que não muda com o negócio e não
 * responde a nada. As contagens ficam mais abaixo, onde servem: para ver se o
 * plano de contas está montado.
 *
 * SÓ OS CONFIRMADOS CONTAM. Um rascunho é uma intenção; somá-lo daria um
 * balanço que não existe em lado nenhum — e é por isso que o cartão dos
 * rascunhos está ao lado, e leva à lista deles.
 *
 * QUEM NÃO PODE VER RELATÓRIOS NÃO VÊ VALORES, e os valores nem chegam ao
 * navegador: o servidor manda `null`. O ecrã diz porque é que não estão lá, em
 * vez de mostrar zeros que seriam uma mentira.
 */
export default function Painel() {
    const hoje = new Date();
    const primeiro = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`;

    const [periodo, porPeriodo] = useState({
        de: primeiro,
        ate: hoje.toISOString().slice(0, 10),
    });

    const painel = useQuery({
        queryKey: ['contabilidade', 'painel', periodo],
        queryFn: () => contabilidade.painel(periodo),
        staleTime: 30_000,
    });

    if (painel.isPending) return <Carregando linhas={12} />;

    if (painel.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o painel')}</h2>
                <p className="text-sm text-red-800">
                    {painel.error instanceof ErroDaApi ? painel.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const p = painel.data;
    const lucro = (p.saldos.resultado ?? 0) >= 0;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Dashboard Contabilidade')}
                subtitulo={t('Visão geral financeira e contabilística')}
                icone="fa-chart-line"
                cor="bom"
                accoes={
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="sr-only" htmlFor="cont-de">{t('De')}</label>
                        <input
                            id="cont-de"
                            type="date"
                            value={periodo.de}
                            max={periodo.ate}
                            onChange={(e) => porPeriodo((x) => ({ ...x, de: e.target.value }))}
                            className={DATA_NA_FAIXA}
                        />
                        <span className="text-sm text-white/70">{t('até')}</span>
                        <label className="sr-only" htmlFor="cont-ate">{t('até')}</label>
                        <input
                            id="cont-ate"
                            type="date"
                            value={periodo.ate}
                            min={periodo.de}
                            onChange={(e) => porPeriodo((x) => ({ ...x, ate: e.target.value }))}
                            className={DATA_NA_FAIXA}
                        />
                    </div>
                }
            >
                <EstadoNaFaixa icone="fa-circle-check">
                    {t(':n lançamento(s) confirmado(s) · :r em rascunho', {
                        n: p.lancamentos.confirmados, r: p.lancamentos.rascunhos,
                    })}
                </EstadoNaFaixa>
            </Faixa>

            {/* OS SALDOS POR NATUREZA. O sinal segue a natureza da conta. */}
            {p.ve_valores ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Activo')} valor={kz(p.saldos.activo ?? 0)} sufixo="Kz" icone="fa-wallet" tom="azul" aspecto="claro" nota={t('Caixa, bancos, clientes')} />
                    <CartaoNumero rotulo={t('Passivo')} valor={kz(p.saldos.passivo ?? 0)} sufixo="Kz" icone="fa-file-invoice-dollar" tom="ambar" aspecto="claro" nota={t('Fornecedores, empréstimos')} />
                    <CartaoNumero rotulo={t('Proveitos')} valor={kz(p.saldos.proveitos ?? 0)} sufixo="Kz" icone="fa-arrow-trend-up" tom="verde" aspecto="claro" nota={t('Vendas e serviços')} />
                    <CartaoNumero rotulo={t('Gastos')} valor={kz(p.saldos.gastos ?? 0)} sufixo="Kz" icone="fa-arrow-trend-down" tom="vermelho" aspecto="claro" nota={t('Compras e despesas')} />
                </div>
            ) : (
                <div className={cls('border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600', RAIO)} role="status">
                    <i className="fas fa-eye-slash mr-2" aria-hidden="true" />
                    {t('Os saldos e os gráficos de valores só aparecem a quem pode ver relatórios de contabilidade.')}
                </div>
            )}

            {/* O RESULTADO — proveitos menos gastos, no período escolhido. */}
            {p.ve_valores && (
                <div
                    className={cls(
                        'animate-fade-in border px-6 py-5',
                        RAIO,
                        lucro ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50',
                    )}
                >
                    <p className={cls('text-sm font-semibold', lucro ? 'text-emerald-700' : 'text-rose-700')}>
                        <i className={cls('fas mr-2', lucro ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down')} aria-hidden="true" />
                        {lucro ? t('Resultado do período (lucro)') : t('Resultado do período (prejuízo)')}
                    </p>
                    <p className={cls('mt-1 text-3xl font-black tabular-nums', lucro ? 'text-emerald-900' : 'text-rose-900')}>
                        {kz(p.saldos.resultado ?? 0)} <span className="text-lg font-bold">Kz</span>
                    </p>
                    <p className={cls('mt-1 text-xs', lucro ? 'text-emerald-700' : 'text-rose-700')}>
                        {t('Só lançamentos confirmados. Um rascunho é uma intenção, não um facto contabilístico.')}
                    </p>
                </div>
            )}

            {/* OS RASCUNHOS PRESOS por confirmar são trabalho a meio: o cartão
                leva à lista, que os sabe separar dos que ainda se podem gravar. */}
            {p.lancamentos.rascunhos > 0 && (
                <div className={cls('flex flex-wrap items-center justify-between gap-3 border-2 border-amber-300 bg-amber-50 px-5 py-3.5', RAIO)} role="status">
                    <span className="text-sm text-amber-900">
                        <i className="fas fa-pen-to-square mr-2" aria-hidden="true" />
                        {t('Há :n lançamento(s) em rascunho — não entram em saldo nenhum enquanto não forem confirmados.', {
                            n: p.lancamentos.rascunhos,
                        })}
                    </span>
                    <a href="/accounting/moves" className={cls('text-sm font-semibold text-amber-800 underline underline-offset-2 hover:text-amber-950', FOCO, RAIO)}>
                        {t('Ver os rascunhos')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                    </a>
                </div>
            )}

            {/* AS CONTAGENS DO PLANO. Servem para saber se está montado. */}
            <Cartao
                titulo={t('O plano de contas')}
                icone="fa-sitemap"
                subtitulo={t('Quantas contas há de cada natureza — e quantas não recebem movimento')}
                accoes={
                    <a href="/accounting/accounts" className={cls('text-sm font-semibold text-emerald-700 hover:text-emerald-900', FOCO, RAIO)}>
                        {t('Abrir o plano')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                    </a>
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Conta rotulo={t('Total de Contas')} valor={p.plano.contas} icone="fa-list" tom="ardosia" />
                    <Conta rotulo={t('Contas Ativo')} valor={p.plano.activo} icone="fa-wallet" tom="azul" />
                    <Conta rotulo={t('Contas Passivo')} valor={p.plano.passivo} icone="fa-file-invoice-dollar" tom="ambar" />
                    <Conta rotulo={t('Capital Próprio')} valor={p.plano.capital} icone="fa-landmark" tom="roxo" />
                    <Conta rotulo={t('Contas Receitas')} valor={p.plano.proveitos} icone="fa-arrow-trend-up" tom="verde" />
                    <Conta rotulo={t('Contas Gastos')} valor={p.plano.gastos} icone="fa-arrow-trend-down" tom="vermelho" />
                    {/* ESTAS DUAS EXPLICAM as contas que não se encontram ao lançar. */}
                    <Conta rotulo={t('De agregação')} valor={p.plano.agregacao} icone="fa-diagram-project" tom="ardosia" nota={t('Somam as filhas')} />
                    <Conta rotulo={t('Bloqueadas')} valor={p.plano.bloqueadas} icone="fa-lock" tom="ardosia" nota={t('Não recebem lançamentos')} />
                </div>
            </Cartao>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Total Lançamentos')} valor={p.lancamentos.total.toLocaleString('pt-PT')} icone="fa-book" tom="indigo" aspecto="claro" />
                <CartaoNumero rotulo={t('Lançados')} valor={p.lancamentos.confirmados.toLocaleString('pt-PT')} icone="fa-check-circle" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('Rascunhos')} valor={p.lancamentos.rascunhos.toLocaleString('pt-PT')} icone="fa-pen-to-square" tom="ambar" aspecto="claro" />
                <CartaoNumero rotulo={t('Este Mês')} valor={p.lancamentos.do_mes.toLocaleString('pt-PT')} icone="fa-calendar" tom="azul" aspecto="claro" />
            </div>

            {p.ve_valores && (
                <Cartao titulo={t('Movimento dos últimos 12 meses')} icone="fa-chart-column" subtitulo={t('Total lançado a débito, por mês')}>
                    <GraficoDeBarras
                        titulo={t('Total lançado a débito, por mês')}
                        altura={240}
                        dados={p.mensal.etiquetas.map((rotulo, i) => ({ rotulo, valor: p.mensal.valores[i] ?? 0 }))}
                    />
                </Cartao>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                {p.ve_valores && (
                    <Cartao titulo={t('Contas mais movimentadas')} icone="fa-ranking-star" subtitulo={t('Débito + crédito no período')}>
                        <GraficoHorizontal
                            titulo={t('Contas mais movimentadas')}
                            vazio={t('Nada foi lançado neste período.')}
                            dados={p.contas_movimentadas.etiquetas.map((rotulo, i) => ({
                                rotulo, valor: p.contas_movimentadas.valores[i] ?? 0,
                            }))}
                        />
                    </Cartao>
                )}

                <Cartao titulo={t('Lançamentos por diário')} icone="fa-layer-group" subtitulo={t('Onde a contabilidade está a ser feita')}>
                    {/* ESTE CONTA LANÇAMENTOS, não dinheiro — e por isso aparece
                        a todos, com a unidade certa. */}
                    <GraficoHorizontal
                        titulo={t('Lançamentos por diário')}
                        unidade={t('lanç.')}
                        vazio={t('Nada foi lançado neste período.')}
                        dados={p.por_diario.etiquetas.map((rotulo, i) => ({
                            rotulo, valor: p.por_diario.valores[i] ?? 0,
                        }))}
                    />
                </Cartao>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h3 className="flex items-center gap-2 text-base font-bold text-slate-900">
                        <i className="fas fa-history text-emerald-600" aria-hidden="true" />
                        {t('Lançamentos Recentes')}
                    </h3>
                    <a href="/accounting/moves" className={cls('text-sm font-semibold text-emerald-700 hover:text-emerald-900', FOCO, RAIO)}>
                        {t('Ver todos')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                    </a>
                </header>

                {p.recentes.length === 0 ? (
                    <SemNada
                        icone="fa-inbox"
                        titulo={t('Nenhum lançamento confirmado')}
                        frase={t('Comece criando seu primeiro lançamento contabilístico')}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                                <tr>
                                    {[t('Data'), t('Referência'), t('Diário'), t('Autor')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Total')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {p.recentes.map((m, i) => (
                                    <tr key={m.id} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/60">
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">{m.dia ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{m.ref}</span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">{m.diario ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-slate-700">{m.autor ?? '—'}</p>
                                            {m.nota && <p className="max-w-xs truncate text-xs text-slate-400">{m.nota}</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                                            {m.total === null ? <span className="text-slate-400">•••</span> : `${kz(m.total)} Kz`}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

/** As datas dentro da faixa: vidro fosco, como o resto do que lá vive. */
const DATA_NA_FAIXA = cls(
    'h-9 rounded-xl border border-white/30 bg-white/20 px-3 text-sm font-semibold text-white',
    '[color-scheme:dark] placeholder:text-white/60',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
);

const TONS = {
    azul: 'bg-blue-100 text-blue-700',
    verde: 'bg-emerald-100 text-emerald-700',
    ambar: 'bg-amber-100 text-amber-700',
    vermelho: 'bg-red-100 text-red-700',
    roxo: 'bg-purple-100 text-purple-700',
    ardosia: 'bg-slate-100 text-slate-600',
} as const;

/**
 * Uma contagem do plano de contas.
 *
 * Não é um `CartaoNumero`: estes oito números vivem DENTRO de um cartão, e
 * repetir a moldura lá dentro daria caixas dentro de caixas.
 */
function Conta({
    rotulo,
    valor,
    icone,
    tom,
    nota,
}: {
    rotulo: string;
    valor: number;
    icone: string;
    tom: keyof typeof TONS;
    nota?: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5 transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-sm">
            <span className={cls('grid h-9 w-9 flex-none place-items-center rounded-xl', TONS[tom])}>
                <i className={`fas ${icone}`} aria-hidden="true" />
            </span>
            <span className="min-w-0">
                <span className="block truncate text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</span>
                <span className="block text-lg font-bold tabular-nums text-slate-900">{valor.toLocaleString('pt-PT')}</span>
                {nota && <span className="block truncate text-[11px] text-slate-400">{nota}</span>}
            </span>
        </div>
    );
}
