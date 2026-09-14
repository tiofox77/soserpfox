<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenants\SinaisDeVida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DE ONDE VEM O QUE O CARTÃO DA EMPRESA DIZ — só lê.
 *
 * A lista de empresas da plataforma diz «entrou há 4h», «0 facturas/30d» e
 * arruma a empresa num de cinco estados. Quando o cartão contradiz o que se
 * sabe da empresa, a pergunta é sempre a mesma: que linha da base deu aquele
 * número? Isto mostra-as — o relógio do PHP e o da base, cada pessoa do pivot
 * com a sua última entrada em bruto, as sessões, e cada tipo de documento dos
 * últimos 30 dias. Sem emails: ids chegam para seguir o fio.
 */
class SinaisDeVidaDasEmpresas extends Command
{
    protected $signature = 'plataforma:sinais {--tenant= : id da empresa (sem ele: o resumo de todas)}';

    protected $description = 'Mostra em bruto de onde vêm os sinais de vida do cartão da empresa na plataforma (só lê)';

    public function handle(): int
    {
        $this->relogios();

        return $this->option('tenant') ? $this->umaEmpresa((int) $this->option('tenant')) : $this->todas();
    }

    private function relogios(): void
    {
        $db = DB::selectOne('SELECT NOW() AS agora, @@session.time_zone AS sessao, @@global.time_zone AS global, @@system_time_zone AS sistema');
        $this->info('Relógios');
        $this->line(sprintf('  PHP: %s (%s) · MySQL NOW(): %s · fuso da sessão: %s · global: %s · sistema: %s',
            now()->format('Y-m-d H:i:s'), config('app.timezone'), $db->agora, $db->sessao, $db->global, $db->sistema));

        $tipos = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn(DB::raw("CONCAT(table_name, '.', column_name)"), [
                'users.last_login_at', 'users.created_at', 'tenants.created_at', 'tenant_user.joined_at',
                'invoicing_sales_invoices.created_at', 'audit_trail.created_at',
            ])
            // MySQL 8 devolve estes nomes em maiúsculas: o alias fixa-os.
            ->get([DB::raw('table_name AS t'), DB::raw('column_name AS c'), DB::raw('data_type AS d')])
            ->map(fn ($c) => "{$c->t}.{$c->c}={$c->d}")->implode(' · ');
        $this->line("  colunas: {$tipos}");
    }

    private function todas(): int
    {
        $empresas = Tenant::query()->orderBy('id')->get(['id', 'name', 'created_at', 'is_active']);
        $sinais = SinaisDeVida::para($empresas->pluck('id'));

        // Quem pertence a várias empresas: a última entrada é da PESSOA, não da
        // empresa — conta em todas as empresas dela.
        $partilhados = DB::table('tenant_user')->select('user_id', DB::raw('COUNT(*) AS n'))
            ->groupBy('user_id')->having('n', '>', 1)->pluck('n', 'user_id');
        $daPlataforma = User::query()->whereIn('id', DB::table('tenant_user')->distinct()->pluck('user_id'))
            ->get()->filter(fn (User $u) => method_exists($u, 'isPlatformSuperAdmin') && $u->isPlatformSuperAdmin())
            ->pluck('id')->flip();
        $pivot = DB::table('tenant_user')->get(['tenant_id', 'user_id'])->groupBy('tenant_id');
        $sessoes = Schema::hasTable('sessions')
            ? DB::table('sessions')->join('tenant_user', 'tenant_user.user_id', '=', 'sessions.user_id')
                ->groupBy('tenant_user.tenant_id')->select('tenant_user.tenant_id', DB::raw('MAX(sessions.last_activity) AS ultima'))
                ->pluck('ultima', 'tenant_id')
            : collect();

        $this->newLine();
        $this->info(sprintf('%d empresas · pessoas em várias empresas: %d · super admins da plataforma em pivots: %d',
            $empresas->count(), $partilhados->count(), $daPlataforma->count()));

        $porEstado = $empresas->groupBy(fn ($t) => $sinais[$t->id]->estado['chave'] ?? '?');
        foreach ($porEstado as $estado => $lista) {
            $this->newLine();
            $this->info(strtoupper($estado) . " ({$lista->count()})");
            foreach ($lista as $t) {
                $s = $sinais[$t->id];
                $membros = collect($pivot[$t->id] ?? []);
                $marcas = [];
                if ($membros->contains(fn ($m) => isset($daPlataforma[$m->user_id]))) {
                    $marcas[] = 'PLATAFORMA-NO-PIVOT';
                }
                if ($membros->contains(fn ($m) => isset($partilhados[$m->user_id]))) {
                    $marcas[] = 'PESSOA-PARTILHADA';
                }
                $this->line(sprintf('  #%d %s · criada %s%s · fact %d · art %d · mov %d · entraram %d/%d · última entrada %s · sessão %s %s',
                    $t->id, mb_strimwidth((string) $t->name, 0, 32, '…'), $t->created_at?->format('Y-m-d H:i'), $t->is_active ? '' : ' (DESACTIVADA)',
                    $s->facturas_30d, $s->artigos, $s->movimentos_30d, $s->entraram_30d, $s->utilizadores,
                    $s->ultima_entrada?->format('Y-m-d H:i') ?? '—',
                    isset($sessoes[$t->id]) ? date('Y-m-d H:i', (int) $sessoes[$t->id]) : '—',
                    $marcas ? '[' . implode(' ', $marcas) . ']' : ''));
            }
        }

        return self::SUCCESS;
    }

    private function umaEmpresa(int $id): int
    {
        $t = Tenant::find($id);
        if (! $t) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $cru = DB::table('tenants')->where('id', $id)->value('created_at');
        $this->newLine();
        $this->info("EMPRESA #{$t->id} — {$t->name}");
        $this->line("  created_at em bruto: {$cru} · pelo modelo: {$t->created_at} · activa: " . ($t->is_active ? 'sim' : 'NÃO'));

        $this->newLine();
        $this->info('Pessoas do pivot');
        $membros = DB::table('tenant_user')->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('tenant_user.tenant_id', $id)
            ->get(['users.id', 'users.tenant_id AS origem', 'users.created_at', 'users.last_login_at', 'users.deleted_at', 'tenant_user.is_active', 'tenant_user.joined_at']);
        foreach ($membros as $m) {
            $u = User::find($m->id);
            $empresas = DB::table('tenant_user')->where('user_id', $m->id)->pluck('tenant_id')->implode(',');
            $sessao = Schema::hasTable('sessions') ? DB::table('sessions')->where('user_id', $m->id)->max('last_activity') : null;
            $logins = Schema::hasTable('audit_trail')
                ? DB::table('audit_trail')->where('event', 'login')->where('user_id', $m->id)->orderByDesc('id')->limit(3)
                    ->get(['created_at', 'tenant_id'])->map(fn ($l) => "{$l->created_at}@{$l->tenant_id}")->implode(' ')
                : '';
            $this->line(sprintf('  #%d origem %s · criado %s · last_login_at %s · joined_at %s · pivot activo %s · apagado %s · plataforma %s · empresas [%s] · sessão %s · logins %s',
                $m->id, $m->origem ?? '—', $m->created_at, $m->last_login_at ?? '—', $m->joined_at ?? '—',
                $m->is_active ? 'sim' : 'não', $m->deleted_at ? 'SIM' : 'não',
                $u && method_exists($u, 'isPlatformSuperAdmin') && $u->isPlatformSuperAdmin() ? 'SIM' : 'não',
                $empresas, $sessao ? date('Y-m-d H:i:s', (int) $sessao) : '—', $logins ?: '—'));
        }

        $desde = now()->subDays(30);
        $this->newLine();
        $this->info('Documentos e movimento dos últimos 30 dias (created_at >= ' . $desde->format('Y-m-d H:i') . ')');
        $tabelas = [
            'invoicing_sales_invoices' => ['invoice_type', 'status'],
            'invoicing_sales_proformas' => ['status'],
            'invoicing_sales_quotes' => ['status'],
            'invoicing_receipts' => ['status'],
            'invoicing_credit_notes' => ['status'],
            'invoicing_purchase_invoices' => ['status'],
            'invoicing_stock_movements' => ['type'],
            'invoicing_pos_shifts' => ['status'],
            'invoicing_products' => [],
            'invoicing_clients' => [],
            'treasury_transactions' => [],
            'hotel_reservations' => [],
            'restaurant_orders' => [],
            'salon_appointments' => [],
            'workshop_work_orders' => [],
            'hr_payrolls' => [],
            'crm_leads' => [],
        ];
        foreach ($tabelas as $tabela => $grupos) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'tenant_id')) {
                continue;
            }
            $apagados = Schema::hasColumn($tabela, 'deleted_at');
            $base = fn () => DB::table($tabela)->where('tenant_id', $id)->when($apagados, fn ($q) => $q->whereNull('deleted_at'));
            $total = $base()->count();
            $recentes = $base()->where('created_at', '>=', $desde)->count();
            $grupos = array_values(array_filter($grupos, fn ($c) => Schema::hasColumn($tabela, $c)));
            $detalhe = $grupos
                ? $base()->where('created_at', '>=', $desde)->select(array_merge($grupos, [DB::raw('COUNT(*) AS n')]))->groupBy($grupos)->get()
                    ->map(fn ($l) => implode('/', array_map(fn ($c) => $l->{$c} ?? '∅', $grupos)) . "={$l->n}")->implode(' ')
                : '';
            $ultimo = $base()->max('created_at');
            $this->line(sprintf('  %-30s total %5d · 30d %5d · último %s %s', $tabela, $total, $recentes, $ultimo ?? '—', $detalhe));
        }

        $s = SinaisDeVida::para([$id])[$id];
        $this->newLine();
        $this->info('O que o cartão mostra hoje');
        $this->line(sprintf('  estado %s · facturas_30d %d · artigos %d · movimentos_30d %d · entraram %d/%d · ultima_entrada %s (%s)',
            $s->estado['chave'], $s->facturas_30d, $s->artigos, $s->movimentos_30d, $s->entraram_30d, $s->utilizadores,
            $s->ultima_entrada?->format('Y-m-d H:i:s') ?? '—', $s->ultima_entrada?->diffForHumans(short: true) ?? '—'));

        return self::SUCCESS;
    }
}
