import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { type ReactNode, useDeferredValue, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { AntiguidadeDeSaldos, EscalaoDeAntiguidade, SaldosDeTerceiros, TipoDeTerceiro } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * A CONTA-CORRENTE DE TERCEIROS — quem nos deve e a quem devemos.
 *
 * Dois lados (clientes, fornecedores) e duas vistas: os SALDOS de cada
 * terceiro e a ANTIGUIDADE desses saldos (há quanto tempo estão por pagar).
 * Carregar num terceiro abre o EXTRATO — os movimentos com o saldo acumulado.
 *
 * O saldo lê-se sempre da mesma maneira: POSITIVO = EM DÍVIDA (o cliente
 * deve-nos, ou devemos ao fornecedor); negativo = a favor do terceiro (pagou a
 * mais, ou adiantámos). O servidor já o manda assim.
 *
 * Só aparecem as linhas de lançamento que levam o terceiro. Os lançamentos
 * antigos ganham-no com `php artisan accounting:preencher-terceiros`.
 */

type Vista = 'saldos' | 'antiguidade';

const ESCALOES: Array<{ chave: EscalaoDeAntiguidade; rotulo: string }> = [
    { chave: 'ate_30', rotulo: '0–30' },
    { chave: 'de_31_60', rotulo: '31–60' },
    { chave: 'de_61_90', rotulo: '61–90' },
    { chave: 'de_91_180', rotulo: '91–180' },
    { chave: 'mais_180', rotulo: '+180' },
];

function hojeIso(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function Terceiros() {
    const [tipo, porTipo] = useState<TipoDeTerceiro>('client');
    const [vista, porVista] = useState<Vista>('saldos');
    const [ate, porAte] = useState(hojeIso());
    const [procura, porProcura] = useState('');
    const [soComSaldo, porSoComSaldo] = useState(true);
    const [aberto, porAberto] = useState<{ id: number; nome: string } | null>(null);

    const procuraAdiada = useDeferredValue(procura.trim());

    const saldos = useQuery({
        queryKey: ['contabilidade', 'terceiros', 'saldos', tipo, ate, procuraAdiada, soComSaldo],
        queryFn: () => contabilidade.terceiros.saldos({ tipo, ate, procura: procuraAdiada, com_saldo: soComSaldo }),
        placeholderData: keepPreviousData,
    });

    const antiguidade = useQuery({
        queryKey: ['contabilidade', 'terceiros', 'antiguidade', tipo, ate],
        queryFn: () => contabilidade.terceiros.antiguidade({ tipo, data: ate }),
        placeholderData: keepPreviousData,
        enabled: vista === 'antiguidade',
    });

    const deCliente = tipo === 'client';
    const emDivida = deCliente ? t('A receber') : t('A pagar');

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Conta-Corrente de Terceiros')}
                subtitulo={deCliente
                    ? t('O que cada cliente nos deve, e há quanto tempo')
                    : t('O que devemos a cada fornecedor, e há quanto tempo')}
                icone="fa-address-book"
                cor="bom"
            >
                <EstadoNaFaixa icone="fa-calendar-day">{t('Saldos em :data', { data: ate })}</EstadoNaFaixa>
            </Faixa>

            {/* O LADO E A VISTA: duas escolhas pequenas, sempre à vista. */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Alternador
                    rotulo={t('Terceiros')}
                    valor={tipo}
                    opcoes={[
                        { valor: 'client', rotulo: t('Clientes'), icone: 'fa-user-tie' },
                        { valor: 'supplier', rotulo: t('Fornecedores'), icone: 'fa-truck' },
                    ]}
                    aoMudar={(v) => porTipo(v as TipoDeTerceiro)}
                />
                <Alternador
                    rotulo={t('Vista')}
                    valor={vista}
                    opcoes={[
                        { valor: 'saldos', rotulo: t('Saldos'), icone: 'fa-scale-balanced' },
                        { valor: 'antiguidade', rotulo: t('Antiguidade'), icone: 'fa-hourglass-half' },
                    ]}
                    aoMudar={(v) => porVista(v as Vista)}
                />
            </div>

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('Saldos em')} ajuda={t('Conta tudo o que foi lançado até este dia.')}>
                        <input type="date" value={ate} max={hojeIso()} onChange={(e) => porAte(e.target.value || hojeIso())} className={entrada} />
                    </Campo>

                    {vista === 'saldos' && (
                        <>
                            <Campo etiqueta={t('Procurar')} className="lg:col-span-2">
                                <input
                                    type="search"
                                    value={procura}
                                    onChange={(e) => porProcura(e.target.value)}
                                    placeholder={t('Nome ou NIF…')}
                                    className={entrada}
                                />
                            </Campo>
                            <label className="flex items-center gap-2 self-end pb-2.5 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={soComSaldo}
                                    onChange={(e) => porSoComSaldo(e.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                {t('Só com saldo em aberto')}
                            </label>
                        </>
                    )}
                </div>
            </Cartao>

            {vista === 'saldos' ? (
                <Resposta consulta={saldos}>
                    {(d) => (
                        <>
                            <Resumo d={d} emDivida={emDivida} />
                            <TabelaDeSaldos d={d} aoAbrir={porAberto} />
                        </>
                    )}
                </Resposta>
            ) : (
                <Resposta consulta={antiguidade}>
                    {(d) => <TabelaDeAntiguidade d={d} emDivida={emDivida} aoAbrir={porAberto} />}
                </Resposta>
            )}

            {aberto && (
                <Extrato
                    tipo={tipo}
                    terceiro={aberto}
                    ate={ate}
                    aoFechar={() => porAberto(null)}
                />
            )}
        </div>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

function Alternador({ rotulo, valor, opcoes, aoMudar }: {
    rotulo: string;
    valor: string;
    opcoes: Array<{ valor: string; rotulo: string; icone: string }>;
    aoMudar: (v: string) => void;
}) {
    return (
        <div role="group" aria-label={rotulo} className={cls('inline-flex border border-slate-200 bg-white p-1', RAIO)}>
            {opcoes.map((o) => {
                const activo = o.valor === valor;

                return (
                    <button
                        key={o.valor}
                        type="button"
                        onClick={() => aoMudar(o.valor)}
                        aria-pressed={activo}
                        className={cls(
                            'flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition-colors',
                            FOCO,
                            activo ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100',
                        )}
                    >
                        <i className={cls('fas', o.icone)} aria-hidden="true" />
                        {o.rotulo}
                    </button>
                );
            })}
        </div>
    );
}

/** Carregar / erro / dados — o mesmo desenho nas duas vistas. */
function Resposta<T>({ consulta, children }: {
    consulta: { isPending: boolean; isError: boolean; error: unknown; data: T | undefined };
    children: (d: T) => ReactNode;
}) {
    if (consulta.isPending) return <Carregando linhas={8} />;

    if (consulta.isError || consulta.data === undefined) {
        return (
            <div className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)} role="alert">
                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                {consulta.error instanceof ErroDaApi ? consulta.error.message : t('Não foi possível calcular a conta-corrente.')}
            </div>
        );
    }

    return <>{children(consulta.data)}</>;
}

function Resumo({ d, emDivida }: { d: SaldosDeTerceiros; emDivida: string }) {
    return (
        <div className="grid gap-3 sm:grid-cols-3">
            <CartaoNumero
                rotulo={emDivida}
                valor={kz(Math.max(0, d.totais.saldo - d.totais.saldo_a_favor))}
                sufixo="Kz"
                icone="fa-hand-holding-dollar"
                tom="verde"
                aspecto="claro"
                nota={t(':n terceiros com saldo', { n: d.totais.com_saldo })}
            />
            <CartaoNumero
                rotulo={t('Saldo a favor do terceiro')}
                valor={kz(Math.abs(d.totais.saldo_a_favor))}
                sufixo="Kz"
                icone="fa-rotate-left"
                tom="ambar"
                aspecto="claro"
                nota={t('Pagamentos a mais ou adiantamentos')}
            />
            <CartaoNumero
                rotulo={t('Saldo líquido')}
                valor={kz(d.totais.saldo)}
                sufixo="Kz"
                icone="fa-scale-balanced"
                tom="indigo"
                aspecto="claro"
                nota={t(':n terceiros listados', { n: d.totais.terceiros })}
            />
        </div>
    );
}

const TH = 'px-4 py-3 text-xs font-bold uppercase tracking-wider text-emerald-700';

function TabelaDeSaldos({ d, aoAbrir }: { d: SaldosDeTerceiros; aoAbrir: (x: { id: number; nome: string }) => void }) {
    if (d.linhas.length === 0) {
        return (
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <SemNada
                    icone="fa-address-book"
                    titulo={t('Nenhum terceiro com movimento')}
                    frase={t('Os lançamentos de faturas, recibos e notas passam a trazer o cliente ou o fornecedor. Os antigos preenchem-se com o comando accounting:preencher-terceiros.')}
                />
            </section>
        );
    }

    return (
        <section className={cls(CARTAO, 'overflow-hidden')}>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                        <tr>
                            <th scope="col" className={cls(TH, 'text-left')}>{t('Terceiro')}</th>
                            <th scope="col" className={cls(TH, 'text-right')}>{t('Movimentos')}</th>
                            <th scope="col" className={cls(TH, 'text-left')}>{t('Último')}</th>
                            <th scope="col" className={cls(TH, 'text-right')}>{t('Débito')}</th>
                            <th scope="col" className={cls(TH, 'text-right')}>{t('Crédito')}</th>
                            <th scope="col" className={cls(TH, 'text-right')}>{t('Saldo')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {d.linhas.map((l, i) => (
                            <tr key={l.id} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/50">
                                <td className="px-4 py-2.5">
                                    <button
                                        type="button"
                                        onClick={() => aoAbrir({ id: l.id, nome: l.nome })}
                                        className={cls('text-left font-semibold text-slate-800 hover:text-emerald-700 hover:underline', FOCO)}
                                    >
                                        {l.nome}
                                    </button>
                                    {l.nif && <span className="block font-mono text-xs text-slate-500">{t('NIF')} {l.nif}</span>}
                                </td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-600">{l.movimentos}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{l.ultimo_movimento ?? '—'}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-emerald-700">{kz(l.debito)}</td>
                                <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-red-700">{kz(l.credito)}</td>
                                <td className={cls('whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums',
                                    l.saldo < 0 ? 'text-amber-700' : 'text-slate-900')}>
                                    {kz(l.saldo)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                        <tr>
                            <td colSpan={3} className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-emerald-700">{kz(d.totais.debito)}</td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-red-700">{kz(d.totais.credito)}</td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{kz(d.totais.saldo)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}

function TabelaDeAntiguidade({ d, emDivida, aoAbrir }: {
    d: AntiguidadeDeSaldos;
    emDivida: string;
    aoAbrir: (x: { id: number; nome: string }) => void;
}) {
    const velho = d.totais.escaloes.de_91_180 + d.totais.escaloes.mais_180;

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-3">
                <CartaoNumero rotulo={emDivida} valor={kz(d.totais.total)} sufixo="Kz" icone="fa-hand-holding-dollar" tom="verde" aspecto="claro"
                    nota={t(':n terceiros', { n: d.linhas.length })} />
                <CartaoNumero rotulo={t('Com mais de 90 dias')} valor={kz(velho)} sufixo="Kz" icone="fa-hourglass-end" tom="vermelho" aspecto="claro"
                    nota={d.totais.total > 0 ? t(':p% do total', { p: Math.round((velho / d.totais.total) * 100) }) : undefined} />
                <CartaoNumero rotulo={t('Saldo a favor do terceiro')} valor={kz(d.totais.a_favor)} sufixo="Kz" icone="fa-rotate-left" tom="ambar" aspecto="claro"
                    nota={t('Pago a mais, sem idade')} />
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {d.linhas.length === 0 ? (
                    <SemNada icone="fa-hourglass-half" titulo={t('Nada em aberto')} frase={t('Todos os terceiros deste lado têm a conta saldada nesta data.')} />
                ) : (
                    <div className="overflow-x-auto">
                        <p className="border-b border-slate-100 px-4 py-2.5 text-xs text-slate-500">
                            <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                            {t('Dias desde a data do documento. Os pagamentos abatem primeiro às dívidas mais antigas.')}
                        </p>
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                                <tr>
                                    <th scope="col" className={cls(TH, 'text-left')}>{t('Terceiro')}</th>
                                    {ESCALOES.map((e) => (
                                        <th key={e.chave} scope="col" className={cls(TH, 'text-right')}>{t(':d dias', { d: e.rotulo })}</th>
                                    ))}
                                    <th scope="col" className={cls(TH, 'text-right')}>{t('Total')}</th>
                                    <th scope="col" className={cls(TH, 'text-right')}>{t('A favor')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.linhas.map((l, i) => (
                                    <tr key={l.id} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/50">
                                        <td className="px-4 py-2.5">
                                            <button
                                                type="button"
                                                onClick={() => aoAbrir({ id: l.id, nome: l.nome })}
                                                className={cls('text-left font-semibold text-slate-800 hover:text-emerald-700 hover:underline', FOCO)}
                                            >
                                                {l.nome}
                                            </button>
                                            {l.nif && <span className="block font-mono text-xs text-slate-500">{t('NIF')} {l.nif}</span>}
                                        </td>
                                        {ESCALOES.map((e, j) => (
                                            <td key={e.chave} className={cls('whitespace-nowrap px-4 py-2.5 text-right tabular-nums',
                                                l.escaloes[e.chave] === 0 ? 'text-slate-300' : j >= 3 ? 'font-semibold text-red-700' : 'text-slate-700')}>
                                                {l.escaloes[e.chave] === 0 ? '—' : kz(l.escaloes[e.chave])}
                                            </td>
                                        ))}
                                        <td className="whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(l.total)}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-amber-700">
                                            {l.a_favor === 0 ? '—' : kz(l.a_favor)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                                <tr>
                                    <td className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                                    {ESCALOES.map((e) => (
                                        <td key={e.chave} className="px-4 py-3 text-right font-bold tabular-nums text-slate-800">{kz(d.totais.escaloes[e.chave])}</td>
                                    ))}
                                    <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{kz(d.totais.total)}</td>
                                    <td className="px-4 py-3 text-right font-bold tabular-nums text-amber-700">{kz(d.totais.a_favor)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </section>
        </>
    );
}

/* ─── O extrato de um terceiro ─────────────────────────────────────────── */

function Extrato({ tipo, terceiro, ate: ateInicial, aoFechar }: {
    tipo: TipoDeTerceiro;
    terceiro: { id: number; nome: string };
    ate: string;
    aoFechar: () => void;
}) {
    const [periodo, porPeriodo] = useState({ de: `${ateInicial.slice(0, 4)}-01-01`, ate: ateInicial });

    const extrato = useQuery({
        queryKey: ['contabilidade', 'terceiros', 'extrato', tipo, terceiro.id, periodo],
        queryFn: () => contabilidade.terceiros.extrato(tipo, terceiro.id, periodo),
        placeholderData: keepPreviousData,
    });

    const d = extrato.data;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Extrato — :nome', { nome: terceiro.nome })}
            subtitulo={d?.terceiro.nif ? `${t('NIF')} ${d.terceiro.nif}` : undefined}
            icone="fa-file-lines"
            cor="bom"
            largura="xl"
        >
            <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('De')}>
                        <input type="date" value={periodo.de} max={periodo.ate}
                            onChange={(e) => e.target.value && porPeriodo((p) => ({ ...p, de: e.target.value }))} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('até')}>
                        <input type="date" value={periodo.ate} min={periodo.de}
                            onChange={(e) => e.target.value && porPeriodo((p) => ({ ...p, ate: e.target.value }))} className={entrada} />
                    </Campo>
                </div>

                {extrato.isPending ? (
                    <Carregando linhas={6} />
                ) : extrato.isError || !d ? (
                    <div className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)} role="alert">
                        {extrato.error instanceof ErroDaApi ? extrato.error.message : t('Não foi possível abrir o extrato.')}
                    </div>
                ) : (
                    <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[t('Data'), t('Documento'), t('Descrição'), t('Diário')].map((c) => (
                                        <th key={c} scope="col" className="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-600">{c}</th>
                                    ))}
                                    {[t('Débito'), t('Crédito'), t('Saldo')].map((c) => (
                                        <th key={c} scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-600">{c}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                <tr className="bg-slate-50/60">
                                    <td colSpan={6} className="px-3 py-2 text-xs font-semibold text-slate-600">
                                        {t('Saldo anterior a :data', { data: d.de })}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-slate-800">{kz(d.saldo_anterior)}</td>
                                </tr>
                                {d.movimentos.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-6 text-center text-sm text-slate-500">{t('Sem movimentos no período.')}</td>
                                    </tr>
                                ) : d.movimentos.map((m) => (
                                    <tr key={m.id} className="hover:bg-slate-50">
                                        <td className="whitespace-nowrap px-3 py-2 text-slate-600">{m.data}</td>
                                        <td className="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">{m.documento ?? '—'}</td>
                                        <td className="px-3 py-2 text-slate-800">{m.descricao ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-xs text-slate-500">{m.diario ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-emerald-700">{m.debito ? kz(m.debito) : ''}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-red-700">{m.credito ? kz(m.credito) : ''}</td>
                                        <td className={cls('whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums',
                                            m.saldo < 0 ? 'text-amber-700' : 'text-slate-900')}>{kz(m.saldo)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                                <tr>
                                    <td colSpan={4} className="px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais do período')}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-emerald-700">{kz(d.totais.debito)}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-red-700">{kz(d.totais.credito)}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(d.totais.saldo_final)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>
        </Modal>
    );
}
