import { api } from './cliente';

/**
 * A OFICINA, para os ecrãs em React.
 *
 * Os catálogos (mecânicos, viaturas, serviços) e as peças passam pelas rotas
 * genéricas — `api/catalogos.ts` e `api/produtos.ts`. Aqui ficam os dois ecrãs
 * que são só da oficina: o painel e os mapas.
 */

export type Escolha = { valor: string; rotulo: string };

export type Serie = { etiquetas: string[]; valores: number[]; chaves?: string[] };

/** Uma ordem urgente, como o painel a mostra. */
export type OrdemUrgente = {
    id: number;
    numero: string;
    matricula: string | null;
    dono: string | null;
    estado: string;
    estado_rotulo: string;
};

/** Uma viatura com documentos a caducar, e quais. */
export type ViaturaComDocumentos = {
    id: number;
    matricula: string;
    viatura: string;
    documentos: Array<{ nome: string; quando: string; dias: number }>;
};

export type PainelDaOficina = {
    periodo: { de: string; ate: string };
    /**
     * SE QUEM ESTÁ A VER PODE VER DINHEIRO.
     *
     * Quando é falso, os valores nem saem do servidor: `dinheiro` vem nulo e
     * os gráficos contam ORDENS em vez de kwanzas. O Blade escondia-os no HTML
     * com «•••» e mandava o número na mesma para o browser.
     */
    ve_dinheiro: boolean;
    cartoes: { ordens: number; pendentes: number; em_curso: number; concluidas: number };
    dinheiro: { facturado: number; a_receber: number } | null;
    viaturas: { total: number; activas: number };
    series: { mensal: Serie; estados: Serie; servicos: Serie; mecanicos: Serie };
    top_servicos: Array<{ nome: string; vezes: number; receita: number | null }>;
    urgentes: OrdemUrgente[];
    documentos_a_caducar: ViaturaComDocumentos[];
};

export const painelDaOficina = {
    ler: (de?: string, ate?: string) =>
        api.ler<PainelDaOficina>('/oficina/painel', { de, ate }),
};

/* ─── Os mapas ──────────────────────────────────────────────────────── */

export type ColunaDoMapa = {
    chave: string;
    rotulo: string;
    formato: 'texto' | 'numero' | 'dinheiro' | 'data' | 'estado';
};

export type LinhaDoMapa = Record<string, string | number | null>;

export type OpcoesDosMapasDaOficina = {
    mapas: Array<Escolha & { icone: string; pede_estado: boolean }>;
    estados: Escolha[];
};

export type MapaDaOficina = {
    mapa: string;
    /** As moradas do papel e do Excel — compostas pelo servidor, com os filtros. */
    descargas: { pdf: string; excel: string };
    colunas: ColunaDoMapa[];
    linhas: LinhaDoMapa[];
    totais: LinhaDoMapa | null;
    /** A frase de «não há nada», escrita pelo mapa que a diz. */
    nada: string | null;
    periodo: { de: string; ate: string };
};

export type FiltrosDoMapa = {
    mapa: string;
    de?: string;
    ate?: string;
    estado?: string;
};

export const mapasDaOficina = {
    opcoes: () => api.ler<OpcoesDosMapasDaOficina>('/oficina/relatorios/opcoes'),
    mostrar: (filtros: FiltrosDoMapa) => api.ler<MapaDaOficina>('/oficina/relatorios', filtros),
};
