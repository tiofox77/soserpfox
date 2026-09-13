<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\RestaurantSettings;
use App\Services\Restaurant\CartaPublica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A CARTA PÚBLICA — a página que o QR da mesa abre, e a porta dos pedidos.
 *
 * Fora de qualquer `auth`: quem abre isto é um cliente sentado à mesa. Quem
 * manda é o SLUG, e uma carta desligada não existe (404 e não 403: a diferença
 * contava a estranhos que aquele restaurante é cliente do sistema).
 */
class CartaPublicaController extends Controller
{
    private function definicoes(string $slug): RestaurantSettings
    {
        $d = RestaurantSettings::porSlugPublico($slug);
        abort_unless($d, 404);

        return $d;
    }

    public function pagina(string $slug, ?string $mesa = null)
    {
        $d = $this->definicoes($slug);
        $carta = new CartaPublica($d);
        $dados = $carta->paraAPagina($mesa);

        return view('react.publico', [
            'ecra' => 'restaurant/carta-online',
            'props' => ['slug' => $slug] + $dados,
            'titulo' => $d->menu_title ?: __('Menu'),
            'descricao' => $d->menu_description ?: __('Menu digital'),
            'imagem' => $dados['casa']['capa'] ?: $dados['casa']['logo'],
        ]);
    }

    public function pedido(Request $request, string $slug): JsonResponse
    {
        $dados = $request->validate([
            'mesa' => 'nullable|string|max:50',
            'escolhas' => 'required|array|min:1|max:100',
            'escolhas.*.id' => 'required|integer',
            'escolhas.*.quantidade' => 'required|integer|min:1|max:99',
            'nome' => 'nullable|string|max:120',
            'telefone' => 'nullable|string|max:40',
            'observacoes' => 'nullable|string|max:500',
        ], [
            'escolhas.required' => __('Escolha alguma coisa primeiro.'),
            'escolhas.min' => __('Escolha alguma coisa primeiro.'),
        ]);

        $escolhas = [];
        foreach ($dados['escolhas'] as $e) {
            $escolhas[(int) $e['id']] = ($escolhas[(int) $e['id']] ?? 0) + (int) $e['quantidade'];
        }

        (new CartaPublica($this->definicoes($slug)))->enviarPedido(
            $dados['mesa'] ?? null, $escolhas, $dados['nome'] ?? null, $dados['telefone'] ?? null, $dados['observacoes'] ?? null,
        );

        return response()->json(['message' => __('Pedido enviado')], 201);
    }
}
