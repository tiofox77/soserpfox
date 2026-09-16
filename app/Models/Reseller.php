<?php

namespace App\Models;

use App\Services\Revenda\RegraDeComissao;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * UM REVENDEDOR (16/09/2026, RV-01).
 *
 * Conta da plataforma, com a sua entrada (guard `revendedor`) — não pertence a
 * nenhuma empresa e não passa pelas permissões delas. Vê só as empresas que
 * tem ligadas (`tenants.reseller_id`).
 */
class Reseller extends Authenticatable
{
    use Notifiable;

    public const ESTADOS = [
        'pendente' => 'Por aprovar',
        'aprovado' => 'Aprovado',
        'suspenso' => 'Suspenso',
        'recusado' => 'Recusado',
    ];

    protected $fillable = [
        'name', 'company_name', 'nif', 'email', 'phone', 'province', 'city', 'website',
        'password', 'bank_name', 'iban', 'motivation',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'commission' => 'array',
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $r) {
            $r->email = mb_strtolower(trim((string) $r->email));
            if ($r->code !== null) {
                $r->code = strtoupper(trim($r->code));
            }
        });
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Tenant::class, 'reseller_id');
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(ResellerCommission::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(ResellerPayout::class);
    }

    public function aprovado(): bool
    {
        return $this->status === 'aprovado';
    }

    public function regra(): RegraDeComissao
    {
        return RegraDeComissao::de($this->commission);
    }

    /** O link de afiliado. */
    public function link(): ?string
    {
        return $this->code ? route('revendedor.link', $this->code) : null;
    }

    /** O nome que se mostra: a empresa do revendedor, se a tiver. */
    public function nomeVisivel(): string
    {
        return $this->company_name ?: $this->name;
    }

    /**
     * Um código novo e livre: as letras do nome e quatro algarismos (ex.: JOAO4821).
     * Sem O/0 e I/1 trocáveis no que vem ao acaso.
     */
    public static function novoCodigo(string $nome): string
    {
        $letras = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($nome)) ?: 'REV', 0, 5));

        do {
            $codigo = $letras . random_int(1000, 9999);
        } while (static::where('code', $codigo)->exists());

        return $codigo;
    }

    /** O email de nova senha leva ao portal do revendedor, e não à entrada das empresas. */
    public function sendPasswordResetNotification($token): void
    {
        \App\Models\SmtpSetting::getForTenant(null)?->configure();

        $this->notify(new \App\Notifications\Revenda\NovaSenhaDoRevendedor($token));
    }
}
