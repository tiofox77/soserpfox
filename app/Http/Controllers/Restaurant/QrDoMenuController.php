<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\RestaurantSettings;
use App\Services\Restaurant\QrDoMenu;
use Illuminate\Http\Request;

/**
 * Os QR da carta, prontos a imprimir e a colar nas mesas.
 *
 * Uma FOLHA e não um ecrã: isto existe para sair na impressora, ser cortado e
 * colado. Por isso a página é feita para papel — cartões com o tamanho certo,
 * quebras de página onde devem estar, e sem nada que só faça sentido num
 * browser.
 *
 * As imagens vão embutidas em `data:` e não como ficheiros: uma folha de vinte
 * mesas com vinte pedidos ao servidor imprime metade das vezes, porque o
 * diálogo de impressão abre antes de as imagens chegarem — e sai papel com
 * quadrados vazios.
 */
class QrDoMenuController extends Controller
{
    /** A folha com um cartão por mesa, mais um cartão geral. */
    public function folha(Request $pedido, QrDoMenu $qr)
    {
        $definicoes = RestaurantSettings::forTenant(activeTenantId());

        // Sem endereço não há QR nenhum para gerar: um código que aponta para
        // /menu/ vazio dá 404 a quem o apontar, impresso e colado numa mesa.
        if (blank($definicoes->menu_slug)) {
            return redirect()
                ->route('restaurant.settings')
                ->with('error', __('Dê um endereço à carta antes de gerar os QR.'));
        }

        $mesas = DiningTable::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->with('area.venue')
            ->orderBy('code')
            ->get();

        $cartoes = [];

        // O cartão GERAL: a carta sem mesa. Vale para o balcão, para a montra
        // e para a porta — sítios onde não há mesa nenhuma a identificar.
        $cartoes[] = [
            'titulo'   => $definicoes->menu_title ?: __('A nossa carta'),
            'subtitulo' => __('Aponte a câmara para ver a carta'),
            'url'      => $definicoes->urlDoMenu(),
            'imagem'   => $qr->dataUri($definicoes->urlDoMenu(), 600),
        ];

        foreach ($mesas as $mesa) {
            $url = $definicoes->urlDaMesa($mesa->code);

            $cartoes[] = [
                'titulo'    => $mesa->name ?: $mesa->code,
                'subtitulo' => trim(($mesa->area?->venue?->name ?? '') . ' · ' . ($mesa->area?->name ?? ''), ' ·')
                    ?: __('Aponte a câmara para pedir'),
                'url'       => $url,
                'imagem'    => $qr->dataUri($url, 600),
            ];
        }

        return view('restaurant.qr-do-menu', [
            'definicoes' => $definicoes,
            'cartoes'    => $cartoes,
        ]);
    }

    /** Um QR sozinho, em PNG, para quem o quer usar noutro sítio. */
    public function imagem(Request $pedido, QrDoMenu $qr, ?string $mesa = null)
    {
        $definicoes = RestaurantSettings::forTenant(activeTenantId());

        abort_if(blank($definicoes->menu_slug), 404, __('A carta ainda não tem endereço.'));

        // A mesa tem de ser DESTA casa. Sem isto, escrever o código de uma
        // mesa qualquer gerava um QR que apontava para o menu desta empresa
        // com o código de outra — um QR que nunca ia reconhecer a mesa.
        if ($mesa !== null) {
            abort_unless(
                DiningTable::where('tenant_id', activeTenantId())->where('code', $mesa)->exists(),
                404,
                __('Mesa não encontrada.')
            );
        }

        $url = $mesa ? $definicoes->urlDaMesa($mesa) : $definicoes->urlDoMenu();
        $nome = 'qr-menu-' . ($mesa ? \Illuminate\Support\Str::slug($mesa) : 'geral') . '.png';

        return response($qr->png($url, 1200))
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="' . $nome . '"');
    }
}
