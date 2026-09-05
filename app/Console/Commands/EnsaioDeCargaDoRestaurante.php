<?php

namespace App\Console\Commands;

use App\Models\Restaurant\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A sala inteira a sincronizar ao mesmo tempo.
 *
 * É exactamente o que acontece quando a rede volta depois de uma falha: cada
 * aparelho tem comandas guardadas e todos as despejam no mesmo segundo. O
 * módulo nunca tinha sido medido nesse momento — e é o momento em que as
 * coisas se partem: números de comanda em duplicado, a mesma mesa dada a dois,
 * o mesmo `local_uuid` a virar duas facturas.
 *
 * O ensaio dispara N comandas EM PARALELO a sério (Http::pool) contra um
 * servidor local, com as três maldades típicas lá dentro:
 *
 *   · comandas repetidas (o mesmo local_uuid duas vezes — retry do aparelho);
 *   · várias comandas à MESMA mesa (duas pessoas a sincronizar a mesma sala);
 *   · o resto ao balcão, como uma enchente normal.
 *
 * E depois confere o que não pode ter acontecido. Devolve FAILURE se
 * aconteceu.
 *
 * SÓ CORRE EM LOCAL: é um ensaio de carga, não se aponta a produção.
 *
 * Uso:
 *   php artisan serve --port=8321       (noutro terminal)
 *   php artisan restaurante:ensaio-carga --token=<token da bancada> --comandas=40
 *
 * O token é o Bearer da API (tabela api_tokens) de um utilizador da empresa
 * de bancada com turno aberto — o `bancada:pwa` deixa tudo pronto.
 */
class EnsaioDeCargaDoRestaurante extends Command
{
    protected $signature = 'restaurante:ensaio-carga
                            {--base=http://127.0.0.1:8321 : Onde está o servidor}
                            {--token= : Bearer da API do utilizador de bancada}
                            {--comandas=30 : Quantas comandas na enchente}';

    protected $description = 'Dispara uma sala inteira de comandas em paralelo e confere o que não pode acontecer (só local)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Ensaio de carga só em local. Em produção isto é um ataque ao próprio servidor.');

            return self::FAILURE;
        }

        $base = rtrim((string) $this->option('base'), '/');
        $token = (string) $this->option('token') ?: $this->tokenDaBancada();
        $quantas = max(5, (int) $this->option('comandas'));

        if (! $token) {
            $this->error('Sem token e sem bancada — corra o bancada:pwa primeiro, ou passe --token.');

            return self::FAILURE;
        }

        // O terreno: o snapshot diz as mesas e os pratos que existem.
        $snapshot = Http::withToken($token)->acceptJson()->get($base.'/api/v1/restaurant/snapshot');

        if (! $snapshot->ok()) {
            $this->error('O snapshot falhou ('.$snapshot->status().') — servidor ligado? token certo? módulo activo?');

            return self::FAILURE;
        }

        $mesas = collect($snapshot->json('data.tables') ?? $snapshot->json('tables') ?? []);
        $pratos = collect($snapshot->json('data.products') ?? $snapshot->json('products') ?? []);
        $salas = collect($snapshot->json('data.venues') ?? $snapshot->json('venues') ?? []);

        if ($pratos->isEmpty() || $salas->isEmpty()) {
            $this->error('A empresa de bancada não tem pratos ou salas — corra o bancada:pwa primeiro.');

            return self::FAILURE;
        }

        $salaId = $salas->first()['id'];

        $this->line('Salas: '.$salas->count().'  ·  Mesas: '.$mesas->count().'  ·  Pratos: '.$pratos->count());

        // ── A enchente ───────────────────────────────────────────────────
        //
        // A mesma mesa de propósito em DUAS comandas, e DOIS pares com o
        // mesmo local_uuid (o retry). O resto ao balcão.
        $pedidos = [];
        $uuidRepetido = (string) Str::uuid();
        $mesaDisputada = $mesas->first()['id'] ?? null;

        for ($i = 0; $i < $quantas; $i++) {
            $prato = $pratos[$i % $pratos->count()];

            $pedidos[] = [
                'venue_id' => $salaId,
                'local_uuid' => match (true) {
                    $i === 1, $i === 2 => $uuidRepetido, // o retry: o MESMO uuid duas vezes
                    default => (string) Str::uuid(),
                },
                'table_id' => match (true) {
                    $i === 3, $i === 4 => $mesaDisputada, // a mesa disputada
                    default => null,
                },
                'guest_count' => 1,
                'items' => [[
                    'local_uuid' => (string) Str::uuid(),
                    'product_id' => $prato['id'],
                    'quantity' => 1,
                ]],
                'confirmar' => true,
            ];
        }

        $this->line('A disparar '.count($pedidos).' comandas em paralelo...');

        $inicio = microtime(true);

        $respostas = Http::pool(fn ($pool) => collect($pedidos)->map(
            fn ($corpo) => $pool->withToken($token)->acceptJson()->timeout(60)
                ->post($base.'/api/v1/restaurant/offline/comanda', $corpo)
        )->all());

        $duracao = microtime(true) - $inicio;

        // ── As contas ────────────────────────────────────────────────────
        $ok = $recusadas = $rebentadas = 0;
        $tempos = [];

        foreach ($respostas as $r) {
            if ($r instanceof \Throwable) {
                $rebentadas++;

                continue;
            }

            $tempos[] = $r->transferStats?->getTransferTime() ?? 0;

            match (true) {
                $r->successful() => $ok++,
                $r->status() >= 500 => $rebentadas++,
                default => $recusadas++,
            };
        }

        sort($tempos);
        $p95 = $tempos ? $tempos[(int) floor(count($tempos) * 0.95)] : 0;

        $this->newLine();
        $this->line(sprintf('<options=bold>%d comandas em %.1fs</> — %.1f/s', count($pedidos), $duracao, count($pedidos) / max(0.001, $duracao)));
        $this->line(sprintf('aceites: %d  ·  recusadas (4xx): %d  ·  rebentadas (5xx/erro): %d', $ok, $recusadas, $rebentadas));
        $this->line(sprintf('p95 por pedido: %.2fs', $p95));
        $this->newLine();

        // ── O que NÃO pode ter acontecido ────────────────────────────────
        $problemas = [];

        // 0. UM ENSAIO EM QUE NADA ENTROU NÃO MEDIU NADA. Na primeira corrida
        // disto, as 40 comandas foram TODAS recusadas (o CSRF barrava a API
        // inteira ao Bearer) e o ensaio declarou vitória: «nada duplicou» —
        // pois, não entrou nada. As recusas de propósito são 3 (o retry
        // repetido e a mesa disputada); muito mais do que isso é o ensaio a
        // medir a recusa em vez da carga.
        if ($ok < $quantas - 5) {
            $problemas[] = "só {$ok} de {$quantas} comandas entraram — o ensaio não mediu carga nenhuma; veja uma recusa: "
                .(collect($respostas)->first(fn ($r) => ! $r instanceof \Throwable && ! $r->successful())?->body() ?? '?');
        }

        // 1. Um 500 é sempre um problema: recusar faz parte, rebentar não.
        if ($rebentadas > 0) {
            $problemas[] = "{$rebentadas} pedido(s) rebentaram com 5xx — sob carga, o servidor tem de recusar com jeito, nunca cair.";
        }

        // 2. O retry não pode virar duas comandas.
        $tenantId = $this->tenantDoToken($token, $base);

        if ($tenantId) {
            $doRetry = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('local_uuid', $uuidRepetido)
                ->count();

            if ($doRetry > 1) {
                $problemas[] = "o mesmo local_uuid virou {$doRetry} comandas — o retry de um aparelho duplica vendas.";
            }

            // 3. Números de comanda sem repetidos.
            $duplicados = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->selectRaw('order_number, COUNT(*) c')
                ->groupBy('order_number')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicados > 0) {
                $problemas[] = "{$duplicados} número(s) de comanda em duplicado — a sequência não aguentou o paralelo.";
            }

            // 4. A mesa disputada ficou com UMA comanda aberta, não duas.
            if ($mesaDisputada) {
                $naMesa = Order::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('table_id', $mesaDisputada)
                    ->whereIn('status', Order::OPEN_STATUSES)
                    ->count();

                if ($naMesa > 1) {
                    $problemas[] = "a mesa disputada ficou com {$naMesa} comandas abertas — dois empregados, a mesma mesa, duas contas.";
                }
            }
        } else {
            $this->warn('Não consegui ler a empresa do token — as contas de base de dados ficaram por conferir.');
        }

        if ($problemas) {
            foreach ($problemas as $p) {
                $this->error('✗ '.$p);
            }

            return self::FAILURE;
        }

        $this->info('A enchente passou: nada rebentou, nada duplicou, a mesa disputada ficou com uma conta só.');

        return self::SUCCESS;
    }

    /** A empresa por trás do token, para conferir a base de dados por dentro. */
    private function tenantDoToken(string $token, string $base): ?int
    {
        $eu = Http::withToken($token)->acceptJson()->get($base.'/api/v1/auth/me');

        return $eu->ok() ? ($eu->json('user.tenant_id') ?? $eu->json('tenant_id')) : null;
    }

    /**
     * Um token acabado de cunhar para o utilizador da bancada — com o turno
     * dele aberto, porque sem turno o restaurante recusa tudo e o ensaio
     * media a recusa em vez da carga.
     *
     * Só corre em local (o comando já o garantiu), e por isso pode mexer na
     * base à vontade.
     */
    private function tokenDaBancada(): ?string
    {
        $user = \App\Models\User::where('email', PrepararBancadaPwa::EMAIL)->first();

        if (! $user) {
            return null;
        }

        $aberto = \App\Models\Invoicing\PosShift::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->where('status', 'open')
            ->exists();

        if (! $aberto) {
            \App\Models\Invoicing\PosShift::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'shift_number' => 'CARGA-'.strtoupper(substr(uniqid(), -8)),
                'opened_at' => now(),
                'opening_balance' => 0,
                'status' => 'open',
            ]);
        }

        $plano = 'carga-'.Str::random(40);

        \App\Models\ApiToken::create([
            'user_id' => $user->id,
            'name' => 'ensaio de carga',
            'token' => \App\Models\ApiToken::hashToken($plano),
        ]);

        $this->line('Token cunhado para a bancada ('.$user->email.').');

        return $plano;
    }
}
