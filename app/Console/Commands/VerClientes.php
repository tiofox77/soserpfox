<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Quantos clientes tem uma empresa, e onde cai um deles na ordem alfabética.
 * Só lê — não escreve nada.
 *
 * Os emissores (factura, proforma) recebiam só os 500 primeiros clientes por
 * ordem alfabética e a caixa de procura filtrava essa lista no browser: um
 * cliente para lá do 500.º não aparecia. Isto diz, em produção, se é esse o
 * caso de um cliente concreto.
 *
 *   php artisan clientes:ver --tenant=57 --procura=televis
 */
class VerClientes extends Command
{
    protected $signature = 'clientes:ver
                            {--tenant= : id da empresa}
                            {--procura= : parte do nome ou do NIF}
                            {--limite=10 : quantos mostrar}';

    protected $description = 'Conta os clientes de uma empresa e mostra a posição de um deles (só leitura)';

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (! $empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $base = fn () => Client::query()->where('tenant_id', $empresa->id);

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(sprintf(' clientes: %d   activos: %d   inactivos: %d   na reciclagem: %d',
            $base()->count(),
            $base()->where('is_active', true)->count(),
            $base()->where('is_active', false)->count(),
            Client::onlyTrashed()->where('tenant_id', $empresa->id)->count()));

        $termo = trim((string) $this->option('procura'));
        if ($termo === '') {
            return self::SUCCESS;
        }

        $achados = Client::withTrashed()->where('tenant_id', $empresa->id)
            ->where(fn ($w) => $w->where('name', 'like', "%{$termo}%")->orWhere('nif', 'like', "%{$termo}%"))
            ->orderBy('name')->limit((int) $this->option('limite'))->get();

        $this->newLine();
        $this->table(
            ['id', 'nome', 'NIF', 'activo', 'apagado', 'posição', 'nos 500 do emissor'],
            $achados->map(function (Client $c) use ($base) {
                // A mesma ordem do emissor: orderBy('name'), sem filtro de activo.
                $posicao = $base()->where('name', '<', $c->name)->count() + 1;

                return [
                    $c->id, mb_strimwidth($c->name, 0, 40, '…'), $c->nif ?: '—',
                    $c->is_active ? 'sim' : 'NÃO', $c->deleted_at ? 'SIM' : 'não',
                    $posicao, $c->deleted_at ? '—' : ($posicao <= 500 ? 'sim' : 'NÃO'),
                ];
            })->all()
        );

        return self::SUCCESS;
    }
}
