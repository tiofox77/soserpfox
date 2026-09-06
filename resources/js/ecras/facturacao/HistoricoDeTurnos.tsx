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
import { t } from '@/i18n';

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
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o histórico')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const { data: linhas, meta, utilizadores, pode_ver_todos } = q.data;
    const mudar = (nome: string, v: string) => porFiltros((f) => ({ ...f, [nome]: v, page: 1 }));

    return (
        <div className="space-y-4" data-historico-turnos>
            <Cartao titulo={<span className="flex items-center gap-2"><i className="fas fa-clock-rotate-left text-slate-400" aria-hidden="true" />{t('Histórico de Turnos')}</span>}
                accoes={<a href="/invoicing/pos/shifts" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-cash-register" aria-hidden="true" />{t('O meu turno')}</a>}>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('De')}><input type="date" value={filtros.dateFrom} onChange={(e) => mudar('dateFrom', e.target.value)} className={entrada} /></Campo>
                    <Campo etiqueta={t('Até')}><input type="date" value={filtros.dateTo} onChange={(e) => mudar('dateTo', e.target.value)} className={entrada} /></Campo>
                    {pode_ver_todos && (
                        <Campo etiqueta={t('Operador')}><select value={filtros.userId} onChange={(e) => mudar('userId', e.target.value)} className={entrada}><option value="">{t('Todos')}</option>{utilizadores.map((u) => <option key={u.id} value={u.id}>{u.nome}</option>)}</select></Campo>
                    )}
                    <Campo etiqueta={t('Estado')}><select value={filtros.status} onChange={(e) => mudar('status', e.target.value)} className={entrada}><option value="">{t('Todos')}</option><option value="open">{t('Abertos')}</option><option value="closed">{t('Fechados')}</option></select></Campo>
                </div>
                {!pode_ver_todos && <p className="mt-3 text-xs text-slate-500">{t('Só vê os seus turnos.')}</p>}
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">{t('Nº')}</th><th className="px-4 py-3 font-semibold">{t('Operador')}</th><th className="px-4 py-3 font-semibold">{t('Abertura')}</th><th className="px-4 py-3 font-semibold">{t('Fecho')}</th><th className="px-4 py-3 text-right font-semibold">{t('Vendas')}</th><th className="px-4 py-3 text-right font-semibold">{t('Esperado')}</th><th className="px-4 py-3 text-right font-semibold">{t('Contado')}</th><th className="px-4 py-3 text-right font-semibold">{t('Diferença')}</th><th className="px-4 py-3 font-semibold">{t('Estado')}</th><th className="w-32 px-4 py-3"></th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', q.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={10} className="px-4 py-10 text-center text-slate-400">{t('Nenhum turno neste período.')}</td></tr>}
                            {linhas.map((turno) => (
                                <tr key={turno.id}>
                                    <td className="px-4 py-2 font-mono text-xs font-semibold text-slate-900">{turno.shift_number}</td>
                                    <td className="px-4 py-2">{turno.operador}</td>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{turno.opened_at}</td>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{turno.closed_at ?? '—'}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(turno.total_sales)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(turno.expected_cash)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{turno.actual_cash === null ? '—' : kz(turno.actual_cash)}</td>
                                    <td className={cls('px-4 py-2 text-right tabular-nums', (turno.cash_difference ?? 0) < 0 && 'text-red-700')}>{turno.cash_difference === null ? '—' : kz(turno.cash_difference)}</td>
                                    <td className="px-4 py-2">{turno.status === 'open' ? <Etiqueta cor="bom">{t('Aberto')}</Etiqueta> : <Etiqueta>{t('Fechado')}</Etiqueta>}</td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            <button type="button" onClick={() => porAberto(turno.id)} aria-label={t('Ver turno :numero', { numero: turno.shift_number })} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-eye" aria-hidden="true" /></button>
                                            <a href={turno.exportar.pdf} target="_blank" rel="noreferrer" aria-label={t('PDF do turno :numero', { numero: turno.shift_number })} className={cls('p-2 text-slate-400 hover:text-red-600', RAIO, FOCO)}><i className="fas fa-file-pdf" aria-hidden="true" /></a>
                                            <a href={turno.exportar.talao} target="_blank" rel="noreferrer" aria-label={t('Talão do turno :numero', { numero: turno.shift_number })} className={cls('p-2 text-slate-400 hover:text-slate-800', RAIO, FOCO)}><i className="fas fa-receipt" aria-hidden="true" /></a>
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>{t('Página :pagina de :ultima · :total turnos', { pagina: meta.current_page, ultima: meta.last_page, total: meta.total })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={meta.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: meta.current_page - 1 }))}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page} onClick={() => porFiltros((f) => ({ ...f, page: meta.current_page + 1 }))}>{t('Seguinte')}</Botao>
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
    const turno: Turno | undefined = q.data?.turno;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={turno ? t('Turno :numero', { numero: turno.shift_number }) : t('Turno')} largura="lg" rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}>
            {q.isPending ? <Carregando linhas={4} /> : q.isError ? <AvisoDeErro erro={q.error} /> : turno && (
                <div className="space-y-4 text-sm">
                    <dl className="grid gap-2 sm:grid-cols-3">
                        {[
                            [t('Operador'), turno.operador ?? '—'], [t('Abertura'), turno.opened_at ?? '—'], [t('Fecho'), turno.closed_at ?? '—'], [t('Fechado por'), turno.fechado_por ?? '—'],
                            [t('Saldo inicial'), `${kz(turno.opening_balance)} Kz`], [t('Dinheiro'), `${kz(turno.cash_sales)} Kz`], [t('Cartão'), `${kz(turno.card_sales)} Kz`], [t('Transferência'), `${kz(turno.bank_transfer_sales)} Kz`],
                            [t('Outros'), `${kz(turno.other_sales)} Kz`], [t('Total de vendas'), `${kz(turno.total_sales)} Kz`], [t('Esperado'), `${kz(turno.expected_cash)} Kz`], [t('Contado'), turno.actual_cash === null ? '—' : `${kz(turno.actual_cash)} Kz`],
                            [t('Diferença'), turno.cash_difference === null ? '—' : `${kz(turno.cash_difference)} Kz`], [t('Motivo'), turno.difference_reason ?? '—'], [t('Notas'), turno.closing_notes ?? turno.opening_notes ?? '—'],
                        ].map(([r, v]) => <div key={r}><dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{r}</dt><dd className="font-medium text-slate-900">{v}</dd></div>)}
                    </dl>
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">{t('Quando')}</th><th className="px-3 py-2">{t('Tipo')}</th><th className="px-3 py-2">{t('Documento')}</th><th className="px-3 py-2">{t('Meio')}</th><th className="px-3 py-2 text-right">{t('Valor')}</th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {(turno.movimentos ?? []).length === 0 && <tr><td colSpan={5} className="px-3 py-4 text-center text-slate-400">{t('Sem movimentos.')}</td></tr>}
                            {(turno.movimentos ?? []).map((m) => <tr key={m.id}><td className="whitespace-nowrap px-3 py-2 text-slate-600">{m.quando}</td><td className="px-3 py-2">{m.tipo_rotulo}</td><td className="px-3 py-2 font-mono text-xs">{m.reference_number ?? '—'}</td><td className="px-3 py-2">{m.meio}</td><td className="px-3 py-2 text-right tabular-nums">{kz(m.amount)}</td></tr>)}
                        </tbody>
                    </table>
                </div>
            )}
        </Modal>
    );
}
