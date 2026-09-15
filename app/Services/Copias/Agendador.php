<?php

namespace App\Services\Copias;

use App\Models\Copias\AgendaDeCopia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QUE CÓPIA ESTÁ NA HORA — e fazê-la.
 *
 * Uma por passagem, no máximo: a da plataforma primeiro (é a que salva tudo),
 * senão a empresa que está há mais tempo à espera. Este alojamento não tem cron
 * garantido; as passagens vêm do tráfego (App\Http\Middleware\FazerCopiasDevidas)
 * e, onde houver cron, também do `copias:devidas` agendado. As duas vias são
 * seguras juntas: a tranca de cada âmbito impede cópias em duplicado.
 */
class Agendador
{
    public function __construct(private FazerCopia $fazer)
    {
    }

    /** @return array{ambito: string, copia_id: ?int, erro: ?string}|null */
    public function correrUma(): ?array
    {
        $plataforma = AgendaDeCopia::para(null);
        if ($plataforma->devida()) {
            return $this->correr(null);
        }

        $tenantId = $this->empresaDevida();

        return $tenantId ? $this->correr($tenantId) : null;
    }

    /** A empresa activa há mais tempo à espera (as que nunca tiveram agenda primeiro). */
    public function empresaDevida(): ?int
    {
        $linha = DB::table('tenants')
            ->leftJoin('agendas_de_copia as a', 'a.tenant_id', '=', 'tenants.id')
            ->where('tenants.is_active', true)
            ->whereNull('tenants.deleted_at')
            ->where(fn ($q) => $q->whereNull('a.id')
                ->orWhere(fn ($w) => $w->where('a.activa', true)
                    ->where(fn ($x) => $x->whereNull('a.proxima_em')->orWhere('a.proxima_em', '<=', now()))))
            ->orderByRaw('a.proxima_em IS NOT NULL, a.proxima_em ASC, tenants.id ASC')
            ->select('tenants.id')
            ->first();

        return $linha ? (int) $linha->id : null;
    }

    private function correr(?int $tenantId): array
    {
        try {
            $copia = $this->fazer->fazer($tenantId, 'automatica');

            return ['ambito' => $tenantId ? "empresa #{$tenantId}" : 'plataforma', 'copia_id' => $copia->id, 'erro' => null];
        } catch (\Throwable $e) {
            Log::warning('Cópia automática não foi feita', ['tenant_id' => $tenantId, 'erro' => $e->getMessage()]);

            return ['ambito' => $tenantId ? "empresa #{$tenantId}" : 'plataforma', 'copia_id' => null, 'erro' => $e->getMessage()];
        }
    }
}
