<?php

namespace App\Services\Revenda;

use App\Models\AuditTrail;
use App\Models\Reseller;
use App\Models\Tenant;

/**
 * A EMPRESA AUTORIZA O REVENDEDOR A ENTRAR (21/09/2026).
 *
 * Decisão do utilizador: o revendedor pode entrar nas empresas que trouxe,
 * como o super admin, para dar suporte — MAS SÓ nas que o autorizarem. O
 * revendedor é uma pessoa de fora da plataforma, e entrar como a empresa dá
 * acesso a tudo dela: facturas, clientes, tesouraria, salários. Quem decide
 * abrir essa porta é quem gere a empresa, e não o revendedor.
 *
 * DESLIGADO POR OMISSÃO. A escolha vive em `tenants.settings`, que já é um
 * array: sem coluna nova, sem migração.
 *
 * E a empresa vê quando ele entrou: as entradas saem da trilha de auditoria,
 * que é append-only e onde ninguém as apaga.
 */
class SuporteDoRevendedor
{
    public const CHAVE = 'suporte_do_revendedor';

    /** Os eventos da trilha que contam como «o revendedor esteve cá». */
    public const EVENTOS = [
        'personificacao.revendedor.entrou',
        'personificacao.revendedor.saiu',
        'personificacao.revendedor.expirou',
    ];

    /** A empresa deixa o SEU revendedor entrar? Sem revendedor, nunca. */
    public static function permitido(Tenant $empresa): bool
    {
        return $empresa->reseller_id !== null
            && (bool) (($empresa->settings ?? [])[self::CHAVE] ?? false);
    }

    public static function definir(Tenant $empresa, bool $permitir): void
    {
        $definicoes = $empresa->settings ?? [];
        $definicoes[self::CHAVE] = $permitir;

        // Só esta chave muda; o resto do `settings` fica como estava.
        $empresa->settings = $definicoes;
        $empresa->save();
    }

    /** O revendedor da empresa, como a empresa o vê. */
    public static function revendedor(Tenant $empresa): ?array
    {
        $r = $empresa->reseller_id ? Reseller::find($empresa->reseller_id) : null;

        return $r ? ['nome' => $r->nomeVisivel(), 'codigo' => $r->code, 'email' => $r->email] : null;
    }

    /**
     * As vezes que o revendedor esteve cá — da trilha, as mais recentes
     * primeiro. Uma entrada e a saída correspondente aparecem as duas: a saída
     * traz quanto tempo durou.
     *
     * @return list<array{evento: string, quando: string, pessoa: ?string, duracao_em_segundos: ?int, motivo: ?string}>
     */
    public static function entradas(Tenant $empresa, int $limite = 20): array
    {
        return AuditTrail::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->whereIn('event', self::EVENTOS)
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->map(function ($linha) {
                // Os actos da auditoria guardam o que dizem em `metadata`, não
                // em `new_values` (esse é dos modelos que mudam).
                $dados = (array) ($linha->metadata ?? []);

                return [
                    'evento' => $linha->event,
                    'quando' => $linha->created_at?->toIso8601String(),
                    'pessoa' => $dados['utilizador']['nome'] ?? null,
                    'duracao_em_segundos' => isset($dados['duracao_em_segundos']) ? (int) $dados['duracao_em_segundos'] : null,
                    'motivo' => $dados['motivo'] ?? null,
                ];
            })->values()->all();
    }
}
