<?php

namespace App\Models\Invoicing;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um modelo de proposta: a forma do documento, sem os números.
 *
 * `blocos` é uma lista ordenada. Cada bloco tem sempre `tipo` e `id`; o resto
 * das chaves depende do tipo (ver TiposDeBloco). `estilos` guarda o que vale
 * para o documento inteiro — cor, tipo de letra, cabeçalho e rodapé.
 */
class QuoteTemplate extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'quote_templates';

    protected $fillable = [
        'tenant_id', 'nome', 'descricao', 'sector',
        'blocos', 'estilos', 'is_default', 'is_active', 'created_by',
    ];

    protected $casts = [
        'blocos'     => 'array',
        'estilos'    => 'array',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    /** Estilos por omissão — um modelo novo nunca nasce sem cor nem letra. */
    public const ESTILOS_PADRAO = [
        'cor_principal'   => '#4f46e5',
        'cor_texto'       => '#111827',
        'fonte'           => 'Arial',
        'tamanho_base'    => 12,
        'mostrar_rodape'  => true,
        'texto_rodape'    => '{{empresa.nome}} · {{empresa.nif}}',
        'numerar_paginas' => true,
    ];

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function orcamentos()
    {
        return $this->hasMany(SalesQuote::class, 'quote_template_id');
    }

    public function estilo(string $chave)
    {
        return ($this->estilos ?? [])[$chave] ?? self::ESTILOS_PADRAO[$chave] ?? null;
    }

    /**
     * Só pode haver um modelo por omissão por empresa. Fazer isto aqui — e não
     * em cada ecrã que marca um — evita que dois caminhos diferentes deixem a
     * empresa com dois modelos "padrão" e o PDF a escolher à sorte.
     */
    public function tornarPadrao(): void
    {
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->forceFill(['is_default' => true])->save();
    }

    /** Os campos livres que este modelo pede ao utilizador em cada orçamento. */
    public function camposLivres(): array
    {
        $campos = [];

        foreach ((array) $this->blocos as $bloco) {
            if (($bloco['tipo'] ?? '') !== 'campo_livre') {
                continue;
            }

            $chave = trim((string) ($bloco['chave'] ?? ''));
            if ($chave !== '') {
                $campos[$chave] = [
                    'chave'    => $chave,
                    'rotulo'   => $bloco['rotulo'] ?? $chave,
                    'ajuda'    => $bloco['ajuda'] ?? '',
                    'linhas'   => (int) ($bloco['linhas'] ?? 4),
                    'titulo'   => $bloco['titulo'] ?? '',
                ];
            }
        }

        return $campos;
    }
}
