<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Uma linha da trilha de auditoria.
 *
 * Append-only por contrato: não se edita nem se apaga. Os guardas abaixo
 * bloqueiam os dois caminhos habituais (update e delete via Eloquent); quem
 * tiver acesso directo à base continua a poder reescrever — o que a selagem
 * garante é que isso fica DETECTÁVEL, não impossível.
 */
class AuditTrail extends Model
{
    protected $table = 'audit_trail';

    protected $fillable = [
        'tenant_id', 'context_tenant_id',
        'event', 'actor_type', 'user_id', 'actor_name', 'channel', 'impersonator_id',
        'auditable_type', 'auditable_id', 'auditable_label',
        'old_values', 'new_values', 'metadata',
        'ip_address', 'user_agent', 'route', 'request_id',
        'sequence', 'hash', 'hash_previous',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException(
            'A trilha de auditoria é append-only: uma linha não pode ser alterada.'
        ));

        static::deleting(fn () => throw new \RuntimeException(
            'A trilha de auditoria é append-only: uma linha não pode ser apagada. '
            . 'Para libertar espaço use audit:archive, que guarda antes de remover.'
        ));
    }

    /**
     * Escreve uma linha, atribuindo sequência e hash encadeado.
     *
     * A sequência é por EMPRESA e é atribuída no momento da escrita, sob
     * bloqueio: diferi-la deixaria buracos impossíveis de distinguir de linhas
     * removidas.
     */
    public static function registar(array $dados): self
    {
        return static::registarLote([$dados])[0];
    }

    /**
     * Escreve várias linhas de uma só vez.
     *
     * UMA transacção e UM bloqueio para o lote inteiro. Antes cada linha abria a
     * sua transacção e pedia o seu `lockForUpdate` sobre a última linha da
     * empresa; com 19 modelos auditados, uma única venda gera dezenas de linhas
     * e isso dava **deadlock** — duas escritas a disputar o mesmo registo de
     * cauda.
     *
     * As sequências são calculadas em memória a partir da última existente, e o
     * encadeamento do hash mantém-se linha a linha.
     *
     * @param  array<int, array>  $lote
     * @return array<int, self>
     */
    public static function registarLote(array $lote): array
    {
        if (empty($lote)) {
            return [];
        }

        if (!config('audit.seal', true)) {
            return array_map(fn ($d) => static::query()->create($d), $lote);
        }

        return DB::transaction(function () use ($lote) {
            $criadas = [];

            // Última linha por empresa, uma leitura por empresa presente no lote.
            $ultimas = [];

            foreach ($lote as $dados) {
                $tenantId = $dados['tenant_id'];

                if (!array_key_exists($tenantId, $ultimas)) {
                    $anterior = static::where('tenant_id', $tenantId)
                        ->orderByDesc('sequence')
                        ->lockForUpdate()
                        ->first();

                    $ultimas[$tenantId] = [
                        'sequence' => (int) ($anterior->sequence ?? 0),
                        'hash'     => $anterior->hash ?? null,
                    ];
                }

                // Fixar o instante ANTES de calcular o hash. Sem isto, um
                // chamador que omita `created_at` fazia o hash sobre `now()` e a
                // linha ficava gravada com o `now()` do insert — instantes que
                // podem cair em segundos diferentes. A verificação da cadeia
                // acusaria adulteração numa linha perfeitamente legítima.
                $dados['created_at'] ??= now();
                $dados['updated_at'] ??= $dados['created_at'];

                $dados['sequence']      = ++$ultimas[$tenantId]['sequence'];
                $dados['hash_previous'] = $ultimas[$tenantId]['hash'];
                $dados['hash']          = static::calcularHash($dados);

                $ultimas[$tenantId]['hash'] = $dados['hash'];

                $criadas[] = static::query()->create($dados);
            }

            return $criadas;
        });
    }

    /**
     * Hash do conteúdo, encadeado com o anterior.
     *
     * Cobre o que identifica o acto e o que ele mudou. Alterar qualquer um
     * destes campos numa linha antiga parte a cadeia de todas as seguintes.
     */
    public static function calcularHash(array $d): string
    {
        return hash('sha256', implode('|', [
            $d['tenant_id'] ?? '',
            $d['sequence'] ?? '',
            $d['event'] ?? '',
            $d['auditable_type'] ?? '',
            $d['auditable_id'] ?? '',
            $d['user_id'] ?? '',
            json_encode($d['old_values'] ?? null),
            json_encode($d['new_values'] ?? null),
            ($d['created_at'] ?? now())->format('Y-m-d H:i:s'),
            $d['hash_previous'] ?? '',
        ]));
    }

    /**
     * A cadeia desta empresa está intacta? Devolve as linhas suspeitas.
     *
     * O arquivo (audit:archive) remove sempre um prefixo — as linhas mais
     * antigas — pelo que o buraco fica no início e a primeira linha lida não
     * tem anterior com que comparar. Por isso arquivar não gera falso alarme
     * aqui. Um buraco NO MEIO continua a ser acusado, que é o caso que importa.
     */
    public static function verificarCadeia(int $tenantId): array
    {
        $problemas = [];
        $anterior  = null;

        static::where('tenant_id', $tenantId)
            ->orderBy('sequence')
            ->chunk(500, function ($linhas) use (&$problemas, &$anterior) {
                foreach ($linhas as $linha) {
                    $esperado = static::calcularHash([
                        'tenant_id'     => $linha->tenant_id,
                        'sequence'      => $linha->sequence,
                        'event'         => $linha->event,
                        'auditable_type' => $linha->auditable_type,
                        'auditable_id'  => $linha->auditable_id,
                        'user_id'       => $linha->user_id,
                        'old_values'    => $linha->old_values,
                        'new_values'    => $linha->new_values,
                        'created_at'    => $linha->created_at,
                        'hash_previous' => $linha->hash_previous,
                    ]);

                    if ($linha->hash !== $esperado) {
                        $problemas[] = ['sequencia' => $linha->sequence, 'motivo' => 'conteúdo alterado'];
                    }

                    if ($anterior && $linha->hash_previous !== $anterior->hash) {
                        $problemas[] = ['sequencia' => $linha->sequence, 'motivo' => 'cadeia partida (linha removida?)'];
                    }

                    $anterior = $linha;
                }
            });

        return $problemas;
    }

    public function utilizador()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?: activeTenantId());
    }
}
