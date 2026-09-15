<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Tenant;

/**
 * O QUE UM CLIENTE VÊ NO PORTAL — as áreas, por módulo.
 *
 * Pedido de 15/09/2026: cada cliente vê só os dados do módulo de onde é cliente
 * — o dono de um carro vê a oficina, não os eventos — e, se a empresa tiver mais
 * de um módulo, ela escolhe na ficha do cliente quais ele vê. Menos confusão
 * para quem entra.
 *
 * A área só aparece se a EMPRESA tiver o módulo activo e o CLIENTE a tiver
 * marcada. Tirar o módulo à empresa tira a área a todos os clientes, sem mexer
 * na escolha gravada (voltando o módulo, volta a área).
 *
 * AS FACTURAS SEGUEM AS ÁREAS. «Facturas e extracto» mostra todas; sem ela, o
 * cliente vê só as facturas que saíram dos módulos que vê
 * (`source_module` = oficina, hotel). Um cliente só da oficina não vê a
 * factura do jantar de gala. O salão fica de fora enquanto as facturas dele não
 * disserem de onde vêm: uma área sempre vazia só confunde.
 */
class PortalDoCliente
{
    public const SECCOES = [
        'facturacao' => [
            'modulo' => 'invoicing',
            'rotulo' => 'Facturas, proformas e extracto',
            'descricao' => 'Todas as facturas, as proformas e o extracto de conta.',
            'icone' => 'fa-file-invoice',
            'origem' => null,
        ],
        'oficina' => [
            'modulo' => 'oficina',
            'rotulo' => 'Oficina',
            'descricao' => 'As viaturas, o estado do carro, as folhas de obra e as facturas da oficina.',
            'icone' => 'fa-car',
            'origem' => 'oficina',
        ],
        'hotel' => [
            'modulo' => 'hotel',
            'rotulo' => 'Hotel',
            'descricao' => 'As facturas das estadias.',
            'icone' => 'fa-hotel',
            'origem' => 'hotel',
        ],
        'eventos' => [
            'modulo' => 'eventos',
            'rotulo' => 'Eventos',
            'descricao' => 'Os eventos contratados, a fase e o progresso.',
            'icone' => 'fa-calendar-days',
            'origem' => null,
        ],
    ];

    /** O portal de sempre, para quem tinha acesso antes de haver escolha. */
    public const DE_SEMPRE = ['facturacao', 'eventos'];

    /** @var array<int, list<string>> */
    private static array $daEmpresa = [];

    /**
     * As áreas que esta empresa pode dar — as dos módulos que tem activos.
     *
     * @return list<string>
     */
    public static function disponiveis(?Tenant $empresa): array
    {
        if (! $empresa) {
            return [];
        }

        return self::$daEmpresa[$empresa->id] ??= array_values(array_filter(
            array_keys(self::SECCOES),
            fn (string $s) => (bool) $empresa->hasModule(self::SECCOES[$s]['modulo'])
        ));
    }

    /**
     * As áreas que ESTE cliente vê: as marcadas na ficha (ou as de sempre) que a
     * empresa ainda pode dar.
     *
     * @return list<string>
     */
    public static function doCliente(Client $cliente): array
    {
        $empresa = Tenant::find($cliente->tenant_id);
        $escolhidas = is_array($cliente->portal_modulos) ? $cliente->portal_modulos : self::DE_SEMPRE;

        return array_values(array_intersect(self::disponiveis($empresa), $escolhidas));
    }

    public static function ve(Client $cliente, string $seccao): bool
    {
        return in_array($seccao, self::doCliente($cliente), true);
    }

    /** Vê facturas de alguma forma — todas, ou as de um módulo. */
    public static function veFacturas(Client $cliente): bool
    {
        return (bool) array_intersect(self::doCliente($cliente), ['facturacao', 'oficina', 'hotel']);
    }

    /**
     * De que módulos são as facturas que o cliente vê. `null` = todas.
     *
     * @return list<string>|null
     */
    public static function origensDasFacturas(Client $cliente): ?array
    {
        $seccoes = self::doCliente($cliente);

        if (in_array('facturacao', $seccoes, true)) {
            return null;
        }

        return array_values(array_filter(array_map(fn ($s) => self::SECCOES[$s]['origem'], $seccoes)));
    }

    /** As áreas para o ecrã (ficha do cliente e menu do portal). */
    public static function paraEcra(array $chaves): array
    {
        return array_map(fn (string $s) => [
            'chave' => $s,
            'rotulo' => __(self::SECCOES[$s]['rotulo']),
            'descricao' => __(self::SECCOES[$s]['descricao']),
            'icone' => self::SECCOES[$s]['icone'],
        ], $chaves);
    }

    /** Para os ensaios: esquecer as empresas lidas. */
    public static function esquecer(): void
    {
        self::$daEmpresa = [];
    }
}
