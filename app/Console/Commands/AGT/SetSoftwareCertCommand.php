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
        {--numero= : Número atribuído pela AGT (ex.: FE/324/AGT/2026)}';

    protected $description = 'Define o número de validação do software AGT (definição global)';

    public function handle(): int
    {
        $actual = (string) softwareSetting('invoicing', 'saft_software_cert', '');
        $this->line('Actual: ' . ($actual ?: '(vazio)'));

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
            $this->line('Nada alterado. Use --numero=FE/324/AGT/2026 para gravar.');
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
}
