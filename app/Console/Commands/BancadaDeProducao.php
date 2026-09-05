<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Uma empresa de ensaio EM PRODUÇÃO, com uma equipa a sério.
 *
 * PORQUE NÃO É O `bancada:pwa`. Esse tem as credenciais escritas no código —
 * `bancada@pwa.local` / `bancada-pwa-2026` — e recusa-se a correr fora de
 * `local` precisamente por isso: quem tiver o repositório entra em produção.
 *
 * Aqui as senhas e os PIN são GERADOS, aparecem uma vez na saída, e não ficam
 * em lado nenhum a não ser na base como hash. Se se perderem, apaga-se e
 * volta-se a montar — é uma bancada, não uma conta de cliente.
 *
 * O QUE ISTO CRIA: uma empresa, um gerente e cinco empregados, cada um com o
 * seu PIN de turno. Cinco porque é a única forma de exercitar o que se parte
 * a sério — dois turnos abertos ao mesmo tempo, um PIN que não pode abrir a
 * caixa de outro, e a fila offline de cada aparelho a subir sem se misturar.
 *
 * APAGA-SE COM `--limpar`. Uma bancada esquecida em produção é uma porta
 * aberta, e esta foi feita para ser fechada.
 */
class BancadaDeProducao extends Command
{
    protected $signature = 'bancada:producao
                            {--limpar : Apaga a empresa de ensaio e toda a gente dela}
                            {--empregados=5 : Quantos empregados criar}
                            {--repor-senhas : Gera senhas e PIN novos para quem já existe}';

    protected $description = 'Monta (ou apaga) uma empresa de ensaio com equipa, com credenciais geradas';

    private const SLUG = 'bancada-de-ensaio';

    private const NIF = '5000000097';

    public function handle(): int
    {
        return $this->option('limpar') ? $this->limpar() : $this->montar();
    }

    private function montar(): int
    {
        $empresa = Tenant::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name'      => 'Bancada de Ensaio',
                'nif'       => self::NIF,
                'email'     => 'bancada@ensaio.soserp.vip',
                'address'   => 'Luanda, Angola',
                'phone'     => '923000001',
                'is_active' => true,
            ]
        );

        $credenciais = [];

        // ── O gerente ────────────────────────────────────────────────────
        $senhaDoGerente = $this->gerarSenha();

        $gerente = User::firstOrCreate(
            ['email' => 'gerente@ensaio.soserp.vip'],
            [
                'name'      => 'Gerente de Ensaio',
                'password'  => Hash::make($senhaDoGerente),
                'tenant_id' => $empresa->id,
                'is_active' => true,
            ]
        );

        // CORRER OUTRA VEZ NÃO MUDA AS CREDENCIAIS DE QUEM JÁ EXISTE.
        //
        // Mudava, e foi um defeito que se apanhou a testar: bastou voltar a
        // montar a bancada — para acrescentar a sala do restaurante — para os
        // cinco PIN deixarem de servir a meio do ensaio. Uma bancada que muda
        // as chaves sempre que se lhe toca não se pode usar para testar nada.
        //
        // Quem quiser rodá-las pede-o: `--repor-senhas`.
        $novo = $gerente->wasRecentlyCreated;
        $repor = $novo || $this->option('repor-senhas');

        if ($repor) {
            $gerente->forceFill(['password' => Hash::make($senhaDoGerente)])->save();
        }

        $gerente->forceFill(['tenant_id' => $empresa->id, 'is_active' => true])->save();

        $pinDoGerente = $repor ? $this->gerarPin() : null;

        if ($pinDoGerente) {
            $gerente->definirPinPos($pinDoGerente);
        }

        $gerente->tenants()->syncWithoutDetaching([$empresa->id => ['is_active' => true]]);

        setPermissionsTeamId($empresa->id);
        $this->darPermissoes($gerente);

        $credenciais[] = [
            'Gerente',
            $gerente->email,
            $repor ? $senhaDoGerente : '(inalterada)',
            $pinDoGerente ?? '(inalterado)',
        ];

        // ── Módulos, subscrição e o mínimo para vender ───────────────────
        $this->ligarModulos($empresa);
        $this->assinatura($empresa);

        $armazem = Warehouse::firstOrCreate(
            ['tenant_id' => $empresa->id, 'code' => 'ENS-01'],
            ['name' => 'Armazém de Ensaio', 'is_active' => true, 'is_default' => true]
        );
        $armazem->setAsDefault();

        $imposto = Tax::firstOrCreate(
            ['tenant_id' => $empresa->id, 'code' => 'ENS-IVA14'],
            ['name' => 'IVA 14%', 'rate' => 14, 'type' => 'iva', 'is_active' => true, 'is_default' => true]
        );

        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $empresa->id],
            [
                'default_warehouse_id' => $armazem->id,
                'default_tax_id'       => $imposto->id,
                'default_tax_rate'     => 14,
                'pos_validate_stock'   => false,
            ]
        );

        // SEM SÉRIE NÃO HÁ DOCUMENTO. Uma venda do PWA sobe e o servidor
        // recusa-a com 500 — e ela fica na fila para sempre.
        $this->call('series:create-defaults', ['--tenant' => $empresa->id]);

        Client::firstOrCreate(
            ['tenant_id' => $empresa->id, 'nif' => '999999999'],
            ['name' => 'Consumidor Final', 'type' => 'pessoa_fisica', 'is_active' => true]
        );

        $this->artigos($empresa, $armazem, $imposto);
        $this->sala($empresa, $armazem);

        // ── A equipa ─────────────────────────────────────────────────────
        $nomes = ['Ana', 'Bruno', 'Carla', 'Diogo', 'Eva', 'Filipe', 'Gabriela', 'Hugo'];
        $quantos = max(1, min(8, (int) $this->option('empregados')));

        for ($i = 0; $i < $quantos; $i++) {
            $nome = $nomes[$i];
            $email = 'emp' . ($i + 1) . '@ensaio.soserp.vip';
            $senha = $this->gerarSenha();

            $empregado = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'      => $nome . ' (ensaio)',
                    'password'  => Hash::make($senha),
                    'tenant_id' => $empresa->id,
                    'is_active' => true,
                ]
            );

            $reporEste = $empregado->wasRecentlyCreated || $this->option('repor-senhas');

            if ($reporEste) {
                $empregado->forceFill(['password' => Hash::make($senha)])->save();
            }

            $empregado->forceFill(['tenant_id' => $empresa->id, 'is_active' => true])->save();

            // CADA UM COM O SEU PIN. É o que permite provar que o PIN de um
            // não abre a caixa de outro — e é exactamente aí que um POS de
            // balcão costuma estar mal feito.
            $pin = $reporEste ? $this->gerarPin() : null;

            if ($pin) {
                $empregado->definirPinPos($pin);
            }

            $empregado->tenants()->syncWithoutDetaching([$empresa->id => ['is_active' => true]]);
            $this->darPermissoes($empregado);

            $credenciais[] = [
                'Empregado ' . ($i + 1),
                $email,
                $reporEste ? $senha : '(inalterada)',
                $pin ?? '(inalterado)',
            ];
        }

        $this->newLine();
        $this->line('<options=bold>BANCADA DE ENSAIO EM PRODUÇÃO</>');
        $this->line('Empresa: ' . $empresa->name . ' (#' . $empresa->id . ')');
        $this->newLine();

        $this->table(['Quem', 'Email', 'Senha', 'PIN'], $credenciais);

        $this->newLine();
        $this->line('<fg=yellow>Estas credenciais aparecem UMA VEZ. Não ficam guardadas em lado nenhum.</>');
        $this->line('<fg=yellow>Quando os ensaios acabarem: php artisan bancada:producao --limpar</>');

        return self::SUCCESS;
    }

    private function limpar(): int
    {
        $empresa = Tenant::where('slug', self::SLUG)->first();

        if (!$empresa) {
            $this->info('Não há bancada de ensaio para apagar.');

            return self::SUCCESS;
        }

        // Os utilizadores primeiro: apagar a empresa deixava-os órfãos, a
        // apontar para um tenant que já não existe — e ainda a conseguir
        // autenticar-se.
        $apagados = User::where('email', 'like', '%@ensaio.soserp.vip')->forceDelete();

        $empresa->delete();

        $this->info("Bancada apagada: {$apagados} utilizador(es) e a empresa.");

        return self::SUCCESS;
    }

    /** Artigos com stock, para haver o que vender. */
    private function artigos(Tenant $empresa, Warehouse $armazem, Tax $imposto): void
    {
        $catalogo = [
            ['Água 1,5L', 500], ['Refrigerante 33cl', 800], ['Pão de forma', 850],
            ['Arroz agulha 1kg', 2300], ['Óleo alimentar 900ml', 3100], ['Muamba de Galinha', 5500],
        ];

        foreach ($catalogo as $i => [$nome, $preco]) {
            $artigo = Product::firstOrCreate(
                ['tenant_id' => $empresa->id, 'code' => 'ENS-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'name'         => $nome,
                    'price'        => $preco,
                    'type'         => 'produto',
                    'manage_stock' => true,
                    'tax_rate_id'  => $imposto->id,
                    'is_active'    => true,
                ]
            );

            // Stock por movimento, nunca por escrita directa no agregado: o
            // StockObserver é que mantém a soma, e mexer no número à mão
            // deixava-o a discordar das linhas.
            if (!$artigo->stocks()->where('warehouse_id', $armazem->id)->exists()) {
                $artigo->stocks()->create([
                    'tenant_id'    => $empresa->id,
                    'warehouse_id' => $armazem->id,
                    'quantity'     => 500,
                ]);
            }
        }
    }

    /**
     * A sala do restaurante: sem mesas não há nada para ensaiar.
     *
     * O módulo activo não chega — a primeira corrida do ensaio completo
     * apanhou exactamente isto: "1 sala, 0 mesas", e os dois passos do
     * restaurante morreram por falta de sítio onde abrir uma comanda.
     */
    private function sala(Tenant $empresa, Warehouse $armazem): void
    {
        // REAPROVEITA A SALA QUE JÁ EXISTE. Activar o módulo do restaurante já
        // cria uma ("Restaurante Principal"); criar aqui uma segunda deixava a
        // empresa com duas — e o ecrã abre na primeira, que ficava vazia. Um
        // empregado aterrava numa sala sem mesas e tinha de descobrir sozinho
        // que havia outra. Foi o que se viu a testar no Android.
        $sala = \App\Models\Restaurant\Venue::where('tenant_id', $empresa->id)
            ->orderBy('id')
            ->first();

        if (!$sala) {
            $sala = \App\Models\Restaurant\Venue::create([
                'tenant_id'    => $empresa->id,
                'code'         => 'ENS-S1',
                'name'         => 'Salão de Ensaio',
                'warehouse_id' => $armazem->id,
                'is_active'    => true,
            ]);
        }

        $zona = \App\Models\Restaurant\Area::firstOrCreate(
            ['tenant_id' => $empresa->id, 'venue_id' => $sala->id, 'name' => 'Esplanada'],
            ['sort_order' => 0, 'is_active' => true]
        );

        for ($n = 1; $n <= 6; $n++) {
            \App\Models\Restaurant\DiningTable::firstOrCreate(
                ['tenant_id' => $empresa->id, 'code' => 'ENS-M' . $n],
                [
                    'venue_id'  => $sala->id,
                    'area_id'   => $zona->id,
                    'name'      => 'Mesa ' . $n,
                    'capacity'  => 4,
                    'status'    => 'available',
                    'is_active' => true,
                ]
            );
        }

        \App\Models\Restaurant\RestaurantSettings::updateOrCreate(
            ['tenant_id' => $empresa->id],
            [
                'default_warehouse_id'        => $armazem->id,
                'use_kitchen_workflow'        => true,
                'require_recipe_for_products' => false,
                'next_order_number'           => 1,
            ]
        );
    }

    private function darPermissoes(User $u): void
    {
        $nomes = \Spatie\Permission\Models\Permission::query()->pluck('name')->all();

        if ($nomes) {
            $u->syncPermissions($nomes);
        }

        $u->forgetCachedPermissions();
    }

    private function ligarModulos(Tenant $empresa): void
    {
        $sync = new \App\Services\Tenant\TenantModuleSyncService();

        // A Bancada de Ensaio valida a plataforma inteira. Limitá-la ao pacote
        // Restaurante deixava os restantes módulos invisíveis e obrigava a
        // intervenções manuais sempre que se queria testar RH, Hotel, Oficina,
        // Contabilidade, etc.
        foreach (\App\Models\Module::query()->orderBy('id')->pluck('slug') as $slug) {
            try {
                $sync->activateModule($empresa, $slug);
            } catch (\Throwable $e) {
                $this->warn("módulo {$slug}: " . $e->getMessage());
            }
        }
    }

    private function assinatura(Tenant $empresa): void
    {
        // A subscrição deve contar a mesma verdade que o pivot tenant_module.
        // Caso contrário uma reconciliação futura volta a reduzir a bancada ao
        // pacote Restaurante, mesmo depois de ativarmos todos os módulos.
        $plano = \App\Models\Plan::where('slug', 'enterprise')->first()
            ?? \App\Models\Plan::where('slug', 'fox-friendly')->first()
            ?? \App\Models\Plan::where('is_active', true)->first();

        if (!$plano) {
            $this->warn('Sem plano disponível — a bancada fica sem subscrição.');

            return;
        }

        \App\Models\Subscription::updateOrCreate(
            ['tenant_id' => $empresa->id],
            [
                'plan_id'              => $plano->id,
                'status'               => 'active',
                'amount'               => 0,
                'current_period_start' => now(),
                'current_period_end'   => now()->addMonth(),
            ]
        );
    }

    /** Uma senha que ninguém adivinha e que não está escrita em código nenhum. */
    private function gerarSenha(): string
    {
        return Str::lower(Str::random(4)) . '-' . Str::lower(Str::random(4)) . '-' . random_int(100, 999);
    }

    private function gerarPin(): string
    {
        return (string) random_int(1000, 9999);
    }
}
