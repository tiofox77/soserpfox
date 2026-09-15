<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM MODELO DE INSPECÇÃO — os pontos que a oficina confere (15/09/2026, OF-02).
 *
 * Os pontos escrevem-se um por linha, «Secção: ponto» («Travões: Pastilhas da
 * frente»). Uma linha sem dois pontos vai para a secção «Geral». Uma empresa que
 * nunca abriu a lista recebe a «Revisão geral» com 33 pontos.
 */
class InspectionTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_inspection_templates';

    protected $fillable = ['tenant_id', 'name', 'description', 'points', 'points_count', 'is_default', 'is_active'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'points_count' => 'integer'];

    public const REVISAO_GERAL = <<<'TXT'
        Travões: Pastilhas da frente
        Travões: Pastilhas de trás
        Travões: Discos e tambores
        Travões: Líquido dos travões
        Travões: Travão de mão
        Pneus e rodas: Pneu dianteiro esquerdo
        Pneus e rodas: Pneu dianteiro direito
        Pneus e rodas: Pneu traseiro esquerdo
        Pneus e rodas: Pneu traseiro direito
        Pneus e rodas: Pneu suplente
        Pneus e rodas: Desgaste irregular / alinhamento
        Suspensão e direcção: Amortecedores
        Suspensão e direcção: Rótulas e terminais
        Suspensão e direcção: Folga na direcção
        Suspensão e direcção: Casquilhos e apoios
        Motor: Nível e estado do óleo
        Motor: Fugas de óleo
        Motor: Líquido de arrefecimento
        Motor: Correias
        Motor: Filtro de ar
        Motor: Velas / injectores
        Eléctrica: Bateria e terminais
        Eléctrica: Luzes da frente
        Eléctrica: Luzes de trás e stop
        Eléctrica: Piscas
        Eléctrica: Buzina
        Eléctrica: Limpa-pára-brisas e esguichos
        Interior e segurança: Cintos de segurança
        Interior e segurança: Ar condicionado
        Interior e segurança: Luzes de aviso do painel
        Escape e fundo: Escape e catalisador
        Escape e fundo: Fugas por baixo do carro
        Escape e fundo: Protecções do fundo
        TXT;

    protected static function booted(): void
    {
        // A contagem dos pontos acompanha o texto: é ela que a lista mostra.
        static::saving(function (InspectionTemplate $m) {
            $m->points = self::limpar((string) $m->points);
            $m->points_count = count(self::lerPontos($m->points));
        });
    }

    /** Uma linha por ponto, sem linhas vazias nem espaços à volta. */
    public static function limpar(string $texto): string
    {
        return collect(preg_split('/\R/u', $texto))->map(fn ($l) => trim(preg_replace('/^[-•*]\s*/u', '', trim((string) $l))))->filter()->implode("\n");
    }

    /** @return array<int, array{seccao: string, ponto: string}> */
    public static function lerPontos(string $texto): array
    {
        return collect(preg_split('/\R/u', self::limpar($texto)))->filter()->map(function ($linha) {
            [$seccao, $ponto] = str_contains($linha, ':') ? array_map('trim', explode(':', $linha, 2)) : ['Geral', $linha];

            return ['seccao' => mb_substr($seccao !== '' ? $seccao : 'Geral', 0, 80), 'ponto' => mb_substr($ponto !== '' ? $ponto : $seccao, 0, 160)];
        })->values()->all();
    }

    public static function garantirCatalogo(int $tenantId): void
    {
        if (self::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        self::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => __('Revisão geral'),
            'description' => __('Os pontos de uma revisão completa: travões, pneus, suspensão, motor, eléctrica, interior e fundo.'),
            'points' => self::REVISAO_GERAL,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
