<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plataforma\AvisosDeConta;
use App\Services\Plataforma\TrocarDePlano;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Uma empresa nova, montada por inteiro.
 *
 * PORQUE NÃO SE FAZ COM UM INSERT. Uma empresa não é uma linha na tabela: sem
 * os papéis, sem o plano de contas PGC-AO, sem as definições de RH e sem os
 * modelos de notificação, ela aparece na lista e depois falha ecrã a ecrã —
 * quem lá entra não tem permissões, a contabilidade não abre, e alguns ecrãs
 * ficam em branco. Aqui corre-se a MESMA sequência do painel do super admin,
 * dentro de uma transacção: ou nasce completa, ou não nasce.
 *
 * Em produção não há consola, e este comando é a forma de provisionar uma
 * empresa pelo endereço de manutenção. Sem `--aplicar` mostra o que faria.
 *
 * A EMPRESA INTEIRA, COM DONO (26/09/2026). Com o plano, liga-o pela regra do
 * ecrã de planos (TrocarDePlano + módulos do plano). Com `dono_email`, cria a
 * pessoa que entra, com o papel Super Admin da empresa, e, com
 * `enviar_acessos`, manda-lhe as credenciais pelo modelo `new-user` — a senha
 * é gerada aqui, viaja só nesse email e nunca aparece no ecrã.
 *
 * OS DADOS PESSOAIS NÃO VÃO NO ENDEREÇO. O endereço de manutenção leva os
 * argumentos na query string, que fica nos registos do servidor. O NIF, o
 * email, o telefone e a morada chegam num ficheiro JSON em
 * `storage/app/privado/` (`--ficheiro=`), que se apaga depois de a empresa
 * nascer.
 */
class CriarEmpresa extends Command
{
    protected $signature = 'empresas:criar
                            {--nome= : nome comercial}
                            {--nif= : número de contribuinte}
                            {--email= : email da empresa}
                            {--telefone= : telefone(s)}
                            {--morada= : morada completa}
                            {--cidade=Luanda : cidade}
                            {--provincia= : província}
                            {--municipio= : município}
                            {--bairro= : bairro}
                            {--pais=AO : código ISO do país}
                            {--regime= : regime do IVA (geral, simplificado, exclusao)}
                            {--plano= : id ou slug do plano}
                            {--expira= : fim da subscrição, AAAA-MM-DD}
                            {--dados= : tudo de uma vez, em JSON codificado em base64}
                            {--ficheiro= : ficheiro JSON em storage/app/privado (os dados pessoais fora do endereço)}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Cria uma empresa completa — papéis, contabilidade, RH, notificações, plano e dono — e envia-lhe os acessos';

    /** Os nomes curtos do regime, como se dizem, para o canónico. */
    private const REGIMES_CURTOS = [
        'geral' => Tenant::REGIME_GERAL,
        'simplificado' => Tenant::REGIME_SIMPLIFICADO,
        'exclusao' => Tenant::REGIME_NAO_SUJEICAO,
        'nao_sujeicao' => Tenant::REGIME_NAO_SUJEICAO,
    ];

    /** O que veio no `--dados` ou no `--ficheiro`, para as opções soltas caírem para trás. */
    private array $doBloco = [];

    public function handle(): int
    {
        /*
         * TUDO NUM BLOCO, POR CAUSA DOS ESPAÇOS.
         *
         * Em produção os comandos correm por um endereço HTTP e os argumentos
         * são separados por ESPAÇOS. Uma morada — «Av. Fidel de Castro
         * (sentido Benfica - Kilamba), junto à loja da PEP» — parte-se em
         * quinze pedaços e o comando recebe lixo. Por isso aceita-se também um
         * JSON inteiro em base64: viaja sem um único espaço.
         */
        if ($bloco = $this->option('dados')) {
            $json = json_decode((string) base64_decode($bloco, true), true);

            if (!is_array($json)) {
                $this->error('O --dados não é um JSON válido em base64.');

                return self::FAILURE;
            }

            $this->doBloco = $json;
        }

        $ficheiro = $this->ficheiro();

        if ($ficheiro === false) {
            return self::FAILURE;
        }

        if ($ficheiro !== null) {
            $json = json_decode((string) file_get_contents($ficheiro), true);

            if (!is_array($json)) {
                $this->error('O ficheiro não tem um JSON válido.');

                return self::FAILURE;
            }

            $this->doBloco = $json + $this->doBloco;
        }

        $nome = trim((string) $this->valor('nome'));
        $nif = trim((string) $this->valor('nif'));

        if ($nome === '' || $nif === '') {
            $this->error('Faltam --nome e --nif.');

            return self::FAILURE;
        }

        // O NIF repetido é o sinal de que a empresa já lá está — ou de que
        // alguém a está a criar duas vezes. Ver antes de escrever.
        $jaExiste = Tenant::where('nif', $nif)->get();

        if ($jaExiste->isNotEmpty()) {
            $this->warn('Já há empresa(s) com este NIF:');
            foreach ($jaExiste as $t) {
                $this->line("  #{$t->id}  {$t->name}");
            }
        }

        $plano = $this->resolverPlano();

        if ($this->valor('plano') && !$plano) {
            $this->error('Plano não encontrado. Os que existem:');
            foreach (Plan::orderBy('id')->get() as $p) {
                $this->line("  #{$p->id}  {$p->name}  (slug: {$p->slug})");
            }

            return self::FAILURE;
        }

        $regime = $this->regime();

        if ($regime === false) {
            return self::FAILURE;
        }

        $dono = $this->dono();

        if ($dono === false) {
            return self::FAILURE;
        }

        $expira = $this->valor('expira') ? \Carbon\Carbon::parse($this->valor('expira'))->endOfDay() : null;

        $dados = [
            'name'          => $nome,
            'company_name'  => $nome,
            'slug'          => $this->slugLivre($nome),
            'nif'           => $nif,
            // O regime entra NA criação: é ele que decide os impostos com que
            // a empresa é provisionada (o mesmo que o registo faz).
            'regime'        => $regime,
            'email'         => $this->valor('email') ?: null,
            'phone'         => $this->valor('telefone') ?: null,
            'address'       => $this->valor('morada') ?: null,
            'city'          => $this->valor('cidade') ?: null,
            'province'      => $this->valor('provincia') ?: null,
            'municipality'  => $this->valor('municipio') ?: null,
            'neighbourhood' => $this->valor('bairro') ?: null,
            'country'       => $this->valor('pais') ?: 'AO',
            'max_users'     => (int) ($plano->max_users ?? 3),
            'max_storage_mb' => (int) ($plano->max_storage_mb ?? 1024),
            'is_active'     => true,
        ];

        if ($expira) {
            $dados['subscription_ends_at'] = $expira;
        }

        $enviarAcessos = $dono !== null && filter_var($this->valor('enviar_acessos') ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->line('<options=bold>EMPRESA A CRIAR</>');
        foreach ($dados as $campo => $valor) {
            if ($valor === null || $valor === '') continue;
            $this->line(sprintf('  %-16s %s', $campo, is_bool($valor) ? ($valor ? 'sim' : 'não') : $valor));
        }
        $this->line(sprintf('  %-16s %s', 'plano', $plano ? $plano->name . ' (#' . $plano->id . ')' : '(nenhum)'));
        $this->line(sprintf('  %-16s %s', 'expira', $expira ? $expira->format('d/m/Y') : '(sem fim definido)'));
        $this->line(sprintf('  %-16s %s', 'dono', $dono ? $dono['nome'] . ' <' . $dono['email'] . '> — Super Admin' : '(nenhum)'));
        $this->line(sprintf('  %-16s %s', 'acessos', $enviarAcessos ? 'enviados por email ao dono (senha gerada, não aparece aqui)' : 'não se enviam'));

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('SIMULAÇÃO. Repita com --aplicar para gravar.');

            return self::SUCCESS;
        }

        // Ou nasce completa, ou não nasce: empresa, plano, módulos e dono.
        [$empresa, $pessoa, $senha, $subscricao] = DB::transaction(function () use ($dados, $plano, $dono) {
            $empresa = Tenant::create($dados);

            createDefaultRolesForTenant($empresa->id);
            initializeAccountingDataForTenant($empresa->id);

            // O plano pela regra do ecrã de planos: a primeira vez começa em
            // teste, se o plano o tiver; e os módulos dele ficam ligados.
            $subscricao = null;

            if ($plano) {
                $subscricao = app(TrocarDePlano::class)->aplicar($empresa, $plano, 'monthly');

                $sync = new TenantModuleSyncService();
                foreach ($plano->moduleSlugsWithDependencies() as $slug) {
                    $sync->activateModule($empresa, $slug);
                }
            }

            $pessoa = null;
            $senha = null;

            if ($dono) {
                // Letras e números, sem símbolos: o email é copiado à mão, e um
                // `$` no meio passaria por marcador de substituição.
                $senha = Str::password(12, symbols: false);

                $pessoa = User::create([
                    'name' => $dono['nome'],
                    'email' => $dono['email'],
                    'phone' => $dono['telefone'],
                    'password' => Hash::make($senha),
                    'is_active' => true,
                    'is_super_admin' => false,
                ]);

                $pessoa->tenants()->attach($empresa->id, ['is_active' => true, 'joined_at' => now()]);
                $pessoa->tenant_id = $empresa->id;
                $pessoa->save();

                setPermissionsTeamId($empresa->id);
                $papel = Role::where('name', 'Super Admin')->where('tenant_id', $empresa->id)->first();

                if ($papel) {
                    $pessoa->assignRole($papel);
                }
            }

            return [$empresa, $pessoa, $senha, $subscricao];
        });

        // Catálogos acessórios: falham em silêncio, mas ficam relatados. Uma
        // empresa não deixa de existir porque um modelo de notificação falhou.
        foreach ([
            'definições de RH'       => fn () => \App\Services\HR\DefinicoesRH::garantirPara($empresa->id),
            'modelos de notificação' => fn () => \App\Services\Notifications\ModelosPadrao::garantirPara($empresa->id),
        ] as $nomeDoPasso => $passo) {
            try {
                $passo();
                $this->line("  <fg=green>ok</> {$nomeDoPasso}");
            } catch (\Throwable $e) {
                $this->line("  <fg=yellow>falhou</> {$nomeDoPasso}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Empresa criada: #{$empresa->id} — {$empresa->name} ({$empresa->regimeLabel()})");

        if ($subscricao) {
            $this->line(sprintf('  plano %s — %s até %s', $plano->name, $subscricao->status,
                $subscricao->ends_at?->format('d/m/Y') ?: '—'));
        }

        if ($pessoa) {
            $this->line("  dono: #{$pessoa->id} {$pessoa->email} (Super Admin)");

            if ($enviarAcessos) {
                $enviado = app(AvisosDeConta::class)->credenciais($pessoa, $senha, $empresa);

                $enviado
                    ? $this->line('  <fg=green>acessos enviados</> por email')
                    : $this->line('  <fg=yellow>os acessos NÃO seguiram</> (ver o registo do email); o dono pode usar «Esqueci a senha»');
            }
        } else {
            $this->line('  Falta criar quem entra: <options=bold>utilizadores:criar</> (ver a ajuda do comando)');
        }

        $senha = null;

        // Os dados pessoais não ficam no servidor depois de servirem.
        if ($ficheiro !== null) {
            @unlink($ficheiro);
            $this->line('  ficheiro dos dados apagado');
        }

        return self::SUCCESS;
    }

    /**
     * O ficheiro dos dados: só um nome, dentro de `storage/app/privado`.
     *
     * @return string|false|null  o caminho; false quando o nome não serve; null sem ficheiro
     */
    private function ficheiro(): string|false|null
    {
        $nome = $this->option('ficheiro');

        if ($nome === null || $nome === '') {
            return null;
        }

        // Um nome e nada mais: sem pastas, sem subir de directório.
        if (!preg_match('/^[a-z0-9_-]+\.json$/i', (string) $nome)) {
            $this->error('O --ficheiro é só o nome de um .json em storage/app/privado.');

            return false;
        }

        $caminho = storage_path('app/privado/' . $nome);

        if (!is_file($caminho)) {
            $this->error('Não há esse ficheiro em storage/app/privado.');

            return false;
        }

        return $caminho;
    }

    /** O regime canónico; sem regime indicado, o geral. */
    private function regime(): string|false
    {
        $pedido = mb_strtolower(trim((string) $this->valor('regime')));

        if ($pedido === '') {
            return Tenant::REGIME_GERAL;
        }

        $pedido = str_replace(['ã', ' '], ['a', '_'], $pedido);

        if (isset(self::REGIMES_CURTOS[$pedido])) {
            return self::REGIMES_CURTOS[$pedido];
        }

        if (isset(Tenant::REGIMES[$pedido]) || isset(Tenant::REGIME_ALIASES[$pedido])) {
            return Tenant::canonicalRegime($pedido);
        }

        $this->error('Regime desconhecido. Use geral, simplificado ou exclusao.');

        return false;
    }

    /**
     * Quem entra na empresa: o dono, se veio.
     *
     * @return array{nome: string, email: string, telefone: ?string}|false|null
     */
    private function dono(): array|false|null
    {
        $email = mb_strtolower(trim((string) $this->valor('dono_email')));

        if ($email === '') {
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('O email do dono não é válido.');

            return false;
        }

        // Uma pessoa que já existe não se junta a outra empresa às escondidas:
        // isso faz-se no ecrã das empresas, onde se vê quem é.
        if (User::where('email', $email)->exists()) {
            $this->error('Já existe uma conta com o email do dono. Junte-a à empresa pelo ecrã das empresas.');

            return false;
        }

        return [
            'nome' => trim((string) ($this->valor('dono_nome') ?: $this->valor('nome'))),
            'email' => $email,
            'telefone' => $this->valor('dono_telefone') ?: $this->valor('telefone'),
        ];
    }

    /** O valor de um campo: primeiro o bloco (`--ficheiro` ou `--dados`), depois a opção solta. */
    private function valor(string $campo): ?string
    {
        if (array_key_exists($campo, $this->doBloco) && $this->doBloco[$campo] !== null) {
            $v = $this->doBloco[$campo];

            return is_bool($v) ? ($v ? '1' : '0') : (string) $v;
        }

        // Os campos que só existem no bloco (o dono) não são opções.
        if (!$this->getDefinition()->hasOption($campo)) {
            return null;
        }

        $solta = $this->option($campo);

        return $solta === null || $solta === '' ? null : (string) $solta;
    }

    private function resolverPlano(): ?Plan
    {
        $chave = $this->valor('plano');

        if (!$chave) return null;

        return is_numeric($chave)
            ? Plan::find((int) $chave)
            : Plan::where('slug', $chave)->orWhere('name', 'like', '%' . $chave . '%')->first();
    }

    /** Um slug que ainda não esteja tomado. */
    private function slugLivre(string $nome): string
    {
        $base = Str::slug($nome) ?: 'empresa';
        $slug = $base;
        $n = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}
