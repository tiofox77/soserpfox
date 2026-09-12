<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PwaController;
use App\Models\PwaDevice;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QUE EMPRESAS USAM O PWA, EM QUE APARELHOS, E EM QUE VERSÃO.
 *
 * Existe porque um deploy do motor podia não chegar aos aparelhos e não havia
 * forma de o saber. A coluna que interessa é a VERSÃO; a linha que interessa é
 * a atrasada.
 *
 *  · SEM VERSÃO DECLARADA CONTA COMO ATRASADO: não saber o que um aparelho
 *    corre é o mesmo problema com outro nome.
 *  · O RESUMO CONTA SEMPRE TUDO, e não o que o filtro deixa ver.
 *  · A PROCURA FICA ENTRE PARÊNTESES. O componente juntava-a com `orWhere`
 *    soltos, e procurar um número de versão com o filtro «instalados» trazia
 *    também os que não estavam instalados.
 */
class AparelhosPwaApiController extends Controller
{
    /** Ao fim de quantos dias sem falar connosco um aparelho conta como adormecido. */
    public const DIAS_ATE_ADORMECER = 14;

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'filtro' => ['nullable', 'in:tudo,atrasados,instalados,adormecidos'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $actual = app(PwaController::class)->buildVersion();
        $limite = now()->subDays(self::DIAS_ATE_ADORMECER);
        $termo = trim((string) ($f['procura'] ?? ''));
        $filtro = $f['filtro'] ?? 'tudo';

        $atrasado = fn ($q) => $q->where(fn ($w) => $w->whereNull('app_version')->orWhere('app_version', '!=', $actual));
        $adormecido = fn ($q) => $q->where(fn ($w) => $w->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $limite));

        $pagina = PwaDevice::query()
            ->with(['tenant:id,name,slug', 'user:id,name,email'])
            ->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('tenant', fn ($t) => $t->where('name', 'like', "%{$termo}%"))
                ->orWhere('device_uuid', 'like', "%{$termo}%")
                ->orWhere('app_version', 'like', "%{$termo}%")))
            ->when($filtro === 'atrasados', $atrasado)
            ->when($filtro === 'instalados', fn ($q) => $q->where('standalone', true))
            ->when($filtro === 'adormecidos', $adormecido)
            ->orderByDesc('last_seen_at')
            ->paginate(25, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'versao_actual' => $actual,
            'dias_ate_adormecer' => self::DIAS_ATE_ADORMECER,
            'resumo' => [
                'aparelhos' => PwaDevice::count(),
                'empresas' => PwaDevice::query()->distinct()->count('tenant_id'),
                'instalados' => PwaDevice::where('standalone', true)->count(),
                'atrasados' => $atrasado(PwaDevice::query())->count(),
                'adormecidos' => $adormecido(PwaDevice::query())->count(),
            ],
            'aparelhos' => collect($pagina->items())->map(fn (PwaDevice $a) => [
                'id' => $a->id,
                'empresa' => $a->tenant?->name,
                'aparelho' => $a->device_uuid,
                'versao' => $a->app_version,
                'atrasado' => $a->app_version !== $actual,
                'instalado' => (bool) $a->standalone,
                'plataforma' => $a->platform,
                'utilizador' => $a->user?->name,
                'email' => $a->user?->email,
                'visto_em' => $a->last_seen_at?->toIso8601String(),
                'adormecido' => ! $a->last_seen_at || $a->last_seen_at->lt($limite),
                'sincronizacoes' => (int) $a->syncs,
            ]),
            // Quem paga pelo módulo e nunca abriu o PWA não aparece em lista
            // nenhuma de aparelhos — e é exactamente a esse que vale ligar.
            'com_modulo_sem_aparelho' => Tenant::query()
                ->whereHas('modules', fn ($m) => $m->where('modules.slug', 'invoicing'))
                ->whereNotIn('id', PwaDevice::query()->select('tenant_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Tenant $t) => ['id' => $t->id, 'nome' => $t->name]),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }
}
