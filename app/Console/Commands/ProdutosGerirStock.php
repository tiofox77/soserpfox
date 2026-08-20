<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Liga o "Gerenciar Stock" nos artigos onde ele devia estar ligado.
 *
 * PORQUE É QUE ISTO É PRECISO
 * ---------------------------
 * O formulário de artigos nasce com a caixa "Gerenciar Stock" DESMARCADA
 * (App\Livewire\Invoicing\Products::$manage_stock = false). Quem cria um
 * artigo e não repara na caixa fica com um artigo que não conta stock — e
 * nada no ecrã o avisa. Numa farmácia, isso está errado para praticamente
 * todos os artigos: vende-se, e o stock não desce.
 *
 * O sintoma só aparece semanas depois, quando as contagens não batem certo e
 * já não se sabe quais foram os artigos afectados.
 *
 * O QUE ISTO NÃO FAZ, E É IMPORTANTE
 * ----------------------------------
 * Ligar a bandeira NÃO INVENTA HISTÓRICO. Um artigo que esteve meses a ser
 * vendido sem contar stock não tem movimentos nesse período, e ligar isto
 * agora não os cria: o stock passa a contar A PARTIR DE AGORA, a partir do
 * valor que lá estiver. Depois de correr isto é preciso uma contagem física
 * dos artigos afectados.
 *
 * Não toca no `stock_quantity` de propósito — as linhas de stock por armazém
 * são a fonte de verdade e o agregado é mantido pelo StockObserver. Escrever
 * aqui o agregado à mão punha-o a discordar das linhas.
 *
 * SERVIÇOS NUNCA SÃO TOCADOS
 * --------------------------
 * Um serviço não tem stock, e ligar-lhe a bandeira faria o POS passar a
 * recusá-lo por "esgotado" — que é exactamente o problema oposto, e já
 * aconteceu nesta casa com os serviços do salão.
 */
class ProdutosGerirStock extends Command
{
    protected $signature = 'produtos:gerir-stock
        {--tenant= : id da empresa (obrigatório para aplicar)}
        {--todas : percorre todas as empresas — só em simulação}
        {--so-com-indicios : apenas os que já dão sinais de ser físicos}
        {--aplicar : grava; sem isto é apenas simulação}
        {--limite=40 : quantos artigos mostrar na lista}';

    protected $description = 'Liga o "Gerenciar Stock" nos artigos que o deviam ter (simulação por omissão)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $aplicar = (bool) $this->option('aplicar');

        if (!$tenantId && !$this->option('todas')) {
            $this->error('Indique --tenant=<id> ou --todas (esta última só simula).');

            return self::FAILURE;
        }

        // Aplicar a TODAS as empresas de uma vez seria mexer no catálogo de
        // clientes diferentes com uma decisão só. Cada empresa é uma decisão.
        if ($aplicar && !$tenantId) {
            $this->error('--aplicar exige --tenant=<id>. Uma empresa de cada vez.');

            return self::FAILURE;
        }

        if ($tenantId) {
            return $this->umaEmpresa($tenantId, $aplicar);
        }

        return $this->panorama();
    }

    // ── o panorama, para saber onde dói ──────────────────────────────────────

    private function panorama(): int
    {
        $linhas = DB::table('invoicing_products')
            ->select(
                'tenant_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN type = 'servico' THEN 1 ELSE 0 END) as servicos"),
                DB::raw("SUM(CASE WHEN type <> 'servico' AND manage_stock = 1 THEN 1 ELSE 0 END) as com"),
                DB::raw("SUM(CASE WHEN type <> 'servico' AND manage_stock = 0 THEN 1 ELSE 0 END) as sem")
            )
            ->groupBy('tenant_id')
            ->orderByDesc('sem')
            ->get();

        $nomes = Tenant::pluck('name', 'id');

        $this->table(
            ['empresa', 'artigos', 'serviços', 'com stock', 'SEM stock'],
            $linhas->map(fn ($l) => [
                '#' . $l->tenant_id . ' ' . mb_strimwidth((string) ($nomes[$l->tenant_id] ?? '—'), 0, 30, '…'),
                $l->total,
                $l->servicos,
                $l->com,
                $l->sem > 0 ? "<comment>{$l->sem}</comment>" : '0',
            ])->all()
        );

        $this->newLine();
        $this->line('Para ver o detalhe de uma: <info>produtos:gerir-stock --tenant=57</info>');
        $this->line('Para corrigir:            <info>produtos:gerir-stock --tenant=57 --aplicar</info>');

        return self::SUCCESS;
    }

    // ── uma empresa ──────────────────────────────────────────────────────────

    private function umaEmpresa(int $tenantId, bool $aplicar): int
    {
        $empresa = Tenant::find($tenantId);

        if (!$empresa) {
            $this->error("Não existe a empresa #{$tenantId}.");

            return self::FAILURE;
        }

        $this->line("Empresa: <info>{$empresa->name}</info> (#{$empresa->id})");

        $candidatos = $this->candidatos($tenantId);

        if ($candidatos->isEmpty()) {
            $this->info('Nada a corrigir: todos os artigos físicos já contam stock.');

            return self::SUCCESS;
        }

        $limite = (int) $this->option('limite');

        $this->table(
            ['id', 'código', 'nome', 'tipo', 'qtd', 'indícios'],
            $candidatos->take($limite)->map(fn ($p) => [
                $p->id,
                mb_strimwidth((string) ($p->code ?: $p->sku ?: '—'), 0, 14, '…'),
                mb_strimwidth((string) $p->name, 0, 40, '…'),
                $p->type,
                rtrim(rtrim(number_format((float) $p->stock_quantity, 3, ',', ''), '0'), ','),
                $p->indicios ?: '—',
            ])->all()
        );

        if ($candidatos->count() > $limite) {
            $this->line('… e mais ' . ($candidatos->count() - $limite) . ' (ver --limite=).');
        }

        $this->newLine();
        $this->line("Artigos a marcar: <comment>{$candidatos->count()}</comment>");

        if (!$aplicar) {
            $this->warn('SIMULAÇÃO — nada foi gravado. Acrescente --aplicar para gravar.');

            return self::SUCCESS;
        }

        $mudados = $this->marcar($candidatos);

        $this->info("{$mudados} artigo(s) passaram a contar stock.");
        $this->newLine();

        // A parte que ninguém pode ignorar.
        $this->warn('ATENÇÃO: ligar a bandeira NÃO cria o histórico que faltou.');
        $this->line('  Estes artigos foram vendidos sem descontar stock, portanto a');
        $this->line('  quantidade que lá está não corresponde ao que há na prateleira.');
        $this->line('  A partir de agora conta; para acertar o ponto de partida é');
        $this->line('  preciso uma <info>contagem física</info> (Inventário → Gestão de Stock).');

        return self::SUCCESS;
    }

    /**
     * Os artigos que deviam contar stock e não contam.
     *
     * Serviços ficam sempre de fora: ligar-lhes a bandeira faria o POS passar
     * a recusá-los por "esgotado".
     */
    private function candidatos(int $tenantId)
    {
        $q = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('type', '<>', 'servico')
            ->where(function ($q) {
                $q->where('manage_stock', false)->orWhereNull('manage_stock');
            })
            // O lote implica stock: um artigo com lotes já está a ser contado
            // por outra via e a bandeira só está a mentir sobre isso.
            ->orderBy('id');

        $produtos = $q->get(['id', 'code', 'sku', 'name', 'type', 'stock_quantity', 'cost', 'supplier_id', 'track_batches']);

        // Indícios de que é mesmo um artigo físico. Servem para explicar a
        // decisão a quem lê a lista, e para o modo conservador.
        $comLinhas = DB::table('invoicing_stocks')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $produtos->pluck('id'))
            ->pluck('product_id')->unique()->flip();

        $comMovimentos = DB::table('invoicing_stock_movements')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $produtos->pluck('id'))
            ->pluck('product_id')->unique()->flip();

        $produtos->each(function ($p) use ($comLinhas, $comMovimentos) {
            $sinais = [];

            if (isset($comLinhas[$p->id])) {
                $sinais[] = 'linhas';
            }

            if (isset($comMovimentos[$p->id])) {
                $sinais[] = 'movimentos';
            }

            if ((float) $p->stock_quantity != 0.0) {
                $sinais[] = 'qtd';
            }

            if ($p->track_batches) {
                $sinais[] = 'lotes';
            }

            if ((float) $p->cost > 0) {
                $sinais[] = 'custo';
            }

            if ($p->supplier_id) {
                $sinais[] = 'fornecedor';
            }

            $p->indicios = implode('+', $sinais);
        });

        if ($this->option('so-com-indicios')) {
            return $produtos->filter(fn ($p) => $p->indicios !== '')->values();
        }

        return $produtos;
    }

    /**
     * Grava a bandeira, e SÓ a bandeira.
     *
     * Nada de `stock_quantity` aqui: as linhas por armazém são a fonte de
     * verdade e o agregado é mantido pelo StockObserver. Escrever o agregado à
     * mão punha-o a discordar das linhas — o erro que já custou uma
     * reconciliação nesta casa.
     */
    private function marcar($candidatos): int
    {
        $ids = $candidatos->pluck('id')->all();
        $mudados = 0;

        foreach (array_chunk($ids, 500) as $lote) {
            $mudados += Product::whereIn('id', $lote)->update([
                'manage_stock' => true,
                'updated_at'   => now(),
            ]);
        }

        return $mudados;
    }
}
