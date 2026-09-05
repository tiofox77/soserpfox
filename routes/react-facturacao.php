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
