<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Mostra artigos de uma empresa. Só lê — não escreve nada.
 *
 * Serve para conferir em produção o que ficou mesmo gravado, sem ter de
 * acreditar no que a importação disse que fez.
 *
 *   php artisan artigos:ver --tenant=57 --procura=GOGYNAX
 *   php artisan artigos:ver --tenant=57 --zeros
 */
class VerArtigos extends Command
{
    protected $signature = 'artigos:ver
                            {--tenant= : id da empresa}
                            {--procura= : parte do nome ou do código}
                            {--zeros : só os que têm o código a começar por zero}
                            {--limite=40 : quantos mostrar}';

    protected $description = 'Mostra artigos de uma empresa (só leitura)';

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $q = Product::query()->where('tenant_id', $empresa->id);

        if ($termo = $this->option('procura')) {
            $q->where(function ($w) use ($termo) {
                $w->where('name', 'like', "%{$termo}%")
                    ->orWhere('barcode', 'like', "%{$termo}%")
                    ->orWhere('code', 'like', "%{$termo}%");
            });
        }

        if ($this->option('zeros')) {
            $q->where('barcode', 'like', '0%');
        }

        $total = (clone $q)->count();
        $apagados = Product::onlyTrashed()->where('tenant_id', $empresa->id)->count();

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(sprintf(' artigos vivos no total: %d   na reciclagem: %d   nesta procura: %d',
            Product::query()->where('tenant_id', $empresa->id)->count(), $apagados, $total));
        $this->newLine();

        $this->table(
            ['id', 'barcode', 'code', 'sku', 'nome', 'compra', 'venda'],
            $q->limit((int) $this->option('limite'))->get()
                ->map(fn ($p) => [
                    $p->id,
                    // Entre plicas para se ver se há zeros à frente ou espaços.
                    "'" . $p->barcode . "'",
                    "'" . $p->code . "'",
                    "'" . $p->sku . "'",
                    mb_substr((string) $p->name, 0, 34),
                    $p->cost,
                    $p->price,
                ])->all()
        );

        return self::SUCCESS;
    }
}
