import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * OS RELATÓRIOS DA CONTABILIDADE — dez mapas, um ecrã.
 *
 * O QUE ESTAVA PARTIDO E CUSTAVA CARO: o ecrã antigo calculava o BALANCETE
 * INTEIRO em todas as visitas, qualquer que fosse o mapa escolhido — e com as
 * somas feitas em PHP sobre todas as contas da empresa. Num plano importado de
 * 1.500 contas com um ano de movimento, abrir o Balanço arrastava o balancete
 * completo à memória para o deitar fora. Aqui cada mapa só se pede quando é
 * escolhido.
 *
 * E OS BOTÕES DE DESCARREGAR apareciam em todos os mapas: carregava-se para
 * ouvir «Tipo de relatório não suporta exportação». Agora só aparecem onde
 * funcionam — e funcionam, que é a outra metade da história: nenhum dos seis
 * exports para folha de cálculo existia de verdade.
 */
export default function Relatorios() {
    const hoje = new Date();
    const primeiro = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`;

    const [mapa, porMapa] = useState('trial_balance');
    const [periodo, porPeriodo] = useState({ de: primeiro, ate: hoje.toISOString().slice(0, 10) });
    const [conta, porConta] = useState<number | ''>('');
    const [diario, porDiario] = useState<number | ''>('');

    const opcoes = useQuery({
        queryKey: ['contabilidade', 'relatorios', 'opcoes'],
        queryFn: contabilidade.relatorios.opcoes,
        staleTime: 5 * 60_000,
    });

    const relatorio = useQuery({
        queryKey: ['contabilidade', 'relatorios', mapa, periodo, conta, diario],
        queryFn: () => contabilidade.relatorios.mostrar({ mapa, ...periodo, conta, diario }),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    if (opcoes.isPending) return <Carregando linhas={12} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios')}</h2>
                <p className="text-sm text-red-800">
                    {opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const o = opcoes.data;
    const escolhido = o.mapas.find((m) => m.valor === mapa);
    const temPdf = o.exportacoes.pdf.includes(mapa);
    const temExcel = o.exportacoes.excel.includes(mapa);

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Relatórios da Contabilidade')}
                subtitulo={escolhido?.descricao ?? t('Os mapas legais e os de gestão')}
                icone={escolhido?.icone ?? 'fa-file-lines'}
                cor="bom"
                accoes={
                    /* SÓ OS FORMATOS QUE ESTE MAPA TEM. */
                    (temPdf || temExcel) && (
                        <div className="flex flex-wrap items-center gap-2">
                            {temPdf && (
                                <a
                                    href={contabilidade.relatorios.morada({ mapa, formato: 'pdf', ...periodo })}
                                    target="_blank"
                                    rel="noopener"
                                    className={ACCAO}
                                >
                                    <i className="fas fa-file-pdf" aria-hidden="true" />
                                    {t('PDF')}
                                </a>
                            )}
                            {temExcel && (
                                <a
                                    href={contabilidade.relatorios.morada({ mapa, formato: 'excel', ...periodo })}
                                    target="_blank"
                                    rel="noopener"
                                    className={ACCAO}
                                >
                                    <i className="fas fa-file-excel" aria-hidden="true" />
                                    {t('Folha de cálculo')}
                                </a>
                            )}
                        </div>
                    )
                }
            >
                <EstadoNaFaixa icone="fa-calendar-days">
                    {periodo.de} → {periodo.ate}
                </EstadoNaFaixa>
            </Faixa>

            {/* OS MAPAS, em cartões: dez nomes num `<select>` não dizem o que cada
                um responde, e a descrição é o que faz escolher. */}
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                {o.mapas.map((m, i) => {
                    const activo = m.valor === mapa;

                    return (
                        <button
                            key={m.valor}
                            type="button"
                            onClick={() => porMapa(m.valor)}
                            aria-pressed={activo}
                            style={cascata(i)}
                            className={cls(
                                'entra flex items-start gap-2.5 border px-3 py-2.5 text-left transition-all duration-200',
                                'hover:-translate-y-0.5 hover:shadow-sm',
                                RAIO, FOCO,
                                activo
                                    ? 'border-emerald-400 bg-emerald-50 ring-1 ring-emerald-300'
                                    : 'border-slate-200 bg-white',
                            )}
                        >
                            <span className={cls('grid h-8 w-8 flex-none place-items-center rounded-lg',
                                activo ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500')}>
                                <i className={cls('fas', m.icone)} aria-hidden="true" />
                            </span>
                            <span className="min-w-0">
                                <span className={cls('block text-sm font-bold', activo ? 'text-emerald-900' : 'text-slate-800')}>
                                    {m.rotulo}
                                </span>
                                <span className="block text-[11px] leading-snug text-slate-500">{m.descricao}</span>
                            </span>
                        </button>
                    );
                })}
            </div>

            <Cartao titulo={t('Período e filtros')} icone="fa-filter">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo
                        etiqueta={mapa === 'balance_sheet' ? t('Data do balanço') : t('De')}
                        ajuda={mapa === 'balance_sheet' ? t('O balanço é uma fotografia de um dia: usa a data de fim.') : undefined}
                    >
                        <input
                            type="date"
                            value={periodo.de}
                            max={periodo.ate}
                            disabled={mapa === 'balance_sheet'}
                            onChange={(e) => porPeriodo((x) => ({ ...x, de: e.target.value }))}
                            className={cls(entrada, 'disabled:bg-slate-50 disabled:text-slate-400')}
                        />
                    </Campo>

                    <Campo etiqueta={mapa === 'balance_sheet' ? t('Em') : t('até')}>
                        <input
                            type="date"
                            value={periodo.ate}
                            min={mapa === 'balance_sheet' ? undefined : periodo.de}
                            onChange={(e) => porPeriodo((x) => ({ ...x, ate: e.target.value }))}
                            className={entrada}
                        />
                    </Campo>

                    {mapa === 'ledger' && (
                        <Campo etiqueta={t('Conta')} obrigatorio className="lg:col-span-2">
                            <select
                                value={conta}
                                onChange={(e) => porConta(e.target.value ? Number(e.target.value) : '')}
                                className={entrada}
                            >
                                <option value="">{t('Escolha a conta…')}</option>
                                {o.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                            </select>
                        </Campo>
                    )}

                    {mapa === 'journal' && (
                        <Campo etiqueta={t('Diário')} className="lg:col-span-2">
                            <select
                                value={diario}
                                onChange={(e) => porDiario(e.target.value ? Number(e.target.value) : '')}
                                className={entrada}
                            >
                                <option value="">{t('Todos os diários')}</option>
                                {o.diarios.map((d) => <option key={d.valor} value={d.valor}>{d.rotulo}</option>)}
                            </select>
                        </Campo>
                    )}
                </div>
            </Cartao>

            {relatorio.isPending ? (
                <Carregando linhas={10} />
            ) : relatorio.isError ? (
                <div className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)} role="alert">
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {relatorio.error instanceof ErroDaApi ? relatorio.error.message : t('Não foi possível calcular o mapa.')}
                </div>
            ) : (
                /*
                 * O MAPA VEM DA RESPOSTA, não do estado do ecrã.
                 *
                 * Com `keepPreviousData`, trocar de mapa mantém o conteúdo do
                 * ANTERIOR à vista enquanto o novo carrega — e desenhá-lo com a
                 * forma do novo rebenta: o balancete não tem `liquidado`. Cada
                 * resposta diz de que mapa é, e é isso que decide o desenho.
                 */
                <Mapa mapa={relatorio.data.mapa} dados={relatorio.data.data} />
            )}
        </div>
    );
}

/* ─── O mapa escolhido ────────────────────────────────────────────────── */

function Mapa({ mapa, dados }: { mapa: string; dados: Record<string, unknown> }) {
    switch (mapa) {
        case 'trial_balance':
            return <Balancete d={dados as never} />;
        case 'ledger':
            return <Razao d={dados as never} />;
        case 'journal':
            return <DiarioGeral d={dados as never} />;
        case 'vat':
            return <MapaDeIva d={dados as never} />;
        case 'income_statement':
            return <ResultadosSimples d={dados as never} />;
        default:
            /*
             * OS QUATRO MAPAS LEGAIS vêm dos serviços que já existiam, cada um
             * com a sua forma. Em vez de cinco desenhos à parte que divergem do
             * que o serviço devolve, um só percorre a árvore: um número é uma
             * rubrica, um bloco com `total` é um grupo.
             */
            return <Rubricas d={dados} />;
    }
}

/* ─── O balancete ─────────────────────────────────────────────────────── */

type LinhaDoBalancete = { id: number; codigo: string; nome: string; debito: number; credito: number; saldo: number };

function Balancete({ d }: { d: { contas: LinhaDoBalancete[]; totais: { debito: number; credito: number; diferenca: number; fecha: boolean } } }) {
    if (d.contas.length === 0) {
        return (
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <SemNada icone="fa-scale-balanced" titulo={t('Sem movimento no período')} />
            </section>
        );
    }

    return (
        <section className={cls(CARTAO, 'overflow-hidden')}>
            {/* UM BALANCETE QUE NÃO FECHA é o primeiro sinal de que alguma coisa
                foi escrita à mão na base. */}
            {!d.totais.fecha && (
                <div className={cls('m-4 border-2 border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)} role="alert">
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {t('O balancete não fecha: diferença de :valor Kz entre o débito e o crédito.', {
                        valor: kz(d.totais.diferenca),
                    })}
                </div>
            )}

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                        <tr>
                            {[t('Código'), t('Conta')].map((c) => (
                                <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                            ))}
                            {[t('Débito'), t('Crédito'), t('Saldo')].map((c) => (
                                <th key={c} scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {d.contas.map((c, i) => (
                            <tr key={c.id} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/50">
                                <td className="whitespace-nowrap px-4 py-2.5">
                                    <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{c.codigo}</span>
                                </td>
                                <td className="px-4 py-2.5 text-slate-800">{c.nome}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-emerald-700">{kz(c.debito)}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-red-700">{kz(c.credito)}</td>
                                <td className={cls('whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums',
                                    c.saldo < 0 ? 'text-red-800' : 'text-slate-900')}>
                                    {kz(c.saldo)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                        <tr>
                            <td colSpan={2} className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-emerald-700">{kz(d.totais.debito)}</td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-red-700">{kz(d.totais.credito)}</td>
                            <td className={cls('px-4 py-3 text-right font-bold tabular-nums',
                                d.totais.fecha ? 'text-slate-400' : 'text-red-800')}>
                                {d.totais.fecha ? '—' : kz(d.totais.diferenca)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}

/* ─── A razão geral ───────────────────────────────────────────────────── */

function Razao({ d }: {
    d: {
        conta: { codigo: string; nome: string; cresce_a_debito: boolean } | null;
        abertura: number;
        linhas: Array<{ id: number; dia: string; ref: string; diario: string | null; nota: string | null; debito: number; credito: number; acumulado: number }>;
        totais: { debito: number; credito: number; saldo: number } | null;
    };
}) {
    if (!d.conta) {
        return (
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <SemNada
                    icone="fa-book-open"
                    titulo={t('Escolha uma conta')}
                    frase={t('A razão é o extracto de UMA conta: sem conta escolhida não há nada para mostrar.')}
                />
            </section>
        );
    }

    return (
        <section className={cls(CARTAO, 'overflow-hidden')}>
            <header className="border-b border-slate-100 px-5 py-4">
                <h3 className="text-base font-bold text-slate-900">{d.conta.codigo} · {d.conta.nome}</h3>
                <p className="text-xs text-slate-500">
                    {d.conta.cresce_a_debito
                        ? t('Cresce a débito: o acumulado é débito menos crédito.')
                        : t('Cresce a crédito: o acumulado é crédito menos débito.')}
                </p>
            </header>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {[t('Data'), t('Referência'), t('Diário'), t('Observação')].map((c) => (
                                <th key={c} scope="col" className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                            ))}
                            {[t('Débito'), t('Crédito'), t('Acumulado')].map((c) => (
                                <th key={c} scope="col" className="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        <tr className="bg-slate-50/60">
                            <td colSpan={6} className="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Saldo de abertura')}
                            </td>
                            <td className="px-4 py-2 text-right font-bold tabular-nums text-slate-700">{kz(d.abertura)}</td>
                        </tr>

                        {d.linhas.map((l, i) => (
                            <tr key={l.id} style={cascata(i)} className="entra">
                                <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{l.dia}</td>
                                <td className="whitespace-nowrap px-4 py-2.5">
                                    <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{l.ref}</span>
                                </td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{l.diario ?? '—'}</td>
                                <td className="max-w-xs truncate px-4 py-2.5 text-slate-600">{l.nota ?? '—'}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-emerald-700">{l.debito > 0 ? kz(l.debito) : '—'}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-red-700">{l.credito > 0 ? kz(l.credito) : '—'}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(l.acumulado)}</td>
                            </tr>
                        ))}
                    </tbody>
                    {d.totais && (
                        <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                            <tr>
                                <td colSpan={4} className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                                <td className="px-4 py-3 text-right font-bold tabular-nums text-emerald-700">{kz(d.totais.debito)}</td>
                                <td className="px-4 py-3 text-right font-bold tabular-nums text-red-700">{kz(d.totais.credito)}</td>
                                <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{kz(d.totais.saldo)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </section>
    );
}

/* ─── O diário ────────────────────────────────────────────────────────── */

function DiarioGeral({ d }: {
    d: {
        lancamentos: Array<{
            id: number; ref: string; dia: string | null; diario: string | null; nota: string | null;
            debito: number; credito: number;
            linhas: Array<{ conta: string | null; nota: string | null; debito: number; credito: number }>;
        }>;
        totais: { lancamentos: number; debito: number; credito: number };
        ha_mais: boolean;
    };
}) {
    if (d.lancamentos.length === 0) {
        return (
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <SemNada icone="fa-book" titulo={t('Sem lançamentos confirmados no período')} />
            </section>
        );
    }

    return (
        <section className={cls(CARTAO, 'overflow-hidden')}>
            {d.ha_mais && (
                <p className={cls('m-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)} role="status">
                    <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                    {t('A mostrar os primeiros 500 lançamentos do período. Aperte o intervalo de datas para ver os restantes.')}
                </p>
            )}

            <ul className="divide-y divide-slate-100">
                {d.lancamentos.map((m, i) => (
                    <li key={m.id} style={cascata(i)} className="entra px-4 py-3">
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <span className="flex flex-wrap items-baseline gap-2">
                                <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700">{m.ref}</span>
                                <span className="text-sm text-slate-600">{m.dia}</span>
                                {m.diario && <span className="text-xs text-slate-400">{m.diario}</span>}
                            </span>
                            <span className="text-sm font-bold tabular-nums text-slate-900">{kz(m.debito)}</span>
                        </div>

                        {m.nota && <p className="mt-0.5 text-sm text-slate-600">{m.nota}</p>}

                        <table className="mt-1.5 min-w-full text-xs">
                            <tbody>
                                {m.linhas.map((l, j) => (
                                    <tr key={j}>
                                        <td className="py-0.5 pr-3 text-slate-700">{l.conta ?? '—'}</td>
                                        <td className="py-0.5 pr-3 text-slate-400">{l.nota ?? ''}</td>
                                        <td className="w-24 py-0.5 text-right tabular-nums text-emerald-700">{l.debito > 0 ? kz(l.debito) : ''}</td>
                                        <td className="w-24 py-0.5 text-right tabular-nums text-red-700">{l.credito > 0 ? kz(l.credito) : ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </li>
                ))}
            </ul>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t-2 border-slate-300 bg-slate-50 px-4 py-3 text-sm">
                <span className="font-bold text-slate-700">
                    {t(':n lançamento(s)', { n: d.totais.lancamentos })}
                </span>
                <span className="flex gap-6">
                    <span className="font-bold tabular-nums text-emerald-700">{kz(d.totais.debito)}</span>
                    <span className="font-bold tabular-nums text-red-700">{kz(d.totais.credito)}</span>
                </span>
            </div>
        </section>
    );
}

/* ─── O mapa de IVA ───────────────────────────────────────────────────── */

type LinhaDeIva = { dia: string; ref: string; conta: string; nota: string | null; debito: number; credito: number };

function MapaDeIva({ d }: {
    d: {
        liquidado: { linhas: LinhaDeIva[]; debito: number; credito: number };
        dedutivel: { linhas: LinhaDeIva[]; debito: number; credito: number };
        totais: { liquidado: number; dedutivel: number; a_entregar: number };
        sem_contas: boolean;
    };
}) {
    /*
     * SEM CONTAS DE IVA MARCADAS não há mapa — e dizê-lo é melhor do que mostrar
     * três zeros: o ecrã antigo devolvia zeros e ninguém sabia se não havia IVA
     * ou se faltava configurar o plano.
     */
    if (d.sem_contas) {
        return (
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <SemNada
                    icone="fa-percent"
                    titulo={t('Sem contas de IVA marcadas no plano')}
                    frase={t('O mapa lê as contas pela chave de integração. Sincronize o plano de contas nas definições, ou marque à mão as contas de IVA liquidado e dedutível.')}
                />
            </section>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-3">
                <Numero rotulo={t('IVA liquidado')} valor={d.totais.liquidado} tom="verde" nota={t('Cobrado nas vendas')} />
                <Numero rotulo={t('IVA dedutível')} valor={d.totais.dedutivel} tom="azul" nota={t('Pago nas compras')} />
                <Numero
                    rotulo={t('A entregar ao Estado')}
                    valor={d.totais.a_entregar}
                    tom={d.totais.a_entregar >= 0 ? 'vermelho' : 'ardosia'}
                    nota={d.totais.a_entregar >= 0 ? t('Liquidado menos dedutível') : t('A recuperar')}
                />
            </div>

            {([['liquidado', t('IVA liquidado (vendas)')], ['dedutivel', t('IVA dedutível (compras)')]] as const).map(([chave, titulo]) => (
                <Cartao key={chave} titulo={titulo} icone="fa-percent" semPadding>
                    {d[chave].linhas.length === 0 ? (
                        <SemNada icone="fa-percent" titulo={t('Sem movimento no período')} />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[t('Data'), t('Documento'), t('Conta'), t('Observação')].map((c) => (
                                            <th key={c} scope="col" className="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                        ))}
                                        {[t('Débito'), t('Crédito')].map((c) => (
                                            <th key={c} scope="col" className="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {d[chave].linhas.map((l, i) => (
                                        <tr key={i} style={cascata(i)} className="entra">
                                            <td className="whitespace-nowrap px-4 py-2 text-slate-600">{l.dia}</td>
                                            <td className="whitespace-nowrap px-4 py-2">
                                                <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{l.ref}</span>
                                            </td>
                                            <td className="px-4 py-2 text-slate-700">{l.conta}</td>
                                            <td className="max-w-xs truncate px-4 py-2 text-slate-500">{l.nota ?? '—'}</td>
                                            <td className="whitespace-nowrap px-4 py-2 text-right tabular-nums text-emerald-700">{l.debito > 0 ? kz(l.debito) : '—'}</td>
                                            <td className="whitespace-nowrap px-4 py-2 text-right tabular-nums text-red-700">{l.credito > 0 ? kz(l.credito) : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Cartao>
            ))}
        </div>
    );
}

/* ─── Os resultados simplificados ─────────────────────────────────────── */

function ResultadosSimples({ d }: {
    d: {
        proveitos: Array<{ grupo: string; nome: string; valor: number }>;
        gastos: Array<{ grupo: string; nome: string; valor: number }>;
        totais: { proveitos: number; gastos: number; resultado: number };
    };
}) {
    const lucro = d.totais.resultado >= 0;

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-3">
                <Numero rotulo={t('Proveitos')} valor={d.totais.proveitos} tom="verde" />
                <Numero rotulo={t('Gastos')} valor={d.totais.gastos} tom="vermelho" />
                <Numero
                    rotulo={lucro ? t('Resultado do período (lucro)') : t('Resultado do período (prejuízo)')}
                    valor={d.totais.resultado}
                    tom={lucro ? 'verde' : 'vermelho'}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {([['proveitos', t('Proveitos')], ['gastos', t('Gastos')]] as const).map(([chave, titulo]) => (
                    <Cartao key={chave} titulo={titulo} icone={chave === 'proveitos' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}>
                        {d[chave].length === 0 ? (
                            <p className="py-6 text-center text-sm text-slate-400">{t('Sem movimento no período.')}</p>
                        ) : (
                            <ul className="divide-y divide-slate-100 text-sm">
                                {d[chave].map((g, i) => (
                                    <li key={g.grupo} style={cascata(i)} className="entra flex items-baseline justify-between gap-3 py-2">
                                        <span className="min-w-0">
                                            <span className="mr-2 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">{g.grupo}</span>
                                            <span className="text-slate-700">{g.nome}</span>
                                        </span>
                                        <span className="shrink-0 font-semibold tabular-nums text-slate-900">{kz(g.valor)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Cartao>
                ))}
            </div>
        </div>
    );
}

/* ─── Os quatro mapas legais ──────────────────────────────────────────── */

/**
 * AS RUBRICAS DE UMA DEMONSTRAÇÃO, aplanadas.
 *
 * O Balanço, as duas Demonstrações de Resultados e os Fluxos de Caixa vêm de
 * serviços que já existiam, cada um com a sua forma. Em vez de quatro desenhos à
 * parte — que divergem do serviço na primeira rubrica nova — percorre-se o que
 * vier: um número é uma rubrica, um bloco com `total` é um grupo.
 */
function Rubricas({ d, nivel = 0 }: { d: Record<string, unknown>; nivel?: number }) {
    const nomes: Record<string, string> = {
        activo: t('Activo'), passivo: t('Passivo'), capital_proprio: t('Capital próprio'),
        total: t('Total'), totals: t('Totais'), details: t('Detalhe'),
        indicadores: t('Indicadores'), margens: t('Margens'), resultados: t('Resultados'),
        operacionais: t('Operacionais'), investimento: t('Investimento'), financiamento: t('Financiamento'),
    };

    const rotulo = (chave: string) =>
        nomes[chave] ?? chave.charAt(0).toUpperCase() + chave.slice(1).replace(/_/g, ' ');

    const entradas = Object.entries(d).filter(([k]) => !['details', 'detalhes', 'count'].includes(k));

    if (entradas.length === 0) {
        return (
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <SemNada icone="fa-file-lines" titulo={t('Sem dados no período')} />
            </section>
        );
    }

    const corpo = (
        <ul className={cls('text-sm', nivel === 0 ? 'divide-y divide-slate-100' : 'mt-1 space-y-0.5 border-l-2 border-slate-100 pl-3')}>
            {entradas.map(([chave, valor], i) => {
                if (typeof valor === 'number') {
                    return (
                        <li key={chave} style={cascata(i)} className="entra flex items-baseline justify-between gap-3 py-1.5">
                            <span className="text-slate-700">{rotulo(chave)}</span>
                            <span className={cls('shrink-0 tabular-nums',
                                nivel === 0 ? 'font-bold text-slate-900' : 'font-semibold text-slate-800',
                                valor < 0 && 'text-red-700')}>
                                {kz(valor)}
                            </span>
                        </li>
                    );
                }

                if (typeof valor === 'boolean') {
                    return (
                        <li key={chave} className="flex items-baseline justify-between gap-3 py-1.5">
                            <span className="text-slate-700">{rotulo(chave)}</span>
                            <span className={cls('shrink-0 text-xs font-bold', valor ? 'text-emerald-700' : 'text-red-700')}>
                                {valor ? t('Sim') : t('Não')}
                            </span>
                        </li>
                    );
                }

                if (typeof valor === 'string') {
                    return (
                        <li key={chave} className="flex items-baseline justify-between gap-3 py-1.5">
                            <span className="text-slate-700">{rotulo(chave)}</span>
                            <span className="shrink-0 text-slate-600">{valor}</span>
                        </li>
                    );
                }

                if (!valor || typeof valor !== 'object') {
                    return null;
                }

                const bloco = valor as Record<string, unknown>;
                const detalhes = (bloco.details ?? bloco.detalhes) as Array<Record<string, unknown>> | undefined;

                return (
                    <li key={chave} style={cascata(i)} className="entra py-1.5">
                        <div className="flex items-baseline justify-between gap-3">
                            <span className={cls(nivel === 0 ? 'font-bold text-slate-900' : 'font-semibold text-slate-700')}>
                                {rotulo(chave)}
                            </span>
                            {typeof bloco.total === 'number' && (
                                <span className={cls('shrink-0 font-bold tabular-nums',
                                    (bloco.total as number) < 0 ? 'text-red-700' : 'text-slate-900')}>
                                    {kz(bloco.total as number)}
                                </span>
                            )}
                        </div>

                        {/* O DETALHE de um grupo: as contas que o compõem. */}
                        {Array.isArray(detalhes) && detalhes.length > 0 && (
                            <ul className="mt-1 space-y-0.5 border-l-2 border-slate-100 pl-3 text-xs">
                                {detalhes.map((x, j) => (
                                    <li key={j} className="flex items-baseline justify-between gap-3">
                                        <span className="min-w-0 truncate text-slate-500">
                                            {String(x.code ?? x.codigo ?? '')} {String(x.name ?? x.nome ?? '')}
                                        </span>
                                        <span className="shrink-0 tabular-nums text-slate-600">
                                            {kz(Number(x.balance ?? x.saldo ?? x.total ?? x.amount ?? 0))}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {/* E os sub-blocos, se houver. */}
                        {Object.values(bloco).some((v) => v && typeof v === 'object' && !Array.isArray(v)) && (
                            <Rubricas
                                d={Object.fromEntries(
                                    Object.entries(bloco).filter(([k, v]) =>
                                        v && typeof v === 'object' && !Array.isArray(v) && !['details', 'detalhes'].includes(k)),
                                )}
                                nivel={nivel + 1}
                            />
                        )}
                    </li>
                );
            })}
        </ul>
    );

    return nivel === 0
        ? <section className={cls(CARTAO, 'px-5 py-4')}>{corpo}</section>
        : corpo;
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const ACCAO = cls(
    'inline-flex items-center gap-2 bg-white/20 px-4 py-2 text-sm font-semibold text-white',
    'transition-all duration-200 hover:bg-white/30 hover:-translate-y-0.5',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
    RAIO,
);

const NUMEROS = {
    ardosia: 'border-slate-200 bg-slate-50 text-slate-700',
    verde: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    vermelho: 'border-red-200 bg-red-50 text-red-800',
    azul: 'border-blue-200 bg-blue-50 text-blue-900',
} as const;

function Numero({ rotulo, valor, tom, nota }: {
    rotulo: string;
    valor: number;
    tom: keyof typeof NUMEROS;
    nota?: string;
}) {
    return (
        <div className={cls('border-2 px-4 py-3', RAIO, NUMEROS[tom])}>
            <p className="text-xs font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-2xl font-bold tabular-nums">{kz(valor)} <span className="text-sm font-semibold">Kz</span></p>
            {nota && <p className="mt-0.5 text-xs opacity-70">{nota}</p>}
        </div>
    );
}

