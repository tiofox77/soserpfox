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

/* ─── As definições ───────────────────────────────────────────────────── */

export type ServidorDeCorreio = {
    id: number; empresa: string | null; empresa_id: number | null; host: string; porta: number;
    utilizador: string; encriptacao: string; remetente: string; nome_do_remetente: string;
    padrao: boolean; activa: boolean; testada_em: string | null; enviados: number; falhados: number;
};

export type FichaDoCorreio = {
    id: number | null; tenant_id: number | null; host: string; port: number; username: string;
    password: string; tem_password?: boolean; encryption: string; from_email: string; from_name: string;
    is_default: boolean; is_active: boolean;
};

export type ConfiguracaoSms = {
    provider: string; api_url: string; sender_id: string; telco_application: string;
    report_url: string; is_active: boolean; token_guardado: boolean; chave_qas_guardada: boolean;
};

export type ModeloSms = {
    id: number; nome: string; slug: string; conteudo: string; descricao: string | null;
    variaveis: Array<{ nome: string; descricao: string | null }>; activo: boolean; caracteres: number;
};

export type RegistoSms = {
    id: number; destino: string; mensagem: string; remetente: string | null; gateway: string;
    tipo: string | null; estado: string; erro: string | null; pedido: string | null;
    quem: string | null; empresa: string | null; enviado_em: string | null; entregue_em: string | null;
};

export type ConfiguracaoWhatsApp = {
    twilio_account_sid: string; twilio_auth_token: string; token_guardado: boolean;
    whatsapp_from_number: string; whatsapp_business_account_id: string;
    is_enabled: boolean; is_sandbox: boolean;
    templates: Array<{ sid: string; name?: string; language?: string }>;
    notification_settings: Record<string, boolean>;
};

export type ChavesDoSaft = {
    publica: { data: string; impressao: string } | null;
    privada: { data: string } | null;
    metadados: { gerada_em: string | null; algoritmo: string; digest: string; conformidade: string };
    copias: string[];
    openssl: boolean;
};

export type DefinicoesDoSistema = {
    valores: Record<string, string | boolean>;
    imagens: Record<string, string | null>;
    sem_efeito: string[];
    auditoria: {
        ficheiros: Record<string, {
            ficheiro: string; url: string; existe: boolean; tamanho: number;
            alterado: string | null; amostra: string | null; enderecos?: number;
        }>;
        verificacoes: Array<{ chave: string; rotulo: string; ok: boolean }>;
        esquemas: string[];
    };
};

export type ProdutorAgt = {
    ambiente: string; username: string; username_herdado: string; credenciais_proprias: boolean;
    tem_credenciais: boolean; certificacao: string; certificacao_herdada: string;
    chave: { bits?: number | null; tipo?: string; impressao?: string; actualizada?: string; propria?: boolean; erro?: string } | null;
};

export type DefinicoesDoSoftware = {
    bloqueios: Array<{ chave: string; rotulo: string; ligado: boolean }>;
    produtor: ProdutorAgt;
    empresas: Array<{
        id: number; nome: string; nif: string | null; ambiente: string;
        submissao_automatica: boolean; series: number; chave_do_contribuinte: boolean;
    }>;
    chaves_saft: boolean;
    certificado_global: boolean;
};

export const definicoes = {
    correio: {
        ler: () => apiDaPlataforma.ler<{
            configuracoes: ServidorDeCorreio[]; empresas: Escolha[];
            plataforma_tem_correio: boolean; o_meu_email: string | null;
        }>('/correio'),
        ficha: (id: number) => apiDaPlataforma.ler<{ ficha: FichaDoCorreio }>(`/correio/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado>(`/correio/${id}`, dados) : apiDaPlataforma.criar<Recado>('/correio', dados),
        alternar: (id: number) => apiDaPlataforma.criar<Recado>(`/correio/${id}/alternar`, {}),
        padrao: (id: number) => apiDaPlataforma.criar<Recado>(`/correio/${id}/padrao`, {}),
        testar: (id: number) => apiDaPlataforma.criar<Recado & { sucesso: boolean }>(`/correio/${id}/testar`, {}),
        enviarTeste: (id: number, email: string) => apiDaPlataforma.criar<Recado>(`/correio/${id}/enviar-teste`, { email }),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/correio/${id}`),
    },
    sms: {
        ler: () => apiDaPlataforma.ler<{
            configuracao: ConfiguracaoSms;
            numeros: { total: number; enviados: number; falhados: number; hoje: number };
            modelos: ModeloSms[]; tipos: string[];
        }>('/sms'),
        guardar: (dados: Record<string, unknown>) => apiDaPlataforma.guardar<Recado>('/sms', dados),
        saldo: (dados: Record<string, unknown>) => apiDaPlataforma.criar<Recado & { aviso?: boolean }>('/sms/saldo', dados),
        testar: (dados: Record<string, unknown>) => apiDaPlataforma.criar<Recado>('/sms/testar', dados),
        previsualizar: (modelo: number) => apiDaPlataforma.ler<{ mensagem: string }>(`/sms/modelos/${modelo}/previsualizar`),
        guardarModelo: (id: number, dados: Record<string, unknown>) => apiDaPlataforma.guardar<Recado>(`/sms/modelos/${id}`, dados),
        historico: (f: Record<string, string | number | undefined>) =>
            apiDaPlataforma.ler<{ registos: RegistoSms[]; paginacao: Paginacao }>('/sms/historico', f),
    },
    whatsapp: {
        ler: () => apiDaPlataforma.ler<{ configuracao: ConfiguracaoWhatsApp; avisos: Escolha[]; activo: boolean }>('/whatsapp'),
        guardar: (dados: Record<string, unknown>) => apiDaPlataforma.guardar<Recado>('/whatsapp', dados),
        testar: () => apiDaPlataforma.criar<Recado & { sucesso: boolean }>('/whatsapp/testar', {}),
        modelos: () => apiDaPlataforma.ler<Recado & { modelos: Array<{ sid: string; name: string; language?: string }> }>('/whatsapp/modelos-da-twilio'),
        enviarTeste: (numero: string, mensagem: string) => apiDaPlataforma.criar<Recado>('/whatsapp/enviar-teste', { numero, mensagem }),
    },
    chavesSaft: {
        ler: () => apiDaPlataforma.ler<ChavesDoSaft>('/chaves-saft'),
        gerar: () => apiDaPlataforma.criar<Recado>('/chaves-saft/gerar', {}),
        regenerar: (confirmacao: string) => apiDaPlataforma.criar<Recado>('/chaves-saft/regenerar', { confirmacao }),
    },
    sistema: {
        ler: () => apiDaPlataforma.ler<DefinicoesDoSistema>('/sistema'),
        guardar: (grupo: string, dados: Record<string, unknown>) => apiDaPlataforma.guardar<Recado>(`/sistema/${grupo}`, dados),
        enviarImagem: (chave: string, ficheiro: File) => {
            const corpo = new FormData();
            corpo.append('ficheiro', ficheiro);

            return apiDaPlataforma.enviar<Recado & { url: string }>(`/sistema/imagens/${chave}`, corpo);
        },
    },
    software: {
        ler: (ambiente: string) => apiDaPlataforma.ler<DefinicoesDoSoftware>('/software', { ambiente }),
        guardarBloqueios: (dados: Record<string, boolean>) => apiDaPlataforma.guardar<Recado>('/software/bloqueios', dados),
        guardarProdutor: (dados: Record<string, unknown>) => apiDaPlataforma.guardar<Recado & { produtor: ProdutorAgt }>('/software/produtor', dados),
        limparProdutor: (ambiente: string) => apiDaPlataforma.apagar<Recado & { produtor: ProdutorAgt }>('/software/produtor', { ambiente }),
        prontidao: (empresa: number) => apiDaPlataforma.ler<{
            ambiente: string; itens: Array<{ chave: string; rotulo: string; ok: boolean }>;
        }>(`/software/empresas/${empresa}/prontidao`),
        aplicarAmbiente: (empresa: number, ambiente: string) => apiDaPlataforma.guardar<Recado>('/software/ambiente', { empresa, ambiente }),
        testarAgt: (empresa: number, ambiente: string) =>
            apiDaPlataforma.criar<Record<string, unknown>>('/software/agt/testar', { empresa, ambiente }),
        operacaoAgt: (dados: Record<string, unknown>) =>
            apiDaPlataforma.criar<Record<string, unknown>>('/software/agt/operacao', dados),
    },
};

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

// ---- as ferramentas e os registos da plataforma ----------------------------

type Filtros = Record<string, string | number | undefined>;

export type MensagemDeContacto = {
    id: number; nome: string; email: string; telefone: string | null; empresa: string | null;
    mensagem: string; estado: 'new' | 'read' | 'replied'; ip: string | null; recebida_em: string | null;
};

export type PedidoDeEstabelecimentos = {
    id: number; empresa: string | null; quota_actual_da_empresa: number | null;
    pedido_por: string | null; pedido_em: string | null; quota: number; pedido: number;
    motivo: string | null; estado: 'pending' | 'approved' | 'rejected';
    nota: string | null; analisado_por: string | null; analisado_em: string | null;
};

export type RegistoDeEmail = {
    id: number; para: string; nome: string | null; assunto: string; modelo: string | null;
    estado: 'sent' | 'failed' | 'pending'; criado_em: string | null;
};

export type DetalheDoEmail = {
    id: number; estado: string; enviado_em: string | null; falhou_em: string | null; erro: string | null;
    para: string; para_nome: string | null; de: string | null; de_nome: string | null;
    modelo: string | null; modelo_nome: string | null; criado_em: string | null;
    assunto: string; previa: string | null; dados: Record<string, unknown> | null;
    empresa: string | null; utilizador: string | null; servidor: string | null; identificador: string | null;
};

export type AparelhoPwa = {
    id: number; empresa: string | null; aparelho: string; versao: string | null; atrasado: boolean;
    instalado: boolean; plataforma: string | null; utilizador: string | null; email: string | null;
    visto_em: string | null; adormecido: boolean; sincronizacoes: number;
};

export type ModeloDeEmail = {
    id: number; slug: string; nome: string; assunto: string; descricao: string | null; activo: boolean;
    actualizado_em: string | null; envios: number; ultimo_envio: string | null;
};

export type FichaDoModeloDeEmail = {
    id?: number; slug: string; name: string; subject: string; body_html: string;
    body_text: string; description: string; is_active: boolean;
};

export type AvisoDaPlataforma = {
    id: number; titulo: string; corpo: string; nivel: string; forma: string; publico: string;
    alcance: number; vistas: number; dispensadas: number; dispensavel: boolean; activa: boolean;
    situacao: 'retirada' | 'terminada' | 'agendada' | 'no_ar';
    comeca: string | null; termina: string | null; ligacao: string | null; texto_da_ligacao: string | null;
    autor: string | null;
};

export type FichaDoAviso = {
    id?: number; title: string; body: string; level: string; display: string; audience: string;
    tenant_ids: number[]; plan_ids: number[]; starts_at: string; ends_at: string;
    dismissible: boolean; link_url: string; link_label: string; is_active: boolean;
};

export type Nomeado = { id: number; nome: string };

export const ferramentas = {
    contactos: {
        ler: (f: Filtros) => apiDaPlataforma.ler<{
            mensagens: MensagemDeContacto[]; paginacao: Paginacao;
            numeros: { total: number; novas: number; lidas: number; respondidas: number };
        }>('/contactos', f),
        marcar: (id: number, estado: 'read' | 'replied') => apiDaPlataforma.criar<Recado>(`/contactos/${id}/marcar`, { estado }),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/contactos/${id}`),
    },
    estabelecimentos: {
        ler: (f: Filtros) => apiDaPlataforma.ler<{
            pedidos: PedidoDeEstabelecimentos[]; paginacao: Paginacao;
            contagens: Record<'pending' | 'approved' | 'rejected', number>;
        }>('/pedidos-de-estabelecimentos', f),
        aprovar: (id: number, limite: number, nota: string) =>
            apiDaPlataforma.criar<Recado>(`/pedidos-de-estabelecimentos/${id}/aprovar`, { limite, nota }),
        recusar: (id: number, nota: string) =>
            apiDaPlataforma.criar<Recado>(`/pedidos-de-estabelecimentos/${id}/recusar`, { nota }),
    },
    emails: {
        ler: (f: Filtros) => apiDaPlataforma.ler<{
            registos: RegistoDeEmail[]; paginacao: Paginacao; modelos: string[];
            numeros: { total: number; enviados: number; falhados: number; pendentes: number };
        }>('/registo-de-emails', f),
        ver: (id: number) => apiDaPlataforma.ler<{ registo: DetalheDoEmail }>(`/registo-de-emails/${id}`),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/registo-de-emails/${id}`),
        limparAntigos: () => apiDaPlataforma.criar<Recado & { apagados: number }>('/registo-de-emails/limpar-antigos', {}),
    },
    aparelhos: {
        ler: (f: Filtros) => apiDaPlataforma.ler<{
            versao_actual: string; dias_ate_adormecer: number;
            resumo: { aparelhos: number; empresas: number; instalados: number; atrasados: number; adormecidos: number };
            aparelhos: AparelhoPwa[]; com_modulo_sem_aparelho: Nomeado[]; paginacao: Paginacao;
        }>('/aparelhos-pwa', f),
    },
    modelosDeEmail: {
        ler: (f: Filtros) => apiDaPlataforma.ler<{
            modelos: ModeloDeEmail[]; variaveis: string[]; o_meu_email: string | null; paginacao: Paginacao;
        }>('/modelos-de-email', f),
        ficha: (id: number) => apiDaPlataforma.ler<{ ficha: FichaDoModeloDeEmail }>(`/modelos-de-email/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado>(`/modelos-de-email/${id}`, dados) : apiDaPlataforma.criar<Recado>('/modelos-de-email', dados),
        alternar: (id: number) => apiDaPlataforma.criar<Recado>(`/modelos-de-email/${id}/alternar`, {}),
        previsualizar: (id: number) =>
            apiDaPlataforma.ler<{ assunto: string; html: string; texto: string | null }>(`/modelos-de-email/${id}/previsualizar`),
        enviarTeste: (id: number, email: string) => apiDaPlataforma.criar<Recado>(`/modelos-de-email/${id}/enviar-teste`, { email }),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/modelos-de-email/${id}`),
    },
    avisos: {
        ler: (pagina: number) => apiDaPlataforma.ler<{
            mensagens: AvisoDaPlataforma[]; paginacao: Paginacao;
            opcoes: { niveis: Escolha[]; formas: Escolha[]; publicos: Escolha[]; empresas: Nomeado[]; planos: Nomeado[] };
        }>('/avisos', { pagina }),
        ficha: (id: number) => apiDaPlataforma.ler<{ ficha: FichaDoAviso }>(`/avisos/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? apiDaPlataforma.guardar<Recado>(`/avisos/${id}`, dados) : apiDaPlataforma.criar<Recado>('/avisos', dados),
        alcance: (dados: { audience: string; tenant_ids: number[]; plan_ids: number[] }) =>
            apiDaPlataforma.criar<{ empresas: number }>('/avisos/alcance', dados),
        alternar: (id: number) => apiDaPlataforma.criar<Recado>(`/avisos/${id}/alternar`, {}),
        leituras: (id: number) => apiDaPlataforma.ler<{
            leituras: Array<{ id: number; nome: string | null; email: string | null; vista_em: string | null; dispensada_em: string | null }>;
        }>(`/avisos/${id}/leituras`),
        apagar: (id: number) => apiDaPlataforma.apagar<Recado>(`/avisos/${id}`),
    },
    smsEmpresas: {
        ler: () => apiDaPlataforma.ler<{
            configurado: boolean; gateway: string | null;
            empresas: Array<Nomeado & { telefone: string | null }>; planos: Nomeado[];
            modelos: Array<Nomeado & { conteudo: string }>; variaveis: string[];
        }>('/sms-empresas'),
        rever: (dados: Record<string, unknown>) => apiDaPlataforma.criar<{
            alvo: number; com_telefone: number; sem_telefone: string[]; partes: number; total_de_partes: number; assinatura: string;
        }>('/sms-empresas/rever', dados),
        enviar: (dados: Record<string, unknown>) => apiDaPlataforma.criar<Recado & {
            resultado: { enviados: number; falhados: string[]; partes: number };
        }>('/sms-empresas/enviar', dados),
    },
};

// ---- o sistema: optimização, comandos, scripts, actualizações e licenças ----

export type LinhaDaConsola = { tipo: 'info' | 'saida' | 'sucesso' | 'erro' | 'aviso' | 'separador'; texto: string };

export type ValoresDoIni = {
    environment: 'production' | 'development';
    validate_timestamps: number;
    revalidate_freq: number;
    max_input_vars: number;
    memory_limit: string;
    max_execution_time: number;
};

export type EstadoDoOpcache = {
    available: boolean;
    enabled: boolean;
    cache_full?: boolean;
    restart_pending?: boolean;
    stats?: { hit_rate: number; hits: string; misses: string; cached_scripts: string; max_scripts: string };
    memory?: { total_mb: number; used_mb: number; free_mb: number; wasted_mb: number; usage_percentage: number; wasted_percentage: number };
    config?: { memory_consumption: string; max_files: string; validate_timestamps: string; revalidate_freq: string; cli_enabled: string };
};

export type ComandoDoSistema = {
    chave: string; nome: string; descricao: string; comando: string; icone: string; cor: string; grupo: string;
    parametros: Array<{ nome: string; rotulo: string; tipo: 'checkbox' | 'select'; opcoes: 'plans' | 'modules' | 'tenants' | null; obrigatorio: boolean }>;
};

export type SeederDoSistema = {
    classe: string; namespace: string; nome: string; categoria: string; executado: boolean; executado_em: string | null;
};

export type ExecucaoDoHistorico = {
    command_key: string; command_name: string; success: boolean; output: string; executed_by: string; executed_at: string;
};

export type ReleaseDoGithub = {
    tag_name: string; name: string; body: string; published_at: string | null; prerelease: boolean; is_newer: boolean; is_current: boolean;
};

export type LinhaDoRegisto = { hora: string; mensagem: string; tipo: 'info' | 'success' | 'warning' | 'error' };

export type InstalacaoOffline = {
    id: number; empresa: string; maquina: string | null; plano: string | null; todos_os_modulos: boolean; modulos: number;
    max_utilizadores: number | null; versao: string | null; ultimo_contacto: string | null;
    situacao: 'activa' | 'silenciosa' | 'expirada' | 'nunca_ligou'; expira_em: string | null;
};

export type FichaDaInstalacao = {
    id: number;
    empresa: { nome: string; nif: string | null; email: string | null; telefone: string | null; activa: boolean } | null;
    plano: string | null; modulos: string[]; max_utilizadores: number | null;
    emitida_em: string | null; expira_em: string | null; dias_sugeridos: number;
    maquina: string | null; versao: string | null; ultimo_ip: string | null; ultimo_contacto: string | null;
    situacao: InstalacaoOffline['situacao'];
    pedido: { codigo: string; responsavel: string | null } | null;
    token: string | null;
};

export type PedidoDeLicenca = {
    id: number; codigo: string; empresa: string; nif: string | null; email: string | null; telefone: string | null;
    responsavel: string | null; utilizadores: number | null; maquina: string | null; observacoes: string | null;
    estado: 'pendente' | 'aprovado' | 'recusado'; motivo_recusa: string | null; entregue: boolean; pedido_em: string | null;
};

export type VersaoPublicada = {
    id: number; versao: string; min_versao: string | null; rollout: 'none' | 'all'; obrigatorio: boolean;
    alvos: Array<{ id: number; empresa: string }>;
};

export const sistema = {
    otimizacao: {
        ler: () => apiDaPlataforma.ler<{
            opcache: EstadoDoOpcache;
            saude: { status: 'success' | 'info' | 'warning' | 'error'; message: string; issues?: string[]; warnings?: string[] };
            php: Record<'version' | 'memory_limit' | 'max_execution_time' | 'upload_max_filesize' | 'post_max_size' | 'max_input_vars', string>;
            actuais: ValoresDoIni;
            perfis: Record<'production' | 'development', Omit<ValoresDoIni, 'environment'>>;
        }>('/otimizacao'),
        limparOpcache: () => apiDaPlataforma.criar<Recado>('/otimizacao/limpar-opcache', {}),
        limparCaches: () => apiDaPlataforma.criar<Recado>('/otimizacao/limpar-caches', {}),
        otimizar: () => apiDaPlataforma.criar<Recado>('/otimizacao/otimizar', {}),
        gerarIni: (valores: ValoresDoIni) => apiDaPlataforma.criar<Recado>('/otimizacao/user-ini', valores),
    },
    comandos: {
        ler: () => apiDaPlataforma.ler<{
            comandos: ComandoDoSistema[];
            opcoes: Record<'plans' | 'modules' | 'tenants', Escolha[]>;
            seeders: SeederDoSistema[];
            numeros_dos_seeders: { total: number; executados: number; pendentes: number };
            historico: ExecucaoDoHistorico[];
        }>('/comandos'),
        correr: (chave: string, parametros: Record<string, unknown>) =>
            apiDaPlataforma.criar<{ ok: boolean; linhas: LinhaDaConsola[]; historico: ExecucaoDoHistorico[] }>(`/comandos/${chave}`, { parametros }),
        semear: (seeder: string) =>
            apiDaPlataforma.criar<{ ok: boolean; linhas: LinhaDaConsola[]; historico: ExecucaoDoHistorico[] }>('/comandos/seeder', { seeder }),
        limparHistorico: () => apiDaPlataforma.apagar<Recado>('/comandos/historico'),
    },
    scripts: {
        ler: () => apiDaPlataforma.ler<{
            scripts: Array<{ nome: string; tamanho: number; modificado_em: string; descricao: string | null }>;
            log: string[];
        }>('/scripts'),
        log: () => apiDaPlataforma.ler<{ log: string[] }>('/scripts/log'),
        correr: (script: string) => apiDaPlataforma.criar<Recado & { ok: boolean; saida: string; log: string[] }>('/scripts/correr', { script }),
        limparLog: () => apiDaPlataforma.apagar<Recado>('/scripts/log'),
    },
    actualizacoes: {
        ler: () => apiDaPlataforma.ler<{ versao_actual: string; repositorio: string }>('/actualizacoes'),
        releases: () => apiDaPlataforma.ler<Recado & { releases: ReleaseDoGithub[] }>('/actualizacoes/releases'),
        instalar: (versao: string) => apiDaPlataforma.criar<Recado & { ok: boolean; registo: LinhaDoRegisto[]; versao_actual?: string }>('/actualizacoes/instalar', { versao }),
    },
    licenciamento: {
        ler: () => apiDaPlataforma.ler<{
            estado: { cripto: boolean; chave_das_licencas: boolean; chave_das_versoes: boolean; problema_da_chave: string | null };
            resumo: { total: number; activas: number; silenciosas: number; expiradas: number; por_ligar: number };
            instalacoes: InstalacaoOffline[];
            pedidos: PedidoDeLicenca[];
            versoes: VersaoPublicada[];
            opcoes: { empresas: Nomeado[]; planos: Nomeado[]; modulos: Array<{ slug: string; nome: string }> };
        }>('/licenciamento'),
        instalacao: (id: number) => apiDaPlataforma.ler<{ instalacao: FichaDaInstalacao }>(`/licenciamento/instalacoes/${id}`),
        emitir: (dados: Record<string, unknown>) => apiDaPlataforma.criar<Recado & { token: string }>('/licenciamento/licencas', dados),
        guardarEmpresa: (id: number, dados: Record<string, unknown>) => apiDaPlataforma.guardar<Recado>(`/licenciamento/instalacoes/${id}/empresa`, dados),
        alternarSuspensao: (id: number) => apiDaPlataforma.criar<Recado & { activa: boolean }>(`/licenciamento/instalacoes/${id}/suspensao`, {}),
        avisar: (id: number, mensagem: string) => apiDaPlataforma.criar<Recado>(`/licenciamento/instalacoes/${id}/aviso`, { mensagem }),
        renovar: (id: number, dias: number) => apiDaPlataforma.criar<Recado & { token: string }>(`/licenciamento/instalacoes/${id}/renovar`, { dias }),
        aprovarPedido: (id: number, dados: Record<string, unknown>) => apiDaPlataforma.criar<Recado>(`/licenciamento/pedidos/${id}/aprovar`, dados),
        recusarPedido: (id: number, motivo: string) => apiDaPlataforma.criar<Recado>(`/licenciamento/pedidos/${id}/recusar`, { motivo }),
        publicarVersao: (dados: Record<string, unknown>) => apiDaPlataforma.criar<Recado>('/licenciamento/versoes', dados),
        definirRollout: (id: number, rollout: 'none' | 'all') => apiDaPlataforma.guardar<Recado>(`/licenciamento/versoes/${id}/rollout`, { rollout }),
        adicionarAlvo: (tenant_id: number, versao: string) => apiDaPlataforma.criar<Recado>('/licenciamento/alvos', { tenant_id, versao }),
        removerAlvo: (id: number) => apiDaPlataforma.apagar<Recado>(`/licenciamento/alvos/${id}`),
    },
};
