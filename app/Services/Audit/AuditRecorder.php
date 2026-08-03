<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gravador da trilha de auditoria.
 *
 * Duas regras que não se negoceiam:
 *
 *  1. **Nunca rebenta.** No POS, a factura, as linhas, os movimentos de stock e
 *     a tesouraria estão todos na mesma transacção. Se uma escrita de auditoria
 *     lançar — uma coluna por migrar, a tabela cheia, o que for — derruba a
 *     venda. Tudo aqui está dentro de try/catch e degrada para o log.
 *
 *  2. **Escreve DEPOIS do commit.** As linhas são acumuladas em memória e
 *     despejadas por DB::afterCommit. Num rollback são descartadas, porque
 *     auditar um facto que foi desfeito é pior do que não o auditar: fica no
 *     registo uma venda que não existe.
 */
class AuditRecorder
{
    /** Linhas por escrever, à espera do commit. */
    protected array $pendentes = [];

    /** Já há um despejo agendado para este commit? */
    protected bool $agendado = false;

    /** Cola as N linhas geradas pelo mesmo pedido (uma venda gera 12-15). */
    protected ?string $requestId = null;

    /**
     * Regista uma alteração de modelo.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function model(string $evento, Model $modelo, array $antes = [], array $depois = [], array $metadata = []): void
    {
        if (!config('audit.enabled', true)) {
            return;
        }

        try {
            $tenantDoRegisto = $this->empresaDoRegisto($modelo);

            if (!$tenantDoRegisto) {
                return;   // sem empresa não há linha de auditoria útil
            }

            $this->enfileirar([
                'tenant_id'         => $tenantDoRegisto,
                'context_tenant_id' => $this->empresaActiva(),
                'event'             => $evento,
                'auditable_type'    => $modelo::class,
                'auditable_id'      => $modelo->getKey(),
                'auditable_label'   => $this->rotular($modelo),
                'old_values'        => $antes ?: null,
                'new_values'        => $depois ?: null,
                'metadata'          => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            $this->falhar($e, $evento);
        }
    }

    /**
     * Regista um acto que não é alteração de modelo: login, exportação,
     * impressão, execução de comando, submissão à AGT.
     */
    public function acto(string $evento, ?int $tenantId = null, array $metadata = [], ?Model $alvo = null): void
    {
        if (!config('audit.enabled', true)) {
            return;
        }

        try {
            $tenantId = $tenantId ?: ($alvo->tenant_id ?? $this->empresaActiva());

            if (!$tenantId) {
                return;
            }

            $this->enfileirar([
                'tenant_id'         => $tenantId,
                'context_tenant_id' => $this->empresaActiva(),
                'event'             => $evento,
                'auditable_type'    => $alvo ? $alvo::class : null,
                'auditable_id'      => $alvo?->getKey(),
                'auditable_label'   => $alvo ? $this->rotular($alvo) : null,
                'old_values'        => null,
                'new_values'        => null,
                'metadata'          => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            $this->falhar($e, $evento);
        }
    }

    /**
     * A que empresa pertence este registo.
     *
     * Preferência ao `tenant_id` do PRÓPRIO registo, e não à sessão: é isso que
     * permite detectar um acto praticado sobre dados de outra empresa — a
     * sessão diria sempre a empresa "certa".
     *
     * Mas as linhas de documento (SalesInvoiceItem, CreditNoteItem, ...) NÃO
     * têm coluna tenant_id: pertencem à empresa do documento que as contém.
     * Sem esta resolução eram descartadas em silêncio — a auditoria registava
     * a factura e perdia as linhas, que é onde vive o imposto e a quantidade.
     */
    protected function empresaDoRegisto(Model $modelo): ?int
    {
        if (!empty($modelo->tenant_id)) {
            return (int) $modelo->tenant_id;
        }

        // Subir ao documento que contém a linha.
        foreach (['invoice', 'creditNote', 'debitNote', 'proforma', 'guide', 'document', 'order'] as $relacao) {
            if (!method_exists($modelo, $relacao)) {
                continue;
            }

            try {
                $pai = $modelo->{$relacao};

                if ($pai && !empty($pai->tenant_id)) {
                    return (int) $pai->tenant_id;
                }
            } catch (\Throwable) {
                // A relação pode não resolver (documento já apagado, por
                // exemplo). Segue para a hipótese seguinte.
            }
        }

        // Último recurso: a empresa activa. Só vale num pedido web — fora dele
        // é null e a linha não se escreve, que é preferível a atribuí-la à
        // empresa errada.
        return $this->empresaActiva();
    }

    /** Acrescenta o contexto do actor e agenda o despejo. */
    protected function enfileirar(array $linha): void
    {
        $this->pendentes[] = array_merge($linha, $this->contextoDoActor(), [
            'request_id' => $this->requestId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!$this->agendado) {
            $this->agendado = true;

            // afterCommit: com transacção aberta adia até ao commit de nível 0
            // e DESCARTA num rollback; sem transacção corre já.
            DB::afterCommit(fn () => $this->despejar());
        }
    }

    /**
     * Descarta o que está pendente. Ligado ao evento de rollback.
     *
     * O `DB::afterCommit` descarta o CALLBACK num rollback, mas não limpa este
     * buffer: as linhas ficavam cá e sairiam no commit seguinte, a auditar uma
     * transacção que foi desfeita — no registo apareceria uma venda que nunca
     * existiu.
     */
    public function descartar(): void
    {
        $this->pendentes = [];
        $this->agendado  = false;
    }

    /** Escreve o que está pendente. Chamado depois do commit. */
    public function despejar(): void
    {
        $linhas = $this->pendentes;

        $this->pendentes = [];
        $this->agendado  = false;

        if (empty($linhas)) {
            return;
        }

        try {
            // Lote e não linha a linha: com 19 modelos auditados, uma venda
            // gera dezenas de linhas, e uma transacção com bloqueio por cada
            // uma dava deadlock entre escritas concorrentes.
            AuditTrail::registarLote($linhas);
        } catch (\Throwable $e) {
            $this->falhar($e, 'despejo');
        }
    }

    /** Quem praticou o acto, e por que caminho. */
    protected function contextoDoActor(): array
    {
        $utilizador = auth()->user();
        $consola    = app()->runningInConsole();

        // Personificação: guarda-se o par real+efectivo, senão o acto fica
        // atribuído a quem não o praticou.
        $personificador = session('impersonator_id');

        return [
            'user_id'         => $utilizador?->getKey(),
            'actor_name'      => $utilizador?->name ?? ($consola ? 'consola' : null),
            'actor_type'      => $this->tipoDeActor($utilizador, $consola),
            'channel'         => $this->canal($consola),
            'impersonator_id' => $personificador,
            'ip_address'      => $consola ? null : request()->ip(),
            'user_agent'      => $consola ? null : Str::limit((string) request()->userAgent(), 500, ''),

            // Truncar é obrigatório: em consola isto é a linha de comandos
            // INTEIRA, que estoura o varchar(255). E como o gravador falha em
            // silêncio, o efeito não era um erro visível — era a auditoria
            // deixar de registar sem ninguém dar por isso.
            'route'           => Str::limit($this->caminho($consola), 250, ''),
        ];
    }

    protected function caminho(bool $consola): string
    {
        if (!$consola) {
            return (string) request()->path();
        }

        // Só o comando, não os argumentos: um `--execute=` traz o script todo.
        $argv = (array) ($_SERVER['argv'] ?? []);

        return 'artisan ' . ($argv[1] ?? '');
    }

    protected function tipoDeActor($utilizador, bool $consola): string
    {
        if ($utilizador) {
            return auth()->guard('client')->check() ? 'client' : 'user';
        }

        return $consola ? 'console' : 'guest';
    }

    protected function canal(bool $consola): string
    {
        if ($consola) {
            // A fila corre em consola mas não é o mesmo que um comando manual.
            return app()->bound('queue.worker.running') ? 'queue' : 'console';
        }

        $caminho = request()->path();

        return match (true) {
            str_starts_with($caminho, 'api/')         => 'api',
            str_starts_with($caminho, 'client/')      => 'client_portal',
            str_starts_with($caminho, 'maintenance/') => 'maintenance',
            str_contains($caminho, 'booking')         => 'public_booking',
            default                                   => 'web',
        };
    }

    /** Rótulo legível, CONGELADO: o documento pode ser apagado depois. */
    protected function rotular(Model $modelo): ?string
    {
        foreach (['invoice_number', 'credit_note_number', 'debit_note_number',
                  'order_number', 'reservation_number', 'name', 'code'] as $campo) {
            if (!empty($modelo->{$campo})) {
                return Str::limit((string) $modelo->{$campo}, 250, '');
            }
        }

        return null;
    }

    protected function empresaActiva(): ?int
    {
        try {
            return activeTenantId() ?: null;
        } catch (\Throwable) {
            return null;   // fora de um pedido web não há empresa activa
        }
    }

    protected function requestId(): string
    {
        return $this->requestId ??= (string) Str::ulid();
    }

    /**
     * Falhar aqui é sempre silencioso para o utilizador — de propósito.
     *
     * Como o silêncio é por desenho, "auditoria vazia" e "não aconteceu nada"
     * tornam-se indistinguíveis. É por isso que existe `audit:health`.
     */
    protected function falhar(\Throwable $e, string $evento): void
    {
        Log::warning('Auditoria não registada', [
            'evento' => $evento,
            'erro'   => $e->getMessage(),
        ]);
    }
}
