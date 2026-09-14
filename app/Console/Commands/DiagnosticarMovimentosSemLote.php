<?php

namespace App\Console\Commands;

use App\Models\Invoicing\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * OS MOVIMENTOS DE TRANSFERÊNCIA E AJUSTE SEM LOTE — só lê.
 *
 * A lista das transferências agrupa por (reference_id, reference_type). Os
 * movimentos sem nenhum dos dois (a transferência e o ajuste de UM artigo no
 * ecrã do stock, a importação de stock inicial) caíam todos no mesmo grupo e
 * apareciam como uma única «transferência» com milhares de artigos. Isto diz
 * de onde vêm antes de se mexer na lista.
 */
class DiagnosticarMovimentosSemLote extends Command
{
    protected $signature = 'stock:movimentos-sem-lote
        {--empresa= : Só esta empresa (id)}
        {--ultimos=25 : Quantos dos mais recentes listar}';

    protected $description = 'Mostra os movimentos de transferência/ajuste sem lote que a lista junta num grupo só (só lê)';

    public function handle(): int
    {
        $empresa = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        $base = fn () => StockMovement::withoutGlobalScopes()
            ->whereIn('invoicing_stock_movements.type', ['transfer', 'adjustment'])
            ->whereNull('invoicing_stock_movements.batch_reference')
            ->when($empresa !== null, fn ($q) => $q->where('invoicing_stock_movements.tenant_id', $empresa));

        $this->info('Por empresa, tipo e origem (reference_type):');
        $this->table(
            ['Empresa', 'Tipo', 'reference_type', 'Com reference_id', 'Movimentos', 'Primeiro', 'Último'],
            $base()->select('tenant_id', 'type', DB::raw("COALESCE(reference_type, '(nulo)') as origem"),
                DB::raw('SUM(reference_id IS NOT NULL) as com_id'), DB::raw('COUNT(*) as n'),
                DB::raw('MIN(created_at) as primeiro'), DB::raw('MAX(created_at) as ultimo'))
                ->groupBy('tenant_id', 'type', 'origem')->orderBy('tenant_id')->orderByDesc('n')->get()
                ->map(fn ($l) => [$l->tenant_id, $l->type, $l->origem, $l->com_id, $l->n, $l->primeiro, $l->ultimo])->all()
        );

        $this->newLine();
        $this->info('O grupo que a lista junta (reference_id E reference_type nulos), por dia e utilizador:');
        $this->table(
            ['Empresa', 'Dia', 'Utilizador', 'Tipo', 'Movimentos', 'Unidades'],
            $base()->whereNull('invoicing_stock_movements.reference_id')->whereNull('invoicing_stock_movements.reference_type')
                ->leftJoin('users', 'users.id', '=', 'invoicing_stock_movements.user_id')
                ->select('invoicing_stock_movements.tenant_id', DB::raw('DATE(invoicing_stock_movements.created_at) as dia'),
                    DB::raw("COALESCE(users.name, '(sem utilizador)') as quem"), 'invoicing_stock_movements.type',
                    DB::raw('COUNT(*) as n'), DB::raw('SUM(ABS(invoicing_stock_movements.quantity)) as unidades'))
                ->groupBy('invoicing_stock_movements.tenant_id', 'dia', 'quem', 'invoicing_stock_movements.type')
                ->orderByDesc('dia')->limit(40)->get()
                ->map(fn ($l) => [$l->tenant_id, $l->dia, $l->quem, $l->type, $l->n, round((float) $l->unidades, 2)])->all()
        );

        $this->newLine();
        $this->info('Os ' . (int) $this->option('ultimos') . ' mais recentes:');
        $this->table(
            ['Id', 'Empresa', 'Quando', 'Quem', 'Tipo', 'Artigo', 'Armazém', 'De → Para', 'Qtd', 'Antes→Depois', 'Notas'],
            $base()->with(['product' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()->select('id', 'name'), 'user:id,name'])
                ->orderByDesc('id')->limit(max(1, (int) $this->option('ultimos')))->get()
                ->map(fn ($m) => [
                    $m->id, $m->tenant_id, (string) $m->created_at, $m->user?->name, $m->type,
                    mb_strimwidth((string) $m->product?->name, 0, 30, '…'), $m->warehouse_id,
                    ($m->from_warehouse_id ?? '-') . '→' . ($m->to_warehouse_id ?? '-'), (float) $m->quantity,
                    ($m->balance_before ?? '-') . '→' . ($m->balance_after ?? '-'), mb_strimwidth((string) $m->notes, 0, 40, '…'),
                ])->all()
        );

        return self::SUCCESS;
    }
}
