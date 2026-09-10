import { api } from './cliente';

export type Movimento = {
    id: number; quando: string | null; tipo: string; tipo_rotulo: string; meio: string; amount: number; reference_number: string | null; description: string | null;
};

export type Turno = {
    id: number; shift_number: string; status: string; status_label: string; operador: string | null; fechado_por: string | null;
    opened_at: string | null; closed_at: string | null; duration: number | null;
    opening_balance: number; opening_notes: string | null;
    cash_sales: number; card_sales: number; bank_transfer_sales: number; other_sales: number; total_sales: number;
    /** O que se devolveu (positivo) e o que ficou: bruto − devolvido. */
    credit_notes_amount: number; net_sales: number; total_credit_notes: number;
    total_invoices: number; total_receipts: number; expected_cash: number; actual_cash: number | null; cash_difference: number | null;
    closing_notes: string | null; difference_reason: string | null;
    exportar: { pdf: string; talao: string };
    movimentos?: Movimento[]; movimentos_n?: number;
};

export type EstadoDoTurno = { turno: Turno | null; caixa: { id: number; nome: string; estado: string } | null; pode_ver_todos: boolean };

type Pagina = { data: Turno[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; utilizadores: Array<{ id: number; nome: string }>; pode_ver_todos: boolean };

export const turnos = {
    estado: () => api.ler<EstadoDoTurno>('/turnos/estado'),
    abrir: (corpo: { opening_balance: number; opening_notes?: string }) => api.criar<{ turno: Turno; message: string }>('/turnos/abrir', corpo),
    fechar: (corpo: { actual_cash: number; closing_notes?: string; difference_reason?: string }) => api.criar<{ turno: Turno; message: string }>('/turnos/fechar', corpo),
    historico: (f: { dateFrom?: string; dateTo?: string; userId?: string; status?: string; page?: number }) => api.ler<Pagina>('/turnos/historico', f),
    mostrar: (id: number) => api.ler<{ turno: Turno }>(`/turnos/${id}`),
};
