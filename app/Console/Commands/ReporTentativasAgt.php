<?php

namespace App\Console\Commands;

use App\Models\AGT\AGTSubmission;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Devolve tentativas a submissões da AGT que esgotaram as suas.
 *
 * Quando a recusa foi NOSSA culpa — versão do schema fora de prazo, imposto
 * mal arredondado — o documento fica com as 5 tentativas gastas e o botão
 * «reenviar» recusa-se («Máximo de tentativas atingido»). Corrigido o
 * código, é preciso repor o contador para o despacho voltar a apanhá-lo.
 *
 * Só toca em submissões PENDENTES ou RECUSADAS: uma validada é história.
 * A seco por omissão; --aplicar escreve. E repor tentativas ARMA UM ENVIO —
 * o DespachoPendentes reenvia à próxima visita de um utilizador da empresa
 * com a submissão automática ligada. Só se corre por ordem do dono.
 */
class ReporTentativasAgt extends Command
{
    protected $signature = 'agt:repor-tentativas
        {--tenant= : id da empresa}
        {--doc= : parte do número do documento (LIKE); sem isto, todas as esgotadas da empresa}
        {--aplicar : escreve de facto}';

    protected $description = 'Repõe as tentativas de submissões AGT esgotadas (a seco por omissão)';

    public function handle(): int
    {
        $tenant = Tenant::find((int) $this->option('tenant'));

        if (! $tenant) {
            $this->error('Empresa não encontrada. Use --tenant=ID.');

            return self::FAILURE;
        }

        $consulta = AGTSubmission::where('tenant_id', $tenant->id)
            ->whereIn('status', [AGTSubmission::STATUS_PENDING, AGTSubmission::STATUS_REJECTED])
            ->where('retry_count', '>', 0)
            ->orderBy('id');

        if ($this->option('doc')) {
            $consulta->where('document_number', 'like', '%'.$this->option('doc').'%');
        }

        $alvos = $consulta->get();

        $this->line("Empresa: #{$tenant->id}  {$tenant->name}");

        if ($alvos->isEmpty()) {
            $this->comment('Nenhuma submissão pendente/recusada com tentativas gastas.');

            return self::SUCCESS;
        }

        foreach ($alvos as $s) {
            $this->line(sprintf(
                '  #%-5d %-28s [%s]  tentativas %d  %s: %s',
                $s->id,
                $s->document_number,
                strtoupper($s->status),
                $s->retry_count,
                $s->error_code ?: '—',
                mb_strimwidth((string) $s->error_message, 0, 90, '…')
            ));
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('A SECO. Corra com --aplicar para repor as tentativas de '.$alvos->count().' submissão(ões).');

            return self::SUCCESS;
        }

        foreach ($alvos as $s) {
            $s->update([
                'status' => AGTSubmission::STATUS_PENDING,
                'retry_count' => 0,
                'error_code' => null,
                'error_message' => null,
            ]);
        }

        $this->info('Tentativas repostas em '.$alvos->count().' submissão(ões). O despacho reenvia-as à próxima visita.');

        return self::SUCCESS;
    }
}
