<?php

namespace App\Observers;

use App\Services\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer único, registado em ciclo sobre a allowlist de config/audit.php.
 *
 * Porque não um wildcard sobre `eloquent.*`: poria as escritas dos 178 modelos
 * a passar por aqui — incluindo as do próprio registo de auditoria, o que dá
 * recursão — e faria o raio de explosão de um erro ser o sistema inteiro. Com
 * allowlist, um erro afecta só os modelos listados.
 *
 * O que este observer NÃO apanha, porque não emite eventos Eloquent:
 * `DB::table()->update()` e os restantes `withoutEvents` do projecto. Esses
 * pontos precisam de chamada explícita ao AuditRecorder.
 *
 * Os movimentos de stock das vendas ERAM o caso mais grave desta lista: os 10
 * sítios que os criam suprimiam os eventos todos só para evitar o duplo débito.
 * Passaram a usar `StockMovement::semAplicarStock()`, que suspende apenas o
 * hook do stock e deixa a auditoria a funcionar.
 */
class AuditObserver
{
    public function __construct(protected AuditRecorder $recorder)
    {
    }

    public function created(Model $modelo): void
    {
        // Na criação regista-se o estado inicial, não um diff — mas só dos
        // campos que interessam. Guardar as 57 colunas de uma factura em cada
        // criação são 2-4 KB por linha, e a criação é o evento mais frequente.
        $this->recorder->model('created', $modelo, [], $this->limpar($modelo->getAttributes()), $this->contexto($modelo));
    }

    public function updated(Model $modelo): void
    {
        $alteracoes = $this->limpar($modelo->getChanges());

        // Só `updated_at` mexeu: é ruído do `touch()`, não um acto.
        if (empty($alteracoes)) {
            return;
        }

        $antes = [];
        foreach (array_keys($alteracoes) as $campo) {
            $antes[$campo] = $modelo->getOriginal($campo);
        }

        $this->recorder->model('updated', $modelo, $this->redigir($antes), $alteracoes, $this->contexto($modelo));
    }

    public function deleted(Model $modelo): void
    {
        $evento = method_exists($modelo, 'isForceDeleting') && $modelo->isForceDeleting()
            ? 'force_deleted'
            : 'deleted';

        $this->recorder->model($evento, $modelo, $this->limpar($modelo->getOriginal()), [], $this->contexto($modelo));
    }

    public function restored(Model $modelo): void
    {
        $this->recorder->model('restored', $modelo, [], [], $this->contexto($modelo));
    }

    /**
     * Contexto legível do registo, congelado no momento do acto.
     *
     * Sem isto a linha dizia apenas "updated SalesInvoice #1095" e obrigava a
     * abrir o detalhe — ou a ir ao documento, que pode já não existir — para
     * perceber de que valor e de que cliente se falava.
     *
     * Só campos que existam: os modelos são muito diferentes entre si e não há
     * um esquema comum.
     */
    protected function contexto(Model $modelo): array
    {
        $ctx = [];

        $mapa = [
            'total'          => 'total',
            'gross_total'    => 'total_ilíquido',
            'status'         => 'estado',
            'invoice_status' => 'estado_fiscal',
            'payment_status' => 'estado_pagamento',
            'quantity'       => 'quantidade',
            'amount'         => 'valor',
            'rate'           => 'taxa',
            'price'          => 'preço',
            'nif'            => 'nif',
            'email'          => 'email',
        ];

        foreach ($mapa as $campo => $rotulo) {
            $valor = $modelo->getAttribute($campo);

            if ($valor !== null && $valor !== '') {
                $ctx[$rotulo] = is_numeric($valor) ? (float) $valor : (string) $valor;
            }
        }

        // Nome do cliente, congelado: o documento pode ser apagado depois.
        if ($modelo->getAttribute('client_id') && method_exists($modelo, 'client')) {
            try {
                $ctx['cliente'] = $modelo->client?->name;
            } catch (\Throwable) {
                // A relação pode não existir neste modelo; não vale um erro.
            }
        }

        return array_filter($ctx, fn ($v) => $v !== null);
    }

    /** Tira o ruído e depois os segredos. */
    protected function limpar(array $atributos): array
    {
        $ignorados = array_map('strtolower', config('audit.ignored', []));

        $atributos = array_filter(
            $atributos,
            fn ($campo) => !in_array(strtolower($campo), $ignorados, true),
            ARRAY_FILTER_USE_KEY
        );

        return $this->redigir($atributos);
    }

    /**
     * Substitui segredos por um marcador.
     *
     * A tabela é append-only e selada: um segredo que aqui entre só sai
     * apagando a linha, e apagar linhas parte a cadeia. Não há segunda
     * oportunidade — daí ser feito antes de qualquer escrita.
     */
    protected function redigir(array $atributos): array
    {
        $proibidos = array_map('strtolower', config('audit.redacted', []));

        foreach ($atributos as $campo => $valor) {
            if (in_array(strtolower($campo), $proibidos, true)) {
                $atributos[$campo] = '[oculto]';
            }
        }

        return $atributos;
    }
}
