<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Uma mensagem do dono da plataforma para as empresas.
 *
 * Escreve-se uma vez e aponta a um público — todas as empresas, algumas, ou
 * as que estejam num certo plano. Quem a viu fica na tabela ao lado.
 */
class PlatformMessage extends Model
{
    protected $fillable = [
        'title', 'body', 'level', 'display', 'audience',
        'tenant_ids', 'plan_ids', 'starts_at', 'ends_at',
        'dismissible', 'link_url', 'link_label', 'is_active', 'created_by',
    ];

    protected $casts = [
        'tenant_ids'  => 'array',
        'plan_ids'    => 'array',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'dismissible' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public const NIVEIS = [
        'info'    => 'Informação',
        'sucesso' => 'Boa notícia',
        'aviso'   => 'Aviso',
        'urgente' => 'Urgente',
    ];

    public const FORMAS = [
        'popup' => 'Pop-up (interrompe)',
        'barra' => 'Barra no topo (não interrompe)',
    ];

    public const PUBLICOS = [
        'todas'    => 'Todas as empresas',
        'empresas' => 'Empresas escolhidas',
        'planos'   => 'Empresas num certo plano',
    ];

    public function autor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leituras()
    {
        return $this->hasMany(PlatformMessageRead::class);
    }

    /** Mensagens no ar neste momento. */
    public function scopeNoAr($query)
    {
        $agora = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $agora))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $agora));
    }

    /**
     * Esta mensagem é para esta empresa?
     *
     * O público resolve-se aqui e não em SQL porque as listas são pequenas
     * (dezenas de empresas) e a alternativa — JSON_CONTAINS — não é portável
     * nem indexável, e ficaria escrita em dois sítios.
     */
    public function ehPara(?Tenant $empresa): bool
    {
        if ($this->audience === 'todas') {
            return true;
        }

        if (!$empresa) {
            return false;
        }

        if ($this->audience === 'empresas') {
            return in_array((int) $empresa->id, array_map('intval', $this->tenant_ids ?? []), true);
        }

        if ($this->audience === 'planos') {
            $planoActual = $empresa->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->latest('id')
                ->value('plan_id');

            return $planoActual
                && in_array((int) $planoActual, array_map('intval', $this->plan_ids ?? []), true);
        }

        return false;
    }

    /**
     * Quantas empresas isto alcança.
     *
     * Serve para quem escreve saber a quantas casas está a bater antes de
     * carregar em enviar.
     */
    public function quantasEmpresas(): int
    {
        if ($this->audience === 'todas') {
            return Tenant::where('is_active', true)->count();
        }

        if ($this->audience === 'empresas') {
            return count($this->tenant_ids ?? []);
        }

        if ($this->audience === 'planos') {
            return DB::table('subscriptions')
                ->whereIn('plan_id', $this->plan_ids ?? [])
                ->whereIn('status', ['active', 'trial'])
                ->distinct()
                ->count('tenant_id');
        }

        return 0;
    }

    public function estilo(): array
    {
        return match ($this->level) {
            'urgente' => ['cor' => 'red',    'icone' => 'fa-triangle-exclamation'],
            'aviso'   => ['cor' => 'amber',  'icone' => 'fa-circle-exclamation'],
            'sucesso' => ['cor' => 'green',  'icone' => 'fa-circle-check'],
            default   => ['cor' => 'blue',   'icone' => 'fa-circle-info'],
        };
    }
}
