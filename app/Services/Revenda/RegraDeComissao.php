<?php

namespace App\Services\Revenda;

use Illuminate\Validation\Rule;

/**
 * A REGRA DA COMISSÃO DE UM REVENDEDOR (16/09/2026, RV-04).
 *
 * O super admin define-a por revendedor — «customizável», foi o pedido:
 *
 *  · TIPO: percentagem do pagamento, ou um valor fixo por pagamento;
 *  · QUANDO: em todos os pagamentos, só no primeiro de cada empresa, ou nos
 *    primeiros N meses desde que a empresa ficou ligada;
 *  · BASE: o valor sem IVA ou com IVA (só conta na percentagem);
 *  · EXCEPÇÕES POR PLANO: um plano pode ter a sua percentagem ou o seu valor.
 *
 * A regra é guardada tal como estava em cada comissão: mudá-la só vale para os
 * pagamentos seguintes.
 */
final class RegraDeComissao
{
    public const TIPOS = ['percentagem' => 'Percentagem do pagamento', 'fixo' => 'Valor fixo por pagamento'];

    public const QUANDO = [
        'sempre' => 'Em todos os pagamentos',
        'primeiro' => 'Só no primeiro pagamento de cada empresa',
        'meses' => 'Nos primeiros meses da empresa',
    ];

    public const BASES = ['sem_iva' => 'Sobre o valor sem IVA', 'com_iva' => 'Sobre o valor com IVA'];

    /** A regra com que um revendedor aprovado nasce, se o super admin não mudar nada. */
    public const PADRAO = ['tipo' => 'percentagem', 'valor' => 20, 'aplica' => 'sempre', 'meses' => null, 'base' => 'sem_iva', 'planos' => []];

    /**
     * @param  list<array{plan_id:int, tipo:string, valor:float}>  $planos
     */
    private function __construct(
        public readonly string $tipo,
        public readonly float $valor,
        public readonly string $aplica,
        public readonly ?int $meses,
        public readonly string $base,
        public readonly array $planos,
    ) {
    }

    public static function de(?array $dados): self
    {
        $d = array_merge(self::PADRAO, $dados ?? []);

        return new self(
            array_key_exists($d['tipo'], self::TIPOS) ? $d['tipo'] : 'percentagem',
            max(0.0, (float) $d['valor']),
            array_key_exists($d['aplica'], self::QUANDO) ? $d['aplica'] : 'sempre',
            $d['aplica'] === 'meses' ? max(1, (int) ($d['meses'] ?? 12)) : null,
            array_key_exists($d['base'], self::BASES) ? $d['base'] : 'sem_iva',
            collect($d['planos'] ?? [])
                ->filter(fn ($p) => ! empty($p['plan_id']))
                ->map(fn ($p) => [
                    'plan_id' => (int) $p['plan_id'],
                    'tipo' => array_key_exists($p['tipo'] ?? '', self::TIPOS) ? $p['tipo'] : 'percentagem',
                    'valor' => max(0.0, (float) ($p['valor'] ?? 0)),
                ])
                ->unique('plan_id')->values()->all(),
        );
    }

    /** As regras de validação do formulário do super admin (chave `comissao`). */
    public static function regras(string $prefixo = 'comissao'): array
    {
        return [
            $prefixo => ['required', 'array'],
            "{$prefixo}.tipo" => ['required', Rule::in(array_keys(self::TIPOS))],
            "{$prefixo}.valor" => ['required', 'numeric', 'min:0', 'max:999999999'],
            "{$prefixo}.aplica" => ['required', Rule::in(array_keys(self::QUANDO))],
            "{$prefixo}.meses" => ["required_if:{$prefixo}.aplica,meses", 'nullable', 'integer', 'min:1', 'max:120'],
            "{$prefixo}.base" => ['required', Rule::in(array_keys(self::BASES))],
            "{$prefixo}.planos" => ['nullable', 'array', 'max:50'],
            "{$prefixo}.planos.*.plan_id" => ['required', 'integer', 'exists:plans,id', 'distinct'],
            "{$prefixo}.planos.*.tipo" => ['required', Rule::in(array_keys(self::TIPOS))],
            "{$prefixo}.planos.*.valor" => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    /** Uma percentagem acima de 100 não é uma comissão — é um engano. */
    public static function validarPercentagens(array $dados, \Illuminate\Validation\Validator $v, string $prefixo = 'comissao'): void
    {
        if (($dados['tipo'] ?? null) === 'percentagem' && (float) ($dados['valor'] ?? 0) > 100) {
            $v->errors()->add("{$prefixo}.valor", __('A percentagem não pode passar de 100.'));
        }
        foreach ($dados['planos'] ?? [] as $i => $p) {
            if (($p['tipo'] ?? null) === 'percentagem' && (float) ($p['valor'] ?? 0) > 100) {
                $v->errors()->add("{$prefixo}.planos.{$i}.valor", __('A percentagem não pode passar de 100.'));
            }
        }
    }

    public function paraGuardar(): array
    {
        return [
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'aplica' => $this->aplica,
            'meses' => $this->meses,
            'base' => $this->base,
            'planos' => $this->planos,
        ];
    }

    /**
     * Esta comissão cabe no «quando» da regra?
     *
     * @param  bool  $jaHouve  a empresa já deu uma comissão (não anulada) a este revendedor
     * @param  int  $mesesDesdeALigacao  meses completos desde que a empresa ficou ligada
     */
    public function aplicaSe(bool $jaHouve, int $mesesDesdeALigacao): bool
    {
        return match ($this->aplica) {
            'primeiro' => ! $jaHouve,
            'meses' => $mesesDesdeALigacao < (int) $this->meses,
            default => true,
        };
    }

    /**
     * O valor da comissão de um pagamento.
     *
     * @return array{base:float, valor:float, tipo:string, taxa:float}
     */
    public function calcular(float $semIva, float $comIva, ?int $planId): array
    {
        $excepcao = $planId ? collect($this->planos)->firstWhere('plan_id', $planId) : null;
        $tipo = $excepcao['tipo'] ?? $this->tipo;
        $taxa = (float) ($excepcao['valor'] ?? $this->valor);
        $base = round($this->base === 'com_iva' ? $comIva : $semIva, 2);

        $valor = $tipo === 'fixo'
            ? $taxa
            // Arredondamento ao cêntimo mais próximo, limpo do lixo do binário.
            : round(round($base * $taxa, 6) / 100, 2);

        return ['base' => $base, 'valor' => round($valor, 2), 'tipo' => $tipo, 'taxa' => $taxa];
    }

    /** «20% sobre o valor sem IVA, em todos os pagamentos» — para os ecrãs. */
    public function resumo(): string
    {
        $quanto = $this->tipo === 'fixo'
            ? __(':v Kz por pagamento', ['v' => number_format($this->valor, 2, ',', '.')])
            : __(':v% :base', ['v' => rtrim(rtrim(number_format($this->valor, 2, ',', '.'), '0'), ','), 'base' => mb_strtolower(__(self::BASES[$this->base]))]);

        $quando = $this->aplica === 'meses'
            ? trans_choice('nos primeiros :n mês da empresa|nos primeiros :n meses da empresa', (int) $this->meses, ['n' => $this->meses])
            : mb_strtolower(__(self::QUANDO[$this->aplica]));

        $resumo = $quanto . ', ' . $quando;

        return $this->planos ? $resumo . ' ' . trans_choice('(+:n excepção por plano)|(+:n excepções por plano)', count($this->planos), ['n' => count($this->planos)]) : $resumo;
    }
}
