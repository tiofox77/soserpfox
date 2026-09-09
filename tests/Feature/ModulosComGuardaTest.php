<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * NENHUM MÓDULO VOLTA A FICAR SEM GUARDA.
 *
 * O levantamento que abriu este trabalho: **71 rotas** em cinco módulos —
 * hotel, salão, oficina, contabilidade e eventos — corriam com
 * `['auth', 'tenant.module:x']` e mais nada. As permissões existiam, apareciam
 * no ecrã de papéis, e nenhuma rota as exigia.
 *
 * Este ensaio é o travão. Uma rota nova sem `permission:` num destes módulos
 * põe a suite vermelha, e quem a escreve tem de decidir a permissão — ou
 * declarar aqui, por escrito, que é pública de propósito.
 *
 * Não vale para tudo: há módulos que ainda não passaram por esta limpeza, e
 * pô-los aqui era pôr a suite vermelha por trabalho que ainda não foi feito.
 * A lista cresce à medida que cada um é fechado.
 */
class ModulosComGuardaTest extends TenantTestCase
{
    /** Os módulos já fechados. Cresce um de cada vez. */
    private const FECHADOS = ['hotel', 'salon', 'workshop', 'accounting', 'events'];

    /**
     * AS ROTAS PÚBLICAS DE PROPÓSITO — e porquê.
     *
     * Todas elas identificam a empresa por outra coisa que não a sessão. Uma
     * entrada nova aqui é uma decisão, e tem de trazer a razão.
     */
    private const PUBLICAS = [
        // O check-in por QR: quem autoriza é o `confirmation_code` do URL. O
        // hóspede lê o código no telemóvel e não tem conta nenhuma.
        'hotel/reservations/{id}/checkin/{code}',
        'hotel/reservations/{id}/checkin/{code}/confirm',

        // A página de reservas do hotel: quem escolhe a empresa é o SLUG.
        'hotel/booking/{slug}',
    ];

    /** @test */
    public function nenhuma_rota_destes_modulos_corre_sem_permissao(): void
    {
        $semGuarda = [];

        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();

            if (! preg_match('#^(' . implode('|', self::FECHADOS) . ')/#', $uri)) {
                continue;
            }

            if (in_array($uri, self::PUBLICAS, true)) {
                continue;
            }

            $temGuarda = collect($rota->gatherMiddleware())
                ->contains(fn ($m) => str_starts_with($m, 'permission:'));

            if (! $temGuarda) {
                $semGuarda[] = $rota->methods()[0] . ' ' . $uri;
            }
        }

        $this->assertSame(
            [],
            $semGuarda,
            "Rotas sem permissão nos módulos já fechados:\n  " . implode("\n  ", $semGuarda)
                . "\n\nOu se lhes dá a permissão, ou se declara em PUBLICAS com a razão."
        );
    }

    /**
     * E O MENU NÃO OFERECE O QUE A ROTA RECUSA.
     *
     * Uma entrada de menu sem permissão, para uma rota que a exige, manda o
     * utilizador para um 403 — que se lê como avaria do sistema quando é a
     * guarda a funcionar.
     */
    /** @test */
    public function o_menu_nao_oferece_o_que_a_rota_recusa(): void
    {
        // A permissão que cada rota exige, pela rota.
        $exigidas = [];

        foreach (Route::getRoutes() as $rota) {
            $nome = $rota->getName();

            if (! $nome || ! preg_match('#^(' . implode('|', self::FECHADOS) . ')\.#', $nome)) {
                continue;
            }

            foreach ($rota->gatherMiddleware() as $m) {
                if (str_starts_with($m, 'permission:')) {
                    $exigidas[$nome] = substr($m, strlen('permission:'));
                    break;
                }
            }
        }

        $descobertas = [];

        foreach ($this->entradasDoMenu() as $entrada) {
            $rota = $entrada['rota'] ?? null;

            if (! $rota || ! isset($exigidas[$rota])) {
                continue;
            }

            if (($entrada['permissao'] ?? null) === null) {
                $descobertas[] = $rota . ' (a rota pede ' . $exigidas[$rota] . ')';
            }
        }

        $this->assertSame(
            [],
            $descobertas,
            "Entradas de menu sem a permissão que a rota exige:\n  " . implode("\n  ", $descobertas)
        );
    }

    /**
     * As entradas do esquema do menu, sem as resolver para um utilizador.
     *
     * @return array<int, array<string, mixed>>
     */
    private function entradasDoMenu(): array
    {
        $metodo = new \ReflectionMethod(\App\Support\MenuDaCasca::class, 'grupos');
        $metodo->setAccessible(true);

        $entradas = [];

        foreach ($metodo->invoke(null) as $grupo) {
            foreach ($grupo['entradas'] ?? [] as $entrada) {
                if (isset($entrada['sub'])) {
                    foreach ($entrada['sub'] as $sub) {
                        $entradas[] = $sub;
                    }

                    continue;
                }

                $entradas[] = $entrada;
            }
        }

        return $entradas;
    }
}
