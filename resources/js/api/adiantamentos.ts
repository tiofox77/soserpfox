import { api } from './cliente';

export type Adiantamento = {
    id: number;
    numero: string;
    client_id: number;
    payment_date: string;
    amount: number;
    used_amount: number;
    remaining_amount: number;
    payment_method: string;
    purpose: string | null;
    notes: string | null;
    status: string;
    pode_editar: boolean;
    abrir: string;
    pdf: string;
};

export type OpcoesDoAdiantamento = {
    clientes: Array<{ id: number; name: string; nif: string | null }>;
    formas: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean };
    /** "Imprimir automaticamente ao gravar", das definições da empresa. */
    imprimir_ao_gravar: boolean;
};

export const adiantamentos = {
    opcoes: () => api.ler<OpcoesDoAdiantamento>('/adiantamentos/opcoes'),
    mostrar: (id: number) => api.ler<{ data: Adiantamento }>(`/adiantamentos/${id}`),
    guardar: (corpo: Record<string, unknown>) => api.criar<{ data: Adiantamento; message: string }>('/adiantamentos', corpo),
    actualizar: (id: number, corpo: Record<string, unknown>) =>
        api.guardar<{ data: Adiantamento; message: string }>(`/adiantamentos/${id}`, corpo),
};
