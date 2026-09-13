import { db, lerMeta, type Registo } from './base';
import { enqueue } from './fila';
import { idLocal, numero } from './util';

/**
 * O TURNO DE CAIXA — uma definição, dois ecrãs (balcão e restaurante).
 *
 * Vivia dentro do POS e o restaurante ficou sem forma de abrir turno: as
 * comandas eram tiradas na mesma, o servidor recusava-as, e a comida já tinha
 * saído. Duas cópias da aritmética da caixa acabam sempre a discordar — e a
 * caixa é onde isso custa mais. Por isso está aqui uma vez.
 */

export interface Turno extends Registo {
    open: boolean;
    number?: string | null;
    opened_at?: string | null;
    opening_balance?: number;
    cash_sales?: number;
    total_sales?: number;
    _local?: boolean;
}

export async function getShift(): Promise<Turno> {
    return (await lerMeta<Turno>('shift')) || { open: false, number: null, opened_at: null };
}

/** Abre o turno já no aparelho e enfileira a abertura real (idempotente no servidor). */
export async function openShiftOffline(dados: { opening_balance: unknown; opening_notes?: string | null }): Promise<Turno> {
    const local_uuid = idLocal('shift');
    const abertoEm = new Date().toISOString();
    const saldo = numero(dados.opening_balance);

    const optimista: Turno = {
        open: true,
        number: 'LOCAL-' + local_uuid.slice(-6).toUpperCase(),
        opened_at: abertoEm,
        opening_balance: saldo,
        cash_sales: 0,
        total_sales: 0,
        _local: true,
    };

    await db.meta.put({ key: 'shift', value: optimista });
    await enqueue('open_pos_shift', {
        local_uuid,
        opening_balance: saldo,
        opening_notes: dados.opening_notes || null,
        opened_at_local: abertoEm,
    });

    return optimista;
}

/** Fecha no aparelho; o fecho real só corre DEPOIS de as vendas pendentes subirem. */
export async function closeShiftOffline(dados: { actual_cash: unknown; closing_notes?: string | null; difference_reason?: string | null }) {
    const local_uuid = idLocal('shiftc');
    const fechadoEm = new Date().toISOString();

    await db.meta.put({ key: 'shift', value: { open: false, number: null, opened_at: null, _localClose: true } });
    await enqueue('close_pos_shift', {
        local_uuid,
        actual_cash: numero(dados.actual_cash),
        closing_notes: dados.closing_notes || null,
        difference_reason: dados.difference_reason || null,
        closed_at_local: fechadoEm,
    });

    return { open: false, closed_at_local: fechadoEm };
}

/** O dinheiro das vendas feitas sem rede desde que o turno abriu. */
export function dinheiroLocalDesdeAAbertura(turno: Turno, vendas: Registo[]): number {
    if (!turno.open) return 0;

    const abertura = turno.opened_at ? new Date(turno.opened_at).getTime() : 0;

    return vendas
        .filter((v) => !v._synced && v.payment_method === 'cash' && new Date(v.created_at).getTime() >= abertura)
        .reduce((soma, v) => soma + numero(v.total), 0);
}

export function dinheiroEsperado(turno: Turno, vendas: Registo[]): number {
    return numero(turno.opening_balance) + numero(turno.cash_sales) + dinheiroLocalDesdeAAbertura(turno, vendas);
}

export function diferencaNoFecho(contado: unknown, esperado: number): number {
    const n = parseFloat(String(contado));

    return Number.isNaN(n) ? 0 : Math.round((n - esperado) * 100) / 100;
}
