<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * O CHECK-IN DE UMA ORDEM DE SERVIÇO — como o carro chegou (15/09/2026, OF-01).
 *
 * Os danos são pontos no desenho do carro visto de cima (`x`/`y` em % da
 * imagem `public/img/oficina/carro-planta.svg`, a mesma no ecrã e no papel),
 * cada um com o tipo e uma nota.
 */
class WorkOrderCheckin extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_work_order_checkins';

    protected $fillable = [
        'tenant_id', 'work_order_id', 'fuel_level', 'damages', 'accessories', 'warning_lights', 'keys_count',
        'belongings', 'notes', 'signature', 'signed_by', 'signed_at', 'signed_hash', 'user_id',
    ];

    protected $casts = [
        'fuel_level' => 'integer',
        'keys_count' => 'integer',
        'damages' => 'array',
        'accessories' => 'array',
        'warning_lights' => 'array',
        'signed_at' => 'datetime',
    ];

    protected $hidden = ['signature'];

    /** Os tipos de dano, com a letra que se desenha no ponto. */
    public const TIPOS_DE_DANO = [
        'risco' => ['rotulo' => 'Risco', 'letra' => 'R', 'cor' => 'ambar'],
        'amolgadela' => ['rotulo' => 'Amolgadela', 'letra' => 'A', 'cor' => 'laranja'],
        'partido' => ['rotulo' => 'Partido / rachado', 'letra' => 'P', 'cor' => 'vermelho'],
        'ferrugem' => ['rotulo' => 'Ferrugem', 'letra' => 'F', 'cor' => 'castanho'],
        'falta' => ['rotulo' => 'Peça em falta', 'letra' => 'X', 'cor' => 'roxo'],
    ];

    /** O que se confere que vem no carro. */
    public const ACESSORIOS = [
        'pneu_suplente' => 'Pneu suplente',
        'macaco' => 'Macaco',
        'chave_rodas' => 'Chave de rodas',
        'triangulo' => 'Triângulo',
        'colete' => 'Colete reflector',
        'extintor' => 'Extintor',
        'radio' => 'Rádio / frente do rádio',
        'tapetes' => 'Tapetes',
        'antena' => 'Antena',
        'tampoes' => 'Tampões das jantes',
        'documentos' => 'Documentos da viatura',
        'cabo_carregador' => 'Cabo / carregador',
    ];

    /** As luzes de aviso acesas no painel à entrada. */
    public const LUZES = [
        'motor' => 'Motor',
        'abs' => 'ABS',
        'airbag' => 'Airbag',
        'oleo' => 'Pressão do óleo',
        'bateria' => 'Bateria',
        'temperatura' => 'Temperatura',
        'travoes' => 'Travões',
        'pneus' => 'Pressão dos pneus',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A IMPRESSÃO DO QUE SE ASSINA — o combustível, os danos, os acessórios, as
     * luzes, as chaves, os objectos e os km da ordem. As notas internas ficam de
     * fora: acrescentar uma nota não invalida a assinatura do cliente.
     */
    public function conteudoAssinado(int $km): string
    {
        $danos = collect($this->damages ?? [])
            ->map(fn ($d) => [round((float) ($d['x'] ?? 0), 1), round((float) ($d['y'] ?? 0), 1), (string) ($d['tipo'] ?? ''), trim((string) ($d['nota'] ?? ''))])
            ->sortBy(fn ($d) => sprintf('%06.1f-%06.1f', $d[1], $d[0]))->values()->all();
        $acessorios = collect($this->accessories ?? [])->map(fn ($a) => (string) $a)->sort()->values()->all();
        $luzes = collect($this->warning_lights ?? [])->sort()->values()->all();

        return hash('sha256', json_encode([$km, $this->fuel_level, $danos, $acessorios, $luzes, $this->keys_count, trim((string) $this->belongings)]));
    }

    public static function listas(): array
    {
        return [
            'tipos_de_dano' => collect(self::TIPOS_DE_DANO)->map(fn ($t, $v) => ['valor' => $v, 'rotulo' => __($t['rotulo']), 'letra' => $t['letra'], 'cor' => $t['cor']])->values()->all(),
            'acessorios' => collect(self::ACESSORIOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values()->all(),
            'luzes' => collect(self::LUZES)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values()->all(),
        ];
    }
}
