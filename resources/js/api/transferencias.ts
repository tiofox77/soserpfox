import { api } from './cliente';

export type OpcoesDasTransferencias = {
    armazens: Array<{ id: number; name: string }>;
    empresas: Array<{ id: number; name: string }>;
    permissoes: { pode_transferir: boolean; pode_ajustar: boolean; pode_entre_empresas: boolean };
};

export type ArtigoParaTransferir = { id: number; name: string; code: string | null; unit: string | null; disponivel: number; custo: number };

export type ItemDaTransferencia = { product_id: number; product_name: string; product_code: string | null; unit: string | null; quantity: number | string; disponivel: number; unit_cost?: number };

export type LoteDoHistorico = {
    id: number; referencia: string | null; reference_id: number | null; tipo: string; tipo_rotulo: string; quando: string;
    armazem: string | null; produtos: number; quantidade: number; quem: string | null; notas: string | null;
    /** Os dois caminhos para o papel do lote: a pré-visualização abre, o PDF descarrega. */
    preview: string | null; pdf: string | null;
    /** Um movimento antigo, sem lote: é uma linha só, e o detalhe pede-se por ele. */
    movimento_id?: number | null;
    /** O artigo, quando a linha é de um só. */
    artigo?: string | null;
};

export type LinhaDoLote = { id: number; artigo: string | null; codigo: string | null; armazem: string | null; quantidade: number; antes: number | null; depois: number | null; notas: string | null; quem: string | null };

export type MovimentoEntreEmpresas = { id: number; quando: string; sentido: 'entrada' | 'saida'; artigo: string | null; codigo: string | null; armazem: string | null; quantidade: number; referencia: string | null; notas: string | null; quem: string | null; preview: string | null; pdf: string | null };

export type Resumo = Record<string, string | number | null>;

type Pagina<T> = { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };

export const transferencias = {
    opcoes: () => api.ler<OpcoesDasTransferencias>('/transferencias/opcoes'),
    armazensDaEmpresa: (empresa: number) => api.ler<{ data: Array<{ id: number; name: string }> }>(`/transferencias/empresas/${empresa}/armazens`),
    artigos: (f: { procura?: string; armazem?: string; so_com_stock?: boolean }) => api.ler<{ data: ArtigoParaTransferir[] }>('/transferencias/artigos', { ...f, so_com_stock: f.so_com_stock ? 1 : undefined }),
    historico: (f: { procura?: string; armazem?: string; tipo?: string; de?: string; ate?: string; page?: number }) => api.ler<Pagina<LoteDoHistorico>>('/transferencias/historico', f),
    detalhes: (f: { referencia?: string | null; reference_id?: number | null; movimento?: number | null }) => api.ler<{ data: LinhaDoLote[] }>('/transferencias/detalhes', { referencia: f.referencia ?? undefined, reference_id: f.reference_id ?? undefined, movimento: f.movimento ?? undefined }),
    historicoEntreEmpresas: (page: number) => api.ler<Pagina<MovimentoEntreEmpresas>>('/transferencias/entre-empresas/historico', { page }),
    entreArmazens: (corpo: Record<string, unknown>) => api.criar<{ referencia: string; resumo: Resumo[]; pdf: string; message: string }>('/transferencias/entre-armazens', corpo),
    ajuste: (corpo: Record<string, unknown>) => api.criar<{ referencia: string; resumo: Resumo[]; pdf: string; message: string }>('/transferencias/ajuste', corpo),
    entreEmpresas: (corpo: Record<string, unknown>) => api.criar<{ referencia_origem: string; referencia_destino: string; destino_nome: string; resumo: Resumo[]; pdf: string; message: string }>('/transferencias/entre-empresas', corpo),
};
