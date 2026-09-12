<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Restaurant\MenuDestaque;
use App\Models\Restaurant\RestaurantSettings;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A APARÊNCIA DA CARTA — o que a faz parecer a casa.
 *
 * Está separada das definições gerais de propósito: escolher a fotografia da
 * capa não é a mesma decisão que escolher o armazém por omissão, e o ecrã das
 * definições já estava a ficar uma gaveta de tudo.
 *
 * A PRÉ-VISUALIZAÇÃO É A CARTA VERDADEIRA num iframe, não uma imitação: uma
 * imitação diverge do original ao primeiro retoque, e o dono acaba a decidir
 * por uma coisa que não é a que o cliente vai ver. Por isso o endereço só vem
 * quando a carta está publicada — um iframe para uma página desligada mostrava
 * a recusa e parecia avaria.
 */
class AparenciaApiController extends Controller
{
    use ManagesFileUploads;

    public const TEMAS = ['claro' => 'Claro', 'escuro' => 'Escuro'];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.view');

        $tenantId = activeTenantId();
        $d = RestaurantSettings::forTenant($tenantId);

        return response()->json([
            'aparencia' => [
                'menu_title' => (string) ($d->menu_title ?? ''),
                'menu_description' => (string) ($d->menu_description ?? ''),
                'menu_primary_color' => (string) ($d->menu_primary_color ?: '#ea580c'),
                'menu_accent_color' => (string) ($d->menu_accent_color ?: '#0f766e'),
                'menu_theme' => (string) ($d->menu_theme ?: 'claro'),
                'menu_destaques_titulo' => (string) ($d->menu_destaques_titulo ?? ''),
                'menu_show_prices' => (bool) ($d->menu_show_prices ?? true),
            ],
            'capa' => $d->menu_cover ? Storage::url($d->menu_cover) : null,
            'logo' => $d->menu_logo ? Storage::url($d->menu_logo) : null,
            'temas' => collect(self::TEMAS)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'url_da_carta' => ($d->online_menu_enabled && $d->menu_slug) ? url('/menu/'.$d->menu_slug) : null,
            'carta_publicada' => (bool) $d->online_menu_enabled,
            'destaques' => $this->destaques($tenantId),
            'maximo_de_destaques' => MenuDestaque::MAXIMO,
            'permissoes' => ['pode_editar' => (bool) $request->user()?->can('restaurant.settings.edit')],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function destaques(int $tenantId): array
    {
        return MenuDestaque::where('tenant_id', $tenantId)
            ->with('produto:id,name,price,featured_image')
            ->orderBy('ordem')->get()
            ->map(fn (MenuDestaque $d) => [
                'id' => $d->id,
                'product_id' => $d->product_id,
                'nome' => $d->produto?->name ?? '—',
                'preco' => (float) ($d->produto?->price ?? 0),
                'imagem' => $d->produto?->featured_image ? Storage::url($d->produto->featured_image) : null,
            ])->values()->all();
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $dados = $request->validate([
            'menu_title' => ['nullable', 'string', 'max:120'],
            'menu_description' => ['nullable', 'string', 'max:2000'],
            'menu_primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'menu_accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'menu_theme' => ['nullable', Rule::in(array_keys(self::TEMAS))],
            'menu_destaques_titulo' => ['nullable', 'string', 'max:120'],
            'menu_show_prices' => ['boolean'],
        ], [
            'menu_primary_color.regex' => __('A cor tem de ser um código do tipo #RRGGBB.'),
            'menu_accent_color.regex' => __('A cor tem de ser um código do tipo #RRGGBB.'),
        ]);

        $tenantId = activeTenantId();
        RestaurantSettings::forTenant($tenantId);

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->update([
            'menu_title' => ($dados['menu_title'] ?? '') ?: null,
            'menu_description' => ($dados['menu_description'] ?? '') ?: null,
            'menu_primary_color' => ($dados['menu_primary_color'] ?? '') ?: null,
            'menu_accent_color' => ($dados['menu_accent_color'] ?? '') ?: null,
            'menu_theme' => $dados['menu_theme'] ?? 'claro',
            'menu_destaques_titulo' => ($dados['menu_destaques_titulo'] ?? '') ?: null,
            'menu_show_prices' => $request->boolean('menu_show_prices'),
        ]);

        return response()->json(['message' => __('Aparência guardada.')]);
    }

    /**
     * A capa ou o logótipo.
     *
     * A imagem nova substitui a anterior E APAGA-A: uma capa trocada dez vezes
     * não pode deixar dez ficheiros no disco.
     */
    public function imagem(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $dados = $request->validate([
            'qual' => ['required', Rule::in(['capa', 'logo'])],
            'ficheiro' => ['required', 'image', 'max:4096'],
        ], ['ficheiro.max' => __('A imagem não pode passar dos 4 MB.')]);

        $tenantId = activeTenantId();
        $d = RestaurantSettings::forTenant($tenantId);

        $coluna = $dados['qual'] === 'logo' ? 'menu_logo' : 'menu_cover';

        $this->deleteOldFile($d->{$coluna});

        /*
         * O NOME LEVA UMA MARCA ÚNICA, e não só a hora.
         *
         * O `uploadFile` nomeia por `<tipo>_<time()>`: duas trocas dentro do
         * mesmo segundo davam o MESMO ficheiro. A antiga era apagada, a nova
         * escrevia por cima com o nome igual, e a coluna ficava a apontar para
         * um caminho que nunca mudou — o browser continuava a mostrar a imagem
         * anterior em cache, e parecia que a gravação não fez nada.
         */
        $caminho = $this->uploadFile(
            $request->file('ficheiro'), 'restaurant-menu', $tenantId,
            $dados['qual'] === 'logo' ? 'logo' : 'cover',
            uniqid(),
        );

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->update([$coluna => $caminho]);

        return response()->json(['message' => __('Imagem guardada.'), 'url' => Storage::url($caminho)]);
    }

    public function removerImagem(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $dados = $request->validate(['qual' => ['required', Rule::in(['capa', 'logo'])]]);

        $tenantId = activeTenantId();
        $d = RestaurantSettings::forTenant($tenantId);
        $coluna = $dados['qual'] === 'logo' ? 'menu_logo' : 'menu_cover';

        $this->deleteOldFile($d->{$coluna});

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->update([$coluna => null]);

        return response()->json(['message' => __('Imagem removida.')]);
    }

    /* ─── Os destaques ─────────────────────────────────────────────────── */

    /** Pratos candidatos: activos, com preço, e ainda por destacar. */
    public function candidatos(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.view');

        $termo = trim((string) $request->string('procura'));

        if (mb_strlen($termo) < 2) {
            return response()->json(['data' => []]);
        }

        $tenantId = activeTenantId();
        $jaLa = MenuDestaque::where('tenant_id', $tenantId)->pluck('product_id');

        return response()->json([
            'data' => Product::where('tenant_id', $tenantId)
                ->where('is_active', true)->where('price', '>', 0)
                ->whereNotIn('id', $jaLa)
                ->where(fn ($q) => $q->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%"))
                ->orderBy('name')->limit(8)->get(['id', 'name', 'price'])
                ->map(fn (Product $p) => ['id' => $p->id, 'nome' => $p->name, 'preco' => (float) $p->price])->values(),
        ]);
    }

    public function destacar(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            // Nunca confiar no id do browser: o artigo tem de ser desta empresa.
            'product_id' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
        ]);

        if (MenuDestaque::where('tenant_id', $tenantId)->count() >= MenuDestaque::MAXIMO) {
            throw ValidationException::withMessages(['geral' => [
                __('Já tem :n destaques. Uma fila longa deixa de destacar o que quer que seja.',
                    ['n' => MenuDestaque::MAXIMO]),
            ]]);
        }

        MenuDestaque::firstOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $dados['product_id']],
            ['ordem' => (int) MenuDestaque::where('tenant_id', $tenantId)->max('ordem') + 1],
        );

        return response()->json(['message' => __('Prato em destaque.'), 'destaques' => $this->destaques($tenantId)]);
    }

    public function retirarDestaque(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();
        MenuDestaque::where('tenant_id', $tenantId)->whereKey($id)->delete();

        return response()->json(['message' => __('Destaque retirado.'), 'destaques' => $this->destaques($tenantId)]);
    }

    /**
     * Sobe ou desce um destaque.
     *
     * Reescreve a coluna inteira a partir da nova ordem da colecção: mais
     * simples do que trocar dois valores, e imune a ordens duplicadas herdadas
     * de qualquer acidente anterior.
     */
    public function mover(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $dados = $request->validate(['sentido' => ['required', Rule::in(['cima', 'baixo'])]]);

        $tenantId = activeTenantId();
        $lista = MenuDestaque::where('tenant_id', $tenantId)->orderBy('ordem')->get();
        $indice = $lista->search(fn ($d) => $d->id === $id);

        if ($indice === false) {
            return response()->json(['destaques' => $this->destaques($tenantId)]);
        }

        $destino = $dados['sentido'] === 'cima' ? $indice - 1 : $indice + 1;

        if ($destino >= 0 && $destino < $lista->count()) {
            $reordenada = $lista->values();
            $item = $reordenada->pull($indice);
            $reordenada = $reordenada->values();
            $reordenada->splice($destino, 0, [$item]);

            foreach ($reordenada->values() as $i => $d) {
                $d->update(['ordem' => $i]);
            }
        }

        return response()->json(['destaques' => $this->destaques($tenantId)]);
    }
}
