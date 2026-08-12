<?php

namespace App\Livewire;

use App\Models\PlatformMessage;
use App\Models\PlatformMessageRead;
use Livewire\Component;

/**
 * Mostra a quem usa o sistema as mensagens do dono da plataforma.
 *
 * Vive no layout, portanto corre em TODAS as páginas de TODOS os
 * utilizadores. Duas consequências que mandam no desenho:
 *
 *   · tem de ser barato. As mensagens no ar são poucas (zero, quase sempre) e
 *     ficam em cache por um minuto; sem mensagens, o custo é uma leitura de
 *     cache e mais nada;
 *   · não pode rebentar. Se isto falhar, falha o sistema inteiro — por isso
 *     o render devolve vazio em vez de deixar subir a excepção.
 *
 * Só se grava alguma coisa quando alguém vê ou dispensa: uma mensagem para
 * todas as empresas não escreve uma única linha até ser aberta.
 */
class MensagensDaPlataforma extends Component
{
    /** Ids já dispensados nesta visita, para sumirem sem esperar pelo servidor. */
    public array $dispensadas = [];

    private const SEGUNDOS_EM_CACHE = 60;

    public function dispensar(int $id): void
    {
        $this->dispensadas[] = $id;

        $utilizador = auth()->user();

        if (!$utilizador) {
            return;
        }

        try {
            PlatformMessageRead::updateOrCreate(
                ['platform_message_id' => $id, 'user_id' => $utilizador->id],
                [
                    'tenant_id'    => function_exists('activeTenantId') ? activeTenantId() : null,
                    'seen_at'      => now(),
                    'dismissed_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Não foi possível registar a dispensa da mensagem', [
                'mensagem' => $id,
                'erro'     => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        try {
            $mensagens = $this->porMostrar();
        } catch (\Throwable $e) {
            // Uma mensagem da plataforma não pode derrubar o sistema todo.
            \Log::warning('Mensagens da plataforma indisponíveis', ['erro' => $e->getMessage()]);
            $mensagens = collect();
        }

        return view('livewire.mensagens-da-plataforma', compact('mensagens'));
    }

    private function porMostrar()
    {
        $utilizador = auth()->user();

        if (!$utilizador) {
            return collect();
        }

        // As mensagens no ar mudam raramente; a consulta corre em cada página.
        $noAr = \Cache::remember(
            'mensagens-plataforma-no-ar',
            self::SEGUNDOS_EM_CACHE,
            fn () => PlatformMessage::noAr()->orderByDesc('id')->get()
        );

        if ($noAr->isEmpty()) {
            return collect();
        }

        $empresa = method_exists($utilizador, 'activeTenant') ? $utilizador->activeTenant() : null;

        $paraEle = $noAr->filter(fn ($m) => $m->ehPara($empresa));

        if ($paraEle->isEmpty()) {
            return collect();
        }

        // O que ele já dispensou.
        $dispensadasNaBase = PlatformMessageRead::where('user_id', $utilizador->id)
            ->whereIn('platform_message_id', $paraEle->pluck('id'))
            ->whereNotNull('dismissed_at')
            ->pluck('platform_message_id')
            ->all();

        $fora = array_merge($dispensadasNaBase, $this->dispensadas);

        $mostrar = $paraEle->reject(fn ($m) => in_array($m->id, $fora, true))->values();

        // Marcar como vistas as que vão aparecer agora — sem tocar na coluna
        // de dispensa de quem já a tinha.
        foreach ($mostrar as $mensagem) {
            try {
                PlatformMessageRead::firstOrCreate(
                    ['platform_message_id' => $mensagem->id, 'user_id' => $utilizador->id],
                    [
                        'tenant_id' => $empresa?->id,
                        'seen_at'   => now(),
                    ]
                );
            } catch (\Throwable) {
                // Não vale a pena esconder a mensagem por não se conseguir
                // registar que ela foi vista.
            }
        }

        return $mostrar;
    }
}
