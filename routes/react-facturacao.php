<?php

use Illuminate\Support\Facades\Route;
use App\Services\Invoicing\TiposDeDocumento;

/*
|--------------------------------------------------------------------------
| Os ecrãs da facturação em React — as moradas de ensaio
|--------------------------------------------------------------------------
|
| Enquanto a migração durar, cada ecrã tem DUAS moradas: a de sempre, com o
| Livewire, e uma `/novo-ecra` com a versão nova. Compara-se lado a lado e a
| antiga nunca deixa de estar lá — é isso que permite voltar atrás sem
| publicar nada.
|
| Este ficheiro é incluído de dentro do grupo `invoicing` do `web.php`, que já
| trata da autenticação, do tenant e do módulo. A PERMISSÃO É A MESMA DO ECRÃ
| DE SEMPRE, em cada linha: um ecrã novo não é uma porta nova.
|
| As cinco listas que partilham forma (proformas, orçamentos, compras,
| recibos) saem todas do mesmo componente; o que muda vem do
| `TiposDeDocumento` e viaja nas props.
*/

$emReact = function (string $ecra, string $titulo, array $props = []) {
    return fn () => view('react.ecra', [
        'ecra' => $ecra,
        'titulo' => $titulo,
        'subtitulo' => __('Ecrã novo, em ensaio'),
        'props' => $props,
    ]);
};

foreach (TiposDeDocumento::todos() as $slug => $def) {
    // A morada de ensaio nasce da morada de sempre: /proformas → /proformas/novo-ecra
    $caminho = ltrim(str_replace('/invoicing', '', $def['rota']), '/') . '/novo-ecra';

    Route::middleware('permission:' . $def['permissao'])
        ->get($caminho, $emReact('facturacao/documentos', $def['titulo'], ['tipo' => $slug]))
        ->name('react.' . $slug);
}

/*
 * OS EMISSORES DE PROPOSTAS.
 *
 * Só os três que não têm número fiscal, assinatura nem stock. A morada é
 * `/create/novo-ecra`, ao lado do `/create` de sempre.
 */
foreach (TiposDeDocumento::editaveis() as $slug => $editor) {
    $def = TiposDeDocumento::um($slug);
    $caminho = ltrim(str_replace('/invoicing', '', $def['rota']), '/') . '/create/novo-ecra';

    Route::middleware('permission:' . str_replace('.view', '.create', $def['permissao']))
        ->get($caminho, $emReact('facturacao/emitir-proposta', __('Emitir') . ' · ' . $def['titulo'], ['tipo' => $slug]))
        ->name('react.emitir.' . $slug);
}

/*
 * REGISTAR UM RECIBO.
 *
 * Fica de fora do laço acima porque não é uma proposta: é documento fiscal
 * (RC/RG), vai à AGT, e o pagamento lança-se nos ganchos do modelo.
 */
Route::middleware('permission:invoicing.receipts.create')
    ->get('receipts/create/novo-ecra', $emReact('facturacao/registar-recibo', __('Registar Recibo')))
    ->name('react.emitir.recibo');

/*
 * NOTAS DE CRÉDITO E DE DÉBITO.
 *
 * A lógica fiscal vive no EmissorDeNotas; o ecrã só acerta quantidades.
 */
Route::middleware('permission:invoicing.credit-notes.create')
    ->get('credit-notes/create/novo-ecra', $emReact('facturacao/emitir-nota', __('Nota de Crédito'), ['tipo' => 'credito']))
    ->name('react.emitir.credito');

Route::middleware('permission:invoicing.debit-notes.create')
    ->get('debit-notes/create/novo-ecra', $emReact('facturacao/emitir-nota', __('Nota de Débito'), ['tipo' => 'debito']))
    ->name('react.emitir.debito');

/*
 * A FACTURA DE VENDA (FT/FR). A lógica fiscal vive no EmissorDeFacturas.
 */
Route::middleware('permission:invoicing.sales.invoices.create')
    ->get('sales/invoices/create/novo-ecra', $emReact('facturacao/emitir-factura', __('Emitir Factura')))
    ->name('react.emitir.factura');

/*
 * A FACTURA DE COMPRA. Dá entrada de stock e de lotes; a lógica vive no
 * EmissorDeCompras.
 */
Route::middleware('permission:invoicing.purchases.invoices.create')
    ->get('purchases/invoices/create/novo-ecra', $emReact('facturacao/emitir-factura-de-compra', __('Registar Factura de Compra')))
    ->name('react.emitir.compra');

/*
 * OS ADIANTAMENTOS: registar um novo, ou editar um que ainda não foi usado.
 */
Route::middleware('permission:invoicing.advances.create')
    ->get('advances/create/novo-ecra', $emReact('facturacao/emitir-adiantamento', __('Novo Adiantamento')))
    ->name('react.emitir.adiantamento');

Route::middleware('permission:invoicing.advances.edit')
    ->get('advances/{id}/edit/novo-ecra', fn (int $id) => view('react.ecra', [
        'ecra' => 'facturacao/emitir-adiantamento',
        'titulo' => __('Editar Adiantamento'),
        'subtitulo' => __('Ecrã novo, em ensaio'),
        'props' => ['id' => $id],
    ]))
    ->whereNumber('id')
    ->name('react.editar.adiantamento');

/*
 * AS GUIAS DE TRANSPORTE. A permissão é a do menu: quem vê as guias ou as
 * notas de débito — o ecrã de sempre não tem guarda própria.
 */
Route::middleware('permission:invoicing.transport-guides.view|invoicing.debit-notes.view')
    ->get('transport-guides/novo-ecra', $emReact('facturacao/guias-de-transporte', __('Guias de Transporte')))
    ->name('react.guias');

/*
 * AS IMPORTAÇÕES.
 */
Route::middleware('permission:invoicing.imports.view')
    ->get('imports/novo-ecra', $emReact('facturacao/importacoes', __('Importações')))
    ->name('react.importacoes');

/*
 * OS CATÁLOGOS. Seis ecrãs Livewire com a mesma forma saem todos do mesmo
 * Catalogo.tsx; o que muda vem do `Catalogos` e viaja no `tipo`.
 */
foreach (\App\Services\Invoicing\Catalogos::todos() as $slug => $def) {
    $caminho = ltrim(str_replace('/invoicing', '', $def['rota']), '/') . '/novo-ecra';

    Route::middleware('permission:' . $def['permissoes']['ver'])
        ->get($caminho, $emReact('facturacao/catalogo', __($def['titulo']), ['tipo' => $slug]))
        ->name('react.catalogo.' . $slug);
}

/*
 * AS DEFINIÇÕES DA FACTURAÇÃO. Ver é uma permissão, editar é outra — a
 * segunda é exigida pela API em cada escrita.
 */
Route::middleware('permission:invoicing.settings.view')
    ->get('settings/novo-ecra', $emReact('facturacao/definicoes', __('Configurações de Faturação')))
    ->name('react.definicoes');
