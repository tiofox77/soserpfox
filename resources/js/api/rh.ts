import { api } from './cliente';
import type { Cor } from '@/ui/tokens';

/**
 * O RECURSOS HUMANOS, para os ecrãs em React.
 *
 * Começa pela FICHA DO FUNCIONÁRIO — o ecrã mais denso do módulo: 74 colunas,
 * nove documentos com validade e uma fotografia. As regras vivem no servidor
 * (`FuncionariosApiController`); aqui é só a forma do que viaja.
 */

export type Escolha = { valor: string; rotulo: string };

/** Um beneficiário: quem recebe o que a lei manda pagar a quem fica. */
export type Beneficiario = { nome: string; parentesco?: string; contacto?: string };

/** Um dos nove documentos da ficha, com o ficheiro que já lá está. */
export type DocumentoDaFicha = { chave: string; rotulo: string; url: string | null };

export type OpcoesDoFuncionario = {
    departamentos: Escolha[];
    /** Os cargos trazem o departamento a que pertencem, para filtrar a lista. */
    cargos: Array<Escolha & { departamento: number | null }>;
    turnos: Array<Escolha & { cor: string }>;
    chefias: Escolha[];
    bancos: Escolha[];
    generos: Escolha[];
    vinculos: Escolha[];
    estados: Array<Escolha & { cor: Cor }>;
    categorias_de_carta: Escolha[];
    geografia: { provincias: string[]; municipios: Record<string, string[]> };
    documentos: Array<{ chave: string; rotulo: string }>;
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

/** A linha da lista: o que se lê de relance. */
export type LinhaDeFuncionario = {
    id: number;
    numero: string;
    nome: string;
    email: string | null;
    telefone: string | null;
    departamento: string | null;
    cargo: string | null;
    admissao: string | null;
    estado: string;
    salario: number;
    fotografia: string | null;
    /** Quantos dos sete documentos caducam nos próximos 60 dias (ou já caducaram). */
    documentos_a_vencer: number;
};

export type ResumoDosFuncionarios = {
    total: number;
    activos: number;
    de_licenca: number;
    cessados: number;
    documentos_a_vencer: number;
};

/** A ficha inteira. As chaves são as da base, para o formulário não traduzir. */
export type FichaDoFuncionario = {
    id: number;
    numero: string;
    fotografia: string | null;
    documentos: DocumentoDaFicha[];
    beneficiaries: Beneficiario[];
} & Record<string, string | number | null | undefined | Beneficiario[] | DocumentoDaFicha[]>;

export type FiltrosDeFuncionarios = {
    procura?: string;
    departamento?: string;
    cargo?: string;
    turno?: string;
    estado?: string;
    page?: number;
    por_pagina?: number;
};

/** Quem já está na casa noutro módulo e ainda não tem ficha de RH. */
export type Importavel = { id: number; nome: string; nota: string | null };

const RAIZ = '/rh/funcionarios';

export const funcionarios = {
    opcoes: () => api.ler<OpcoesDoFuncionario>(`${RAIZ}/opcoes`),

    lista: (filtros: FiltrosDeFuncionarios) =>
        api.ler<{
            data: LinhaDeFuncionario[];
            meta: { total: number; current_page: number; last_page: number; from: number | null; to: number | null };
            resumo: ResumoDosFuncionarios;
        }>(RAIZ, filtros),

    abrir: (id: number) => api.ler<{ documento: FichaDoFuncionario }>(`${RAIZ}/${id}`),

    guardar: (dados: Record<string, unknown>) =>
        api.criar<{ documento: FichaDoFuncionario; message: string }>(RAIZ, dados),

    actualizar: (id: number, dados: Record<string, unknown>) =>
        api.guardar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}`, dados),

    eliminar: (id: number) => api.apagar<{ message: string }>(`${RAIZ}/${id}`),

    /*
     * OS FICHEIROS SOBEM À PARTE — um ficheiro não viaja em JSON, e o
     * formulário não fica refém do upload: grava-se a ficha, e os documentos
     * sobem depois, um a um.
     */
    fotografia: (id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('ficheiro', ficheiro);

        return api.enviar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}/fotografia`, corpo);
    },

    documento: (id: number, tipo: string, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('ficheiro', ficheiro);

        return api.enviar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}/documentos/${tipo}`, corpo);
    },

    apagarDocumento: (id: number, tipo: string) =>
        api.apagar<{ documento: FichaDoFuncionario; message: string }>(`${RAIZ}/${id}/documentos/${tipo}`),

    importaveis: () => api.ler<{ tecnicos: Importavel[]; hotel: Importavel[] }>(`${RAIZ}/importaveis`),

    importar: (origem: 'tecnicos' | 'hotel', ids: number[]) =>
        api.criar<{ importados: number; saltados: number; message: string }>(`${RAIZ}/importar`, { origem, ids }),
};
