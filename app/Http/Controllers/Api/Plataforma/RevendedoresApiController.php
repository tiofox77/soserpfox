<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\ResellerPayout;
use App\Services\Revenda\AvisosDaRevenda;
use App\Services\Revenda\ComissoesDoRevendedor;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Services\Revenda\RegraDeComissao;
use App\Services\Tenants\SinaisDeVida;
use App\Support\EstadoDaSubscricao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS REVENDEDORES NO PAINEL DO SUPER ADMIN (16/09/2026, RV-03, RV-04, RV-12).
 *
 * Aprovar (com o código e a comissão), recusar com motivo, suspender e
 * reactivar; mudar a regra da comissão (só vale para os pagamentos seguintes);
 * anular uma comissão por pagar; registar os pagamentos. Nada se apaga.
 */
class RevendedoresApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(Reseller::ESTADOS))],
            'procura' => ['nullable', 'string', 'max:120'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);
        $procura = trim((string) ($f['procura'] ?? ''));

        $pagina = Reseller::query()
            ->withCount('empresas')
            ->withSum(['comissoes as por_pagar' => fn ($q) => $q->where('status', 'por_pagar')], 'amount')
            ->withSum(['comissoes as pago' => fn ($q) => $q->where('status', 'paga')], 'amount')
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")->orWhere('company_name', 'like', "%{$procura}%")
                ->orWhere('email', 'like', "%{$procura}%")->orWhere('code', 'like', "%{$procura}%")
                ->orWhere('phone', 'like', "%{$procura}%")->orWhere('nif', 'like', "%{$procura}%")))
            // Os pedidos por aprovar à frente — é por eles que se abre o ecrã.
            ->orderByRaw("CASE status WHEN 'pendente' THEN 0 WHEN 'aprovado' THEN 1 WHEN 'suspenso' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(15, ['*'], 'pagina', (int) ($f['pagina'] ?? 1));

        return response()->json([
            'revendedores' => collect($pagina->items())->map(fn (Reseller $r) => $this->linha($r))->values(),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total(), 'de' => $pagina->firstItem() ?? 0, 'ate' => $pagina->lastItem() ?? 0],
            'contagens' => Reseller::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all()
                + array_fill_keys(array_keys(Reseller::ESTADOS), 0),
            'totais' => [
                'empresas' => \App\Models\Tenant::whereNotNull('reseller_id')->count(),
                'por_pagar' => round((float) ResellerCommission::where('status', 'por_pagar')->sum('amount'), 2),
                'pago' => round((float) ResellerCommission::where('status', 'paga')->sum('amount'), 2),
            ],
        ]);
    }

    private function linha(Reseller $r): array
    {
        return [
            'id' => $r->id,
            'nome' => $r->name,
            'empresa' => $r->company_name,
            'email' => $r->email,
            'telefone' => $r->phone,
            'localidade' => trim(($r->city ?? '') . ($r->province ? ', ' . $r->province : ''), ', ') ?: null,
            'codigo' => $r->code,
            'estado' => $r->status,
            'estado_rotulo' => __(Reseller::ESTADOS[$r->status] ?? $r->status),
            'regra' => $r->aprovado() || $r->status === 'suspenso' ? $r->regra()->resumo() : null,
            'empresas' => (int) ($r->empresas_count ?? 0),
            'por_pagar' => round((float) ($r->por_pagar ?? 0), 2),
            'pago' => round((float) ($r->pago ?? 0), 2),
            'pedido_em' => $r->created_at?->toIso8601String(),
            'ultima_entrada' => $r->last_login_at?->toIso8601String(),
        ];
    }

    public function ver(int $id): JsonResponse
    {
        $r = Reseller::withCount('empresas')->findOrFail($id);
        $empresas = $r->empresas()->with('activeSubscription.plan')->latest('reseller_linked_at')->limit(100)->get();
        $sinais = SinaisDeVida::para($empresas->pluck('id'));

        return response()->json([
            'revendedor' => $this->linha($r) + [
                'nif' => $r->nif,
                'provincia' => $r->province,
                'cidade' => $r->city,
                'site' => $r->website,
                'banco' => $r->bank_name,
                'iban' => $r->iban,
                'motivacao' => $r->motivation,
                'notas' => $r->internal_notes,
                'motivo_da_recusa' => $r->rejection_reason,
                'aprovado_em' => $r->approved_at?->toIso8601String(),
                'suspenso_em' => $r->suspended_at?->toIso8601String(),
                'link' => $r->link(),
                'comissao' => $r->regra()->paraGuardar(),
            ],
            'empresas' => $empresas->map(function ($t) use ($sinais) {
                $e = EstadoDaSubscricao::para($t);

                return [
                    'id' => $t->id,
                    'nome' => $t->name,
                    'nif' => $t->nif,
                    'plano' => $t->activeSubscription?->plan?->name,
                    'estado' => __($e['rotulo']),
                    'cor' => $e['cor'],
                    'activa' => (bool) $t->is_active,
                    'via' => $t->reseller_via ? __(LigacaoAoRevendedor::VIAS[$t->reseller_via] ?? $t->reseller_via) : null,
                    'ligada_em' => $t->reseller_linked_at?->toIso8601String(),
                    'ultima_entrada' => ($sinais[$t->id] ?? null)?->ultima_entrada?->toIso8601String(),
                ];
            })->values(),
            'comissoes' => ResellerCommission::with(['empresa', 'plano', 'pagamento'])->where('reseller_id', $r->id)
                ->orderByRaw("CASE status WHEN 'por_pagar' THEN 0 ELSE 1 END")->latest()->limit(200)->get()
                ->map(fn ($c) => ComissoesDoRevendedor::paraEcra($c))->values(),
            'pagamentos' => ResellerPayout::with('autor')->withCount('comissoes')->where('reseller_id', $r->id)->latest('paid_at')->limit(50)->get()
                ->map(fn (ResellerPayout $p) => [
                    'id' => $p->id,
                    'valor' => (float) $p->amount,
                    'data' => $p->paid_at?->toDateString(),
                    'forma' => __(ResellerPayout::METODOS[$p->method] ?? $p->method),
                    'referencia' => $p->reference,
                    'notas' => $p->notes,
                    'comissoes' => (int) $p->comissoes_count,
                    'por' => $p->autor?->name,
                ])->values(),
            'totais' => ComissoesDoRevendedor::totais($r->id),
        ]);
    }

    /** As opções do formulário da comissão e do pagamento. */
    public function opcoes(): JsonResponse
    {
        $lista = fn (array $a) => collect($a)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values();

        return response()->json([
            'tipos' => $lista(RegraDeComissao::TIPOS),
            'quando' => $lista(RegraDeComissao::QUANDO),
            'bases' => $lista(RegraDeComissao::BASES),
            'metodos' => $lista(ResellerPayout::METODOS),
            'planos' => Plan::where('is_active', true)->orderBy('order')->get(['id', 'name', 'price_monthly'])
                ->map(fn ($p) => ['valor' => $p->id, 'rotulo' => $p->name, 'preco' => (float) $p->price_monthly])->values(),
            'padrao' => RegraDeComissao::PADRAO,
        ]);
    }

    private function regraDoPedido(Request $request): RegraDeComissao
    {
        $v = Validator::make($request->all(), RegraDeComissao::regras(), [
            'comissao.meses.required_if' => __('Diga durante quantos meses a comissão conta.'),
        ], [
            'comissao.valor' => __('Valor da comissão'),
            'comissao.meses' => __('Meses'),
        ]);
        $v->after(fn ($v) => RegraDeComissao::validarPercentagens((array) $request->input('comissao', []), $v));
        $v->validate();

        return RegraDeComissao::de($request->input('comissao'));
    }

    private function codigoDoPedido(Request $request, Reseller $r): string
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->input('codigo')))]);
        $dados = $request->validate([
            'codigo' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-Z0-9]+$/', Rule::unique('resellers', 'code')->ignore($r->id)],
        ], [
            'codigo.regex' => __('O código só leva letras e algarismos, sem espaços.'),
            'codigo.unique' => __('Este código já é de outro revendedor.'),
        ]);

        return $dados['codigo'] ?: ($r->code ?: Reseller::novoCodigo($r->company_name ?: $r->name));
    }

    public function aprovar(Request $request, int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);

        if (! in_array($r->status, ['pendente', 'recusado'], true)) {
            throw ValidationException::withMessages(['id' => __('Este revendedor já foi aprovado.')]);
        }

        $regra = $this->regraDoPedido($request);
        $r->forceFill([
            'code' => $this->codigoDoPedido($request, $r),
            'commission' => $regra->paraGuardar(),
            'status' => 'aprovado',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'rejection_reason' => null,
            'suspended_at' => null,
        ])->save();

        $enviado = app(AvisosDaRevenda::class)->aprovado($r);

        return response()->json(['message' => $enviado
            ? __(':nome aprovado com o código :codigo. Enviámos o email com o link.', ['nome' => $r->nomeVisivel(), 'codigo' => $r->code])
            : __(':nome aprovado com o código :codigo, mas o email não saiu — avise-o por outro meio.', ['nome' => $r->nomeVisivel(), 'codigo' => $r->code])]);
    }

    public function recusar(Request $request, int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [
            'motivo.required' => __('Escreva o motivo — é o que o revendedor vai ler.'),
        ]);

        if ($r->status !== 'pendente') {
            throw ValidationException::withMessages(['motivo' => __('Só um pedido por aprovar se recusa. Um revendedor aprovado suspende-se.')]);
        }

        $r->forceFill(['status' => 'recusado', 'rejection_reason' => $dados['motivo']])->save();
        app(AvisosDaRevenda::class)->recusado($r);

        return response()->json(['message' => __('Pedido de :nome recusado.', ['nome' => $r->nomeVisivel()])]);
    }

    public function suspender(int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);

        if ($r->status !== 'aprovado') {
            throw ValidationException::withMessages(['id' => __('Só um revendedor aprovado se suspende.')]);
        }

        $r->forceFill(['status' => 'suspenso', 'suspended_at' => now()])->save();
        app(AvisosDaRevenda::class)->suspenso($r);

        return response()->json(['message' => __(':nome suspenso: o portal fechou e os pagamentos deixam de dar comissão.', ['nome' => $r->nomeVisivel()])]);
    }

    public function reactivar(int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);

        if ($r->status !== 'suspenso') {
            throw ValidationException::withMessages(['id' => __('Este revendedor não está suspenso.')]);
        }

        $r->forceFill(['status' => 'aprovado', 'suspended_at' => null])->save();

        return response()->json(['message' => __(':nome reactivado.', ['nome' => $r->nomeVisivel()])]);
    }

    /** Os dados, o código, as notas e a comissão (a regra nova só vale para os pagamentos seguintes). */
    public function guardar(Request $request, int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'nif' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150', Rule::unique('resellers', 'email')->ignore($r->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'iban' => ['nullable', 'string', 'max:60'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ], ['email.unique' => __('Este email já é de outro revendedor.')]);

        $regra = $this->regraDoPedido($request);
        $codigo = $r->status === 'pendente' && ! $request->filled('codigo') ? $r->code : $this->codigoDoPedido($request, $r);

        $r->fill(collect($dados)->except('internal_notes')->all());
        $r->forceFill(['internal_notes' => $dados['internal_notes'] ?? null, 'commission' => $regra->paraGuardar(), 'code' => $codigo])->save();

        return response()->json(['message' => __('Revendedor :nome guardado.', ['nome' => $r->nomeVisivel()])]);
    }

    public function anularComissao(Request $request, int $id, int $comissao): JsonResponse
    {
        $c = ResellerCommission::where('reseller_id', $id)->findOrFail($comissao);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [
            'motivo.required' => __('Escreva porque é que a comissão não conta.'),
        ]);

        app(ComissoesDoRevendedor::class)->anular($c, $dados['motivo'], auth()->id());

        return response()->json(['message' => __('Comissão anulada.')]);
    }

    public function pagar(Request $request, int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);
        $dados = $request->validate([
            'comissoes' => ['required', 'array', 'min:1', 'max:500'],
            'comissoes.*' => ['integer'],
            'method' => ['required', Rule::in(array_keys(ResellerPayout::METODOS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'comissoes.required' => __('Escolha as comissões que está a pagar.'),
            'paid_at.before_or_equal' => __('A data do pagamento não pode ser futura.'),
        ]);

        $pagamento = app(ComissoesDoRevendedor::class)->pagar($r, $dados['comissoes'], $dados, auth()->id());
        $enviado = app(AvisosDaRevenda::class)->pagamento($pagamento);

        return response()->json(['message' => __('Pagamento de :v registado.', ['v' => number_format((float) $pagamento->amount, 2, ',', '.') . ' Kz'])
            . ($enviado ? ' ' . __('O revendedor foi avisado por email.') : '')], 201);
    }
}
