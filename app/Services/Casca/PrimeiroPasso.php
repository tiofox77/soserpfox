<?php

namespace App\Services\Casca;

use App\Models\Tenant;

/**
 * O PRÓXIMO PASSO DE UMA EMPRESA NOVA (26/09/2026).
 *
 * Depois do registo, o `/home` mostrava números a zero e mais nada. Quem veio
 * do anúncio do hotel não sabia que o primeiro passo é o primeiro quarto. Aqui
 * decide-se UM passo concreto, pelo módulo do plano: o primeiro que ainda não
 * tem a configuração mínima feita.
 *
 * NUNCA CRIA NADA. Não há facturas nem dados de exemplo em contas reais: o
 * passo leva ao ecrã onde a pessoa faz ela mesma. Só aponta para ecrãs que
 * ela pode abrir (módulo activo e permissão), e desaparece quando está feito.
 */
final class PrimeiroPasso
{
    /**
     * Por ordem: os módulos de sector primeiro (são a razão de o cliente ter
     * vindo), a facturação no fim (vem com quase todos os pacotes).
     *
     * @return list<array{modulo:string, modelo:class-string<\Illuminate\Database\Eloquent\Model>, permissao:string, rota:string, titulo:string, texto:string, botao:string, icone:string}>
     */
    private static function passos(): array
    {
        return [
            ['modulo' => 'hotel', 'modelo' => \App\Models\Hotel\RoomType::class, 'permissao' => 'hotel.room-types.view', 'rota' => 'hotel.room-types',
                'titulo' => __('Configure o primeiro quarto'), 'texto' => __('Comece pelo tipo de quarto (por exemplo «Duplo»), com o preço por noite; depois junte os quartos.'),
                'botao' => __('Criar o primeiro tipo de quarto'), 'icone' => 'fa-bed'],
            ['modulo' => 'restaurant', 'modelo' => \App\Models\Restaurant\DiningTable::class, 'permissao' => 'restaurant.floor.view', 'rota' => 'restaurant.floor',
                'titulo' => __('Configure a primeira mesa'), 'texto' => __('Desenhe a sala com as mesas; a seguir junte os pratos à carta.'),
                'botao' => __('Criar a primeira mesa'), 'icone' => 'fa-utensils'],
            ['modulo' => 'oficina', 'modelo' => \App\Models\Workshop\Service::class, 'permissao' => 'workshop.services.view', 'rota' => 'workshop.services',
                'titulo' => __('Comece a configurar a oficina'), 'texto' => __('Registe os serviços que faz (revisão, mudança de óleo…) com o preço da mão-de-obra.'),
                'botao' => __('Criar o primeiro serviço'), 'icone' => 'fa-wrench'],
            ['modulo' => 'rh', 'modelo' => \App\Models\HR\Employee::class, 'permissao' => 'employees.view', 'rota' => 'hr.employees.index',
                'titulo' => __('Adicione o primeiro colaborador'), 'texto' => __('Com o colaborador registado já pode marcar presenças e processar o salário.'),
                'botao' => __('Adicionar colaborador'), 'icone' => 'fa-user-plus'],
            ['modulo' => 'salon', 'modelo' => \App\Models\Salon\Service::class, 'permissao' => 'salon.services.view', 'rota' => 'salon.services',
                'titulo' => __('Adicione o primeiro serviço do salão'), 'texto' => __('Registe os serviços e os preços; a agenda usa-os para as marcações.'),
                'botao' => __('Criar o primeiro serviço'), 'icone' => 'fa-scissors'],
            ['modulo' => 'invoicing', 'modelo' => \App\Models\Product::class, 'permissao' => 'invoicing.products.view', 'rota' => 'invoicing.products',
                'titulo' => __('Adicione o primeiro produto'), 'texto' => __('Com o produto (ou serviço) e o preço, já pode vender no balcão e emitir facturas.'),
                'botao' => __('Adicionar produto'), 'icone' => 'fa-box-open'],
        ];
    }

    /** @return array{modulo:string, titulo:string, texto:string, botao:string, icone:string, url:string}|null */
    public static function para(?Tenant $empresa): ?array
    {
        if (! $empresa) {
            return null;
        }

        foreach (self::passos() as $p) {
            if (! $empresa->hasModule($p['modulo']) || ! podeVer($p['permissao'])) {
                continue;
            }

            // Pelo MODELO e não pela tabela: os serviços do salão vivem em
            // `invoicing_products` (com o escopo do salão), e só o modelo sabe.
            $modelo = $p['modelo'];
            if ($modelo::query()->where((new $modelo)->getTable() . '.tenant_id', $empresa->id)->exists()) {
                continue;
            }

            return [
                'modulo' => $p['modulo'],
                'titulo' => $p['titulo'],
                'texto' => $p['texto'],
                'botao' => $p['botao'],
                'icone' => $p['icone'],
                'url' => route($p['rota']),
            ];
        }

        return null;
    }
}
