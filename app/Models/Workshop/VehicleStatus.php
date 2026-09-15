<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * UM ESTADO DE VIATURA — o catálogo que cada oficina gere (15/09/2026).
 *
 * A viatura guarda o CÓDIGO (`workshop_vehicles.status`), que não muda quando
 * se renomeia o estado: «Em serviço» pode passar a «Na oficina» sem mexer em
 * nenhuma viatura. O código nasce do nome, uma vez.
 *
 * `accepts_orders` é a pergunta que o código fazia com `status = 'active'`:
 * pode esta viatura receber uma ordem de serviço nova? Uma viatura abatida ou
 * vendida não aparece na lista das ordens.
 */
class VehicleStatus extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_vehicle_statuses';

    protected $fillable = ['tenant_id', 'code', 'name', 'color', 'accepts_orders', 'is_default', 'is_active', 'sort_order'];

    protected $casts = [
        'accepts_orders' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // A lista lida fica em memória durante o pedido; quem a muda, esquece-a.
        static::saved(fn () => self::esquecer());
        static::deleted(fn () => self::esquecer());
    }

    /** As cores que o ecrã sabe desenhar (etiqueta na tabela). */
    public const CORES = ['verde', 'azul', 'ambar', 'laranja', 'teal', 'roxo', 'vermelho', 'cinza'];

    /**
     * Os estados com que uma oficina nasce. Os quatro primeiros códigos são os
     * de sempre — as viaturas que já existem continuam a apontar para eles.
     */
    public const PADROES = [
        ['code' => 'active', 'name' => 'Activa', 'color' => 'verde', 'accepts_orders' => true, 'is_default' => true],
        ['code' => 'in_service', 'name' => 'Em serviço', 'color' => 'azul', 'accepts_orders' => true, 'is_default' => false],
        ['code' => 'aguarda_orcamento', 'name' => 'Aguarda orçamento', 'color' => 'ambar', 'accepts_orders' => true, 'is_default' => false],
        ['code' => 'aguarda_pecas', 'name' => 'Aguarda peças', 'color' => 'laranja', 'accepts_orders' => true, 'is_default' => false],
        ['code' => 'pronta_entrega', 'name' => 'Pronta para entrega', 'color' => 'teal', 'accepts_orders' => true, 'is_default' => false],
        ['code' => 'completed', 'name' => 'Concluída', 'color' => 'roxo', 'accepts_orders' => true, 'is_default' => false],
        ['code' => 'inactive', 'name' => 'Inactiva', 'color' => 'cinza', 'accepts_orders' => false, 'is_default' => false],
        ['code' => 'abatida', 'name' => 'Abatida / vendida', 'color' => 'vermelho', 'accepts_orders' => false, 'is_default' => false],
    ];

    /** @var array<int, \Illuminate\Support\Collection> */
    private static array $daEmpresa = [];

    /**
     * Uma oficina sem estados nenhuns recebe os padrões — e só essa: quem apagou
     * «Abatida» de propósito não a vê voltar.
     */
    public static function garantirCatalogo(?int $tenantId): void
    {
        if (! $tenantId || static::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::PADROES as $i => $p) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $p['code']],
                $p + ['tenant_id' => $tenantId, 'is_active' => true, 'sort_order' => $i + 1]
            );
        }

        unset(self::$daEmpresa[$tenantId]);
    }

    /** Os estados da empresa, por ordem (com os inactivos, para os rótulos antigos). */
    public static function daEmpresa(int $tenantId)
    {
        return self::$daEmpresa[$tenantId] ??= static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    /**
     * As opções de uma lista: os activos, mais o de uma viatura que ainda
     * aponte para um desactivado (senão a lista abria em branco e gravar trocava-o).
     *
     * @return list<array{valor: string, rotulo: string, cor: string}>
     */
    public static function opcoesDe(int $tenantId, ?string $actual = null): array
    {
        return self::daEmpresa($tenantId)
            ->filter(fn (self $e) => $e->is_active || $e->code === $actual)
            ->map(fn (self $e) => ['valor' => $e->code, 'rotulo' => $e->name, 'cor' => $e->color])
            ->values()->all();
    }

    /** Todos os códigos com rótulo — para mostrar mesmo os desactivados. */
    public static function todosDe(int $tenantId): array
    {
        return self::daEmpresa($tenantId)
            ->map(fn (self $e) => ['valor' => $e->code, 'rotulo' => $e->name, 'cor' => $e->color])
            ->values()->all();
    }

    /** Os códigos em que uma viatura pode receber uma ordem nova. */
    public static function aceitamOrdens(int $tenantId): array
    {
        $estados = self::daEmpresa($tenantId);

        // Uma oficina sem catálogo nenhum continua a funcionar como sempre.
        return $estados->isEmpty()
            ? ['active', 'in_service', 'completed']
            : $estados->where('accepts_orders', true)->pluck('code')->all();
    }

    public static function padraoDe(int $tenantId): string
    {
        return self::daEmpresa($tenantId)->where('is_active', true)->firstWhere('is_default', true)?->code ?? 'active';
    }

    /** Um código novo e livre a partir do nome. */
    public static function codigoPara(int $tenantId, string $nome): string
    {
        $base = Str::limit(Str::slug($nome, '_'), 34, '') ?: 'estado';
        $codigo = $base;
        $n = 2;

        while (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $codigo)->exists()) {
            $codigo = $base . '_' . $n++;
        }

        return $codigo;
    }

    public static function esquecer(): void
    {
        self::$daEmpresa = [];
    }
}
