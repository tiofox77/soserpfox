<?php

namespace App\Http\Controllers\Api\Revenda;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\ResellerPayout;
use App\Models\Tenant;
use App\Rules\EmailQueExiste;
use App\Rules\NifDeEmpresa;
use App\Rules\NomeQueParecePessoa;
use App\Services\Revenda\ComissoesDoRevendedor;
use App\Services\Revenda\EmpresasDoRevendedor;
use App\Support\Seguranca\RegraDaSenha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A API DO PORTAL DO REVENDEDOR (RV-07 a RV-12).
 *
 * Tudo a partir do revendedor da sessão (guard `revendedor`): nenhum pedido
 * escolhe o revendedor, e as empresas passam sempre por
 * EmpresasDoRevendedor::empresa(), que só encontra as dele.
 */
class PortalDoRevendedorApiController extends Controller
{
    public function __construct(private readonly EmpresasDoRevendedor $empresas)
    {
    }

    private function eu(Request $request): Reseller
    {
        return $request->user('revendedor');
    }

    public function painel(Request $request): JsonResponse
    {
        $r = $this->eu($request);
        $lista = $this->empresas->lista($r, ['por_pagina' => 50]);
        $todas = collect($lista['empresas']);

        return response()->json([
            'revendedor' => [
                'nome' => $r->name,
                'empresa' => $r->company_name,
                'codigo' => $r->code,
                'link' => $r->link(),
                'regra' => $r->regra()->resumo(),
                'desde' => $r->approved_at?->toIso8601String(),
            ],
            'contagens' => $lista['contagens'],
            'atencao' => $todas->filter(fn ($e) => $e['estado']['por_pagar'] || $e['estado']['a_vencer'] || $e['estado']['chave'] === 'vencida')
                ->take(6)->values(),
            'recentes' => $todas->take(5)->values(),
            'comissoes' => ComissoesDoRevendedor::totais($r->id),
            'ultimas_comissoes' => ResellerCommission::with(['empresa', 'plano', 'pagamento'])->where('reseller_id', $r->id)
                ->latest()->limit(5)->get()->map(fn ($c) => ComissoesDoRevendedor::paraEcra($c))->values(),
        ]);
    }

    /** O QR do link de afiliado, em SVG — para mostrar num cartão ou imprimir. */
    public function qr(Request $request): \Illuminate\Http\Response
    {
        $link = $this->eu($request)->link();
        abort_unless($link, 404);

        $svg = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(320, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        )))->writeString($link);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function empresas(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_merge(array_keys(EmpresasDoRevendedor::ESTADOS), ['por_pagar', 'a_vencer']))],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer'],
        ]);

        return response()->json($this->empresas->lista($this->eu($request), $f));
    }

    public function empresa(Request $request, int $id): JsonResponse
    {
        return response()->json($this->empresas->ficha($this->eu($request), $id));
    }

    public function opcoes(Request $request): JsonResponse
    {
        return response()->json([
            // Com o preço de revendedor ao lado do de tabela — ver EmpresasDoRevendedor::planos.
            'planos' => EmpresasDoRevendedor::planos($this->eu($request)),
            'ciclos' => EmpresasDoRevendedor::ciclos(),
            'regimes' => collect(Tenant::REGIMES)->map(fn ($r, $chave) => ['valor' => $chave, 'rotulo' => __($r['label']), 'descricao' => __($r['description'] ?? '')])->values(),
            'regime_padrao' => Tenant::REGIME_GERAL,
            // A conta da plataforma para a transferência, como no registo.
            'conta' => \App\Support\ContaDaPlataforma::dados(),
        ]);
    }

    /** RV-09: a empresa nova, já ligada ao revendedor. */
    public function criarEmpresa(Request $request): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $dados = $request->validate([
            'company_name' => ['required', 'string', 'min:3', 'max:150', new NomeQueParecePessoa()],
            'company_nif' => ['required', new NifDeEmpresa(), 'unique:tenants,nif'],
            'company_regime' => ['required', Rule::in(array_keys(Tenant::REGIMES))],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'name' => ['required', 'string', 'min:3', 'max:120', new NomeQueParecePessoa()],
            'email' => ['required', 'email', 'max:150', 'unique:users,email', new EmailQueExiste()],
            'selected_plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'email.unique' => __('Este email já tem conta no sistema. Use outro email para o dono da empresa.'),
            'company_nif.unique' => __('Já existe uma empresa com este NIF.'),
            'payment_proof.mimes' => __('O comprovativo tem de ser PDF, JPG ou PNG.'),
            'payment_proof.max' => __('O comprovativo não pode passar de 5 MB.'),
        ], [
            'company_name' => __('Nome da empresa'),
            'company_nif' => __('NIF da empresa'),
            'company_regime' => __('Regime fiscal'),
            'name' => __('Nome do dono'),
            'email' => __('Email do dono'),
            'selected_plan_id' => __('Plano'),
        ]);

        $feito = $this->empresas->criar($this->eu($request), $dados, $request->file('payment_proof'));

        return response()->json([
            'message' => match ($feito['estado']) {
                'pending' => __('Empresa :nome criada. Fica activa quando o pagamento for confirmado.', ['nome' => $feito['empresa']->name]),
                'trial' => __('Empresa :nome criada, em período de teste.', ['nome' => $feito['empresa']->name]),
                default => __('Empresa :nome criada e activa.', ['nome' => $feito['empresa']->name]),
            },
            'id' => $feito['empresa']->id,
            'email_enviado' => $feito['email_enviado'],
            // A senha volta UMA vez: se o email não chegar, o revendedor entrega-a.
            'senha' => $feito['senha'],
        ], 201);
    }

    /** RV-10: pedir um plano (mudar ou renovar) pela empresa. */
    public function pedirPlano(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'ciclo' => ['required', Rule::in(EmpresasDoRevendedor::CICLOS)],
            'referencia' => ['nullable', 'string', 'max:120'],
            'comprovativo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'comprovativo.mimes' => __('O comprovativo tem de ser PDF, JPG ou PNG.'),
            'comprovativo.max' => __('O comprovativo não pode passar de 5 MB.'),
        ]);

        $pedido = $this->empresas->pedirPlano($this->eu($request), $id, $dados, $request->file('comprovativo'));

        return response()->json([
            'message' => $pedido->payment_proof
                ? __('Pedido enviado com o comprovativo. Aguarda a confirmação do pagamento.')
                : __('Pedido enviado. Faça a transferência e anexe o comprovativo ao pedido.'),
            'pedido' => $pedido->id,
        ], 201);
    }

    public function comprovativo(Request $request, int $id, int $pedido): JsonResponse
    {
        $dados = $request->validate([
            'comprovativo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'referencia' => ['nullable', 'string', 'max:120'],
        ], [
            'comprovativo.mimes' => __('O comprovativo tem de ser PDF, JPG ou PNG.'),
            'comprovativo.max' => __('O comprovativo não pode passar de 5 MB.'),
        ]);

        $this->empresas->comprovativo($this->eu($request), $id, $pedido, $request->file('comprovativo'), $dados['referencia'] ?? null);

        return response()->json(['message' => __('Comprovativo anexado. Aguarda a confirmação do pagamento.')]);
    }

    /** O que as empresas têm por pagar, o que foi enviado e o histórico. */
    public function pagamentos(Request $request): JsonResponse
    {
        return response()->json($this->empresas->pagamentos($this->eu($request)) + [
            'conta' => \App\Support\ContaDaPlataforma::dados(),
        ]);
    }

    /** Pagar uma factura de renovação pelo cliente: referência e comprovativo. */
    public function pagarFactura(Request $request, int $id, int $factura): JsonResponse
    {
        $dados = $request->validate([
            'comprovativo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'referencia' => ['nullable', 'string', 'max:120'],
        ], [
            'comprovativo.required' => __('Anexe o comprovativo da transferência.'),
            'comprovativo.mimes' => __('O comprovativo tem de ser PDF, JPG ou PNG.'),
            'comprovativo.max' => __('O comprovativo não pode passar de 5 MB.'),
        ]);

        $f = $this->empresas->pagarFactura($this->eu($request), $id, $factura, $request->file('comprovativo'), $dados['referencia'] ?? null);

        return response()->json(['message' => __('Pagamento da factura :n enviado. Aguarda a confirmação.', ['n' => $f->invoice_number])]);
    }

    public function comissoes(Request $request): JsonResponse
    {
        $r = $this->eu($request);
        $f = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(ResellerCommission::ESTADOS))],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = ResellerCommission::with(['empresa', 'plano', 'pagamento'])->where('reseller_id', $r->id)
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->latest()->paginate(15, ['*'], 'pagina', (int) ($f['pagina'] ?? 1));

        return response()->json([
            'comissoes' => collect($pagina->items())->map(fn ($c) => ComissoesDoRevendedor::paraEcra($c))->values(),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total(), 'de' => $pagina->firstItem() ?? 0, 'ate' => $pagina->lastItem() ?? 0],
            'totais' => ComissoesDoRevendedor::totais($r->id),
            'regra' => $r->regra()->resumo(),
            'pagamentos' => ResellerPayout::withCount('comissoes')->where('reseller_id', $r->id)->latest('paid_at')->limit(20)->get()
                ->map(fn (ResellerPayout $p) => [
                    'id' => $p->id,
                    'valor' => (float) $p->amount,
                    'data' => $p->paid_at?->toDateString(),
                    'forma' => __(ResellerPayout::METODOS[$p->method] ?? $p->method),
                    'referencia' => $p->reference,
                    'comissoes' => (int) $p->comissoes_count,
                ])->values(),
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        $r = $this->eu($request);

        return response()->json(['perfil' => $r->only(['name', 'company_name', 'nif', 'email', 'phone', 'province', 'city', 'website', 'bank_name', 'iban']) + [
            'codigo' => $r->code,
            'link' => $r->link(),
            'regra' => $r->regra()->resumo(),
        ]]);
    }

    public function guardarPerfil(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'nif' => ['nullable', 'string', 'max:30'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'province' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'iban' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 .]+$/'],
        ], ['iban.regex' => __('O IBAN só leva letras, algarismos e espaços.')]);

        // O email não se muda aqui: é a entrada, e muda-se com o super admin.
        $this->eu($request)->update($dados);

        return response()->json(['message' => __('Perfil guardado.')]);
    }

    public function senha(Request $request): JsonResponse
    {
        $r = $this->eu($request);
        $dados = $request->validate([
            'actual' => ['required', 'string'],
            'nova' => ['required', 'string', 'different:actual', 'confirmed', RegraDaSenha::regra()],
        ], [
            'nova.different' => __('A senha nova tem de ser diferente da actual.'),
            'nova.confirmed' => __('As duas senhas não coincidem.'),
        ]);

        if (! Hash::check($dados['actual'], $r->password)) {
            throw ValidationException::withMessages(['actual' => __('A senha actual não está certa.')]);
        }

        $r->forceFill(['password' => $dados['nova']])->save();

        return response()->json(['message' => __('Senha alterada.')]);
    }
}
