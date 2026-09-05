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
};
