import { api } from './cliente';

export type Modelo = {
    id: number; nome: string; descricao: string | null; sector: string | null; is_default: boolean; is_active: boolean;
    blocos_n: number; orcamentos_n: number; cor: string; actualizado: string | null; editor: string; previa: string;
};

export type OpcoesDosModelos = {
    arranque: Array<{ chave: string; nome: string; descricao: string; icone: string; cor: string }>;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_eliminar: boolean };
};

export type Layout = { pagina: number; x: number; y: number; largura: number; altura: number; z: number; bloqueado: boolean };

export type Bloco = { id: string; tipo: string; layout?: Layout } & Record<string, unknown>;

export type EstadoDoEditor = { id: number; nome: string; descricao: string; blocos: Bloco[]; estilos: Record<string, unknown>; paginas: number; seleccionado: string | null };

export type Editor = {
    estado: EstadoDoEditor;
    previa: string;
    catalogo: Array<{ tipo: string; nome: string; icone: string; ajuda: string; padroes: Record<string, unknown> }>;
    variaveis: Record<string, Record<string, string>>;
    estilos_padrao: Record<string, unknown>;
};

type Pagina = { data: Modelo[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };

export const modelos = {
    opcoes: () => api.ler<OpcoesDosModelos>('/modelos-de-proposta/opcoes'),
    lista: (f: { procura?: string; page?: number }) => api.ler<Pagina>('/modelos-de-proposta', f),
    criar: (corpo: { arranque?: string }) => api.criar<{ data: Modelo; message: string }>('/modelos-de-proposta', corpo),
    duplicar: (id: number) => api.criar<{ data: Modelo; message: string }>(`/modelos-de-proposta/${id}/duplicar`, {}),
    tornarPadrao: (id: number) => api.criar<{ message: string }>(`/modelos-de-proposta/${id}/padrao`, {}),
    eliminar: (id: number) => api.apagar<{ message: string }>(`/modelos-de-proposta/${id}`),
    editor: (id: number) => api.ler<Editor>(`/modelos-de-proposta/${id}/editor`),
    accao: (id: number, corpo: Record<string, unknown>) => api.criar<{ estado: EstadoDoEditor; previa: string; message: string | null }>(`/modelos-de-proposta/${id}/editor`, corpo),
};
