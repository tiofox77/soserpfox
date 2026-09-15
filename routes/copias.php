<?php

use Illuminate\Support\Facades\Route;

/*
 * AS ROTAS DAS CÓPIAS DE SEGURANÇA — as mesmas para a plataforma e para cada
 * empresa. Incluídas duas vezes em routes/web.php, cada vez com o seu
 * controlador em `$c` (a plataforma copia a base inteira; a empresa só os dados
 * dela) e com a guarda do grupo onde são incluídas.
 *
 * Os limites: repor e descarregar levam senha e tudo; um robô com uma sessão
 * roubada não pode tentar senhas nem descarregar a base sem fim.
 */

/** @var string $c */
Route::get('/', [$c, 'index'])->name('index');
Route::put('/agenda', [$c, 'agenda'])->name('agenda');
Route::post('/fazer', [$c, 'fazer'])->middleware('throttle:6,10')->name('fazer');

Route::post('/destinos', [$c, 'guardarDestino'])->name('destinos.criar');
Route::put('/destinos/{id}', [$c, 'guardarDestino'])->whereNumber('id')->name('destinos.guardar');
Route::delete('/destinos/{id}', [$c, 'apagarDestino'])->whereNumber('id')->name('destinos.apagar');
Route::post('/destinos/{id}/testar', [$c, 'testarDestino'])->whereNumber('id')->middleware('throttle:10,1')->name('destinos.testar');
Route::get('/destinos/{id}/ligar', [$c, 'ligarDestino'])->whereNumber('id')->name('destinos.ligar');
Route::get('/destinos/{id}/ficheiros', [$c, 'ficheirosDoDestino'])->whereNumber('id')->middleware('throttle:20,1')->name('destinos.ficheiros');

Route::get('/{id}/descarregar', [$c, 'descarregar'])->whereNumber('id')->middleware('throttle:10,10')->name('descarregar');
Route::post('/{id}/enviar', [$c, 'reenviar'])->whereNumber('id')->middleware('throttle:10,10')->name('enviar');
Route::delete('/{id}', [$c, 'apagarCopia'])->whereNumber('id')->name('apagar');

Route::post('/restaurar', [$c, 'restaurar'])->middleware('throttle:5,10')->name('restaurar');
Route::post('/restaurar/carregar', [$c, 'restaurarCarregado'])->middleware('throttle:5,10')->name('restaurar.carregar');
Route::get('/restauros/{id}', [$c, 'estadoDoRestauro'])->whereNumber('id')->name('restauros.estado');