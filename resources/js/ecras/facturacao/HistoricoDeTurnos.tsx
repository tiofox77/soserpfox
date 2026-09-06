import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { turnos, type Turno } from '@/api/turnos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';

/**
 * O HISTÓRICO DE TURNOS. Quem não pode ver todos fica preso aos seus — e
 * não escapa pelo filtro: a regra é do servidor.
 */
const kz = (v: number | null | undefined) => new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v) || 0);
const hoje = new Date().toISOString().slice(0, 10);

export default function HistoricoDeTurnos() {
    const [filtros, porFiltros] = useState({ dateFrom: `${hoje.slice(0, 8)}01`, dateTo: hoje, userId: '', status: '', page: 1 });
    const [aberto, porAberto] = useState<number | null>(null);
    const q = useQuery({ queryKey: ['turnos', 'historico', filtros], queryFn: () => turnos.historico(filtros), placeholderData: keepPreviousData });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir o histórico</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const { data: linhas, meta, utilizadores, pode_ver_todos } = q.data;
    const mudar = (nome: string, v: string) => porFiltros((f) => ({ ...f, [nome]: v, page: 1 }));

    return (
        <div className="space-y-4" data-historico-turnos>
            <Cartao titulo={<span className="flex items-center gap-2"><i className="fas fa-clock-rotate-left text-slate-400" aria-hidden="true" />Histórico de Turnos</span>}
                accoes={<a href="/invoicing/pos/shifts/novo-ecra" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-cash-register" aria-hidden="true" />O meu turno</a>}>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta="De"><input type="date" value={filtros.dateFrom} onChange={(e) => mudar('dateFrom', e.target.value)} className={entrada} /></Campo>
                    <Campo etiqueta="Até"><input type="date" value={filtros.dateTo} onChange={(e) => mudar('dateTo', e.target.value)} className={entrada} /></Campo>
                    {pode_ver_todos && (
                        <Campo etiqueta="Operador"><select value={filtros.userId} onChange={(e) => mudar('userId', e.target.value)} className={entrada}><option value="">Todos</option>{utilizadores.map((u) => <option key={u.id} value={u.id}>{u.nome}</option>)}</select></Campo>
                    )}
                    <Campo etiqueta="Estado"><select value={filtros.status} onChange={(e) => mudar('status', e.target.value)} className={entrada}><option value="">Todos</option><option value="open">Abertos</option><option value="closed">Fechados</option></select></Campo>
                </div>
                {!pode_ver_todos && <p className="mt-3 text-xs text-slate-500">Só vê os seus turnos.</p>}
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Nº</th><th className="px-4 py-3 font-semibold">Operador</th><th className="px-4 py-3 font-semibold">Abertura</th><th className="px-4 py-3 font-semibold">Fecho</th><th className="px-4 py-3 text-right font-semibold">Vendas</th><th className="px-4 py-3 text-right font-semibold">Esperado</th><th className="px-4 py-3 text-right font-semibold">Contado</th><th className="px-4 py-3 text-right font-semibold">Diferença</th><th className="px-4 py-3 font-semibold">Estado</th><th className="w-32 px-4 py-3"></th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', q.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={10} className="px-4 py-10 text-center text-slate-400">Nenhum turno neste período.</td></tr>}
                            {linhas.map((t) => (
                                <tr key={t.id}>
                                    <td className="px-4 py-2 font-mono text-xs font-semibold text-slate-900">{t.shift_number}</td>
                                    <td className="px-4 py-2">{t.operador}</td>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{t.opened_at}</td>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{t.closed_at ?? '—'}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(t.total_sales)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(t.expected_cash)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{t.actual_cash === null ? '—' : kz(t.actual_cash)}</td>
                                    <td className={cls('px-4 py-2 text-right tabular-nums', (t.cash_difference ?? 0) < 0 && 'text-red-700')}>{t.cash_difference === null ? '—' : kz(t.cash_difference)}</td>
                                    <td className="px-4 py-2">{t.status === 'open' ? <Etiqueta cor="bom">Aberto</Etiqueta> : <Etiqueta>Fechado</Etiqueta>}</td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            <button type="button" onClick={() => porAberto(t.id)} aria-label={`Ver turno ${t.shift_number}`} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-eye" aria-hidden="true" /></button>
                                            <a href={t.exportar.pdf} target="_blank" rel="noreferrer" aria-label={`PDF do turno ${t.shift_number}`} className={cls('p-2 text-slate-400 hover:text-red-600', RAIO, FOCO)}><i className="fas fa-file-pdf" aria-hidden="true" /></a>
                                            <a href={t.exportar.talao} target="_blank" rel="noreferrer" aria-label={`Talão do turno ${t.shift_number}`} className={cls('p-2 text-slate-400 hover:text-slate-800', RAIO, FOCO)}><i className="fas fa-receipt" aria-hidden="true" /></a>
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>Página {meta.current_page} de {meta.last_page} · {meta.total} turnos</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={meta.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: meta.current_page - 1 }))}>Anterior</Botao>
                            <Botao icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page} onClick={() => porFiltros((f) => ({ ...f, page: meta.current_page + 1 }))}>Seguinte</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {aberto !== null && <Detalhe id={aberto} aoFechar={() => porAberto(null)} />}
        </div>
    );
}

function Detalhe({ id, aoFechar }: { id: number; aoFechar: () => void }) {
    const q = useQuery({ queryKey: ['turnos', 'turno', id], queryFn: () => turnos.mostrar(id) });
    const t: Turno | undefined = q.data?.turno;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t ? `Turno ${t.shift_number}` : 'Turno'} largura="lg" rodape={<Botao onClick={aoFechar}>Fechar</Botao>}>
            {q.isPending ? <Carregando linhas={4} /> : q.isError ? <AvisoDeErro erro={q.error} /> : t && (
                <div className="space-y-4 text-sm">
                    <dl className="grid gap-2 sm:grid-cols-3">
                        {[
                            ['Operador', t.operador ?? '—'], ['Abertura', t.opened_at ?? '—'], ['Fecho', t.closed_at ?? '—'], ['Fechado por', t.fechado_por ?? '—'],
                            ['Saldo inicial', `${kz(t.opening_balance)} Kz`], ['Dinheiro', `${kz(t.cash_sales)} Kz`], ['Cartão', `${kz(t.card_sales)} Kz`], ['Transferência', `${kz(t.bank_transfer_sales)} Kz`],
                            ['Outros', `${kz(t.other_sales)} Kz`], ['Total de vendas', `${kz(t.total_sales)} Kz`], ['Esperado', `${kz(t.expected_cash)} Kz`], ['Contado', t.actual_cash === null ? '—' : `${kz(t.actual_cash)} Kz`],
                            ['Diferença', t.cash_difference === null ? '—' : `${kz(t.cash_difference)} Kz`], ['Motivo', t.difference_reason ?? '—'], ['Notas', t.closing_notes ?? t.opening_notes ?? '—'],
                        ].map(([r, v]) => <div key={r}><dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{r}</dt><dd className="font-medium text-slate-900">{v}</dd></div>)}
                    </dl>
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">Quando</th><th className="px-3 py-2">Tipo</th><th className="px-3 py-2">Documento</th><th className="px-3 py-2">Meio</th><th className="px-3 py-2 text-right">Valor</th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {(t.movimentos ?? []).length === 0 && <tr><td colSpan={5} className="px-3 py-4 text-center text-slate-400">Sem movimentos.</td></tr>}
                            {(t.movimentos ?? []).map((m) => <tr key={m.id}><td className="whitespace-nowrap px-3 py-2 text-slate-600">{m.quando}</td><td className="px-3 py-2">{m.tipo_rotulo}</td><td className="px-3 py-2 font-mono text-xs">{m.reference_number ?? '—'}</td><td className="px-3 py-2">{m.meio}</td><td className="px-3 py-2 text-right tabular-nums">{kz(m.amount)}</td></tr>)}
                        </tbody>
                    </table>
                </div>
            )}
        </Modal>
    );
}
