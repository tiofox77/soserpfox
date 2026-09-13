<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Currency;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS MOEDAS E OS CÂMBIOS.
 *
 * A LISTA DE MOEDAS É DA PLATAFORMA, não da empresa: as tabelas `currencies` e
 * `exchange_rates` não têm `tenant_id`, e nunca tiveram. Uma moeda é um código
 * ISO — o dólar é o dólar em todas as companhias — e um câmbio de dia é um
 * facto do mercado, não uma opinião de cada empresa.
 *
 * MAS ISSO TEM UMA CONSEQUÊNCIA que o ecrã antigo não dizia: quem mudasse aqui
 * o nome ou o símbolo de uma moeda mudava-o PARA TODAS AS EMPRESAS da
 * plataforma. Escrever passa a pedir `accounting.currencies.manage`, a
 * permissão de gerir, e o ecrã diz que a lista é partilhada — é a mesma regra
 * dos bancos angolanos na tesouraria.
 *
 * O QUE ESTAVA PARTIDO:
 *
 *  · não havia como APAGAR uma moeda nem um câmbio;
 *  · `is_active` e `decimal_places` só se escreviam ao CRIAR — uma moeda não se
 *    podia desactivar nem corrigir as casas decimais;
 *  · e o câmbio aceitava a mesma moeda de um lado e do outro pela validação
 *    (`different`) mas não impedia uma taxa ZERO, que faz uma conversão dar
 *    sempre zero.
 */
class MoedasApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.currencies.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:60'],
            'so_activas' => ['nullable', 'boolean'],
            'moeda' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $moedas = Currency::query()
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('code', 'like', $t)->orWhere('name', 'like', $t));
            })
            ->when(! empty($filtros['so_activas']), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')->get();

        // Quantos câmbios usam cada moeda: é o que decide se se pode apagar.
        $usos = ExchangeRate::selectRaw('currency_from_id, currency_to_id')->get();

        $conta = function (int $id) use ($usos) {
            return $usos->filter(fn ($r) => (int) $r->currency_from_id === $id || (int) $r->currency_to_id === $id)->count();
        };

        $taxas = ExchangeRate::with(['currencyFrom:id,code,name', 'currencyTo:id,code,name'])
            ->when(! empty($filtros['moeda']), fn ($q) => $q->where(fn ($w) => $w
                ->where('currency_from_id', $filtros['moeda'])
                ->orWhere('currency_to_id', $filtros['moeda'])))
            ->when(! empty($filtros['de']), fn ($q) => $q->where('date', '>=', $filtros['de']))
            ->when(! empty($filtros['ate']), fn ($q) => $q->where('date', '<=', $filtros['ate']))
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(100)->get();

        return response()->json([
            'moedas' => $moedas->map(fn (Currency $m) => [
                'id' => $m->id,
                'codigo' => $m->code,
                'nome' => $m->name,
                'simbolo' => $m->symbol,
                'casas' => (int) $m->decimal_places,
                'activa' => (bool) $m->is_active,
                'cambios' => $conta((int) $m->id),
            ])->values(),

            'taxas' => $taxas->map(fn (ExchangeRate $t) => [
                'id' => $t->id,
                'de_id' => $t->currency_from_id,
                'de' => $t->currencyFrom?->code,
                'para_id' => $t->currency_to_id,
                'para' => $t->currencyTo?->code,
                'dia' => $t->date?->format('Y-m-d'),
                'taxa' => (float) $t->rate,
                'origem' => $t->source,
            ])->values(),

            'resumo' => [
                'moedas' => Currency::count(),
                'activas' => Currency::where('is_active', true)->count(),
                'cambios' => ExchangeRate::count(),
                'ultimo_cambio' => ExchangeRate::max('date'),
            ],

            'permissoes' => [
                // A LISTA É DA PLATAFORMA: escrever nela mexe com todas as
                // empresas, e por isso pede a permissão de gerir.
                'gerir' => (bool) $request->user()?->can('accounting.currencies.manage') && (bool) $request->user()?->isPlatformSuperAdmin(),
            ],
        ]);
    }

    public function guardarMoeda(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'accounting.currencies.manage');
        // As moedas e as taxas de câmbio não têm empresa: são as mesmas para todas.
        // Escrevê-las era mexer na contabilidade das outras casas (auditoria de 2026-09-13).
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403, __('As moedas e os câmbios são da plataforma: só ela os altera.'));

        $dados = $request->validate([
            // O CÓDIGO É ISO-4217: três letras, e único na plataforma.
            'code' => [
                'required', 'string', 'size:3', 'alpha',
                Rule::unique('currencies', 'code')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['boolean'],
        ], [], [
            'code' => __('código'), 'name' => __('nome'), 'symbol' => __('símbolo'),
        ]);

        $moeda = $id ? Currency::findOrFail($id) : new Currency();

        $moeda->fill([
            'code' => strtoupper($dados['code']),
            'name' => trim($dados['name']),
            'symbol' => trim($dados['symbol']),
            // AS CASAS DECIMAIS E O ACTIVO gravam-se sempre: só se escreviam ao
            // criar, pelo que uma moeda não se podia desactivar nem corrigir.
            'decimal_places' => (int) ($dados['decimal_places'] ?? 2),
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ])->save();

        return response()->json([
            'message' => $id
                ? __('Moeda :codigo actualizada.', ['codigo' => $moeda->code])
                : __('Moeda :codigo criada.', ['codigo' => $moeda->code]),
        ], $id ? 200 : 201);
    }

    public function apagarMoeda(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.currencies.manage');
        // As moedas e as taxas de câmbio não têm empresa: são as mesmas para todas.
        // Escrevê-las era mexer na contabilidade das outras casas (auditoria de 2026-09-13).
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403, __('As moedas e os câmbios são da plataforma: só ela os altera.'));

        $moeda = Currency::findOrFail($id);

        /*
         * UMA MOEDA COM CÂMBIOS NÃO DESAPARECE: as taxas ficariam a apontar
         * para um id que não existe, e uma conversão histórica deixaria de
         * saber de que moeda falava. Desactiva-se.
         */
        $cambios = ExchangeRate::where('currency_from_id', $id)->orWhere('currency_to_id', $id)->count();

        if ($cambios > 0) {
            throw ValidationException::withMessages([
                'geral' => [__('Esta moeda tem :n câmbio(s) registado(s). Desactive-a em vez de a apagar.', ['n' => $cambios])],
            ]);
        }

        $codigo = $moeda->code;
        $moeda->delete();

        return response()->json(['message' => __('Moeda :codigo eliminada.', ['codigo' => $codigo])]);
    }

    /**
     * UM CÂMBIO DE UM DIA.
     *
     * O par mais a data são a chave: gravar outra vez o mesmo par no mesmo dia
     * CORRIGE a taxa em vez de somar uma segunda linha — duas taxas do mesmo dia
     * fariam a conversão depender da ordem da consulta.
     */
    public function guardarTaxa(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.currencies.manage');
        // As moedas e as taxas de câmbio não têm empresa: são as mesmas para todas.
        // Escrevê-las era mexer na contabilidade das outras casas (auditoria de 2026-09-13).
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403, __('As moedas e os câmbios são da plataforma: só ela os altera.'));

        $dados = $request->validate([
            'currency_from_id' => ['required', 'integer', 'exists:currencies,id'],
            'currency_to_id' => ['required', 'integer', 'different:currency_from_id', 'exists:currencies,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            // UMA TAXA ZERO faz toda a conversão dar zero: não é um câmbio.
            'rate' => ['required', 'numeric', 'gt:0'],
        ], [], [
            'currency_from_id' => __('moeda de origem'), 'currency_to_id' => __('moeda de destino'),
            'date' => __('data'), 'rate' => __('taxa'),
        ]);

        $taxa = ExchangeRate::updateOrCreate(
            [
                'currency_from_id' => $dados['currency_from_id'],
                'currency_to_id' => $dados['currency_to_id'],
                'date' => $dados['date'],
            ],
            ['rate' => $dados['rate'], 'source' => 'manual'],
        );

        $taxa->load(['currencyFrom:id,code', 'currencyTo:id,code']);

        return response()->json([
            'message' => __('Câmbio :de → :para de :dia gravado.', [
                'de' => $taxa->currencyFrom?->code,
                'para' => $taxa->currencyTo?->code,
                'dia' => $dados['date'],
            ]),
        ], 201);
    }

    public function apagarTaxa(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.currencies.manage');
        // As moedas e as taxas de câmbio não têm empresa: são as mesmas para todas.
        // Escrevê-las era mexer na contabilidade das outras casas (auditoria de 2026-09-13).
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403, __('As moedas e os câmbios são da plataforma: só ela os altera.'));

        ExchangeRate::findOrFail($id)->delete();

        return response()->json(['message' => __('Câmbio eliminado.')]);
    }
}
