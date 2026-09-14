import { api } from './cliente';

export type Formato = 'dinheiro' | 'inteiro' | 'numero' | 'percentagem' | 'data' | 'texto' | 'estado' | 'dias' | 'ligacao';

export type Opcao = { valor: string; rotulo: string };

export type Filtro = {
    nome: string; rotulo: string; tipo: 'select' | 'date' | 'text' | 'number' | 'entidade';
    opcoes?: Opcao[]; omissao?: string | number | null;
};

/**
 * As moradas de uma coluna `ligacao`: cada uma é o CAMINHO de um campo da
 * linha (`lote_pdf`), não a morada em si — cada linha leva a sua.
 */
export type Ligacao = { abrir?: string; previsao?: string; pdf?: string };

export type Coluna = { rotulo: string; chave: string; formato?: Formato; alinhar?: 'direita' | 'centro'; ligacao?: Ligacao };

export type Tabela = { titulo?: string; chave: string; colunas: Coluna[]; rodape?: Record<string, string>; numerada?: boolean; vazio?: string };

export type Esquema = {
    slug: string; titulo: string; descricao?: string;
    periodo: { omissao: string } | null;
    filtros: Filtro[];
    cartoes: Array<{ rotulo: string; chave: string; formato?: Formato; cor?: string }>;
    tabelas: Tabela[];
    csv: boolean;
};

export type Dados = Record<string, unknown> & { intervalo?: { de: string; ate: string } };

/** `pdf` é o papel próprio do mapa, já com os filtros do ecrã — null quando não há. */
export type Relatorio = { esquema: Esquema; dados: Dados; atalhos: Opcao[]; csv: string | null; pdf?: string | null };

export type Seccao = { titulo: string; icone: string; cor: string; relatorios: Array<{ slug: string; nome: string; desc: string; icone: string; caminho: string }> };

export const relatorios = {
    seccoes: () => api.ler<{ seccoes: Seccao[] }>('/relatorios'),
    ler: (slug: string, filtros: Record<string, string>) => api.ler<Relatorio>(`/relatorios/${slug}`, filtros),
    entidades: (slug: string, entidade: string, q: string) => api.ler<{ data: Array<{ id: number; name: string; nif: string | null }> }>(`/relatorios/${slug}/entidades`, { entidade, q }),
};
