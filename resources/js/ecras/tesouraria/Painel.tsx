import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { PeriodoDoPainel } from '@/api/tesouraria';
import { painelDaTesouraria } from '@/api/tesouraria';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoDeDuasSeries } from '@/ui/GraficoDeDuasSeries';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ArrumarMovimentos } from './ArrumarMovimentos';
import { EtiquetaDeEstado, EtiquetaDeTipo } from './FichaDoMovimento';

/**
 * O PAINEL DA TESOURARIA — onde está o dinheiro, e o que lhe aconteceu.
 *
 * Só lê. É a página que se abre primeiro de manhã, e por isso o que importa
 * está em cima: quanto há, quanto entrou, quanto saiu.
 *
 * O SALDO NÃO SEGUE O PERÍODO, e os outros números seguem. Um saldo é o que
 * está lá hoje; entradas e saídas são as do período escolhido. O ecrã diz
 * qual é qual em vez de deixar a pessoa adivinhar porque é que um número
 * mudou ao trocar de semana e o outro não.
 *
 * O QUE PRECISA DE CONSERTO tem cartão próprio, e só aparece quando há
 * conserto a fazer: movimentos que não caíram em conta nem caixa nenhum são
 * dinheiro registado que não mexeu saldo nenhum, e a causa está quase sempre
 * numa forma de pagamento sem destino configurado. O cartão leva lá.
 */
const PERIODOS: Array<{ valor: PeriodoDoPainel; rotulo: string }> = [
    { valor: 'today', rotulo: 'Hoje' },
    { valor: 'week', rotulo: 'Esta semana' },
    { valor: 'month', rotulo: 'Este mês' },
    { valor: 'year', rotulo: 'Este ano' },
];

export default function Painel() {
    const [periodo, porPeriodo] = useState<PeriodoDoPainel>('today');
    const [aArrumar, porAArrumar] = useState(false);

    const painel = useQuery({
        queryKey: ['tesouraria', 'painel', periodo],
        queryFn: () => painelDaTesouraria.ler(periodo),
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
    const conserto = p.por_consertar.movimentos_sem_destino + p.por_consertar.formas_sem_destino;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Tesouraria')}
                subtitulo={t('Onde está o dinheiro, e o que lhe aconteceu')}
                icone="fa-coins"
                cor="teal"
                accoes={
                    <div className="flex flex-wrap gap-1.5" role="group" aria-label={t('Período')}>
                        {PERIODOS.map((x) => (
                            <button
                                key={x.valor}
                                type="button"
                                onClick={() => porPeriodo(x.valor)}
                                aria-pressed={periodo === x.valor}
                                className={cls(
                                    'px-3 py-1.5 text-sm font-semibold transition-all duration-200',
                                    RAIO,
                                    FOCO,
                                    periodo === x.valor
                                        ? 'bg-white text-teal-700 shadow-sm'
                                        : 'bg-white/20 text-white hover:bg-white/30',
                                )}
                            >
                                {t(x.rotulo)}
                            </button>
                        ))}
                    </div>
                }
            >
                <EstadoNaFaixa icone="fa-wallet">
                    {t('Saldo total agora')}: {kz(p.saldos.total)} Kz
                </EstadoNaFaixa>
            </Faixa>

            {/* O DINHEIRO QUE EXISTE. Não segue o período — e diz-se. */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero
                    rotulo={t('Saldo Total')}
                    valor={kz(p.saldos.total)}
                    sufixo="Kz"
                    icone="fa-wallet"
                    tom={p.saldos.total < 0 ? 'vermelho' : 'teal'}
                    nota={t('Agora, não do período')}
                />
                <CartaoNumero rotulo={t('Em caixas')} valor={kz(p.saldos.caixas)} sufixo="Kz" icone="fa-cash-register" tom="ambar" aspecto="claro" />
                <CartaoNumero rotulo={t('Em contas bancárias')} valor={kz(p.saldos.contas)} sufixo="Kz" icone="fa-building-columns" tom="azul" aspecto="claro" />
                <CartaoNumero
                    rotulo={t('Saldo do período')}
                    valor={kz(p.movimento.saldo)}
                    sufixo="Kz"
                    icone="fa-scale-balanced"
                    tom={p.movimento.saldo < 0 ? 'vermelho' : 'verde'}
                    aspecto="claro"
                    nota={`${t('Entradas')} ${kz(p.movimento.entradas)} · ${t('Saídas')} ${kz(p.movimento.saidas)}`}
                />
            </div>

            {/* O QUE PRECISA DE CONSERTO — só quando há. */}
            {conserto > 0 && (
                <div className={cls('border-2 border-amber-300 bg-amber-50 px-5 py-4', RAIO)} role="status">
                    <p className="mb-2 flex items-center gap-2 font-bold text-amber-900">
                        <i className="fas fa-screwdriver-wrench" aria-hidden="true" />
                        {t('Há coisas por arrumar')}
                    </p>
                    <ul className="space-y-1.5 text-sm text-amber-900">
                        {p.por_consertar.movimentos_sem_destino > 0 && (
                            <li className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <strong className="tabular-nums">{p.por_consertar.movimentos_sem_destino}</strong>{' '}
                                    {t('movimento(s) não caíram em conta nem caixa nenhum — o dinheiro está registado e não mexeu saldo.')}
                                </span>
                                {/* ARRUMAR DE UMA VEZ (23/09/2026): a ligação levava à lista inteira de
                                    movimentos, sem filtro — eram arrumados um a um. */}
                                <button type="button" onClick={() => porAArrumar(true)} className={cls('bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700', RAIO, FOCO)} data-arrumar>
                                    <i className="fas fa-wand-magic-sparkles mr-1.5" aria-hidden="true" />
                                    {t('Arrumar agora')}
                                </button>
                            </li>
                        )}
                        {p.por_consertar.formas_sem_destino > 0 && (
                            <li className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <strong className="tabular-nums">{p.por_consertar.formas_sem_destino}</strong>{' '}
                                    {t('forma(s) de pagamento sem destino configurado — é daqui que vêm os movimentos sem destino.')}
                                </span>
                                <a href="/treasury/payment-methods" className={cls('font-semibold underline underline-offset-2', FOCO)}>
                                    {t('Configurar')}
                                </a>
                            </li>
                        )}
                    </ul>
                </div>
            )}

            {/* FACTURAR NÃO É RECEBER. */}
            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Vendas do período')} icone="fa-file-invoice" subtitulo={t('Facturar não é receber')}>
                    <Barra rotulo={t('Facturado')} valor={p.facturacao.facturado} tom="azul" />
                    <Barra rotulo={t('Já cobrado')} valor={p.facturacao.cobrado} tom="verde" de={p.facturacao.facturado} />
                    <Barra rotulo={t('Por receber')} valor={p.facturacao.a_receber} tom="ambar" de={p.facturacao.facturado} />
                </Cartao>

                <Cartao titulo={t('Compras do período')} icone="fa-cart-shopping" subtitulo={t('O que se deve aos fornecedores')}>
                    <Barra rotulo={t('Comprado')} valor={p.facturacao.comprado} tom="azul" />
                    <Barra rotulo={t('Já pago')} valor={p.facturacao.pago} tom="verde" de={p.facturacao.comprado} />
                    <Barra rotulo={t('Por pagar')} valor={p.facturacao.a_pagar} tom="vermelho" de={p.facturacao.comprado} />
                </Cartao>
            </div>

            <Cartao titulo={t('Os últimos sete dias')} icone="fa-chart-column">
                <GraficoDeDuasSeries
                    titulo={t('Entradas e saídas por dia')}
                    primeira={t('Entradas')}
                    segunda={t('Saídas')}
                    dados={p.grafico.dias.map((dia, i) => ({
                        rotulo: dia,
                        a: p.grafico.entradas[i] ?? 0,
                        b: p.grafico.saidas[i] ?? 0,
                    }))}
                />
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('De onde veio')} icone="fa-arrow-down">
                    {p.categorias.entradas.length === 0
                        ? <p className="py-6 text-center text-sm text-slate-400">{t('Nada entrou neste período.')}</p>
                        : <GraficoDeBarras titulo={t('Entradas por categoria')} dados={p.categorias.entradas} />}
                </Cartao>

                <Cartao titulo={t('Para onde foi')} icone="fa-arrow-up">
                    {p.categorias.saidas.length === 0
                        ? <p className="py-6 text-center text-sm text-slate-400">{t('Nada saiu neste período.')}</p>
                        : <GraficoDeBarras titulo={t('Saídas por categoria')} dados={p.categorias.saidas} />}
                </Cartao>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Caixas')} icone="fa-cash-register">
                    <Bolsos
                        linhas={p.caixas.map((c) => ({
                            id: c.id,
                            nome: c.nome,
                            nota: c.estado === 'open' ? t('Aberto') : t('Fechado'),
                            saldo: c.saldo,
                        }))}
                        vazio={t('Nenhum caixa activo.')}
                        icone="fa-cash-register"
                    />
                </Cartao>

                <Cartao titulo={t('Contas bancárias')} icone="fa-building-columns">
                    <Bolsos
                        linhas={p.contas.map((c) => ({
                            id: c.id,
                            nome: c.nome,
                            nota: [c.banco, c.numero].filter(Boolean).join(' · '),
                            saldo: c.saldo,
                        }))}
                        vazio={t('Nenhuma conta activa.')}
                        icone="fa-building-columns"
                    />
                </Cartao>
            </div>

            <Cartao
                titulo={t('Movimentos recentes')}
                icone="fa-clock-rotate-left"
                accoes={
                    <a href="/treasury/transactions" className={cls('text-sm font-semibold text-teal-700 hover:text-teal-900', FOCO, RAIO)}>
                        {t('Ver todos')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                    </a>
                }
                semPadding
            >
                {p.recentes.length === 0 ? (
                    <SemNada icone="fa-clock-rotate-left" frase={t('Ainda não há movimentos.')} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-slate-50/60">
                                <tr>
                                    {[t('Data'), t('Número'), t('Descrição'), t('Tipo'), t('Destino')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t('Valor')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t('Status')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {p.recentes.map((m, i) => (
                                    <tr key={m.id} style={cascata(i)} className="entra transition-colors hover:bg-teal-50/60">
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{m.data}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5">
                                            <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{m.numero}</span>
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-2.5 text-slate-800">{m.descricao}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5"><EtiquetaDeTipo tipo={m.tipo} /></td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">
                                            {m.destino ?? <span className="text-amber-700">{t('Por classificar')}</span>}
                                        </td>
                                        <td className={cls('whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums',
                                            m.tipo === 'income' ? 'text-emerald-600' : 'text-red-600')}>
                                            {m.tipo === 'income' ? '+' : '−'} {kz(m.valor)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5"><EtiquetaDeEstado estado={m.estado} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>
            <ArrumarMovimentos aberto={aArrumar} aoFechar={() => porAArrumar(false)} />
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

const TONS = {
    azul: 'bg-blue-500',
    verde: 'bg-emerald-500',
    ambar: 'bg-amber-500',
    vermelho: 'bg-red-500',
} as const;

/**
 * Um valor com a sua parte do total desenhada por baixo.
 *
 * «Facturado 800.000, cobrado 300.000» são dois números; a barra diz que é
 * pouco mais de um terço sem obrigar ninguém a fazer a conta.
 */
function Barra({
    rotulo,
    valor,
    tom,
    de,
}: {
    rotulo: string;
    valor: number;
    tom: keyof typeof TONS;
    /** O total contra o qual isto se mede. Sem ele, a barra vai cheia. */
    de?: number;
}) {
    const parte = de && de > 0 ? Math.min(100, (valor / de) * 100) : 100;

    return (
        <div className="mb-3 last:mb-0">
            <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span className="text-slate-600">{rotulo}</span>
                <span className="font-bold tabular-nums text-slate-900">{kz(valor)} <span className="text-xs font-normal text-slate-400">Kz</span></span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                <div className={cls('h-full rounded-full transition-all duration-500', TONS[tom])} style={{ width: `${parte}%` }} />
            </div>
        </div>
    );
}

/** A lista de caixas ou de contas, com o saldo de cada uma. */
function Bolsos({
    linhas,
    vazio,
    icone,
}: {
    linhas: Array<{ id: number; nome: string; nota: string; saldo: number }>;
    vazio: string;
    icone: string;
}) {
    if (linhas.length === 0) {
        return <SemNada icone={icone} frase={vazio} />;
    }

    return (
        <ul className="divide-y divide-slate-100 text-sm">
            {linhas.map((l, i) => (
                <li key={l.id} style={cascata(i)} className="entra -mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-slate-50">
                    <span className="min-w-0">
                        <span className="block truncate font-semibold text-slate-900">{l.nome}</span>
                        {l.nota && <span className="block truncate text-xs text-slate-400">{l.nota}</span>}
                    </span>
                    <span className={cls('shrink-0 font-bold tabular-nums', l.saldo < 0 ? 'text-red-600' : 'text-slate-900')}>
                        {kz(l.saldo)}
                    </span>
                </li>
            ))}
        </ul>
    );
}
