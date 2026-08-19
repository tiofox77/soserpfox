<?php

namespace App\Services\Plataforma;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Monta um plano à medida de um cliente e atribui-lho.
 *
 * O plano nasce ACTIVO mas FORA DA MONTRA (is_public = false): funciona na
 * subscrição e continua visível na gestão, mas não aparece na landing, no
 * registo, nem na grelha de planos dos outros clientes.
 *
 * A atribuição reutiliza o caminho canónico — TrocarDePlano::aplicar —
 * seguido da sincronização de módulos. Isto NÃO é opcional: o TrocarDePlano
 * trata da subscrição e não toca em módulos nem permissões; sozinho,
 * deixaria a empresa com a subscrição nova e os módulos do plano antigo.
 */
class PlanoAMedida
{
    public function __construct(
        private TrocarDePlano $trocarDePlano,
    ) {
    }

    /**
     * @param  array  $dados  nome, modulos (slugs), preco_mensal, preco_anual,
     *                        max_users, max_companies, max_storage_mb,
     *                        trial_days, ciclo
     */
    public function criarEAtribuir(Tenant $empresa, array $dados): Plan
    {
        $this->validar($dados);

        return DB::transaction(function () use ($empresa, $dados) {
            $plano = $this->criarPlano($empresa, $dados);

            // 1) A subscrição, pelo caminho de sempre (cancela a anterior,
            //    respeita a regra do teste único, calcula o período).
            $this->trocarDePlano->aplicar($empresa, $plano, $dados['ciclo'] ?? 'monthly');

            // 2) Os módulos e as permissões — o que o TrocarDePlano não faz.
            $this->sincronizarModulos($empresa, $plano);

            // 3) O preço e o teste de CADA módulo, no pivô da empresa. É o
            //    que permite dar um módulo a experimentar sem pôr o plano
            //    inteiro em teste, e saber de onde veio o total cobrado.
            $this->marcarModulos($empresa, $dados);

            // 3) Os limites do plano passam a ser os da empresa.
            $empresa->forceFill([
                'max_users'       => $plano->max_users,
                'max_storage_mb'  => $plano->max_storage_mb,
            ])->save();

            return $plano;
        });
    }

    /**
     * A mensalidade é a SOMA do que cada módulo custa.
     *
     * @param  array  $precos  [slug => preço mensal]
     */
    public static function somar(array $modulos, array $precos): float
    {
        $total = 0.0;

        foreach ($modulos as $slug) {
            $total += (float) ($precos[$slug] ?? 0);
        }

        return round($total, 2);
    }

    private function validar(array $dados): void
    {
        if (empty(trim($dados['nome'] ?? ''))) {
            throw new \InvalidArgumentException('O plano tem de ter nome.');
        }

        if (empty($dados['modulos'] ?? [])) {
            throw new \InvalidArgumentException('Escolha pelo menos um módulo.');
        }

        // ARMADILHA CONHECIDA: um plano com mensalidade 0 é tratado em todo o
        // sistema como "o plano gratuito" — queima a cortesia única do cliente
        // e é recusado a quem já foi cliente. Um plano negociado, mesmo barato,
        // tem de ter preço; o desconto dá-se no valor cobrado, não aqui.
        if ((float) ($dados['preco_mensal'] ?? 0) <= 0) {
            throw new \InvalidArgumentException(
                'A mensalidade tem de ser maior que zero. Um plano a zero é tratado '
                . 'como o plano gratuito e gasta a cortesia única do cliente.'
            );
        }
    }

    private function criarPlano(Tenant $empresa, array $dados): Plan
    {
        $mensal = round((float) $dados['preco_mensal'], 2);

        // Anual em branco = doze vezes o mensal. Gravar 0 não seria "grátis":
        // os accessors do Plan devolvem 12x o mensal quando a coluna está a 0,
        // e isso esconderia o preço combinado.
        $anual = isset($dados['preco_anual']) && (float) $dados['preco_anual'] > 0
            ? round((float) $dados['preco_anual'], 2)
            : round($mensal * 12, 2);

        $plano = Plan::create([
            'name'          => $dados['nome'],
            'slug'          => $this->slugUnico($dados['nome'], $empresa),
            'description'   => $dados['descricao']
                ?? 'Plano à medida de ' . $empresa->name,
            'price_monthly' => $mensal,
            'price_yearly'  => $anual,
            'max_users'      => (int) ($dados['max_users'] ?? 5),
            'max_companies'  => (int) ($dados['max_companies'] ?? 1),
            'max_storage_mb' => (int) ($dados['max_storage_mb'] ?? 2000),
            'trial_days'    => (int) ($dados['trial_days'] ?? 0),
            'auto_activate' => false,   // é negociado; não arranca sozinho
            'is_active'     => true,    // tem de funcionar na subscrição
            'is_public'     => false,   // mas fica fora da montra
            'features'      => $this->descrever($dados),
            'order'         => 99,
        ]);

        $ids = Module::whereIn('slug', $dados['modulos'])->pluck('id')->all();
        $plano->modules()->sync($ids);

        return $plano;
    }

    /**
     * Grava, por módulo, quanto custa a esta empresa e até quando é teste.
     *
     * O trial por módulo tem efeito real: o Tenant::hasModule — o único
     * portão por onde o acesso aos módulos passa — deixa de o dar como
     * disponível depois da data.
     */
    private function marcarModulos(Tenant $empresa, array $dados): void
    {
        $precos = $dados['precos'] ?? [];
        $testes = $dados['testes'] ?? [];

        $ids = Module::whereIn('slug', $dados['modulos'])->pluck('id', 'slug');

        foreach ($dados['modulos'] as $slug) {
            if (!isset($ids[$slug])) {
                continue;
            }

            $dias = (int) ($testes[$slug] ?? 0);

            $empresa->modules()->updateExistingPivot($ids[$slug], [
                'price'         => round((float) ($precos[$slug] ?? 0), 2),
                // Sem dias não há teste: o módulo vale enquanto o plano valer.
                'trial_ends_at' => $dias > 0 ? now()->addDays($dias) : null,
            ]);
        }
    }

    /** Slug único e reconhecível: quem o vir na base sabe de quem é. */
    private function slugUnico(string $nome, Tenant $empresa): string
    {
        $base = 'medida-' . Str::slug($empresa->name) . '-' . Str::slug($nome);
        $slug = Str::limit($base, 60, '');
        $i = 1;

        while (Plan::where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 56, '') . '-' . (++$i);
        }

        return $slug;
    }

    /** A descrição sai do que foi montado — não se escreve à mão. */
    private function descrever(array $dados): array
    {
        $nomes = Module::whereIn('slug', $dados['modulos'])
            ->orderBy('name')->pluck('name')->all();

        return array_values(array_filter([
            'Módulos: ' . implode(', ', $nomes),
            ($dados['max_users'] ?? null) ? $dados['max_users'] . ' utilizadores' : null,
            ($dados['max_companies'] ?? null) ? $dados['max_companies'] . ' empresa(s)' : null,
            ($dados['max_storage_mb'] ?? null)
                ? round(((int) $dados['max_storage_mb']) / 1000, 1) . 'GB de armazenamento'
                : null,
            'Plano à medida',
        ]));
    }

    /**
     * Activa os módulos do plano e desactiva os que já não pertencem.
     * Mesmo padrão do painel de empresas (SuperAdmin\Tenants::syncPlanModules).
     */
    private function sincronizarModulos(Tenant $empresa, Plan $plano): void
    {
        // Com as dependências resolvidas: quem leva Faturação leva Tesouraria.
        $doPlano = $plano->moduleSlugsWithDependencies();
        $sync = new TenantModuleSyncService();

        $activos = $empresa->modules()->wherePivot('is_active', true)
            ->pluck('modules.slug')->all();

        foreach (array_diff($activos, $doPlano) as $slug) {
            $sync->deactivateModule($empresa, $slug);
        }

        foreach ($doPlano as $slug) {
            $sync->activateModule($empresa, $slug);
        }
    }
}
