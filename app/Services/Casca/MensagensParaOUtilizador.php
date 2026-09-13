<?php

namespace App\Services\Casca;

use App\Models\PlatformMessage;
use App\Models\PlatformMessageRead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AS MENSAGENS DO DONO DA PLATAFORMA, do lado de quem as recebe.
 *
 * Duas formas de as ver, com a mesma pergunta por baixo:
 *
 *  · A BARRA E O POP-UP (em todas as páginas) mostram só o que ainda não foi
 *    dispensado, e marcam como vistas as que aparecem.
 *  · O PAINEL DO ECRÃ DE ENTRADA mostra TUDO o que está no ar para esta
 *    empresa, incluindo o que já foi dispensado — é o sítio a que se volta
 *    para reler o número do suporte depois de fechar a barra.
 *
 * Corre em todas as páginas: as mensagens no ar ficam um minuto em cache, e
 * sem mensagens o custo é uma leitura de cache. E NÃO REBENTA — uma mensagem
 * da plataforma não pode derrubar o sistema.
 */
class MensagensParaOUtilizador
{
    public const CACHE = 'mensagens-plataforma-no-ar';

    private const SEGUNDOS_EM_CACHE = 60;

    /** As que ainda não dispensou — e ficam registadas como vistas. */
    public function porMostrar(User $user): Collection
    {
        try {
            [$paraEle, $empresa] = $this->noArPara($user);

            if ($paraEle->isEmpty()) {
                return collect();
            }

            $dispensadas = $this->dispensadas($user, $paraEle);
            $mostrar = $paraEle->reject(fn ($m) => isset($dispensadas[$m->id]))->values();

            foreach ($mostrar as $m) {
                try {
                    // firstOrCreate: não se mexe na dispensa de quem já a tinha.
                    PlatformMessageRead::firstOrCreate(
                        ['platform_message_id' => $m->id, 'user_id' => $user->id],
                        ['tenant_id' => $empresa?->id, 'seen_at' => now()]
                    );
                } catch (\Throwable) {
                    // Não se esconde a mensagem por não se conseguir registar a vista.
                }
            }

            return $mostrar->map(fn (PlatformMessage $m) => $this->comoLinha($m, false));
        } catch (\Throwable $e) {
            Log::warning('Mensagens da plataforma indisponíveis', ['erro' => $e->getMessage()]);

            return collect();
        }
    }

    /** Tudo o que está no ar para esta empresa, com as já dispensadas marcadas. */
    public function paraOPainel(User $user): Collection
    {
        try {
            [$paraEle] = $this->noArPara($user);

            if ($paraEle->isEmpty()) {
                return collect();
            }

            $dispensadas = $this->dispensadas($user, $paraEle);

            return $paraEle->map(fn (PlatformMessage $m) => $this->comoLinha($m, isset($dispensadas[$m->id])));
        } catch (\Throwable $e) {
            Log::warning('Painel de avisos da plataforma indisponível', ['erro' => $e->getMessage()]);

            return collect();
        }
    }

    public function dispensar(User $user, int $id): void
    {
        $mensagem = PlatformMessage::find($id);

        // Uma que não se dispensa não se dispensa por aqui também.
        if (! $mensagem || ! $mensagem->dismissible) {
            return;
        }

        PlatformMessageRead::updateOrCreate(
            ['platform_message_id' => $id, 'user_id' => $user->id],
            ['tenant_id' => function_exists('activeTenantId') ? activeTenantId() : null, 'seen_at' => now(), 'dismissed_at' => now()]
        );
    }

    /** @return array{0: Collection, 1: ?\App\Models\Tenant} */
    private function noArPara(User $user): array
    {
        $noAr = Cache::remember(self::CACHE, self::SEGUNDOS_EM_CACHE, fn () => PlatformMessage::noAr()->orderByDesc('id')->get());

        if ($noAr->isEmpty()) {
            return [collect(), null];
        }

        $empresa = method_exists($user, 'activeTenant') ? $user->activeTenant() : null;

        return [$noAr->filter(fn ($m) => $m->ehPara($empresa))->values(), $empresa];
    }

    private function dispensadas(User $user, Collection $mensagens): array
    {
        return PlatformMessageRead::where('user_id', $user->id)
            ->whereIn('platform_message_id', $mensagens->pluck('id'))
            ->whereNotNull('dismissed_at')
            ->pluck('platform_message_id')
            ->flip()
            ->all();
    }

    private function comoLinha(PlatformMessage $m, bool $dispensada): array
    {
        $estilo = $m->estilo();

        return [
            'id' => $m->id,
            'titulo' => $m->title,
            'corpo' => $m->body,
            'nivel' => $m->level,
            'cor' => $estilo['cor'],
            'icone' => $estilo['icone'],
            'forma' => $m->display,
            'dispensavel' => (bool) $m->dismissible,
            'ligacao' => $m->link_url,
            'texto_da_ligacao' => $m->link_label,
            // No relógio de parede de Angola: a hora que foi escrita.
            'termina' => $m->noRelogioDeParede('ends_at')?->format('d/m/Y H:i'),
            'dispensada' => $dispensada,
        ];
    }
}
