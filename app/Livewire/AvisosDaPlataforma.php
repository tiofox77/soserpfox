<?php

namespace App\Livewire;

use App\Models\PlatformMessage;
use App\Models\PlatformMessageRead;
use Livewire\Component;

/**
 * O painel dos avisos do dono da plataforma, no ecrã de entrada.
 *
 * A barra do topo — [[MensagensDaPlataforma]] — é passageira por desenho:
 * aparece, dá para dispensar, e desaparece para sempre. Serve para interromper
 * quem está a trabalhar. O problema é que uma vez dispensada não havia forma
 * nenhuma de voltar a ler o aviso, nem sequer de saber que tinha existido: o
 * número de telefone do suporte, a data de uma paragem, o que fosse.
 *
 * Este painel é o outro lado: fica quieto, não interrompe, não se dispensa, e
 * mostra TUDO o que está no ar para esta empresa — incluindo o que já foi
 * dispensado na barra, marcado como lido. É o sítio a que se volta.
 *
 * Vive só no ecrã de entrada e não no layout, ao contrário da barra. Não tem
 * de ser barato ao ponto de correr em todas as páginas, e por isso pode dar-se
 * ao luxo de mostrar também as dispensadas.
 */
class AvisosDaPlataforma extends Component
{
    private const SEGUNDOS_EM_CACHE = 60;

    public function render()
    {
        try {
            [$avisos, $dispensados] = $this->paraEstaEmpresa();
        } catch (\Throwable $e) {
            // Pela mesma razão da barra: um aviso não pode derrubar o ecrã de
            // entrada. Sem avisos, o painel não se desenha.
            \Log::warning('Painel de avisos da plataforma indisponível', ['erro' => $e->getMessage()]);
            $avisos = collect();
            $dispensados = [];
        }

        return view('livewire.avisos-da-plataforma', compact('avisos', 'dispensados'));
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: array<int,bool>} */
    private function paraEstaEmpresa(): array
    {
        $utilizador = auth()->user();

        if (!$utilizador) {
            return [collect(), []];
        }

        // A mesma chave de cache da barra: é a mesma pergunta, e são as duas
        // limpas quando se publica uma mensagem nova.
        $noAr = \Cache::remember(
            'mensagens-plataforma-no-ar',
            self::SEGUNDOS_EM_CACHE,
            fn () => PlatformMessage::noAr()->orderByDesc('id')->get()
        );

        if ($noAr->isEmpty()) {
            return [collect(), []];
        }

        $empresa = method_exists($utilizador, 'activeTenant') ? $utilizador->activeTenant() : null;

        $avisos = $noAr->filter(fn ($m) => $m->ehPara($empresa))->values();

        if ($avisos->isEmpty()) {
            return [collect(), []];
        }

        // Quais é que ele já dispensou na barra. Continuam a aparecer aqui,
        // mas discretas: o painel é para consultar, não para voltar a avisar.
        $dispensados = PlatformMessageRead::where('user_id', $utilizador->id)
            ->whereIn('platform_message_id', $avisos->pluck('id'))
            ->whereNotNull('dismissed_at')
            ->pluck('platform_message_id')
            ->flip()
            ->map(fn () => true)
            ->all();

        return [$avisos, $dispensados];
    }
}
