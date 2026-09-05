<?php

namespace App\Livewire\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\MenuDestaque;
use App\Models\Restaurant\RestaurantSettings;
use App\Traits\ManagesFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Aparência da carta online — o que faz a carta parecer a casa.
 *
 * Está separado das definições gerais do restaurante de propósito: escolher a
 * fotografia da capa não é a mesma decisão que escolher o armazém por omissão,
 * e o ecrã das definições já estava a ficar uma gaveta de tudo.
 *
 * A pré-visualização é a carta VERDADEIRA num iframe, não uma imitação: uma
 * imitação diverge do original ao primeiro retoque, e o dono acaba a decidir
 * por uma coisa que não é a que o cliente vai ver.
 */
#[Layout('layouts.app')]
#[Title('Aparência da Carta')]
class AparenciaDaCarta extends Component
{
    use ManagesFileUploads, WithFileUploads;

    public string $titulo = '';

    public string $descricao = '';

    public string $cor = '#ea580c';

    public string $corAcento = '#0f766e';

    public string $tema = 'claro';

    public string $tituloDestaques = '';

    public bool $mostrarPrecos = true;

    public $capaNova;

    public $logoNovo;

    public ?string $capa = null;

    public ?string $logo = null;

    // ── Destaques ────────────────────────────────────────────────────────
    public bool $showDestaques = false;

    public string $procurar = '';

    /** Muda a cada gravação para forçar o iframe a recarregar. */
    public int $versaoPreview = 0;

    public const TEMAS = ['claro' => 'Claro', 'escuro' => 'Escuro'];

    public function mount(): void
    {
        $d = RestaurantSettings::forTenant(activeTenantId());

        $this->titulo = (string) ($d->menu_title ?? '');
        $this->descricao = (string) ($d->menu_description ?? '');
        $this->cor = (string) ($d->menu_primary_color ?: '#ea580c');
        $this->corAcento = (string) ($d->menu_accent_color ?: '#0f766e');
        $this->tema = (string) ($d->menu_theme ?: 'claro');
        $this->tituloDestaques = (string) ($d->menu_destaques_titulo ?? '');
        $this->mostrarPrecos = (bool) ($d->menu_show_prices ?? true);
        $this->capa = $d->menu_cover;
        $this->logo = $d->menu_logo;
    }

    public function guardar(): void
    {
        if (! $this->podeOu('restaurant.settings.edit')) {
            return;
        }

        $dados = $this->validate([
            'titulo' => ['nullable', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'cor' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'corAcento' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tituloDestaques' => ['nullable', 'string', 'max:120'],
            'capaNova' => ['nullable', 'image', 'max:4096'],
            'logoNovo' => ['nullable', 'image', 'max:2048'],
        ], [
            'cor.regex' => __('A cor tem de ser um código do tipo #RRGGBB.'),
            'corAcento.regex' => __('A cor tem de ser um código do tipo #RRGGBB.'),
            'capaNova.max' => __('A capa não pode passar dos 4 MB.'),
        ]);

        $tenantId = activeTenantId();

        // Imagem nova substitui a anterior e apaga-a: uma capa trocada dez
        // vezes não pode deixar dez ficheiros no disco.
        if ($this->capaNova) {
            $this->deleteOldFile($this->capa);
            $this->capa = $this->uploadFile($this->capaNova, 'restaurant-menu', $tenantId, 'cover');
        }

        if ($this->logoNovo) {
            $this->deleteOldFile($this->logo);
            $this->logo = $this->uploadFile($this->logoNovo, 'restaurant-menu', $tenantId, 'logo');
        }

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->update([
            'menu_title' => $dados['titulo'] ?: null,
            'menu_description' => $dados['descricao'] ?: null,
            'menu_primary_color' => $dados['cor'] ?: null,
            'menu_accent_color' => $dados['corAcento'] ?: null,
            'menu_theme' => array_key_exists($this->tema, self::TEMAS) ? $this->tema : 'claro',
            'menu_destaques_titulo' => $dados['tituloDestaques'] ?: null,
            'menu_show_prices' => $this->mostrarPrecos,
            'menu_cover' => $this->capa,
            'menu_logo' => $this->logo,
        ]);

        $this->reset(['capaNova', 'logoNovo']);
        $this->versaoPreview++;
        $this->dispatch('notify', type: 'success', message: __('Aparência guardada.'));
    }

    public function removerCapa(): void
    {
        if (! $this->podeOu('restaurant.settings.edit')) {
            return;
        }

        $this->deleteOldFile($this->capa);
        $this->capa = null;

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', activeTenantId())
            ->update(['menu_cover' => null]);

        $this->versaoPreview++;
        $this->dispatch('notify', type: 'success', message: __('Capa removida.'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destaques
    // ─────────────────────────────────────────────────────────────────────

    public function destacar(int $productId): void
    {
        if (! $this->podeOu('restaurant.settings.edit')) {
            return;
        }

        $tenantId = activeTenantId();

        if (MenuDestaque::where('tenant_id', $tenantId)->count() >= MenuDestaque::MAXIMO) {
            $this->dispatch('notify', type: 'error',
                message: __('Já tem :n destaques. Uma fila longa deixa de destacar o que quer que seja.',
                    ['n' => MenuDestaque::MAXIMO]));

            return;
        }

        // Nunca confiar no id do browser: o artigo tem de ser desta empresa.
        $existe = Product::where('tenant_id', $tenantId)->whereKey($productId)->exists();

        if (! $existe) {
            return;
        }

        MenuDestaque::firstOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $productId],
            ['ordem' => (int) MenuDestaque::where('tenant_id', $tenantId)->max('ordem') + 1],
        );

        $this->versaoPreview++;
        $this->dispatch('notify', type: 'success', message: __('Prato em destaque.'));
    }

    public function retirarDestaque(int $id): void
    {
        if (! $this->podeOu('restaurant.settings.edit')) {
            return;
        }

        MenuDestaque::where('tenant_id', activeTenantId())->whereKey($id)->delete();

        $this->versaoPreview++;
        $this->dispatch('notify', type: 'success', message: __('Destaque retirado.'));
    }

    /** Sobe ou desce um destaque, trocando a ordem com o vizinho. */
    public function mover(int $id, string $sentido): void
    {
        if (! $this->podeOu('restaurant.settings.edit')) {
            return;
        }

        $tenantId = activeTenantId();
        $lista = MenuDestaque::where('tenant_id', $tenantId)->orderBy('ordem')->get();
        $indice = $lista->search(fn ($d) => $d->id === $id);

        if ($indice === false) {
            return;
        }

        $destino = $sentido === 'cima' ? $indice - 1 : $indice + 1;

        if ($destino < 0 || $destino >= $lista->count()) {
            return;
        }

        // Reescreve a coluna inteira a partir da nova ordem da colecção: mais
        // simples do que trocar dois valores, e imune a ordens duplicadas
        // herdadas de qualquer acidente anterior.
        $reordenada = $lista->values();
        $item = $reordenada->pull($indice);
        $reordenada = $reordenada->values();
        $reordenada->splice($destino, 0, [$item]);

        foreach ($reordenada->values() as $i => $d) {
            $d->update(['ordem' => $i]);
        }

        $this->versaoPreview++;
    }

    public function getDestaquesProperty()
    {
        return MenuDestaque::where('tenant_id', activeTenantId())
            ->with('produto:id,name,price,featured_image')
            ->orderBy('ordem')->get();
    }

    /** Pratos candidatos a destaque: activos, com preço, e ainda por destacar. */
    public function getCandidatosProperty()
    {
        $termo = trim($this->procurar);

        if (mb_strlen($termo) < 2) {
            return collect();
        }

        $jaDestacados = MenuDestaque::where('tenant_id', activeTenantId())->pluck('product_id');

        return Product::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->whereNotIn('id', $jaDestacados)
            ->where(fn ($q) => $q->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%"))
            ->orderBy('name')->limit(8)->get(['id', 'name', 'price']);
    }

    private function podeOu(string $permissao): bool
    {
        if (auth()->user()?->can($permissao)) {
            return true;
        }

        $this->dispatch('notify', type: 'error', message: __('Não tem permissão para isto.'));

        return false;
    }

    public function render()
    {
        $d = RestaurantSettings::forTenant(activeTenantId());

        return view('livewire.restaurant.aparencia-da-carta', [
            'definicoes' => $d,
            // Só há pré-visualização quando a carta está publicada: um iframe
            // para um endereço desligado mostrava a página de recusa e parecia
            // avaria.
            'urlDaCarta' => ($d->online_menu_enabled && $d->menu_slug)
                ? url('/menu/'.$d->menu_slug)
                : null,
        ]);
    }
}
