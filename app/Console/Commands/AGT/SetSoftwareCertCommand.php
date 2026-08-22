<?php

namespace App\Console\Commands\AGT;

use App\Helpers\AGTHelper;
use App\Models\SoftwareSetting;
use Illuminate\Console\Command;

/**
 * Grava o número de validação do software atribuído pela AGT.
 *
 * É uma definição GLOBAL (software_settings → invoicing.saft_software_cert):
 * alimenta o softwareInfo de todos os payloads, a jwsSoftwareSignature e o
 * rodapé dos documentos impressos. Existe ecrã próprio em SuperAdmin › Billing;
 * este comando serve para o aplicar remotamente sem sessão.
 *
 *   php artisan agt:set-software-cert                    (mostra o actual)
 *   php artisan agt:set-software-cert --numero=FE/324/AGT/2026
 */
class SetSoftwareCertCommand extends Command
{
    protected $signature = 'agt:set-software-cert
        {--numero= : Número atribuído pela AGT (ex.: FE/324/AGT/2026)}
        {--produto= : Product ID exactamente como consta no processo AGT}
        {--versao= : Product Version exactamente como consta no processo AGT}
        {--ambiente= : sandbox ou production — grava só nesse ambiente, sem tocar no outro}';

    protected $description = 'Define o número de validação do software AGT (definição global)';

    public function handle(): int
    {
        // Com --ambiente grava-se APENAS nesse ambiente (saft_*_{ambiente}),
        // deixando o outro intacto. É o que permite acertar produção sem
        // estragar homologação, que foi certificada com outros valores.
        if ($amb = trim((string) $this->option('ambiente'))) {
            return $this->gravarPorAmbiente($amb);
        }

        $actual = (string) softwareSetting('invoicing', 'saft_software_cert', '');
        $this->line('Actual: ' . ($actual ?: '(vazio)'));

        $produto = trim((string) $this->option('produto'));
        $versao = trim((string) $this->option('versao'));

        if ($produto !== '') {
            SoftwareSetting::set('invoicing', 'saft_product_id', $produto, 'string', 'Product ID certificado pela AGT');
            $this->info("Product ID gravado: {$produto}");
        }

        if ($versao !== '') {
            SoftwareSetting::set('invoicing', 'saft_version', $versao, 'string', 'Product Version certificada pela AGT');
            $this->info("Product Version gravada: {$versao}");
        }

        $novo = trim((string) $this->option('numero'));

        // Auto-reparação: quando o valor gravado não é um número de certificado
        // válido (vazio, ou o literal 'PENDENTE' que ficou de antes da AGT o
        // atribuir), repõe-se o oficial sem ser preciso passar opções.
        //
        // Isto existe porque a via de manutenção em produção invoca comandos
        // por HTTP e NÃO aceita opções — sem esta reparação, um 'PENDENTE'
        // gravado só se corrigia pelo ecrã de SuperAdmin, e entretanto ia
        // assinado e impresso em todos os documentos.
        $actualValido = (bool) preg_match('#^FE/\d+/AGT/\d{4}$#', $actual);

        if ($novo === '' && !$actualValido) {
            $novo = AGTHelper::VALIDACAO_FALLBACK;
            $this->warn("O valor gravado não é um certificado válido. A repor o oficial: {$novo}");
        }

        if ($novo === '') {
            SoftwareSetting::clearCache();
            $this->line('Product ID: ' . softwareSetting('invoicing', 'saft_product_id', ''));
            $this->line('Product Version: ' . softwareSetting('invoicing', 'saft_version', ''));
            $this->line(($produto !== '' || $versao !== '')
                ? 'Metadados do produto actualizados; certificado mantido.'
                : 'Nada alterado. Use --numero=FE/324/AGT/2026 para gravar.');
            return self::SUCCESS;
        }

        // Formato AGT: FE/<n>/AGT/<ano>. Validar evita gravar um valor que
        // depois vai assinado em todos os documentos e é recusado.
        if (!preg_match('#^FE/\d+/AGT/\d{4}$#', $novo)) {
            $this->error("Formato inválido: '{$novo}'. Esperado FE/<numero>/AGT/<ano>.");
            return self::FAILURE;
        }

        // O tipo TEM de ser 'string': o set() assume 'boolean' por omissão e o
        // cast transformava o texto em falso, deixando o certificado vazio.
        SoftwareSetting::set(
            'invoicing',
            'saft_software_cert',
            $novo,
            'string',
            'Número de validação do software atribuído pela AGT'
        );
        SoftwareSetting::clearCache();

        $this->info("Gravado: {$novo}");
        $this->line('Confirmação (lido de volta): ' . AGTHelper::softwareValidationNumber());

        return self::SUCCESS;
    }

    /**
     * Grava número, productId e/ou versão SÓ para um ambiente.
     *
     * As chaves são as que o AGTProducerStore lê por ambiente:
     *   saft_software_cert_{amb}, saft_product_id_{amb}, saft_version_{amb}.
     * O que não vier na linha fica como está — não se apaga o que lá estava.
     */
    private function gravarPorAmbiente(string $ambiente): int
    {
        $ambiente = \App\Services\AGT\AGTProducerStore::normalizar($ambiente);
        $this->info("Ambiente: {$ambiente}");

        $numero  = trim((string) $this->option('numero'));
        $produto = trim((string) $this->option('produto'));
        $versao  = trim((string) $this->option('versao'));

        if ($numero !== '' && !preg_match('#^FE/\d+/AGT/\d{4}$#', $numero)) {
            $this->error("Formato inválido: '{$numero}'. Esperado FE/<numero>/AGT/<ano>.");
            return self::FAILURE;
        }

        $mapa = [
            "saft_software_cert_{$ambiente}" => [$numero, 'Número de validação AGT (' . $ambiente . ')'],
            "saft_product_id_{$ambiente}"    => [$produto, 'Product ID certificado (' . $ambiente . ')'],
            "saft_version_{$ambiente}"       => [$versao, 'Product Version certificada (' . $ambiente . ')'],
        ];

        $gravou = false;
        foreach ($mapa as $chave => [$valor, $desc]) {
            if ($valor === '') {
                continue;
            }
            SoftwareSetting::set('invoicing', $chave, $valor, 'string', $desc);
            $this->info("  {$chave} = {$valor}");
            $gravou = true;
        }

        SoftwareSetting::clearCache();

        if (!$gravou) {
            $this->warn('Nada gravado. Passe --numero, --produto e/ou --versao.');
            return self::SUCCESS;
        }

        // Ler de volta exactamente o que passará a ir assinado neste ambiente.
        $this->newLine();
        $this->line('Passa a assinar neste ambiente:');
        $this->line('  productId              : ' . \App\Services\AGT\AGTProducerStore::productId($ambiente));
        $this->line('  productVersion         : ' . \App\Services\AGT\AGTProducerStore::productVersion($ambiente));
        $this->line('  softwareValidationNumber: ' . \App\Services\AGT\AGTProducerStore::numeroCertificacao($ambiente));
        $this->newLine();
        $this->warn('O outro ambiente NÃO foi tocado. Re-sincronize as séries para confirmar.');

        return self::SUCCESS;
    }
}