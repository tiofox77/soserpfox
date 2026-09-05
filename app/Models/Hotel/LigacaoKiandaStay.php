<?php

namespace App\Models\Hotel;

use App\Models\Tenant;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ligação de UMA empresa ao KiandaStay.
 *
 * O KiandaStay é o site onde o hóspede reserva; isto é o hotel onde ele fica.
 * Uma linha por empresa, com as credenciais e o mapa entre os tipos de quarto
 * de lá e os desta casa.
 *
 * Os segredos ficam CIFRADOS em repouso pelo cast 'encrypted' — quem olhar para
 * a base não os lê, e decifram-se sozinhos ao ler o atributo. É o mesmo que se
 * faz na ligação ao Meta.
 */
class LigacaoKiandaStay extends Model
{
    use BelongsToTenant;

    protected $table = 'hotel_ligacao_kiandastay';

    protected $fillable = [
        'tenant_id',
        'activa',
        'base_url',
        'api_key',
        'webhook_secret',
        'webhook_id',
        'property_id',
        'property_name',
        'mapa_tipos',
        'estado_inicial',
        'criar_hospede',
        'ultimo_evento_em',
        'eventos_recebidos',
        'ultimo_erro',
    ];

    protected $casts = [
        // Nunca em claro na base.
        'api_key'          => 'encrypted',
        'webhook_secret'   => 'encrypted',

        'activa'           => 'boolean',
        'criar_hospede'    => 'boolean',
        'mapa_tipos'       => 'array',
        'ultimo_evento_em' => 'datetime',
    ];

    /** A ligação desta empresa — cria uma vazia se ainda não existe. */
    public static function paraTenant(?int $tenantId = null): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId ?: activeTenantId()]
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * O endereço que se cola no KiandaStay para ele entregar os eventos.
     *
     * Leva a empresa no caminho porque é uma porta pública: é a assinatura de
     * cada evento que prova quem o mandou, e a empresa no endereço diz de quem
     * ele é.
     */
    public function urlDoWebhook(): string
    {
        return url('/webhooks/kiandastay/' . $this->tenant_id);
    }

    /** Tem o mínimo para falar com o site? */
    public function configurada(): bool
    {
        return ! empty($this->base_url) && ! empty($this->api_key);
    }

    /** Está mesmo a receber reservas? */
    public function aReceber(): bool
    {
        return $this->activa && $this->configurada() && ! empty($this->webhook_secret) && ! empty($this->property_id);
    }

    /**
     * O tipo de quarto desta casa que corresponde a um do site.
     *
     * Sem correspondência devolve nulo — e a reserva entra na mesma, sem tipo,
     * para a recepção decidir. Perder a reserva por causa do mapa seria pior do
     * que recebê-la incompleta.
     */
    public function tipoDeQuartoLocal($tipoNoSite): ?int
    {
        $mapa = $this->mapa_tipos ?: [];

        $id = $mapa[(string) $tipoNoSite] ?? null;

        return $id ? (int) $id : null;
    }

    /** O endereço base sem barra no fim, para colar caminhos sem pensar. */
    public function base(): string
    {
        return rtrim((string) $this->base_url, '/');
    }
}
