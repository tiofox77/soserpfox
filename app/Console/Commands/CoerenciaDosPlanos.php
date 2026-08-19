<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Console\Command;

/**
 * Põe a escada dos planos coerente: quem paga mais recebe, no mínimo,
 * tudo o que recebe quem paga menos.
 *
 * O que estava errado:
 *  - O Professional prometia "Contabilidade" na descrição e não a incluía.
 *  - O Business e o Enterprise diziam "Todos os módulos" e ficavam-se por 9
 *    de 14 — faltavam-lhes hotel, salão, restaurante, eventos e avisos.
 *  - Consequência: o Pacote Salão (7.900) dava o módulo do salão que o
 *    Enterprise (89.900) não dava. Um plano de baixo com mais do que um de
 *    cima.
 *
 * Os PACOTES verticais (salão, hotel, oficina, restaurante) ficam como
 * estão de propósito: são especializados e baratos, não degraus da escada.
 * O que se corrige é a escada — Starter → Professional → Business →
 * Enterprise — e a verdade das descrições.
 */
class CoerenciaDosPlanos extends Command
{
    protected $signature = 'planos:coerencia
        {--so-ver : mostra o que mudaria, sem gravar}';

    protected $description = 'Corrige a escada dos planos: cada degrau inclui o de baixo';

    /** A escada, por ordem de preço. Cada degrau tem de conter o anterior. */
    private const ESCADA = ['starter', 'professional', 'business', 'enterprise'];

    public function handle(): int
    {
        $soVer = $this->option('so-ver');
        $todos = Module::orderBy('slug')->pluck('id', 'slug');

        if ($todos->isEmpty()) {
            $this->error('Não há módulos no sistema.');
            return self::FAILURE;
        }

        // O que cada degrau passa a incluir. Os de cima levam tudo — é o que
        // as próprias descrições prometem ("Todos os módulos").
        $pretendido = [
            'starter'      => ['invoicing', 'treasury'],
            'professional' => ['invoicing', 'treasury', 'rh', 'inventario', 'contabilidade'],
            'business'     => $todos->keys()->all(),
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

            if (!$soVer && !empty($novos)) {
                // sync (não syncWithoutDetaching): o conjunto pretendido é a
                // verdade. Nenhum degrau perde módulos — o pretendido contém
                // sempre o que já lá estava.
                $plano->modules()->sync(
                    collect($depois)->map(fn ($s) => $todos[$s])->all()
                );
            }

            $linhas[] = [
                $plano->name,
                count($antes),
                count($depois),
                empty($novos) ? '—' : implode(', ', $novos),
            ];
        }

        $this->table(['plano', 'módulos antes', 'depois', 'acrescentados'], $linhas);

        // A descrição do Professional prometia Contabilidade; agora é verdade,
        // mas o texto não dizia o Inventário que ele já tinha.
        $prof = Plan::where('slug', 'professional')->first();
        if ($prof && !$soVer) {
            $features = is_array($prof->features) ? $prof->features : (json_decode($prof->features ?? '[]', true) ?: []);
            if (!empty($features)) {
                $features[0] = 'Módulos: Faturação + RH + Contabilidade + Inventário';
                $prof->features = $features;
                $prof->save();
                $this->line('Descrição do Professional actualizada (incluía Inventário e não o dizia).');
            }
        }

        // A prova de que a escada ficou coerente: cada degrau contém o anterior.
        $this->verificarEscada();

        $this->info($soVer ? '(só ver) nada foi gravado.' : 'Escada dos planos coerente.');

        return self::SUCCESS;
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
            }

            $anterior = $mods;
            $anteriorNome = $plano->name;
        }
    }
}
