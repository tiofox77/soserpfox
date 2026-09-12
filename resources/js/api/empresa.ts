/**
 * A PONTE DO ECRÃ DOS DADOS DA EMPRESA.
 *
 * Fala com `/api/v1/invoicing/react/empresa`. O logótipo vai em multipart — é
 * o único sítio deste ecrã onde o corpo não é JSON.
 */

import { api } from './cliente';

type Recado = { message: string };

export type DadosDaEmpresa = {
    name: string;
    company_name: string | null;
    nif: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    /** Código ISO de duas letras — é o único formato que a AGT aceita. */
    country: string;
    province: string;
    municipality: string;
    neighbourhood: string;
    regime: string;
    logo: string | null;
    slug: string | null;
    actualizado: string | null;
};

export type RegimeFiscal = {
    valor: string;
    rotulo: string;
    curto: string;
    descricao: string;
    facturacao: string;
    isento: boolean;
    taxa: number;
    codigo_de_isencao: string | null;
};

export type ResumoFiscal = {
    regime: { chave: string; curto: string; isento: boolean; taxa: number };
    imposto: { nome: string; taxa: number; saft: string | null; isencao: string | null } | null;
    produtos: {
        total: number;
        com_iva: number;
        isentos: number;
        /** A AGT recusa um documento isento sem código — cada um é uma rejeição. */
        isentos_sem_motivo: number;
    };
    falta_configurar_imposto: boolean;
    tem_definicoes_de_facturacao: boolean;
};

export type Geografia = {
    provincias: string[];
    provincias_novas: string[];
    municipios: Record<string, string[]>;
    /** Município → bairros SUGERIDOS. Sugere, nunca fecha. */
    bairros: Record<string, string[]>;
    paises: Record<string, string>;
    pais_padrao: string;
};

export type FichaDaEmpresa = {
    data: DadosDaEmpresa;
    resumo: ResumoFiscal;
    regimes: RegimeFiscal[];
    geografia: Geografia;
    permissoes: { editar: boolean; definicoes_de_facturacao: boolean };
};

const R = '/empresa';

export const empresa = {
    ficha: () => api.ler<FichaDaEmpresa>(R),
    guardar: (dados: Record<string, unknown>) =>
        api.guardar<Recado & { aviso: string | null }>(R, dados),
    logotipo: (ficheiro: File) => {
        const corpo = new FormData();

        corpo.append('logo', ficheiro);

        return api.enviar<Recado & { logo: string }>(`${R}/logotipo`, corpo);
    },
    apagarLogotipo: () => api.apagar<Recado>(`${R}/logotipo`),
};
