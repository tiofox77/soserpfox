<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Console\Command;

/**
 * Põe a escada dos planos coerente: quem paga mais recebe tudo o que recebe
 * quem paga menos, MAIS alguma coisa. Cada degrau tem de valer o seu preço.
 *
 * O que estava errado:
 *  - O Professional prometia "Contabilidade" na descrição e não a incluía.
 *  - O Business e o Enterprise diziam "Todos os módulos" e ficavam-se por 9
 *    de 14 — faltavam-lhes hotel, salão, restaurante, eventos e avisos.
 *    Consequência: o Pacote Salão (7.900) dava o módulo do salão que o
 *    Enterprise (89.900) não dava.
 *  - Depois de os dois passarem a levar os 14, ficou o erro simétrico e igual
 *    de mau: o Business (44.900) e o Enterprise (89.900) davam EXACTAMENTE os
 *    mesmos módulos. Quem pagasse o dobro não recebia um módulo a mais — só
 *    utilizadores. Um degrau que não acrescenta nada não é um degrau.
 *
 * A regra que separa os dois, e que é a única defensável à frente do cliente:
 *
 *   BUSINESS   — os módulos HORIZONTAIS, os que qualquer empresa usa:
 *                faturação, tesouraria, compras, inventário, contabilidade,
 *                RH, CRM, projetos e avisos.
 *   ENTERPRISE — tudo isso MAIS os módulos de SECTOR: hotel, restaurante,
 *                salão, oficina e eventos.
 *
 * Os PACOTES verticais (salão, hotel, oficina, restaurante) ficam como estão
 * de propósito: são especializados e baratos, não degraus da escada. Quem só
 * quer o salão compra o pacote; quem quer o salão e a casa toda vai ao
 * Enterprise.
 *
 * NOTA IMPORTANTE — isto não tira módulos a ninguém. O acesso é decidido pelo
 * pivô `tenant_module` (Tenant::hasModule), não pelo plano; quem já é cliente
 * Business mantém o que lhe foi activado. A mudança vale para quem entrar de
 * novo ou trocar de plano a partir daqui.
 */
class CoerenciaDosPlanos extends Command
{
    protected $signature = 'planos:coerencia
        {--so-ver : mostra o que mudaria, sem gravar}';

    protected $description = 'Corrige a escada dos planos: cada degrau inclui o de baixo';

    /** A escada, por ordem de preço. Cada degrau tem de conter o anterior. */
    private const ESCADA = ['starter', 'professional', 'business', 'enterprise'];

    /**
     * Os módulos de SECTOR: o que separa o Business do Enterprise.
     *
     * São os que servem um ramo concreto e têm pacote próprio à venda. Um
     * escritório de contabilidade não precisa de housekeeping nem de comandas
     * de mesa; um hotel precisa. É por aqui que o degrau de cima ganha
     * conteúdo em vez de ser só "mais utilizadores".
     */
    private const DE_SECTOR = ['hotel', 'restaurant', 'salon', 'oficina', 'eventos'];

    public function handle(): int
    {
        $soVer = $this->option('so-ver');
        $todos = Module::orderBy('slug')->pluck('id', 'slug');

        if ($todos->isEmpty()) {
            $this->error('Não há módulos no sistema.');
            return self::FAILURE;
        }

        // O Business leva tudo MENOS os de sector; o Enterprise leva tudo.
        // Calculado a partir da tabela e não escrito à mão, para que um módulo
        // novo entre no Enterprise sozinho — e no Business também, a não ser
        // que seja declarado de sector acima.
        $horizontais = $todos->keys()->reject(
            fn ($slug) => in_array($slug, self::DE_SECTOR, true)
        )->values()->all();

        $pretendido = [
            'starter'      => ['invoicing', 'treasury'],
            'professional' => ['invoicing', 'treasury', 'rh', 'inventario', 'contabilidade'],
            'business'     => $horizontais,
            'enterprise'   => $todos->keys()->all(),
        ];

        $linhas = [];

        foreach (self::ESCADA as $slug) {
            $plano = Plan::where('slug', $slug)->first();

            if (!$plano) {
                $this->warn("Plano '{$slug}' não existe — ignorado.");
                continue;
            }

            $antes = $plano->modules()->pluck('modules.slug')->sort()->values()->all();
            $depois = collect($pretendido[$slug])->sort()->values()->all();

            $novos = array_values(array_diff($depois, $antes));
            $tirados = array_values(array_diff($antes, $depois));

            // Tem de tratar REMOÇÕES e não só adições: o Business passou a não
            // levar os módulos de sector, e a versão anterior deste comando —
            // que só gravava quando havia módulos a acrescentar — deixava-o na
            // mesma com os catorze.
            if (!$soVer && (!empty($novos) || !empty($tirados))) {
                // sync (não syncWithoutDetaching): o conjunto pretendido é a
                // verdade, e é preciso que também tire o que sobra.
                $plano->modules()->sync(
                    collect($depois)->map(fn ($s) => $todos[$s])->all()
                );
            }

            $linhas[] = [
                $plano->name,
                count($antes),
                count($depois),
                empty($novos) ? '—' : implode(', ', $novos),
                empty($tirados) ? '—' : implode(', ', $tirados),
            ];
        }

        $this->table(['plano', 'antes', 'depois', 'acrescentados', 'retirados'], $linhas);

        if (!$soVer) {
            $this->corrigirDescricoes();
        }

        // A prova de que a escada ficou coerente: cada degrau contém o anterior
        // e acrescenta-lhe alguma coisa.
        $this->verificarEscada();

        $this->info($soVer ? '(só ver) nada foi gravado.' : 'Escada dos planos coerente.');

        return self::SUCCESS;
    }

    /**
     * As descrições têm de dizer a verdade do que o plano dá.
     *
     * "Todos os módulos incluídos" no Business era falso das duas maneiras:
     * era-o antes (tinha 9 de 14) e voltaria a sê-lo agora, porque os de
     * sector deixaram de lá estar. Quem compra tem de saber o que compra
     * ANTES de comprar, não ao chegar ao ecrã do hotel e não o encontrar.
     */
    private function corrigirDescricoes(): void
    {
        $textos = [
            // O Professional incluía Inventário e não o dizia.
            'professional' => ['Módulos: Faturação + RH + Contabilidade + Inventário'],

            'business' => [
                'Todos os módulos de gestão: Faturação, Tesouraria, Compras, '
                . 'Inventário, Contabilidade, RH, CRM, Projetos e Avisos',
                'Módulos de sector (Hotel, Restaurante, Salão, Oficina, Eventos) — só no Enterprise',
            ],

            'enterprise' => [
                'TODOS os módulos, incluindo os de sector: Hotel, Restaurante, '
                . 'Salão, Oficina e Eventos',
            ],
        ];

        foreach ($textos as $slug => $linhas) {
            $plano = Plan::where('slug', $slug)->first();

            if (!$plano) {
                continue;
            }

            $features = is_array($plano->features)
                ? $plano->features
                : (json_decode($plano->features ?? '[]', true) ?: []);

            // A primeira linha é a dos módulos — é a que a montra mostra em
            // destaque. O resto (utilizadores, suporte, SLA) fica como está.
            array_splice($features, 0, 1, $linhas);

            $plano->features = array_values($features);
            $plano->save();

            $this->line("Descrição do {$plano->name} actualizada.");
        }
    }

    private function verificarEscada(): void
    {
        $anterior = null;
        $anteriorNome = null;

        foreach (self::ESCADA as $slug) {
            $plano = Plan::where('slug', $slug)->first();
            if (!$plano) {
                continue;
            }

            $mods = $plano->modules()->pluck('modules.slug')->all();

            if ($anterior !== null) {
                $emFalta = array_diff($anterior, $mods);
                if (!empty($emFalta)) {
                    $this->error(
                        "INCOERENTE: {$plano->name} não inclui o que {$anteriorNome} inclui — "
                        . implode(', ', $emFalta)
                    );
                }

                // Conter o degrau de baixo não chega: tem de lhe ACRESCENTAR
                // alguma coisa, senão é o mesmo plano a dobro do preço.
                if (empty(array_diff($mods, $anterior))) {
                    $this->error(
                        "INCOERENTE: {$plano->name} dá exactamente os mesmos módulos "
                        . "que {$anteriorNome} — quem paga mais não recebe mais."
                    );
                }
            }

            $anterior = $mods;
            $anteriorNome = $plano->name;
        }
    }
}
