/**
 * A PONTE DOS ECRÃS DOS UTILIZADORES.
 *
 * Fala com `/api/v1/invoicing/react/utilizadores/*` e `.../papeis/*` — a morada
 * dos ecrãs com sessão, a mesma de todos os outros módulos.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

type Meta = {
    current_page: number; last_page: number; per_page: number;
    total: number; from: number | null; to: number | null;
};

/* ─── Os utilizadores ─────────────────────────────────────────────────── */

/** O papel é POR EMPRESA: é gerente numa casa e caixa noutra. */
export type EmpresaDoUtilizador = {
    id: number;
    nome: string;
    papel: string | null;
    papel_id: number | null;
};

export type Utilizador = {
    id: number;
    nome: string;
    email: string;
    activo: boolean;
    super_admin: boolean;
    tem_pin: boolean;
    pin_em: string | null;
    criado_em: string | null;
    empresas: EmpresaDoUtilizador[];
    papel_aqui: string | null;
};

export type PapelDisponivel = Escolha & { empresa: number };

export type OpcoesDosUtilizadores = {
    empresas: Escolha[];
    papeis: PapelDisponivel[];
    empresa_activa: number;
    /** 0 no máximo quer dizer ilimitado — e só as contas activas contam. */
    limite: { usados: number; maximo: number; cabe_mais: boolean };
    permissoes: {
        criar: boolean; editar: boolean; eliminar: boolean;
        convidar: boolean; papeis: boolean; super_admin: boolean;
    };
};

export type FiltrosDosUtilizadores = {
    procura?: string;
    estado?: 'todos' | 'activos' | 'inactivos';
    papel?: number | '';
    por_pagina?: number;
    page?: number;
};

export type ListaDeUtilizadores = {
    data: Utilizador[];
    meta: Meta;
    resumo: { total: number; activos: number; inactivos: number; com_pin: number };
};

/* ─── Os convites ─────────────────────────────────────────────────────── */

export type Convite = {
    id: number;
    nome: string;
    email: string;
    papel: string | null;
    estado: 'pending' | 'accepted' | 'expired' | 'cancelled' | string;
    expira_em: string | null;
    convidado_por: string | null;
    aceite_por: string | null;
    quando: string | null;
};

export type ListaDeConvites = {
    data: Convite[];
    resumo: { total: number; pendentes: number; aceites: number; expirados: number };
};

/* ─── Os papéis ───────────────────────────────────────────────────────── */

export type Papel = {
    id: number;
    nome: string;
    descricao: string | null;
    permissoes: number;
    utilizadores: number;
    criado_em: string | null;
};

export type LinhaDePermissao = {
    id: number;
    nome: string;
    rotulo: string;
    accao: string;
    /** Ver, aceder, relatórios e painel — o que o atalho «só consulta» marca. */
    leitura: boolean;
};

export type GrupoDePermissoes = {
    slug: string;
    nome: string;
    icone: string;
    ids: number[];
    total: number;
    entidades: Array<{ nome: string; linhas: LinhaDePermissao[] }>;
};

export type UtilizadorParaAtribuir = {
    id: number;
    nome: string;
    email: string;
    activo: boolean;
    super_admin: boolean;
    papeis: Array<{ id: number; nome: string }>;
};

const U = '/utilizadores';
const P = '/papeis';

export const utilizadores = {
    opcoes: () => api.ler<OpcoesDosUtilizadores>(`${U}/opcoes`),
    listar: (f: FiltrosDosUtilizadores) => api.ler<ListaDeUtilizadores>(U, f),
    ficha: (id: number) => api.ler<{ data: Utilizador }>(`${U}/${id}`),
    guardar: (id: number | null, dados: Record<string, unknown>) =>
        id ? api.guardar<Recado & { data: Utilizador }>(`${U}/${id}`, dados)
           : api.criar<Recado & { data: Utilizador }>(U, dados),
    apagar: (id: number) => api.apagar<Recado>(`${U}/${id}`),
    estado: (id: number) => api.criar<Recado & { activo: boolean }>(`${U}/${id}/estado`, {}),
    pin: (id: number, pin: string, pin_confirmation: string) =>
        api.criar<Recado>(`${U}/${id}/pin`, { pin, pin_confirmation }),

    convites: {
        listar: () => api.ler<ListaDeConvites>(`${U}/convites`),
        convidar: (dados: { name: string; email: string; role_id: number | string }) =>
            api.criar<Recado>(`${U}/convites`, dados),
        reenviar: (id: number) => api.criar<Recado>(`${U}/convites/${id}/reenviar`, {}),
        cancelar: (id: number) => api.apagar<Recado>(`${U}/convites/${id}`),
    },
};

export const papeis = {
    opcoes: () => api.ler<{ grupos: GrupoDePermissoes[]; papeis: Escolha[] }>(`${P}/opcoes`),
    listar: () => api.ler<{
        data: Papel[];
        resumo: {
            papeis: number; sem_utilizadores: number;
            sem_permissoes: number; permissoes_visiveis: number;
        };
    }>(P),
    ficha: (id: number) => api.ler<{
        data: {
            id: number; nome: string; descricao: string | null;
            permissoes: number[]; utilizadores: number;
        };
    }>(`${P}/${id}`),
    guardar: (id: number | null, dados: Record<string, unknown>) =>
        id ? api.guardar<Recado & { data: { id: number; nome: string } }>(`${P}/${id}`, dados)
           : api.criar<Recado & { data: { id: number; nome: string } }>(P, dados),
    apagar: (id: number) => api.apagar<Recado>(`${P}/${id}`),

    utilizadores: (procura?: string) =>
        api.ler<{ data: UtilizadorParaAtribuir[] }>(`${P}/utilizadores`, { procura }),
    atribuir: (id: number, papeis: number[]) =>
        api.criar<Recado & { papeis: Array<{ id: number; nome: string }> }>(
            `${P}/utilizadores/${id}`, { papeis },
        ),
};
