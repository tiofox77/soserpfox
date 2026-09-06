import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

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
 * O TURNO DO BALCÃO: abrir, ver o que entrou, fechar com o dinheiro
 * CONTADO. Nunca se pré-preenche o contado com o esperado — senão a
 * diferença de caixa dá sempre zero e as quebras nunca aparecem. Tudo pelo
 * mesmo serviço do ecrã de sempre (`TurnosDoPos`).
 */
const kz = (v: number | null | undefined) => `${new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v) || 0)} Kz`;

export default function TurnosDoPos() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['turnos', 'estado'], queryFn: turnos.estado });
    const [abrirModal, porAbrirModal] = useState(false);
    const [fecharModal, porFecharModal] = useState(false);
    const [fechado, porFechado] = useState<Turno | null>(null);
    const [recado, porRecado] = useState('');

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['turnos'] }); };

    if (q.isPending) return <Carregando linhas={6} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir o turno</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const { turno, caixa } = q.data;

    return (
        <div className="space-y-4" data-turnos>
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar" className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-cash-register text-slate-400" aria-hidden="true" />Turno do POS</span>}
                accoes={<span className="flex gap-2">
                    <a href="/invoicing/pos/shift-history/novo-ecra" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-clock-rotate-left" aria-hidden="true" />Histórico</a>
                    {turno
                        ? <Botao cor="perigo" tom="solida" icone="fa-lock" onClick={() => porFecharModal(true)}>Fechar turno</Botao>
                        : <Botao cor="primaria" tom="solida" icone="fa-unlock" onClick={() => porAbrirModal(true)}>Abrir turno</Botao>}
                </span>}
            >
                <div className="mb-4 flex flex-wrap items-center gap-2" data-estado-turno>
                    {turno ? <Etiqueta cor="bom" icone="fa-circle">Turno {turno.shift_number} aberto às {turno.opened_at}</Etiqueta> : <Etiqueta icone="fa-circle">Sem turno aberto</Etiqueta>}
                    {caixa ? <Etiqueta cor={caixa.estado === 'open' ? 'primaria' : 'neutra'} icone="fa-vault">Caixa {caixa.nome} · {caixa.estado === 'open' ? 'aberta' : 'fechada'}</Etiqueta> : <Etiqueta icone="fa-vault">Sem caixa atribuída</Etiqueta>}
                </div>

                {!turno && <p className="text-sm text-slate-500">Abra o turno com o dinheiro que está na gaveta. As vendas do POS ficam ligadas a ele até o fechar.</p>}

                {turno && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-resumo>
                        {[
                            ['Saldo inicial', kz(turno.opening_balance)], ['Vendas em dinheiro', kz(turno.cash_sales)], ['Cartão / TPA', kz(turno.card_sales)], ['Transferência', kz(turno.bank_transfer_sales)],
                            ['Outros', kz(turno.other_sales)], ['Total de vendas', kz(turno.total_sales)], ['Documentos', `${turno.total_invoices} facturas · ${turno.total_receipts} recibos`], ['Esperado em caixa', kz(turno.expected_cash)],
                        ].map(([r, v]) => (
                            <div key={r} className={cls('border border-slate-200 bg-white p-4', RAIO)}>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{r}</p>
                                <p className="mt-1 text-lg font-bold tabular-nums text-slate-900">{v}</p>
                            </div>
                        ))}
                    </div>
                )}
            </Cartao>

            {turno && (
                <Cartao titulo={`Movimentos do turno (${turno.movimentos_n ?? 0})`} semPadding>
                    <div className="max-h-96 overflow-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Quando</th><th className="px-4 py-3 font-semibold">Tipo</th><th className="px-4 py-3 font-semibold">Documento</th><th className="px-4 py-3 font-semibold">Meio</th><th className="px-4 py-3 text-right font-semibold">Valor</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {(turno.movimentos ?? []).length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-400">Ainda não houve movimentos.</td></tr>}
                                {(turno.movimentos ?? []).map((m) => (
                                    <tr key={m.id}><td className="whitespace-nowrap px-4 py-2 text-slate-600">{m.quando}</td><td className="px-4 py-2">{m.tipo_rotulo}</td><td className="px-4 py-2 font-mono text-xs">{m.reference_number ?? '—'}</td><td className="px-4 py-2">{m.meio}</td><td className="px-4 py-2 text-right tabular-nums">{kz(m.amount)}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {abrirModal && <AbrirTurno aoFechar={() => porAbrirModal(false)} feito={(m) => { feito(m); porAbrirModal(false); }} />}
            {fecharModal && turno && <FecharTurno turno={turno} aoFechar={() => porFecharModal(false)} feito={(t, m) => { feito(m); porFecharModal(false); porFechado(t); }} />}

            <Modal aberto={fechado !== null} aoFechar={() => porFechado(null)} titulo="Turno fechado" rodape={<Botao onClick={() => porFechado(null)}>Fechar</Botao>}>
                {fechado && (
                    <div className="space-y-3 text-sm text-slate-700" data-pos-fecho>
                        <p>Turno <strong>{fechado.shift_number}</strong> fechado. Esperado {kz(fechado.expected_cash)}, contado {kz(fechado.actual_cash)}, diferença <strong className={cls((fechado.cash_difference ?? 0) < 0 && 'text-red-700')}>{kz(fechado.cash_difference)}</strong>.</p>
                        <div className="flex flex-wrap gap-2">
                            <a href={fechado.exportar.pdf} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-file-pdf" aria-hidden="true" />Resumo em PDF</a>
                            <a href={fechado.exportar.talao} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-receipt" aria-hidden="true" />Talão</a>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}

function AbrirTurno({ aoFechar, feito }: { aoFechar: () => void; feito: (m: string) => void }) {
    const [saldo, porSaldo] = useState('0');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const abrir = useMutation({ mutationFn: () => turnos.abrir({ opening_balance: Number(saldo.replace(',', '.')) || 0, opening_notes: notas || undefined }), onSuccess: (r) => feito(r.message), onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo="Abrir turno" rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-unlock" aTrabalhar={abrir.isPending} onClick={() => abrir.mutate()}>Abrir</Botao></>}>
            <AvisoDeErro erro={abrir.error} />
            <div className="grid gap-4">
                <Campo etiqueta="Saldo inicial em caixa (Kz)" erro={erros.opening_balance} obrigatorio><input type="number" min="0" step="0.01" value={saldo} onChange={(e) => porSaldo(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta="Notas" erro={erros.opening_notes}><textarea value={notas} onChange={(e) => porNotas(e.target.value)} rows={2} className={entrada} /></Campo>
            </div>
        </Modal>
    );
}

function FecharTurno({ turno, aoFechar, feito }: { turno: Turno; aoFechar: () => void; feito: (t: Turno, m: string) => void }) {
    const [contado, porContado] = useState('');
    const [notas, porNotas] = useState('');
    const [motivo, porMotivo] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const fechar = useMutation({ mutationFn: () => turnos.fechar({ actual_cash: Number(contado.replace(',', '.')), closing_notes: notas || undefined, difference_reason: motivo || undefined }), onSuccess: (r) => feito(r.turno, r.message), onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}) });
    const diferenca = contado === '' ? null : (Number(contado.replace(',', '.')) || 0) - turno.expected_cash;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={`Fechar o turno ${turno.shift_number}`} rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="perigo" tom="solida" icone="fa-lock" aTrabalhar={fechar.isPending} disabled={contado === ''} onClick={() => fechar.mutate()}>Fechar turno</Botao></>}>
            <AvisoDeErro erro={fechar.error} />
            <p className="mb-4 text-sm text-slate-600">Esperado em caixa: <strong className="tabular-nums">{kz(turno.expected_cash)}</strong> (saldo inicial {kz(turno.opening_balance)} + dinheiro {kz(turno.cash_sales)}). Conte a gaveta e escreva o que lá está.</p>
            <div className="grid gap-4">
                <Campo etiqueta="Dinheiro contado (Kz)" erro={erros.actual_cash} obrigatorio><input type="number" min="0" step="0.01" value={contado} onChange={(e) => porContado(e.target.value)} placeholder="0,00" className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                {diferenca !== null && <p className={cls('text-sm font-semibold tabular-nums', diferenca < 0 ? 'text-red-700' : diferenca > 0 ? 'text-amber-700' : 'text-emerald-700')} data-diferenca>Diferença: {kz(diferenca)}</p>}
                {diferenca !== null && Math.abs(diferenca) >= 0.01 && <Campo etiqueta="Motivo da diferença" erro={erros.difference_reason}><input value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada} /></Campo>}
                <Campo etiqueta="Notas de fecho" erro={erros.closing_notes}><textarea value={notas} onChange={(e) => porNotas(e.target.value)} rows={2} className={entrada} /></Campo>
            </div>
        </Modal>
    );
}
