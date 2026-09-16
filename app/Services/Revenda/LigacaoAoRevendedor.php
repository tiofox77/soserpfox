<?php

namespace App\Services\Revenda;

use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * A LIGAÇÃO DE UMA EMPRESA AO REVENDEDOR (16/09/2026, RV-05 e RV-06).
 *
 * Três caminhos: o link de afiliado (um cookie de 60 dias), o código escrito no
 * registo, e a empresa criada pelo próprio revendedor. A primeira ligação
 * fica: um link aberto depois não rouba a empresa a quem a trouxe.
 */
class LigacaoAoRevendedor
{
    public const COOKIE = 'sos_revendedor';

    public const DIAS_DO_COOKIE = 60;

    public const VIAS = ['link' => 'Link de afiliado', 'codigo' => 'Código no registo', 'revendedor' => 'Criada pelo revendedor'];

    /** Um revendedor APROVADO com este código; qualquer outro não conta. */
    public static function porCodigo(?string $codigo): ?Reseller
    {
        $codigo = strtoupper(trim((string) $codigo));

        if ($codigo === '' || ! preg_match('/^[A-Z0-9]{3,20}$/', $codigo)) {
            return null;
        }

        return Reseller::where('code', $codigo)->where('status', 'aprovado')->first();
    }

    /** O código que o link deixou no browser. */
    public static function doCookie(Request $request): ?string
    {
        $codigo = $request->cookie(self::COOKIE);

        return is_string($codigo) && $codigo !== '' ? strtoupper($codigo) : null;
    }

    /**
     * Liga a empresa, se ainda não tiver revendedor.
     *
     * @return bool se ficou ligada agora
     */
    public static function ligar(Tenant $empresa, Reseller $revendedor, string $via): bool
    {
        if ($empresa->reseller_id || ! $revendedor->aprovado() || ! array_key_exists($via, self::VIAS)) {
            return false;
        }

        $empresa->forceFill([
            'reseller_id' => $revendedor->id,
            'reseller_via' => $via,
            'reseller_linked_at' => now(),
        ])->save();

        return true;
    }
}
