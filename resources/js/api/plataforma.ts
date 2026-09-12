/**
 * A PONTE DOS ECRÃS DA PLATAFORMA.
 *
 * RAIZ PRÓPRIA: `/api/v1/plataforma/react`. Não é a API da facturação com outro
 * caminho — é outro grupo, com outra guarda (`superadmin`) e sem a verificação
 * de subscrição, porque o dono da plataforma não é subscritor de nada. Misturar
 * as duas fazia com que uma mudança na guarda de uma mexesse na outra.
 */

import type { GrupoDeIcones } from './catalogos';
import { criarApi } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

export const apiDaPlataforma = criarApi('/api/v1/plataforma/react');

export type Serie = { etiquetas: string[]; valores: number[] };

/* ─── O painel ────────────────────────────────────────────────────────── */

export type NumerosDaPlataforma = {
    empresas: number;
    activas: number;
    inactivas: number;
    utilizadores: number;
    modulos: number;
    subscricoes_activas: number;
    subscricoes_em_ensaio: number;
    receita_total: number;
    receita_do_mes: number;
    receita_por_cobrar: number;
    pedidos_por_aprovar: number;
    empresas_novas_do_mes: number;
    /** Sem mês passado não há percentagem: «100%» sobre zero não diz nada. */
    crescimento: number | null;
};

export type PainelDaPlataforma = {
    numeros: NumerosDaPlataforma;
    crescimento_das_empresas: Serie;
    receita_mensal: Serie;
    planos: Array<{ id: number; nome: string; empresas: number; preco: number; promocional: boolean }>;
    modulos: Array<{ id: number; nome: string; icone: string; empresas: number; nucleo: boolean }>;
    maiores_subscricoes: Array<{
        id: number; empresa: string | null; empresa_id: number | null;
        plano: string | null; valor: number; ciclo: string | null;
    }>;
    a_expirar: Array<{
        id: number; empresa: string | null; empresa_id: number | null; plano: string | null;
        dia: string | null; dias: number | null; valor: number;
    }>;
    empresas_recentes: Array<{
        id: number; nome: string; email: string | null; activa: boolean;
        utilizadores: number; plano: string | null; criada_em: string | null;
    }>;
    pedidos_recentes: Array<{
        id: number; empresa: string | null; plano: string | null;
        valor: number; estado: string; dia: string | null;
    }>;
    facturas_recentes: Array<{
        id: number; numero: string | null; empresa: string | null;
        valor: number; estado: string; dia: string | null;
    }>;
};

/* ─── Quem nos descobriu ──────────────────────────────────────────────── */

export type FiltrosDaAnalitica = {
    periodo?: string;
    de?: string;
    ate?: string;
    aparelho?: string;
    canal?: string;
    pais?: string;
    browser?: string;
    pagina?: string;
};

export type Analitica = {
    periodo: { nome: string; de: string; ate: string };
    agora: {
        visitantes: number;
        paginas: Array<{ pagina: string; visitantes: number }>;
    };
    /** Estes não são visitantes: são clientes a trabalhar, com nome. */
    utilizadores: {
        online: number;
        lista: Array<{
            nome: string; email: string | null; empresa: string | null;
            pagina: string; ha_quanto: string | null; agora: boolean;
            acessos: number; aparelho: string | null; cidade: string | null;
        }>;
    };
    numeros: {
        visitantes: number; sessoes: number; pageviews: number; cliques: number;
        pesquisas: number; registos: number; whatsapp: number; modulos: number;
        eventos: number; conversao: number; paginas_por_sessao: number; rejeicao: number;
        /** Sem período anterior não há tendência — e zero não é «igual». */
        tendencia: number | null;
    };
    paginas: Array<{ pagina: string; vistas: number; visitantes: number; tempo_medio: number | null }>;
    origens: {
        canais: Array<{ canal: string; icone: string; visitantes: number }>;
        fontes: Array<{ fonte: string; canal: string; icone: string; visitantes: number }>;
    };
    regiao: {
        paises: Array<{ codigo: string; nome: string; bandeira: string; visitantes: number }>;
        cidades: Array<{ nome: string; bandeira: string; visitantes: number }>;
        por_resolver: number;
    };
    procuras: Array<{ termo: string; vezes: number; pessoas: number; ultima: string | null }>;
    aparelhos: {
        tipos: Array<{ nome: string; visitantes: number }>;
        browsers: Array<{ nome: string; visitantes: number }>;
        sistemas: Array<{ nome: string; visitantes: number }>;
    };
    dias: Serie;
    funil: Array<{ degrau: string; quantos: number; icone: string }>;
    visitantes: Array<{
        id: string; pontos: number; eventos: number; pageviews: number; cliques: number;
        paginas: number; tempo: number; bandeira: string; pais: string | null;
        cidade: string | null; aparelho: string | null; browser: string | null;
        utm: string | null; referrer: string | null;
        primeiro: string | null; visto: string | null;
    }>;
    ao_vivo: {
        eventos: Array<{
            id: number; ha_quanto: string | null; tipo: string; nome: string | null;
            pagina: string | null; modulo: string | null; utm: string | null; visitante: string;
        }>;
        por_acao: Array<{ nome: string | null; quantos: number }>;
    };
    opcoes: {
        paises: Escolha[];
        browsers: Escolha[];
        paginas: Escolha[];
        aparelhos: Escolha[];
        canais: Array<Escolha & { icone: string }>;
        periodos: Escolha[];
    };
};

export type PercursoDoVisitante = {
    visitante: string;
    cabeca: {
        chegou: string | null; canal: string; fonte: string;
        bandeira: string; onde: string | null;
        aparelho: string | null; browser: string | null;
    } | null;
    passos: Array<{
        id: number; quando: string | null; tipo: string; nome: string | null;
        pagina: string; termo: string | null; segundos: number | null;
    }>;
};

/* ─── Os planos ───────────────────────────────────────────────────────── */

export type ModuloDoPlano = { id: number; nome: string; slug: string; icone: string };

export type PlanoDaLista = {
    id: number; nome: string; slug: string; descricao: string | null;
    preco_mensal: number; preco_trimestral: number; preco_semestral: number; preco_anual: number;
    desconto_anual: number; poupanca_anual: number;
    max_utilizadores: number; max_empresas: number; max_espaco_mb: number;
    /** Nulo é «sem tecto», e é diferente de zero. */
    max_documentos: number | null;
    dias_de_ensaio: number; ordem: number;
    activo: boolean; na_montra: boolean; destacado: boolean;
    promocional: boolean; activa_sozinho: boolean;
    funcionalidades: string[];
    modulos: ModuloDoPlano[];
    subscricoes_activas: number;
    pode_apagar: boolean;
    subscricoes_presas: number;
};

export type FichaDoPlano = {
    id: number; name: string; slug: string; description: string;
    price_monthly: number; price_quarterly: number; price_semiannual: number; price_yearly: number;
    max_users: number; max_companies: number; max_storage_mb: number; max_documents: number | null;
    trial_days: number; order: number;
    is_active: boolean; is_public: boolean; is_featured: boolean;
    is_promotional: boolean; auto_activate: boolean;
    features: string[]; modulos: number[];
};

export type ListaDePlanos = {
    planos: PlanoDaLista[];
    modulos: Array<ModuloDoPlano & { nucleo: boolean; preco: number }>;
    numeros: { planos: number; activos: number; na_montra: number; modulos: number };
};

/* ─── Os módulos ──────────────────────────────────────────────────────── */

export type ModuloDaLista = {
    id: number; nome: string; slug: string; descricao: string | null;
    icone: string; versao: string; ordem: number;
    activo: boolean; nucleo: boolean; preco: number;
    dependencias: Array<{ slug: string; nome: string }>;
    empresas: number; planos: number;
    pode_apagar: boolean; porque_nao_apaga: string | null;
};

export type FichaDoModulo = {
    id: number; name: string; slug: string; description: string;
    icon: string; version: string; order: number;
    is_active: boolean; is_core: boolean; default_price: number;
    dependencies: string[];
};

export type ListaDeModulos = {
    modulos: ModuloDaLista[];
    numeros: { modulos: number; activos: number; nucleo: number; planos: number };
    galeria_de_icones: GrupoDeIcones[];
    escolhas: Escolha[];
};

/* ─── As empresas ─────────────────────────────────────────────────────── */

export type FiltrosDasEmpresas = {
    procura?: string;
    estado?: string;
    plano?: string;
    activa?: string;
    ordenar?: string;
    por_pagina?: string;
    pagina?: number;
};

export type EmpresaDaLista = {
    id: number; nome: string; slug: string; email: string | null; telefone: string | null;
    razao_social: string | null; nif: string | null;
    /** Nulo quando não há NIF; falso quando não começa por 5. */
    nif_de_empresa: boolean | null;
    logo: string | null; activa: boolean; criada_em: string | null;
    plano: string | null; ciclo: string | null;
    max_utilizadores: number; max_espaco_mb: number; modulos: number; utilizadores: number;
    subscricao: {
        rotulo: string; cor: string; icone: string; detalhe: string;
        nota: string | null; falta: string | null; ate: string | null;
    };
    vida: {
        chave: string; texto: string; facturas_30d: number; artigos: number;
        movimentos_30d: number; entraram_30d: number; utilizadores: number;
        ultima_entrada: string | null; entrou_ha_pouco: boolean;
    } | null;
};

export type EmpresaVista = EmpresaDaLista & {
    morada: string | null; cidade: string | null; codigo_postal: string | null; pais: string | null;
    actualizada_em: string | null; limite_de_utilizadores: number; ficha_abaixo_do_plano: boolean;
    motivo_da_desactivacao: string | null; desactivada_em: string | null;
    documentos_emitidos: number; limite_de_documentos: number | null;
    pessoas: Array<{ id: number; nome: string; email: string }>;
    modulos_lista: Array<{ id: number; nome: string; icone: string; activo: boolean }>;
};

export type FichaDaEmpresa = {
    id: number; name: string; slug: string; email: string; phone: string | null;
    company_name: string | null; nif: string | null; address: string | null;
    city: string | null; postal_code: string | null; country: string;
    max_users: number; max_storage_mb: number; is_active: boolean;
};

export type ListaDeEmpresas = {
    empresas: EmpresaDaLista[];
    paginacao: { pagina: number; ultima: number; total: number; por_pagina: number };
    contagens: Record<string, number>;
    opcoes: { planos: Escolha[]; ordenacoes: Escolha[]; paises: Escolha[] };
};

export type UtilizadoresDaEmpresa = {
    empresa: { id: number; nome: string };
    limite: number;
    cabe_mais_um: boolean;
    utilizadores: Array<{ id: number; nome: string; email: string; papel: number | null; entrou_em: string | null }>;
    papeis: Escolha[];
};

export type PlanoDaEmpresa = {
    empresa: { id: number; nome: string };
    actual: {
        plano_id: number; plano: string | null; ciclo: string; ciclo_nome: string; valor: number;
        termina_em: string | null; faltam: number | null; dias_personalizados: number | null;
        com_oferta: boolean; preco_por_utilizador: number | null; utilizadores_cobrados: number | null;
        max_documentos: number | null; max_utilizadores: number; max_espaco_mb: number;
    } | null;
    documentos: { emitidos: number; tecto: number | null };
    planos: Array<{
        id: number; nome: string; descricao: string | null; destacado: boolean; na_montra: boolean;
        max_utilizadores: number; max_espaco_mb: number; preco_mensal: number; preco_anual: number;
    }>;
};

export type ResumoDoAcordo = {
    fim: string; dias: number; valor: number; base: string;
    oferta_aplicavel: boolean; com_oferta: boolean; max_documentos: number | null; ciclo_nome: string;
};

export type PlanoAMedidaDaEmpresa = {
    empresa: { id: number; nome: string };
    sugestao: { nome: string; utilizadores: number; empresas: number; armazenamento: number; ciclo: string };
    modulos: Array<{ slug: string; nome: string; icone: string; preco: number; ja_tem: boolean; dependencias: string[] }>;
};

/* ─── A facturação da plataforma ──────────────────────────────────────── */

export type PedidoPendente = {
    id: number; empresa: string | null; empresa_apagada: boolean; pessoa: string | null; email: string | null;
    plano: string | null; valor: number; ciclo: string; dia: string | null;
    metodo: string | null; referencia: string | null; comprovativo: string | null;
};

export type FacturacaoDaPlataforma = {
    numeros: {
        cobrado: number; pendente: number; vencido: number;
        facturas: number; subscricoes: number; pedidos_pendentes: number;
    };
    pedidos: PedidoPendente[];
    opcoes: {
        empresas: Escolha[];
        planos: Array<{
            id: number; nome: string; max_utilizadores: number; max_espaco_mb: number;
            preco_mensal: number; preco_trimestral: number; preco_semestral: number; preco_anual: number;
            modulos: number;
        }>;
        metodos: Escolha[];
    };
    saft: { certificado: string; produto: string; versao: string };
};

export type SubscricaoDaLista = {
    id: number; empresa: string | null; empresa_apagada: boolean; plano: string | null;
    estado: string; valor: number; ciclo: string; inicio: string | null; renovacao: string | null;
    /** Dias inteiros e com sinal: negativo = já passou. */
    dias: number | null;
    cancelada_em: string | null; com_oferta: boolean; dias_personalizados: number | null;
    preco_por_utilizador: number | null; utilizadores_cobrados: number | null;
    limites: { utilizadores: number; empresas: number; espaco_mb: number; dias_de_ensaio: number } | null;
    modulos: Array<{ nome: string; icone: string }>;
    funcionalidades: string[];
    pode_apagar: boolean;
};

export type FacturaDaPlataforma = {
    id: number; numero: string; empresa: string | null; empresa_apagada: boolean; descricao: string | null;
    dia: string | null; vence: string | null; paga_em: string | null;
    subtotal: number; imposto: number; total: number; estado: string;
    metodo: string | null; referencia: string | null;
};

export type FichaDaFactura = {
    id: number | null; tenant_id: number | null; invoice_number: string; description: string;
    invoice_date: string; due_date: string; subtotal: number; tax: number; status: string;
};

export type FichaDaSubscricao = {
    id: number; tenant_id: number; empresa: string | null; plan_id: number; billing_cycle: string;
    com_oferta: boolean; dias: number | null; preco_por_utilizador: number | null;
    utilizadores: number | null; pago: boolean;
};

export type Paginacao = { pagina: number; ultima: number; total: number };

export const plataforma = {
    facturacao: {
        ler: () => apiDaPlataforma.ler<FacturacaoDaPlataforma>('/facturacao'),
        subscricoes: (f: { procura?: string; estado?: string; pagina?: number }) =>
            apiDaPlataforma.ler<{ subscricoes: SubscricaoDaLista[]; paginacao: Paginacao }>('/facturacao/subscricoes', f),
        fichaDaSubscricao: (id: number) =>
            apiDaPlataforma.ler<{ ficha: FichaDaSubscricao }>(`/facturacao/subscricoes/${id}`),
        activaDe: (empresa: number) =>
            apiDaPlataforma.ler<{ activa: { plano: string | null; termina_em: string | null } | null }>(`/facturacao/empresas/${empresa}/subscricao-activa`),
        resumo: (dados: Record<string, unknown>) =>
            apiDaPlataforma.criar<ResumoDoAcordo>('/facturacao/subscricoes/resumo', dados),
        guardarSubscricao: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado>(`/facturacao/subscricoes/${id}`, dados)
               : apiDaPlataforma.criar<Recado>('/facturacao/subscricoes', dados),
        cancelarSubscricao: (id: number) => apiDaPlataforma.criar<Recado>(`/facturacao/subscricoes/${id}/cancelar`, {}),
        apagarSubscricao: (id: number) => apiDaPlataforma.apagar<Recado>(`/facturacao/subscricoes/${id}`),

        facturas: (f: { procura?: string; estado?: string; pagina?: number }) =>
            apiDaPlataforma.ler<{ facturas: FacturaDaPlataforma[]; paginacao: Paginacao }>('/facturacao/facturas', f),
        novaFactura: () => apiDaPlataforma.ler<{ ficha: FichaDaFactura }>('/facturacao/facturas/nova'),
        fichaDaFactura: (id: number) => apiDaPlataforma.ler<{ ficha: FichaDaFactura }>(`/facturacao/facturas/${id}`),
        guardarFactura: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado>(`/facturacao/facturas/${id}`, dados)
               : apiDaPlataforma.criar<Recado>('/facturacao/facturas', dados),
        pagarFactura: (id: number) => apiDaPlataforma.criar<Recado>(`/facturacao/facturas/${id}/pagar`, {}),
        apagarFactura: (id: number) => apiDaPlataforma.apagar<Recado>(`/facturacao/facturas/${id}`),

        aprovarPedido: (id: number) => apiDaPlataforma.criar<Recado>(`/facturacao/pedidos/${id}/aprovar`, {}),
        recusarPedido: (id: number, motivo: string) =>
            apiDaPlataforma.criar<Recado>(`/facturacao/pedidos/${id}/recusar`, { motivo }),

        guardarSaft: (dados: { certificado: string; produto: string; versao: string }) =>
            apiDaPlataforma.guardar<Recado>('/facturacao/saft', dados),
    },
    empresas: {
        ler: (f: FiltrosDasEmpresas) => apiDaPlataforma.ler<ListaDeEmpresas>('/empresas', f),
        ver: (id: number) => apiDaPlataforma.ler<{ empresa: EmpresaVista }>(`/empresas/${id}`),
        ficha: (id: number) => apiDaPlataforma.ler<{
            ficha: FichaDaEmpresa;
            do_plano: { nome: string | null; max_users: number; max_storage_mb: number };
        }>(`/empresas/${id}/ficha`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado & { id: number }>(`/empresas/${id}`, dados)
               : apiDaPlataforma.criar<Recado & { id: number }>('/empresas', dados),
        desactivar: (id: number, motivo: string) =>
            apiDaPlataforma.criar<Recado>(`/empresas/${id}/desactivar`, { motivo }),
        activar: (id: number) => apiDaPlataforma.criar<Recado>(`/empresas/${id}/activar`, {}),
        suspender: (id: number) => apiDaPlataforma.criar<Recado>(`/empresas/${id}/suspender`, {}),
        perdas: (id: number) => apiDaPlataforma.ler<{
            nome: string; perdas: Array<{ rotulo: string; quantos: number }>; impedido: string | null;
        }>(`/empresas/${id}/apagar`),
        apagar: (id: number, confirmacao: string) =>
            apiDaPlataforma.apagar<Recado>(`/empresas/${id}`, { confirmacao }),

        utilizadores: (id: number) => apiDaPlataforma.ler<UtilizadoresDaEmpresa>(`/empresas/${id}/utilizadores`),
        procurarPessoas: (id: number, q: string) =>
            apiDaPlataforma.ler<{ pessoas: Array<{ id: number; nome: string; email: string }> }>(
                `/empresas/${id}/utilizadores/procurar`, { q }),
        juntar: (id: number, dados: Record<string, unknown>) =>
            apiDaPlataforma.criar<Recado>(`/empresas/${id}/utilizadores`, dados),
        mudarPapel: (id: number, pessoa: number, papel: number) =>
            apiDaPlataforma.guardar<Recado>(`/empresas/${id}/utilizadores/${pessoa}/papel`, { papel }),
        retirar: (id: number, pessoa: number) =>
            apiDaPlataforma.apagar<Recado>(`/empresas/${id}/utilizadores/${pessoa}`),

        plano: (id: number) => apiDaPlataforma.ler<PlanoDaEmpresa>(`/empresas/${id}/plano`),
        resumo: (id: number, dados: Record<string, unknown>) =>
            apiDaPlataforma.criar<ResumoDoAcordo>(`/empresas/${id}/plano/resumo`, dados),
        mudarPlano: (id: number, dados: Record<string, unknown>) =>
            apiDaPlataforma.guardar<Recado>(`/empresas/${id}/plano`, dados),
        medida: (id: number) => apiDaPlataforma.ler<PlanoAMedidaDaEmpresa>(`/empresas/${id}/plano-a-medida`),
        guardarMedida: (id: number, dados: Record<string, unknown>) =>
            apiDaPlataforma.criar<Recado>(`/empresas/${id}/plano-a-medida`, dados),
    },
    painel: {
        ler: () => apiDaPlataforma.ler<PainelDaPlataforma>('/painel'),
        /** Entrar na casa de uma empresa — fica na trilha de auditoria. */
        entrarNaEmpresa: (id: number) =>
            apiDaPlataforma.criar<Recado & { seguir_para: string }>(`/painel/empresas/${id}/entrar`, {}),
    },
    analitica: {
        ler: (filtros: FiltrosDaAnalitica = {}) =>
            apiDaPlataforma.ler<Analitica>('/analitica', filtros),
        /**
         * O percurso de UM visitante tem morada própria: abrir a ficha de
         * alguém não tem de recalcular os vinte agregados do ecrã todo.
         */
        percurso: (visitante: string) =>
            apiDaPlataforma.ler<PercursoDoVisitante>(`/analitica/percurso/${encodeURIComponent(visitante)}`),
    },
    planos: {
        ler: (procura = '') => apiDaPlataforma.ler<ListaDePlanos>('/planos', { procura }),
        ficha: (id: number) => apiDaPlataforma.ler<{ ficha: FichaDoPlano }>(`/planos/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado & { id: number }>(`/planos/${id}`, dados)
               : apiDaPlataforma.criar<Recado & { id: number }>('/planos', dados),
        alternar: (id: number) =>
            apiDaPlataforma.criar<Recado & { aviso?: boolean }>(`/planos/${id}/alternar`, {}),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/planos/${id}`),
    },
    modulos: {
        ler: (procura = '') => apiDaPlataforma.ler<ListaDeModulos>('/modulos', { procura }),
        ficha: (id: number) => apiDaPlataforma.ler<{ ficha: FichaDoModulo }>(`/modulos/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado & { id: number }>(`/modulos/${id}`, dados)
               : apiDaPlataforma.criar<Recado & { id: number }>('/modulos', dados),
        alternar: (id: number) =>
            apiDaPlataforma.criar<Recado & { aviso?: boolean }>(`/modulos/${id}/alternar`, {}),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/modulos/${id}`),
    },
};
