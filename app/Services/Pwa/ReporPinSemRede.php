<?php

namespace App\Services\Pwa;

use App\Models\ReposicaoDePin;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PinDeTurno;
use Illuminate\Support\Facades\DB;

/**
 * Um PIN de turno reposto no aparelho, sem rede, chega ao servidor.
 *
 * O QUE ACONTECEU NO BALCÃO: um funcionário esqueceu o PIN, não há rede, e
 * um gestor presente pôs o seu próprio PIN para autorizar um novo. O tablet
 * verificou o gestor contra o verificador que já tinha, calculou o bcrypt do
 * PIN novo, e pôs isto na fila. O PIN em claro nunca saiu do tablet.
 *
 * O QUE O SERVIDOR PODE CONFIRMAR, e é só isto: que o alvo e quem autorizou
 * são membros activos da empresa, que quem autorizou pode gerir utilizadores
 * AGORA (não só quando o aparelho sincronizou pela última vez — um gestor
 * demitido tem o verificador no tablet durante a janela de 14 dias), e que a
 * conta com sessão no aparelho tem o direito de repor PIN de outros, ou é o
 * próprio. O servidor não tem como saber que o gestor pôs mesmo o PIN — essa
 * confiança é a mesma que já se dá a uma venda feita sem rede.
 *
 * Idempotente pelo `local_uuid`: a fila repete, o registo não.
 */
final class ReporPinSemRede
{
    /** O direito que abre a Gestão de Utilizadores, onde o PIN se define com rede. */
    public const PERMISSAO = 'users.manage';

    public function aplicar(array $pedido, User $sessao, int $tenantId): ReposicaoDePin
    {
        $existente = ReposicaoDePin::where('tenant_id', $tenantId)
            ->where('local_uuid', $pedido['local_uuid'])
            ->first();

        if ($existente) {
            return $existente;
        }

        $tenant = Tenant::findOrFail($tenantId);
        $alvo   = $this->membroActivo($tenant, (int) $pedido['user_id']);
        $gestor = $this->membroActivo($tenant, (int) $pedido['authorized_by']);

        $motivo = match (true) {
            !$alvo   => 'O utilizador já não pertence à empresa.',
            !$gestor => 'Quem autorizou já não pertence à empresa.',
            !$this->podeGerirUtilizadores($gestor, $tenantId)
                     => 'Quem autorizou no aparelho não pode gerir utilizadores.',
            $sessao->id !== $alvo->id && !$this->podeGerirUtilizadores($sessao, $tenantId)
                     => 'A conta com sessão neste aparelho não pode repor o PIN de outros. '
                      . 'Entre com uma conta de gestor e sincronize outra vez.',
            !PinDeTurno::ehVerificadorBcrypt($pedido['pin_hash'] ?? null)
                     => 'O verificador recebido não é um bcrypt válido.',
            default  => null,
        };

        return DB::transaction(function () use ($pedido, $sessao, $tenantId, $alvo, $gestor, $motivo) {
            if ($motivo === null) {
                $alvo->adoptarVerificadorPinPos($pedido['pin_hash']);
            }

            return ReposicaoDePin::create([
                'tenant_id'              => $tenantId,
                'local_uuid'             => $pedido['local_uuid'],
                'user_id'                => (int) $pedido['user_id'],
                'autorizado_por'         => (int) $pedido['authorized_by'],
                'sessao_user_id'         => $sessao->id,
                'aparelho'               => isset($pedido['aparelho']) ? substr((string) $pedido['aparelho'], 0, 64) : null,
                'estado'                 => $motivo === null ? ReposicaoDePin::ACEITE : ReposicaoDePin::RECUSADA,
                'motivo'                 => $motivo,
                'reposto_no_aparelho_em' => $pedido['reposto_em'] ?? null,
            ]);
        });
    }

    /** Conta activa E ligação activa a esta empresa — as duas, como no sync. */
    private function membroActivo(Tenant $tenant, int $userId): ?User
    {
        return $tenant->users()
            ->where('users.id', $userId)
            ->where('users.is_active', true)
            ->wherePivot('is_active', true)
            ->first();
    }

    public function podeGerirUtilizadores(User $u, int $tenantId): bool
    {
        if ($u->is_super_admin) {
            return true;
        }

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($tenantId);
        }

        return $u->can(self::PERMISSAO);
    }
}
