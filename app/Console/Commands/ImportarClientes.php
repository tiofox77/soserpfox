<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa uma carteira de clientes vinda de outro sistema.
 *
 * O QUE ISTO RECUSA, E PORQUÊ. Uma lista exportada de outro programa traz
 * sempre o que lá se deixou entrar ao longo dos anos: o mesmo NIF em três
 * pessoas diferentes, um nome de empresa escrito no campo do NIF, números com
 * quinze dígitos. Nada disso pode entrar:
 *
 *   · o NIF é ÚNICO por empresa na base — dois iguais fazem a importação
 *     rebentar a meio, com metade dos clientes dentro e metade fora;
 *   · um NIF inválido vai parar a uma factura e a AGT recusa-a.
 *
 * Por isso: repetido entra UMA vez, inválido fica de fora, e ambos são
 * relatados nome a nome. Sem NIF entra — a lei angolana só o exige acima de um
 * valor, e o campo aceita vazio.
 *
 * A SECO por omissão. Corre tudo numa transacção e desfaz no fim; só grava com
 * --aplicar. Assim o ensaio percorre o caminho verdadeiro sem escrever nada.
 */
class ImportarClientes extends Command
{
    protected $signature = 'clientes:importar
        {--tenant= : id da empresa}
        {--ficheiro= : caminho do JSON}
        {--aplicar : grava de facto (sem isto, corre a seco)}
        {--apagar-ficheiro : apaga o JSON depois de importar}';

    protected $description = 'Importa clientes de um JSON para uma empresa (simulação por omissão)';

    /** O NIF angolano anda entre 9 e 14 caracteres. Fora disso não é NIF. */
    private const NIF_MIN = 9;
    private const NIF_MAX = 14;

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $empresa = Tenant::find($tenantId);

        if (!$empresa) {
            $this->error('Empresa não encontrada. Use --tenant=<id>.');

            return self::FAILURE;
        }

        $ficheiro = $this->option('ficheiro') ?: storage_path('app/imports/clientes.json');

        if (!is_file($ficheiro)) {
            $this->error("Ficheiro não encontrado: {$ficheiro}");

            return self::FAILURE;
        }

        $lista = json_decode((string) file_get_contents($ficheiro), true);

        if (!is_array($lista)) {
            $this->error('O ficheiro não é um JSON válido.');

            return self::FAILURE;
        }

        $this->line(str_repeat('=', 64));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(' Ficheiro: ' . $ficheiro . '  (' . count($lista) . ' linhas)');
        $this->line($this->option('aplicar') ? ' MODO: --aplicar — VAI GRAVAR.' : ' MODO: a seco (transacção desfeita no fim).');
        $this->line(str_repeat('=', 64));

        $invalidos = [];
        $repetidos = [];
        $inactivos = [];
        $jaExistiam = 0;
        $criados = 0;

        // O que já lá está, para não duplicar numa segunda passagem.
        $nifsNaBase = Client::where('tenant_id', $tenantId)
            ->whereNotNull('nif')
            ->pluck('nif')
            ->map(fn ($n) => (string) $n)
            ->flip();

        $vistos = [];

        DB::beginTransaction();

        try {
            foreach ($lista as $c) {
                $nome = trim((string) ($c['nome'] ?? ''));
                $nif = trim((string) ($c['nif'] ?? ''));
                $activo = ($c['activo'] ?? true) ? true : false;

                if ($nome === '') {
                    continue;
                }

                if (!$activo) {
                    $inactivos[] = $nome;
                    continue;
                }

                if ($nif !== '' && !$this->nifPlausivel($nif)) {
                    $invalidos[] = "{$nome}  [{$nif}]";
                    continue;
                }

                if ($nif !== '') {
                    if (isset($vistos[$nif])) {
                        $repetidos[] = "{$nome}  [{$nif}] — já entrou como «{$vistos[$nif]}»";
                        continue;
                    }

                    if ($nifsNaBase->has($nif)) {
                        $jaExistiam++;
                        continue;
                    }

                    $vistos[$nif] = $nome;
                }

                $cliente = new Client([
                    'name'      => $nome,
                    // Vazio tem de ir a NULL: o índice único aceita vários
                    // nulos, mas não aceita duas cadeias vazias iguais.
                    'nif'       => $nif !== '' ? $nif : null,
                    'is_active' => true,
                ]);

                $cliente->tenant_id = $tenantId;
                $cliente->save();

                $criados++;
            }

            $this->newLine();
            $this->line(sprintf('  a criar:        %d', $criados));
            $this->line(sprintf('  já existiam:    %d', $jaExistiam));
            $this->line(sprintf('  NIF repetido:   %d  (entra o primeiro)', count($repetidos)));
            $this->line(sprintf('  NIF inválido:   %d  (ficam de fora)', count($invalidos)));
            $this->line(sprintf('  inactivos:      %d  (ficam de fora)', count($inactivos)));

            foreach ([['NIF REPETIDO', $repetidos], ['NIF INVÁLIDO', $invalidos], ['INACTIVOS', $inactivos]] as [$titulo, $grupo]) {
                if (!$grupo) continue;

                $this->newLine();
                $this->line("<options=bold>{$titulo}</>");
                foreach ($grupo as $linha) {
                    $this->line('  · ' . $linha);
                }
            }

            if ($this->option('aplicar')) {
                DB::commit();
                $this->newLine();
                $this->info("Gravado: {$criados} clientes na empresa #{$empresa->id}.");
            } else {
                DB::rollBack();
                $this->newLine();
                $this->warn('A SECO — nada foi gravado. Repita com --aplicar.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nada foi gravado. ' . $e->getMessage());

            return self::FAILURE;
        }


        /*
         * O FICHEIRO NÃO FICA NO SERVIDOR.
         *
         * Traz nomes e NIF de gente real, e em produção o site é servido
         * da raiz do projecto — um ficheiro esquecido em storage é um
         * ficheiro que alguém pode descarregar. Importado, apaga-se.
         */
        if ($this->option('apagar-ficheiro') && $this->option('aplicar')) {
            @unlink($ficheiro);
            $this->line('  Ficheiro apagado do servidor: ' . basename($ficheiro));
        }

        return self::SUCCESS;
    }

    /**
     * Um NIF que pode mesmo ser um NIF.
     *
     * Não se valida o dígito de controlo: o objectivo é apanhar o lixo óbvio —
     * o nome da empresa no campo errado, o número com quinze dígitos — sem
     * recusar contribuintes legítimos por excesso de zelo.
     */
    private function nifPlausivel(string $nif): bool
    {
        $limpo = preg_replace('/\s+/', '', $nif);

        if (strlen($limpo) < self::NIF_MIN || strlen($limpo) > self::NIF_MAX) {
            return false;
        }

        // O NIF de pessoa singular é o BI: dígitos e duas letras no meio.
        return (bool) preg_match('/^[0-9A-Za-z]+$/', $limpo) && preg_match('/[0-9]/', $limpo);
    }
}
