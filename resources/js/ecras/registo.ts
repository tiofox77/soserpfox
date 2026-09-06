/**
 * OS ECRÃS QUE O REACT SABE MONTAR.
 *
 * O nome à esquerda é o que vai no `data-ecra` do Blade. O `import()` é
 * preguiçoso de propósito: cada ecrã é um pedaço à parte, e quem abre a lista
 * de facturas não descarrega o ecrã dos produtos.
 *
 * Um nome que não esteja aqui não monta nada e diz-se na consola — em vez de
 * a página ficar com um buraco branco sem explicação.
 */

import type { ComponentType } from 'react';

/*
 * As props vêm do `data-props` do Blade e são diferentes por ecrã, por isso
 * o registo aceita qualquer forma. A verificação a sério é dentro do ecrã, que
 * declara o que espera receber.
 */
type Ecra = () => Promise<{ default: ComponentType<any> }>;

export const ecras: Record<string, Ecra> = {
    'facturacao/lista-de-facturas': () =>
        import('./facturacao/vendas/ListaDeFacturas'),

    'facturacao/clientes': () =>
        import('./facturacao/Clientes'),

    'facturacao/produtos': () =>
        import('./facturacao/Produtos'),

    'facturacao/painel': () =>
        import('./facturacao/Painel'),

    // Serve CINCO documentos: o `tipo` vem nas props, do Blade.
    'facturacao/documentos': () =>
        import('./facturacao/ListaDeDocumentos'),

    // E TRÊS propostas: proforma de venda, orçamento, proforma de compra.
    'facturacao/emitir-proposta': () =>
        import('./facturacao/EmitirProposta'),

    'facturacao/registar-recibo': () =>
        import('./facturacao/RegistarRecibo'),

    // Notas de crédito e de débito: o `tipo` vem nas props.
    'facturacao/emitir-nota': () =>
        import('./facturacao/EmitirNota'),

    // A factura de venda, FT ou FR — o ecrã mais delicado da casa.
    'facturacao/emitir-factura': () =>
        import('./facturacao/EmitirFactura'),

    // A factura de compra: a que dá entrada de stock e de lotes.
    'facturacao/emitir-factura-de-compra': () =>
        import('./facturacao/EmitirFacturaDeCompra'),

    // As definições da facturação e as séries de numeração.
    'facturacao/definicoes': () =>
        import('./facturacao/Definicoes'),

    // A casca: a barra lateral com o menu, o suporte e o utilizador.
    'casca': () =>
        import('./casca/Casca'),

    // SEIS catálogos (fornecedores, categorias, marcas, armazéns, condições
    // de pagamento, impostos): o `tipo` vem nas props, o esquema do servidor.
    'facturacao/catalogo': () =>
        import('./facturacao/Catalogo'),

    // Registar (ou editar, com `id` nas props) um adiantamento.
    'facturacao/emitir-adiantamento': () =>
        import('./facturacao/EmitirAdiantamento'),

    // As guias de transporte e de remessa: lista e registo.
    'facturacao/guias-de-transporte': () =>
        import('./facturacao/GuiasDeTransporte'),

    // As importações: o processo, do pedido ao armazém.
    'facturacao/importacoes': () =>
        import('./facturacao/Importacoes'),

    // O stock por armazém: ajustar, transferir, movimentação em lote.
    'facturacao/stock': () =>
        import('./facturacao/Stock'),

    // As quebras: registo e relatório no mesmo sítio.
    'facturacao/quebras': () =>
        import('./facturacao/Quebras'),

    // Os lotes e as validades.
    'facturacao/lotes': () =>
        import('./facturacao/Lotes'),

    // Transferências entre armazéns e ajustes em lote; e entre empresas.
    'facturacao/transferencias-entre-armazens': () =>
        import('./facturacao/TransferenciasEntreArmazens'),
    'facturacao/transferencias-entre-empresas': () =>
        import('./facturacao/TransferenciasEntreEmpresas'),
};
