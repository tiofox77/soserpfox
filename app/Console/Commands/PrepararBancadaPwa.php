<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Monta a bancada de ensaio do PWA: empresa, utilizador, catálogo e stock.
 *
 * Serve os testes de browser (Playwright) que exercitam o modo offline a
 * sério — sem uma empresa montada, o PWA não passa do login e não há nada
 * para sincronizar.
 *
 * RECUSA-SE A CORRER FORA DE `local`. As credenciais são fixas e conhecidas,
 * o que num servidor a sério seria uma porta aberta. A verificação não é um
 * aviso: é um `abort`.
 */
class PrepararBancadaPwa extends Command
{
    protected $signature = 'bancada:pwa
                            {--limpar : Apaga a bancada em vez de a montar}';

    protected $description = 'Monta (ou limpa) a empresa de ensaio do PWA — só em ambiente local';

    public const SLUG     = 'bancada-pwa';
    public const EMAIL    = 'bancada@pwa.local';
    public const PASSWORD = 'bancada-pwa-2026';
    public const PIN      = '4321';

    public function handle(): int
    {
        if (!app()->environment('local')) {
            $this->error('bancada:pwa só corre em APP_ENV=local. Aqui é ' . app()->environment() . '.');

            return self::FAILURE;
        }

        return $this->option('limpar') ? $this->limpar() : $this->montar();
    }

    private function montar(): int
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name'      => 'Bancada PWA',
                'nif'       => '5000000099',
                'email'     => self::EMAIL,
                'address'   => 'Luanda, Angola',
                'phone'     => '923000000',
                'is_active' => true,
            ]
        );

        $utilizador = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name'      => 'Operador da Bancada',
                'password'  => Hash::make(self::PASSWORD),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );

        // A password é reposta sempre: uma bancada que não deixa entrar não
        // serve para nada, e o teste não tem como adivinhar outra.
        $utilizador->forceFill([
            'password'  => Hash::make(self::PASSWORD),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ])->save();

        $utilizador->tenants()->syncWithoutDetaching([$tenant->id => ['is_active' => true]]);
        setPermissionsTeamId($tenant->id);

        $this->darTodasAsPermissoes($utilizador);
        $this->ligarModulos($tenant);
        $this->assinatura($tenant);

        $armazem = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-01'],
            ['name' => 'Armazém da Bancada', 'is_active' => true, 'is_default' => true]
        );
        $armazem->setAsDefault();

        $imposto = Tax::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BANC-IVA14'],
            ['name' => 'IVA 14%', 'rate' => 14, 'type' => 'iva', 'is_active' => true, 'is_default' => true]
        );

        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'default_warehouse_id' => $armazem->id,
                'default_tax_id'       => $imposto->id,
                'default_tax_rate'     => 14,
                // Sem isto o POS recusa vender o que não tem stock, e metade
                // dos ensaios morria antes de chegar ao que interessa.
                'pos_validate_stock'   => false,
            ]
        );

        // SÉRIES FISCAIS. Sem elas, uma fatura ou fatura-recibo criada no PWA
        // sobe e o servidor recusa-a com "Nenhuma série activa de homologação
        // para este tipo de documento" — um 500, e o documento fica na fila
        // para sempre. A proforma passava, porque não é documento fiscal e não
        // precisa de série; foi essa diferença que denunciou o que faltava.
        $this->call('series:create-defaults', ['--tenant' => $tenant->id]);

        $cliente = Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nif' => '5000000098'],
            ['name' => 'Cliente da Bancada', 'type' => 'pessoa_juridica', 'is_active' => true]
        );

        $artigos = 0;
        foreach ([
            ['BANC-A', 'Água 1,5L', 500],
            ['BANC-B', 'Pão de forma', 850],
            ['BANC-C', 'Leite meio-gordo 1L', 1200],
            ['BANC-D', 'Arroz agulha 1kg', 2300],
            ['BANC-E', 'Óleo alimentar 900ml', 3100],
        ] as [$codigo, $nome, $preco]) {
            $artigo = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $codigo],
                [
                    'name'         => $nome,
                    'price'        => $preco,
                    'cost'         => round($preco * 0.7),
                    'tax_id'       => $imposto->id,
                    'tax_rate'     => 14,
                    'is_active'    => true,
                    'manage_stock' => true,
                ]
            );

            Stock::updateOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $artigo->id, 'warehouse_id' => $armazem->id],
                ['quantity' => 500]
            );

            $artigos++;
        }

        $this->newLine();
        $this->info('Bancada do PWA montada.');
        $this->table(['', ''], [
            ['URL',      config('app.url') . '/invoicing/offline'],
            ['Empresa',  $tenant->name . ' (#' . $tenant->id . ')'],
            ['Email',    self::EMAIL],
            ['Password', self::PASSWORD],
            ['PIN',      self::PIN],
            ['Artigos',  $artigos],
            ['Cliente',  $cliente->name],
            ['Armazém',  $armazem->name],
        ]);

        return self::SUCCESS;
    }

    private function limpar(): int
    {
        $tenant = Tenant::where('slug', self::SLUG)->first();

        if (!$tenant) {
            $this->info('Não há bancada para limpar.');

            return self::SUCCESS;
        }

        // Sem softDeletes aqui: uma bancada meio-apagada dá ensaios que
        // dependem do lixo da corrida anterior.
        Stock::where('tenant_id', $tenant->id)->forceDelete();
        Product::where('tenant_id', $tenant->id)->forceDelete();
        Client::where('tenant_id', $tenant->id)->forceDelete();
        Warehouse::where('tenant_id', $tenant->id)->forceDelete();
        InvoicingSettings::where('tenant_id', $tenant->id)->delete();
        User::where('email', self::EMAIL)->forceDelete();
        $tenant->forceDelete();

        $this->info('Bancada apagada.');

        return self::SUCCESS;
    }

    private function darTodasAsPermissoes(User $u): void
    {
        // O PWA toca em faturação, POS e clientes. Dar tudo é mais honesto do
        // que adivinhar a lista e ver o ensaio morrer num 403 daqui a um mês.
        $todas = \Spatie\Permission\Models\Permission::pluck('name')->all();

        if ($todas) {
            $u->syncPermissions($todas);
        }
    }

    private function ligarModulos(Tenant $tenant): void
    {
        foreach (['invoicing', 'treasury'] as $slug) {
            $modulo = Module::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'is_active' => true]
            );

            $tenant->modules()->syncWithoutDetaching([
                $modulo->id => ['is_active' => true, 'activated_at' => now(), 'trial_ends_at' => null],
            ]);
        }
    }

    private function assinatura(Tenant $tenant): void
    {
        $plano = Plan::where('slug', 'business')->first() ?: Plan::first();

        if (!$plano) {
            return;
        }

        // Sem subscrição activa o CheckSubscription manda tudo para
        // /subscription-expired e o PWA nem chega a carregar.
        $tenant->subscriptions()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id'              => $plano->id,
                'status'               => 'active',
                'current_period_start' => now()->subDay(),
                'current_period_end'   => now()->addYear(),
                'ends_at'              => now()->addYear(),
                'amount'               => $plano->price_monthly ?? 0,
                'billing_cycle'        => 'monthly',
            ]
        );
    }
}
