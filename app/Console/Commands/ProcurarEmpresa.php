<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Rules\NifDeEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Procurar uma empresa pelo nome, e ver os seus contactos.
 *
 * SÓ LÊ. Existe porque a cópia local da base tem uma mão-cheia de empresas e a
 * produção tem dezenas: perguntar "qual é o número da empresa X" não tinha
 * resposta sem abrir o ecrã e procurar à mão.
 *
 * Mostra uma empresa de cada vez e só os campos de identificação e contacto —
 * não é uma porta para despejar a lista de clientes.
 *
 *   php artisan tenants:procurar --termo=kienga
 */
class ProcurarEmpresa extends Command
{
    protected $signature = 'tenants:procurar {--termo= : nome, slug, NIF ou email (ou parte)}';

    protected $description = 'Procura uma empresa e mostra os contactos dela (só lê)';

    public function handle(): int
    {
        $termo = trim((string) $this->option('termo'));

        if (mb_strlen($termo) < 3) {
            $this->error('Indique pelo menos três caracteres: --termo=kienga');

            return self::FAILURE;
        }

        $empresas = Tenant::withTrashed()
            ->where(function ($q) use ($termo) {
                foreach (['name', 'company_name', 'slug', 'nif', 'email'] as $campo) {
                    $q->orWhere($campo, 'like', '%' . $termo . '%');
                }
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($empresas->isEmpty()) {
            $this->warn("Nenhuma empresa encontrada com \"{$termo}\".");

            return self::SUCCESS;
        }

        foreach ($empresas as $e) {
            $this->newLine();
            $this->line(str_repeat('=', 58));
            $this->info("#{$e->id} — {$e->name}");
            $this->line(str_repeat('=', 58));

            $this->line(sprintf('  %-12s %s', 'Telefone:', $e->phone ?: '(por preencher)'));
            $this->line(sprintf('  %-12s %s', 'Email:', $e->email ?: '(por preencher)'));
            $this->line(sprintf('  %-12s %s', 'NIF:', $e->nif ?: '(por preencher)'));
            $this->line(sprintf('  %-12s %s', 'Criada em:', optional($e->created_at)->format('d/m/Y H:i') ?: '—'));
            $this->line(sprintf('  %-12s %s', 'Estado:', $this->estado($e)));

            // O telefone como o SMS o vai marcar. É a pergunta a seguir a
            // "qual é o número", e poupa uma segunda ida à base.
            if ($e->phone) {
                $formatado = (new \App\Services\SmsService())->formatPhoneNumber($e->phone);

                $this->line(sprintf(
                    '  %-12s %s',
                    'Para SMS:',
                    $formatado ?: 'INVÁLIDO — não é um número angolano, o SMS não sai'
                ));
            }

            if ($e->nif && Validator::make(['n' => $e->nif], ['n' => [new NifDeEmpresa()]])->fails()) {
                $this->warn('  ⚠  O NIF não é de empresa. Os documentos comunicados à AGT são recusados.');
            }
        }

        return self::SUCCESS;
    }

    private function estado(Tenant $e): string
    {
        if ($e->deleted_at) {
            return 'suspensa em ' . $e->deleted_at->format('d/m/Y');
        }

        return $e->is_active ? 'activa' : 'desactivada';
    }
}
