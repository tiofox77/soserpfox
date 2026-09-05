<?php

namespace App\Livewire\SuperAdmin;

use App\Http\Controllers\PwaController;
use App\Models\PwaDevice;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Que empresas usam o PWA, em que aparelhos, e em que versão.
 *
 * PORQUE ESTE ECRÃ EXISTE. Descobriu-se, a testar num Android, que um deploy
 * do motor podia não chegar aos aparelhos — e, pior, que não havia forma
 * nenhuma de o saber. Produção tinha a correcção, o telemóvel corria a versão
 * de antes, e só se percebeu por acaso.
 *
 * A causa está corrigida. Isto trata da cegueira: a coluna que interessa é a
 * VERSÃO, e a linha que interessa é a que está atrasada.
 *
 * O que se responde aqui, por ordem:
 *
 *   1. quantas empresas têm mesmo o PWA a ser usado (e não só o módulo);
 *   2. quantos aparelhos estão ATRASADOS — os que não receberam a última;
 *   3. quantos estão INSTALADOS no ecrã principal, que são os que mais tempo
 *      podem ficar presos numa versão antiga;
 *   4. quais adormeceram — deixaram de falar connosco.
 */
#[Layout('layouts.superadmin')]
class AparelhosPwa extends Component
{
    use WithPagination;

    public string $pesquisa = '';

    /** tudo | atrasados | instalados | adormecidos */
    public string $filtro = 'tudo';

    /** Ao fim de quantos dias sem falar connosco um aparelho conta como adormecido. */
    public const DIAS_ATE_ADORMECER = 14;

    public function updatingPesquisa(): void
    {
        $this->resetPage();
    }

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    /** A versão que o servidor está a servir AGORA. É a régua de tudo o resto. */
    public function getVersaoActualProperty(): string
    {
        return app(PwaController::class)->buildVersion();
    }

    public function render()
    {
        $actual = $this->versaoActual;
        $limiteAdormecido = now()->subDays(self::DIAS_ATE_ADORMECER);

        $consulta = PwaDevice::query()
            ->with(['tenant:id,name,slug', 'user:id,name,email'])
            ->when($this->pesquisa !== '', function ($q) {
                $termo = '%' . trim($this->pesquisa) . '%';

                $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', $termo))
                    ->orWhere('device_uuid', 'like', $termo)
                    ->orWhere('app_version', 'like', $termo);
            })
            ->when($this->filtro === 'atrasados', fn ($q) => $q->where(function ($w) use ($actual) {
                // Sem versão declarada conta como atrasado: ou é anterior a
                // isto existir, ou não está a dizer — e nos dois casos não se
                // sabe o que corre, que é o mesmo problema.
                $w->whereNull('app_version')->orWhere('app_version', '!=', $actual);
            }))
            ->when($this->filtro === 'instalados', fn ($q) => $q->where('standalone', true))
            ->when($this->filtro === 'adormecidos', fn ($q) => $q->where(function ($w) use ($limiteAdormecido) {
                $w->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $limiteAdormecido);
            }))
            ->orderByDesc('last_seen_at');

        // Os números do topo contam SEMPRE tudo, e não o que o filtro deixou
        // ver. Um resumo que muda com o filtro faz o leitor pensar que o
        // problema encolheu quando só encolheu a lista.
        $todos = PwaDevice::query();

        $resumo = [
            'aparelhos'   => (clone $todos)->count(),
            'empresas'    => (clone $todos)->distinct('tenant_id')->count('tenant_id'),
            'instalados'  => (clone $todos)->where('standalone', true)->count(),
            'atrasados'   => (clone $todos)->where(function ($w) use ($actual) {
                $w->whereNull('app_version')->orWhere('app_version', '!=', $actual);
            })->count(),
            'adormecidos' => (clone $todos)->where(function ($w) use ($limiteAdormecido) {
                $w->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $limiteAdormecido);
            })->count(),
        ];

        // As empresas que têm o módulo mas NUNCA apareceram aqui.
        //
        // É a pergunta que o inventário sozinho não responde: um cliente que
        // paga pelo PWA e nunca o usou não aparece em lista nenhuma de
        // aparelhos — e é exactamente esse que vale a pena ligar.
        $comModuloSemAparelho = Tenant::query()
            ->whereHas('modules', fn ($m) => $m->where('modules.slug', 'invoicing'))
            ->whereNotIn('id', PwaDevice::query()->select('tenant_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.super-admin.aparelhos-pwa', [
            'aparelhos'            => $consulta->paginate(25),
            'resumo'               => $resumo,
            'versaoActual'         => $actual,
            'comModuloSemAparelho' => $comModuloSemAparelho,
            'diasAteAdormecer'     => self::DIAS_ATE_ADORMECER,
        ]);
    }
}
