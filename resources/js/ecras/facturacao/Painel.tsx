import { useQuery } from '@tanstack/react-query';

import { painel, type NumerosDoPainel } from '@/api/painel';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { CARTAO, RAIO, cls, data, kz } from '@/ui/tokens';

/**
 * O PAINEL DA FACTURAÇÃO.
 *
 * Não faz contas nenhumas: os números vêm do `PainelDaFacturacao`, o mesmo
 * serviço que o painel em Blade usa. Era essa a condição para os dois
 * existirem ao mesmo tempo sem se contradizerem.
 *
 * E a regra que esses números guardam: conta-se pelo SALDO, nunca pelo nome do
 * estado. Nomear os estados que contam foi o que partiu o painel de origem —
 * dizia 76 mil por cobrar quando havia 15 milhões.
 */
export default function Painel() {
    const numeros = useQuery({
        queryKey: ['painel'],
        queryFn: painel.numeros,
        staleTime: 60_000,
    });

    if (numeros.isPending) {
        return <Carregando linhas={8} />;
    }

    if (numeros.isError) {
        return <Falhou erro={numeros.error} />;
    }

    const d = numeros.data;
    const anoActual = new Date().getFullYear();

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Numero
                    rotulo="Facturado este mês"
                    valor={d.stats.total_invoiced}
                    variacao={d.stats.growth}
                    comparacao="vs. mês passado"
                />
                <Numero rotulo="Recebido este mês" valor={d.stats.total_received} cor="bom" />
                <Numero rotulo="Por cobrar" valor={d.stats.total_pending} cor="aviso" />
                <Numero rotulo="Vencido" valor={d.stats.total_overdue} cor="perigo" />
            </div>

            {/* ANO A ANO, ATÉ AO MESMO DIA. Comparar um ano a meio com um ano
                inteiro daria sempre uma queda que não existe. */}
            <Cartao titulo={`Facturação de ${anoActual}, mês a mês`}>
                <div className="mb-5 flex flex-wrap items-baseline gap-x-6 gap-y-2">
                    <div>
                        <span className="text-2xl font-bold tabular-nums text-slate-900">
                            {kz(d.stats.year_invoiced)}
                        </span>
                        <span className="ml-1 text-sm text-slate-500">Kz este ano</span>
                    </div>
                    <div className="text-sm text-slate-500">
                        <span className="tabular-nums">{kz(d.stats.year_invoiced_previous)}</span> no
                        ano passado, até hoje
                    </div>
                    <Variacao valor={d.stats.year_growth} />
                </div>

                <GraficoDeBarras dados={d.por_mes} titulo={`Facturado por mês em ${anoActual}`} />
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo="Estado das facturas deste mês">
                    {/* Não se sobrepõem: uma factura cai numa caixa e numa só. */}
                    <div className="grid grid-cols-2 gap-3">
                        <Caixa rotulo="Liquidadas" valor={d.estado_das_facturas.paid} cor="bom" />
                        <Caixa rotulo="Por receber, no prazo" valor={d.estado_das_facturas.pending} cor="primaria" />
                        <Caixa
                            rotulo="Parcialmente pagas"
                            valor={d.estado_das_facturas.partially_paid}
                            cor="aviso"
                        />
                        <Caixa rotulo="Vencidas" valor={d.estado_das_facturas.overdue} cor="perigo" />
                    </div>

                    <div className="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        <Conta rotulo="Facturas" valor={d.documentos.invoices} />
                        <Conta rotulo="Recibos" valor={d.documentos.receipts} />
                        <Conta rotulo="Notas de crédito" valor={d.documentos.credit_notes} />
                        <Conta rotulo="Notas de débito" valor={d.documentos.debit_notes} />
                        <Conta rotulo="Adiantamentos" valor={d.documentos.advances} />
                    </div>
                </Cartao>

                <Cartao titulo="Melhores clientes deste mês">
                    {d.melhores_clientes.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">
                            Ainda não há facturas este mês.
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
                                            {c.documentos} documento(s)
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

            <Cartao titulo="Por cobrar — as dez mais antigas" semPadding>
                {d.por_cobrar.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-slate-400">
                        Não há nada por cobrar.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">Número</th>
                                    <th className="px-4 py-3 font-semibold">Cliente</th>
                                    <th className="px-4 py-3 font-semibold">Vencimento</th>
                                    <th className="px-4 py-3 text-right font-semibold">Falta receber</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.por_cobrar.map((f) => (
                                    <tr key={f.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <a
                                                href={`/invoicing/sales/invoices/${f.id}`}
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
                                                    <span className="ml-2">Vencida</span>
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
        </div>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

function Numero({
    rotulo,
    valor,
    variacao,
    comparacao,
    cor = 'primaria',
}: {
    rotulo: string;
    valor: number;
    variacao?: number;
    comparacao?: string;
    cor?: 'primaria' | 'bom' | 'aviso' | 'perigo';
}) {
    const risca = {
        primaria: 'border-l-indigo-500',
        bom: 'border-l-emerald-500',
        aviso: 'border-l-amber-500',
        perigo: 'border-l-red-500',
    } as const;

    return (
        <div className={cls(CARTAO, 'border-l-4 p-4', risca[cor])}>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</p>
            <p className="mt-1.5 text-2xl font-bold tabular-nums text-slate-900">
                {kz(valor)}
                <span className="ml-1 text-sm font-normal text-slate-400">Kz</span>
            </p>
            {variacao !== undefined && (
                <p className="mt-1 flex items-center gap-1.5 text-xs">
                    <Variacao valor={variacao} />
                    <span className="text-slate-400">{comparacao}</span>
                </p>
            )}
        </div>
    );
}

function Variacao({ valor }: { valor: number }) {
    // Zero não é subida nem descida, e pintá-lo de verde seria dizer que sim.
    if (Math.abs(valor) < 0.05) {
        return <span className="text-xs font-semibold text-slate-400">sem variação</span>;
    }

    const sobe = valor > 0;

    return (
        <span
            className={cls(
                'text-xs font-semibold tabular-nums',
                sobe ? 'text-emerald-600' : 'text-red-600',
            )}
        >
            <i className={`fas fa-arrow-${sobe ? 'up' : 'down'} mr-1`} aria-hidden="true" />
            {Math.abs(valor).toFixed(1)}%
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
                <h2 className="mb-2 text-lg font-bold text-amber-900">A sessão expirou</h2>
                <p className="mb-4 text-sm text-amber-900">Entre outra vez para continuar.</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    Voltar a entrar
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível carregar o painel</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? 'Verifique a ligação.'}</p>
        </div>
    );
}
