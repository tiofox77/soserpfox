<?php

namespace App\Console\Commands;

use App\Helpers\SAFTHelper;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Preenche o ELO que falta às facturas emitidas pelos módulos.
 *
 * O ModuleInvoiceService (restaurante, hotel, salão, oficina, e as vendas
 * repostas pelo PWA offline) assinava com o hash do documento anterior mas
 * gravava só o `saft_hash` — ficava com a assinatura certa e sem dizer a que
 * documento se ligava. Já está corrigido na emissão; isto trata do passado.
 *
 * O QUE ESTE COMANDO NÃO FAZ: não inventa nada. Para cada documento sem elo,
 * recalcula o hash com o anterior candidato. Só grava se o resultado bater
 * LETRA A LETRA com o hash que já lá está. Se não bater, não toca e diz.
 *
 * Um documento assinado é imutável: aqui completa-se o registo do que foi
 * assinado, nunca se reassina.
 *
 * Seco por omissão. Escreve com --aplicar.
 */
class RepararElosDaCadeia extends Command
{
    protected $signature = 'agt:reparar-elos
                            {--tenant= : Só esta empresa (id)}
                            {--aplicar : Grava. Sem isto só mostra o que faria}';

    protected $description = 'Preenche hash_previous/hash nas facturas assinadas sem elo registado (só quando o hash o prova)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        $empresas = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey((int) $this->option('tenant')))
            ->orderBy('id')
            ->get();

        $totalProvados = 0;
        $totalRecusados = 0;

        foreach ($empresas as $empresa) {
            [$provados, $recusados] = $this->repararEmpresa($empresa, $aplicar);
            $totalProvados += $provados;
            $totalRecusados += $recusados;
        }

        $this->newLine();

        if ($totalProvados === 0 && $totalRecusados === 0) {
            $this->info('Nada a reparar: todas as facturas assinadas já têm o elo registado.');

            return self::SUCCESS;
        }

        $this->line($aplicar
            ? "<fg=green>{$totalProvados} elo(s) gravado(s).</>"
            : "<fg=yellow>{$totalProvados} elo(s) por gravar. Correr outra vez com --aplicar.</>");

        if ($totalRecusados > 0) {
            $this->error("{$totalRecusados} documento(s) NÃO provaram o elo — ficaram intactos. Ver acima.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int} */
    private function repararEmpresa(Tenant $empresa, bool $aplicar): array
    {
        $facturas = SalesInvoice::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->whereNotNull('invoice_number')
            ->orderBy('id')
            ->get();

        if ($facturas->isEmpty()) {
            return [0, 0];
        }

        $provados = 0;
        $recusados = 0;
        $anterior = null;
        $mostrouCabecalho = false;

        foreach ($facturas as $f) {
            $hash = $f->hash ?: $f->saft_hash;

            if (empty($hash)) {
                // Sem assinatura não há elo para completar, e este documento
                // também não serve de elo ao seguinte — é o que a emissão faz.
                continue;
            }

            $elo = $anterior->saft_hash ?? '';

            // O primeiro documento assinado da empresa liga-se a nada. Vazio é
            // a resposta certa, não uma falta.
            $precisa = $anterior !== null && empty($f->hash_previous);
            $faltaHash = empty($f->hash);

            if (!$precisa && !$faltaHash) {
                $anterior = $f;
                continue;
            }

            if (!$mostrouCabecalho) {
                $this->line('');
                $this->line("<options=bold>#{$empresa->id} {$empresa->name}</>");
                $mostrouCabecalho = true;
            }

            // A PROVA: o hash guardado tem de ser o que sai deste elo.
            //
            // Há mais de uma fórmula em uso, e é preciso tentar as que existem
            // de facto — não a que devia existir. O ModuleInvoiceService assina
            // com `created_at` e `total`; o SalesInvoice::generateHash assina
            // com `system_entry_date` e `gross_total`. Num documento em que os
            // dois instantes diferem por um segundo isto decide entre provar o
            // elo e recusá-lo sem razão.
            //
            // Continua a ser prova: ou o hash guardado sai de uma destas
            // fórmulas com este elo, ou não se escreve nada.
            if (!$this->eloProva($f, $elo, $hash)) {
                $this->line("   <fg=red>{$f->invoice_number}: o elo NÃO se prova — intacto</>");
                $recusados++;
                $anterior = $f;
                continue;
            }

            $provados++;

            if (!$aplicar) {
                $this->line("   <fg=yellow>{$f->invoice_number}: elo provado (por gravar)</>");
                $anterior = $f;
                continue;
            }

            // Escrita cirúrgica: só as colunas do registo da assinatura, sem
            // mexer em totais, estado ou datas, e sem acordar observers.
            SalesInvoice::withoutGlobalScopes()->whereKey($f->id)->update([
                'hash'          => $hash,
                'saft_hash'     => $hash,
                'hash_previous' => $elo,
                'hash_control'  => $f->hash_control ?: '1',
            ]);

            $this->line("   <fg=green>{$f->invoice_number}: elo gravado</>");
            $anterior = $f;
        }

        return [$provados, $recusados];
    }

    /**
     * O hash guardado sai deste elo, por alguma das fórmulas em uso?
     */
    private function eloProva(SalesInvoice $f, string $elo, string $hash): bool
    {
        $instantes = array_unique(array_filter([
            $f->created_at?->format('Y-m-d H:i:s'),
            $f->system_entry_date?->format('Y-m-d H:i:s'),
        ]));

        $totais = array_unique([
            (string) $f->total,
            (string) ($f->gross_total ?: $f->total),
        ]);

        foreach ($instantes as $instante) {
            foreach ($totais as $total) {
                $candidato = SAFTHelper::generateHash(
                    $f->invoice_date->format('Y-m-d'),
                    $instante,
                    $f->invoice_number,
                    $total,
                    $elo ?: null
                );

                if ($candidato === $hash) {
                    return true;
                }
            }
        }

        return false;
    }
}
