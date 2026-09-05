import { api, type Pagina } from './cliente';

/** O que um cliente é, do lado de cá. Sem palavra-passe: ver ClientResource. */
export type Cliente = {
    id: number;
    type: 'pessoa_juridica' | 'pessoa_fisica';
    tipo_rotulo: string;
    name: string;
    nif: string;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    municipality: string | null;
    neighbourhood: string | null;
    postal_code: string | null;
    country: string;
    pais_nome: string | null;
    documentos: number;
    pode_apagar: boolean;
};

/** O que se envia ao gravar. O `id` não vai no corpo — vai no endereço. */
export type ClienteParaGravar = Omit<
    Cliente,
    'id' | 'tipo_rotulo' | 'pais_nome' | 'documentos' | 'pode_apagar'
>;

export type FiltrosDeClientes = {
    procura?: string;
    tipo?: string;
    provincia?: string;
    por_pagina?: number;
    page?: number;
};

export type OpcoesDosClientes = {
    provincias: string[];
    paises: Record<string, string>;
    pais_padrao: string;
    tipos: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

export const clientes = {
    lista: (filtros: FiltrosDeClientes) => api.ler<Pagina<Cliente>>('/clients', filtros),
    opcoes: () => api.ler<OpcoesDosClientes>('/clients/opcoes'),
    criar: (dados: ClienteParaGravar) => api.criar<{ data: Cliente }>('/clients', dados),
    guardar: (id: number, dados: ClienteParaGravar) =>
        api.guardar<{ data: Cliente }>(`/clients/${id}`, dados),
    apagar: (id: number) => api.apagar<{ message: string }>(`/clients/${id}`),
};
