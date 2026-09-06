import { api } from './cliente';

export type Registo = {
    id: number; quando: string | null; evento: string; canal: string | null; quem: string | null; modelo: string; registo: string | null; frase: string;
    campos?: Array<{ campo: string; rotulo: string; antes: string | null; depois: string | null; referencia: boolean }>;
    ip?: string | null;
};

export type OpcoesDaAuditoria = {
    eventos: string[];
    canais: string[];
    actores: Array<{ id: number; nome: string }>;
};

export type Integridade = { ok: boolean; total: number; problemas: unknown[]; em: string };

type Pagina = { data: Registo[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };

export const auditoria = {
    opcoes: () => api.ler<OpcoesDaAuditoria>('/auditoria/opcoes'),
    lista: (f: { procura?: string; evento?: string; canal?: string; actor?: string; de?: string; ate?: string; page?: number }) => api.ler<Pagina>('/auditoria', f),
    mostrar: (id: number) => api.ler<{ data: Registo }>(`/auditoria/${id}`),
    integridade: () => api.ler<Integridade>('/auditoria/integridade'),
};
