<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                            {--plano= : id ou slug do plano}
                            {--expira= : fim da subscrição, AAAA-MM-DD}
                            {--dados= : tudo de uma vez, em JSON codificado em base64}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Cria uma empresa completa — papéis, contabilidade, RH e notificações — e liga-lhe o plano';

    /** O que veio no `--dados`, para as opções soltas caírem para trás. */
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

        $expira = $this->valor('expira') ? \Carbon\Carbon::parse($this->valor('expira'))->endOfDay() : null;

        $dados = [
            'name'          => $nome,
            'company_name'  => $nome,
            'slug'          => $this->slugLivre($nome),
            'nif'           => $nif,
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

        $this->line('<options=bold>EMPRESA A CRIAR</>');
        foreach ($dados as $campo => $valor) {
            if ($valor === null || $valor === '') continue;
            $this->line(sprintf('  %-16s %s', $campo, is_bool($valor) ? ($valor ? 'sim' : 'não') : $valor));
        }
        $this->line(sprintf('  %-16s %s', 'plano', $plano ? $plano->name . ' (#' . $plano->id . ')' : '(nenhum)'));
        $this->line(sprintf('  %-16s %s', 'expira', $expira ? $expira->format('d/m/Y') : '(sem fim definido)'));

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('SIMULAÇÃO. Repita com --aplicar para gravar.');

            return self::SUCCESS;
        }

        // Ou nasce completa, ou não nasce.
        $empresa = DB::transaction(function () use ($dados) {
            $empresa = Tenant::create($dados);

            createDefaultRolesForTenant($empresa->id);
            initializeAccountingDataForTenant($empresa->id);

            return $empresa;
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
        $this->info("Empresa criada: #{$empresa->id} — {$empresa->name}");

        if ($plano) {
            $this->line('  Falta ligar o plano: <options=bold>empresas:trocar-plano --tenant=' . $empresa->id
                . ' --plano=' . $plano->slug . ' --aplicar</>');
        }

        $this->line('  Falta criar quem entra: <options=bold>utilizadores:criar</> (ver a ajuda do comando)');

        return self::SUCCESS;
    }

    /** O valor de um campo: primeiro o bloco `--dados`, depois a opção solta. */
    private function valor(string $campo): ?string
    {
        if (array_key_exists($campo, $this->doBloco) && $this->doBloco[$campo] !== null) {
            return (string) $this->doBloco[$campo];
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
