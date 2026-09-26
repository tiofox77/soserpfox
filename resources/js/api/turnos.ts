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
    /** O que a tesouraria tirou ou pôs na gaveta durante o turno (recolhas, despesas, troco). Já contado no esperado. */
    saidas_da_gaveta: number; entradas_na_gaveta: number;
    /** O que o operador emitiu A PRAZO pelos Documentos (FT, ND, NC sem devolução). Fora da gaveta e do total. */
    a_prazo?: { quantos: number; valor: number };
    closing_notes: string | null; difference_reason: string | null;
    /** O papel resumido e o com produtos (`?detalhe=produtos`). */
    exportar: { pdf: string; talao: string; pdf_produtos: string; talao_produtos: string };
    movimentos?: Movimento[]; movimentos_n?: number;
};

/** A pergunta do fecho: só os totais, ou também artigo a artigo. */
export type TipoDeFecho = 'resumido' | 'produtos';

export type ProdutoDoTurno = {
    chave: string; nome: string; codigo: string | null;
    /** Vendida, devolvida e a que ficou (vendida − devolvida). */
    quantidade: number; devolvida: number; liquida: number;
    preco_medio: number; total: number; devolvido: number; liquido: number;
    documentos: number;
    /** A parte do líquido do turno, em %. */
    peso: number;
};

export type DocumentoDoTurno = {
    /** `numero` é o da série interna; `numero_agt` o fiscal, quando é outro. */
    tipo: 'factura' | 'nota'; numero: string; numero_agt?: string | null; hora: string | null; cliente: string | null;
    meio: string; artigos: number; total: number; anulada: boolean;
};

export type VendasDoTurno = {
    produtos: ProdutoDoTurno[];
    totais: {
        artigos: number; quantidade: number; bruto: number; descontos: number; devolvido: number; liquido: number; imposto: number;
        facturas: number; anuladas: number; notas: number; ticket_medio: number;
    };
    documentos: DocumentoDoTurno[];
};

export type EstadoDoTurno = { turno: Turno | null; ultimo_fechado: Turno | null; caixa: { id: number; nome: string; estado: string } | null; pode_ver_todos: boolean };

type Pagina = { data: Turno[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; utilizadores: Array<{ id: number; nome: string }>; pode_ver_todos: boolean };

export const turnos = {
    estado: () => api.ler<EstadoDoTurno>('/turnos/estado'),
    abrir: (corpo: { opening_balance: number; opening_notes?: string }) => api.criar<{ turno: Turno; message: string }>('/turnos/abrir', corpo),
    fechar: (corpo: { actual_cash: number; closing_notes?: string; difference_reason?: string }) => api.criar<{ turno: Turno; message: string }>('/turnos/fechar', corpo),
    historico: (f: { dateFrom?: string; dateTo?: string; userId?: string; status?: string; page?: number }) => api.ler<Pagina>('/turnos/historico', f),
    mostrar: (id: number) => api.ler<{ turno: Turno }>(`/turnos/${id}`),
    produtos: (id: number) => api.ler<VendasDoTurno>(`/turnos/${id}/produtos`),
};
