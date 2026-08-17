<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Cria utilizadores de uma empresa com um papel, a partir de uma lista de nomes.
 *
 * O email serve de nome de utilizador. Quando não há emails reais — o caso das
 * caixas de uma loja — gera-se um interno a partir do nome; entra-se com ele na
 * mesma, mas a recuperação de palavra-passe por email não funciona, e quem
 * esquecer tem de pedir a quem gere.
 *
 * CADA UM LEVA A SUA PALAVRA-PASSE, gerada aqui e diferente das outras. Uma
 * palavra-passe partilhada faz com que as vendas no POS fiquem atribuídas à
 * pessoa errada, e depois não há como saber quem fez o quê.
 *
 * SIMULAÇÃO POR OMISSÃO. Sem --aplicar diz o que faria e não cria ninguém —
 * as palavras-passe só aparecem quando se grava, e aparecem UMA vez.
 *
 *   php artisan utilizadores:criar --tenant=57 --papel=Caixa --dominio=kienga.local \
 *       --nomes="Judite Victoriano;Rosa Recruta;Josefa Chipata;Maria Clemente"
 */
class CriarUtilizadoresDaEmpresa extends Command
{
    protected $signature = 'utilizadores:criar
                            {--tenant= : id da empresa}
                            {--papel=Caixa : nome do papel a atribuir}
                            {--nomes= : nomes separados por ponto e vírgula}
                            {--ficheiro= : ficheiro de texto com um nome por linha}
                            {--dominio= : domínio do email gerado (ex: kienga.local)}
                            {--aplicar : cria (sem isto é simulação)}';

    protected $description = 'Cria utilizadores de uma empresa com um papel (simulação por omissão)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        // Um nome por linha, num ficheiro. Nomes têm espaços, e a rota de
        // manutenção parte os argumentos nos espaços — por ficheiro não há
        // aspas nem codificação para acertar.
        $nomes = [];
        $ficheiro = (string) $this->option('ficheiro');

        if ($ficheiro !== '') {
            if (!is_file($ficheiro) && is_file(base_path($ficheiro))) {
                $ficheiro = base_path($ficheiro);
            }

            if (!is_file($ficheiro)) {
                $this->error('Ficheiro não encontrado: ' . $ficheiro);

                return self::FAILURE;
            }

            $nomes = preg_split('/\R/', (string) file_get_contents($ficheiro)) ?: [];
        } else {
            $nomes = explode(';', (string) $this->option('nomes'));
        }

        $nomes = array_values(array_filter(array_map('trim', $nomes)));

        if (!$nomes) {
            $this->error('Sem nomes. Use --ficheiro=... (um por linha) ou --nomes="Um Nome;Outro".');

            return self::FAILURE;
        }

        $dominio = trim((string) $this->option('dominio')) ?: 'local';
        $papel = trim((string) $this->option('papel'));

        // O papel é por empresa: o Spatie guarda tenant_id na própria linha.
        $role = Role::where('name', $papel)
            ->where(fn ($q) => $q->where('tenant_id', $empresa->id)->orWhereNull('tenant_id'))
            ->orderByRaw('tenant_id IS NULL')   // o da empresa ganha ao global
            ->first();

        if (!$role) {
            $this->error("A empresa não tem o papel \"{$papel}\". Papéis disponíveis:");

            foreach (Role::where('tenant_id', $empresa->id)->pluck('name') as $n) {
                $this->line('   - ' . $n);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(' Papel: ' . $role->name . ' (#' . $role->id . ')   Utilizadores: ' . count($nomes));
        $this->line($aplicar ? ' MODO: --aplicar — VAI CRIAR.' : ' MODO: simulação — ninguém é criado.');
        $this->line(str_repeat('=', 62));

        $planeados = [];

        foreach ($nomes as $nome) {
            $email = $this->email($nome, $dominio);
            $existe = User::where('email', $email)->exists();

            $planeados[] = ['nome' => $nome, 'email' => $email, 'existe' => $existe];

            $this->line(sprintf('  %-22s %-38s %s', $nome, $email, $existe ? '← JÁ EXISTE, fica de fora' : ''));
        }

        $novos = array_values(array_filter($planeados, fn ($p) => !$p['existe']));

        $this->newLine();
        $this->line(sprintf('  a criar: %d   já existem: %d', count($novos), count($planeados) - count($novos)));

        if (!$aplicar) {
            $this->newLine();
            $this->warn('  Nada foi criado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        if (!$novos) {
            $this->warn('  Não há ninguém para criar.');

            return self::SUCCESS;
        }

        $criados = [];

        foreach ($novos as $p) {
            // Cada um na sua transacção: se um falhar, os outros ficam feitos
            // e repetir o comando trata só do que faltou.
            DB::transaction(function () use ($p, $empresa, $role, &$criados) {
                $senha = $this->senha();

                $user = User::create([
                    'name'      => $p['nome'],
                    'email'     => $p['email'],
                    'password'  => Hash::make($senha),
                    'tenant_id' => $empresa->id,
                    'is_active' => true,
                ]);

                $user->tenants()->syncWithoutDetaching([
                    $empresa->id => [
                        'role_id'   => $role->id,
                        'is_active' => true,
                        'joined_at' => now(),
                    ],
                ]);

                // O Spatie é multi-equipa: sem dizer de que empresa se trata,
                // o papel ia parar à equipa errada ou a nenhuma.
                setPermissionsTeamId($empresa->id);
                $user->assignRole($role);

                $criados[] = ['nome' => $p['nome'], 'email' => $p['email'], 'senha' => $senha];
            });
        }

        $this->newLine();
        $this->info('  ✓ ' . count($criados) . ' utilizadores criados.');
        $this->newLine();
        $this->warn('  AS PALAVRAS-PASSE APARECEM SÓ AGORA. Guarde-as antes de fechar.');
        $this->newLine();

        $this->table(
            ['nome', 'email (entra com este)', 'palavra-passe'],
            array_map(fn ($c) => [$c['nome'], $c['email'], $c['senha']], $criados)
        );

        $this->warn('  Cada uma deve mudar a sua no primeiro acesso.');

        return self::SUCCESS;
    }

    /** primeiro.ultimo@dominio, sem acentos e sem repetir. */
    private function email(string $nome, string $dominio): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $primeiro = Str::slug((string) reset($partes), '');
        $ultimo = count($partes) > 1 ? Str::slug((string) end($partes), '') : '';

        $base = $ultimo !== '' ? "{$primeiro}.{$ultimo}" : $primeiro;

        return $base . '@' . $dominio;
    }

    /**
     * Legível ao telefone e forte à mesma.
     *
     * Sem letras e algarismos que se confundem — O/0, I/l/1 — porque isto vai
     * ser lido em voz alta ou copiado de um papel, e uma palavra-passe que se
     * engana ao escrever acaba num post-it colado ao monitor.
     */
    private function senha(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyz';
        $maius = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $numeros = '23456789';

        $s = $maius[random_int(0, strlen($maius) - 1)];

        for ($i = 0; $i < 6; $i++) {
            $s .= $letras[random_int(0, strlen($letras) - 1)];
        }

        for ($i = 0; $i < 3; $i++) {
            $s .= $numeros[random_int(0, strlen($numeros) - 1)];
        }

        return $s;
    }
}
