<?php

namespace App\Services\Revenda;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Registo\AssistenteDeRegisto;
use App\Services\Registo\RegistarEmpresa;
use App\Services\Subscriptions\DireitoACortesia;
use App\Services\Tenants\SinaisDeVida;
use App\Support\CicloDeFacturacao;
use App\Support\EstadoDaSubscricao;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AS EMPRESAS DE UM REVENDEDOR (16/09/2026, RV-08, RV-09 e RV-10).
 *
 * O que o revendedor vê de cada empresa é a CONTA NA PLATAFORMA — o plano, o
 * estado da subscrição, o que está por pagar, quando entraram pela última vez,
 * quantas pessoas a usam. Nunca os dados de negócio (facturas aos clientes
 * dela, stock, contabilidade).
 *
 * E só as dele: cada acesso começa por `empresa()`, que devolve 404 para uma
 * empresa de outro revendedor.
 */
class EmpresasDoRevendedor
{
    public const ESTADOS = [
        'teste' => 'Em teste',
        'activa' => 'Activa',
        'vencida' => 'Vencida',
        'sem_plano' => 'Sem plano',
        'suspensa' => 'Desactivada',
    ];

    public const CICLOS = ['monthly', 'quarterly', 'semiannual', 'yearly'];

    public function empresa(Reseller $r, int $id): Tenant
    {
        $t = Tenant::where('reseller_id', $r->id)->find($id);

        if (! $t) {
            throw (new ModelNotFoundException())->setModel(Tenant::class, [$id]);
        }

        return $t;
    }

    /** A chave do estado e as duas marcas (por pagar, a vencer). */
    private static function estado(Tenant $t, bool $porPagar): array
    {
        $s = EstadoDaSubscricao::para($t);

        $chave = match (true) {
            ! $t->is_active => 'suspensa',
            $s['rotulo'] === 'Em teste' => 'teste',
            $s['rotulo'] === 'Activo' => 'activa',
            in_array($s['rotulo'], ['Expirado', 'Teste expirado'], true) => 'vencida',
            default => 'sem_plano',
        };

        return [
            'chave' => $chave,
            'rotulo' => __(self::ESTADOS[$chave]),
            'detalhe' => __($s['detalhe']),
            'ate' => $s['ate'],
            'dias' => $s['dias'],
            'a_vencer' => in_array($chave, ['teste', 'activa'], true) && $s['dias'] !== null && $s['dias'] <= 7,
            'por_pagar' => $porPagar,
        ];
    }

    /** Os ids das empresas com um pedido ou uma factura da plataforma por pagar. */
    private static function comContasPorPagar(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return Order::whereIn('tenant_id', $ids)->where('status', 'pending')->pluck('tenant_id')
            ->merge(Invoice::whereIn('tenant_id', $ids)->whereNotNull('subscription_id')->whereIn('status', ['pending', 'overdue'])->pluck('tenant_id'))
            ->unique()->flip()->all();
    }

    private static function linha(Tenant $t, ?object $sinais, bool $porPagar): array
    {
        $sub = $t->activeSubscription;

        return [
            'id' => $t->id,
            'nome' => $t->name,
            'nif' => $t->nif,
            'email' => $t->email,
            'telefone' => $t->phone,
            'plano' => $sub?->plan?->name,
            'ciclo' => $sub?->billing_cycle ? __(CicloDeFacturacao::nome($sub->billing_cycle)) : null,
            'valor' => $sub ? (float) $sub->amount : null,
            'estado' => self::estado($t, $porPagar),
            'utilizadores' => (int) ($t->users_count ?? 0),
            'ultima_entrada' => $sinais?->ultima_entrada?->toIso8601String(),
            'ultima_entrada_ha' => SinaisDeVida::distancia($sinais?->ultima_entrada),
            'via' => $t->reseller_via,
            'via_rotulo' => $t->reseller_via ? __(LigacaoAoRevendedor::VIAS[$t->reseller_via] ?? $t->reseller_via) : null,
            'ligada_em' => $t->reseller_linked_at?->toIso8601String(),
            'criada_em' => $t->created_at?->toIso8601String(),
        ];
    }

    /**
     * A LISTA, com as contagens por estado (antes do filtro, para os cartões).
     *
     * @param  array{procura?:?string, estado?:?string, pagina?:?int, por_pagina?:?int}  $f
     */
    public function lista(Reseller $r, array $f): array
    {
        $procura = trim((string) ($f['procura'] ?? ''));
        $todas = Tenant::where('reseller_id', $r->id)
            ->with('activeSubscription.plan')->withCount('users')
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('company_name', 'like', "%{$procura}%")
                ->orWhere('nif', 'like', "%{$procura}%")
                ->orWhere('email', 'like', "%{$procura}%")))
            ->orderByDesc('reseller_linked_at')->orderByDesc('id')
            ->get();

        $porPagar = self::comContasPorPagar($todas->pluck('id')->all());
        $sinais = SinaisDeVida::para($todas->pluck('id'));
        $linhas = $todas->map(fn (Tenant $t) => self::linha($t, $sinais[$t->id] ?? null, isset($porPagar[$t->id])));

        $contagens = ['todas' => $linhas->count(), 'por_pagar' => 0, 'a_vencer' => 0] + array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach ($linhas as $l) {
            $contagens[$l['estado']['chave']]++;
            $contagens['por_pagar'] += $l['estado']['por_pagar'] ? 1 : 0;
            $contagens['a_vencer'] += $l['estado']['a_vencer'] ? 1 : 0;
        }

        $estado = $f['estado'] ?? null;
        $filtradas = match (true) {
            $estado === 'por_pagar' => $linhas->filter(fn ($l) => $l['estado']['por_pagar']),
            $estado === 'a_vencer' => $linhas->filter(fn ($l) => $l['estado']['a_vencer']),
            $estado !== null && $estado !== '' => $linhas->filter(fn ($l) => $l['estado']['chave'] === $estado),
            default => $linhas,
        };

        $porPagina = in_array((int) ($f['por_pagina'] ?? 15), [15, 30, 50], true) ? (int) ($f['por_pagina'] ?? 15) : 15;
        $total = $filtradas->count();
        $ultima = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, (int) ($f['pagina'] ?? 1)), $ultima);

        return [
            'empresas' => $filtradas->slice(($pagina - 1) * $porPagina, $porPagina)->values(),
            'paginacao' => ['pagina' => $pagina, 'ultima' => $ultima, 'total' => $total, 'por_pagina' => $porPagina,
                'de' => $total ? ($pagina - 1) * $porPagina + 1 : 0, 'ate' => min($total, $pagina * $porPagina)],
            'contagens' => $contagens,
        ];
    }

    /** A FICHA de uma empresa: a conta, a subscrição, os pedidos, as facturas e as comissões. */
    public function ficha(Reseller $r, int $id): array
    {
        $t = $this->empresa($r, $id);
        $t->load('activeSubscription.plan')->loadCount('users');
        $sinais = SinaisDeVida::para([$t->id])[$t->id] ?? null;
        $porPagar = self::comContasPorPagar([$t->id]);
        $dono = $t->users()->orderBy('tenant_user.id')->first();
        $sub = $t->activeSubscription;

        return [
            'empresa' => self::linha($t, $sinais, isset($porPagar[$t->id])) + [
                'razao_social' => $t->company_name,
                'morada' => $t->address,
                'regime' => $t->regime ? __(Tenant::REGIMES[$t->regime]['label'] ?? $t->regime) : null,
                'dono' => $dono ? ['nome' => $dono->name, 'email' => $dono->email] : null,
            ],
            'subscricao' => $sub ? [
                'plano_id' => $sub->plan_id,
                'plano' => $sub->plan?->name,
                'ciclo' => $sub->billing_cycle,
                'ciclo_rotulo' => __(CicloDeFacturacao::nome($sub->billing_cycle ?? 'monthly')),
                'valor' => (float) $sub->amount,
                'inicio' => $sub->current_period_start?->toDateString(),
                'fim' => ($sub->trial_ends_at && $sub->trial_ends_at->isFuture() ? $sub->trial_ends_at : $sub->ends_at)?->toDateString(),
            ] : null,
            'pedidos' => Order::with('plan')->where('tenant_id', $t->id)->latest()->limit(10)->get()->map(fn (Order $o) => [
                'id' => $o->id,
                'plano' => $o->plan?->name,
                'ciclo' => $o->billing_cycle ? __(CicloDeFacturacao::nome($o->billing_cycle)) : null,
                'valor' => (float) $o->amount,
                'estado' => $o->status,
                'estado_rotulo' => __(['pending' => 'Por confirmar', 'approved' => 'Aprovado', 'rejected' => 'Recusado'][$o->status] ?? $o->status),
                'referencia' => $o->payment_reference,
                'comprovativo' => $o->payment_proof ? Storage::url($o->payment_proof) : null,
                'motivo' => $o->rejection_reason,
                'data' => $o->created_at?->toIso8601String(),
            ])->values(),
            'facturas' => Invoice::where('tenant_id', $t->id)->whereNotNull('subscription_id')->latest('invoice_date')->limit(10)->get()->map(fn (Invoice $i) => [
                'id' => $i->id,
                'numero' => $i->invoice_number,
                'descricao' => $i->description,
                'data' => $i->invoice_date?->toDateString(),
                'vencimento' => $i->due_date?->toDateString(),
                'total' => (float) $i->total,
                'estado' => $i->status,
                'estado_rotulo' => __(['pending' => 'Por pagar', 'paid' => 'Paga', 'overdue' => 'Vencida', 'cancelled' => 'Anulada'][$i->status] ?? $i->status),
                'referencia' => $i->payment_reference,
                'pagamento_enviado' => $i->payment_submitted_at !== null && $i->status !== 'paid',
                'comprovativo' => $i->payment_proof ? Storage::url($i->payment_proof) : null,
                'motivo_recusa' => $i->payment_rejection_reason,
                'pode_pagar' => in_array($i->status, ['pending', 'overdue'], true),
            ])->values(),
            'comissoes' => ResellerCommission::with(['plano', 'pagamento'])->where('reseller_id', $r->id)->where('tenant_id', $t->id)
                ->latest()->limit(20)->get()->map(fn ($c) => ComissoesDoRevendedor::paraEcra($c))->values(),
        ];
    }

    /**
     * UM PLANO 100% GRATUITO não passa pelo revendedor (16/09/2026): a empresa
     * activa-o sozinha no registo, e o revendedor não o pode dar aos clientes.
     */
    public static function gratuito(Plan $p): bool
    {
        return (float) ($p->getPrice('monthly') ?? 0) <= 0;
    }

    private static function recusarGratuito(Plan $p, string $campo): void
    {
        if (self::gratuito($p)) {
            throw ValidationException::withMessages([$campo => __('Os planos gratuitos não se dão pelo revendedor: a empresa pode activá-los sozinha no registo.')]);
        }
    }

    /** Os planos que se podem escolher, com o preço de cada ciclo — sem os gratuitos. */
    public static function planos(): array
    {
        return Plan::publico()->orderBy('order')->get()->reject(fn (Plan $p) => self::gratuito($p))->values()->map(fn (Plan $p) => [
            'id' => $p->id,
            'nome' => $p->name,
            'descricao' => $p->description,
            'utilizadores' => (int) $p->max_users,
            'dias_de_teste' => (int) $p->trial_days,
            'destaque' => (bool) $p->is_featured,
            'precos' => collect(self::CICLOS)->mapWithKeys(fn ($c) => [$c => (float) ($p->getPrice($c) ?? 0)])->all(),
        ])->values()->all();
    }

    public static function ciclos(): array
    {
        return collect(self::CICLOS)->map(fn ($c) => ['valor' => $c, 'rotulo' => __(CicloDeFacturacao::nome($c))])->all();
    }

    /**
     * CRIAR A EMPRESA PELO CLIENTE (RV-09) — pelo mesmo caminho do registo.
     *
     * A senha é gerada e segue por email para o dono (e volta ao revendedor uma
     * vez, para a entregar se o email não chegar).
     *
     * @return array{empresa:Tenant, estado:string, senha:string, email_enviado:bool}
     */
    public function criar(Reseller $r, array $dados, ?UploadedFile $comprovativo): array
    {
        $plano = Plan::publico()->findOrFail($dados['selected_plan_id']);
        self::recusarGratuito($plano, 'selected_plan_id');

        if ($recusa = DireitoACortesia::de(null, $dados['company_nif'])->motivoParaRecusar($plano)) {
            throw ValidationException::withMessages(['selected_plan_id' => __($recusa)]);
        }

        $senha = Str::password(12, symbols: false);
        $a = AssistenteDeRegisto::paraRevendedor($dados + ['password' => $senha], $comprovativo);

        $feito = app(RegistarEmpresa::class)->registar($a);
        $empresa = $feito['empresa'];

        LigacaoAoRevendedor::ligar($empresa, $r, 'revendedor');

        // O pedido do plano é do revendedor: é ele que paga pelo cliente.
        Order::where('tenant_id', $empresa->id)->update(['reseller_id' => $r->id]);

        $enviado = app(AvisosDaRevenda::class)->empresaCriada($feito['utilizador'], $empresa, $senha, $r, $plano, $feito['estado']);

        return ['empresa' => $empresa, 'estado' => $feito['estado'], 'senha' => $senha, 'email_enviado' => $enviado];
    }

    /**
     * TRATAR DA SUBSCRIÇÃO (RV-10): o pedido que a empresa faria em «A minha
     * conta» — plano, ciclo, referência e comprovativo. Quem aprova é o super
     * admin, como sempre.
     */
    public function pedirPlano(Reseller $r, int $empresaId, array $dados, ?UploadedFile $comprovativo): Order
    {
        $t = $this->empresa($r, $empresaId);
        $plano = Plan::publico()->findOrFail($dados['plan_id']);
        self::recusarGratuito($plano, 'plan_id');
        $dono = $t->users()->orderBy('tenant_user.id')->first();

        if ($recusa = DireitoACortesia::daEmpresa($t, $dono)->motivoParaRecusar($plano)) {
            throw ValidationException::withMessages(['plan_id' => __($recusa)]);
        }

        if (Order::where('tenant_id', $t->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['plan_id' => __('Esta empresa já tem um pedido à espera de confirmação. Anexe-lhe o comprovativo em vez de fazer outro.')]);
        }

        $valor = (float) ($plano->getPrice($dados['ciclo']) ?? 0);

        if ($valor <= 0) {
            throw ValidationException::withMessages(['plan_id' => __('Este plano não tem preço neste ciclo.')]);
        }

        return DB::transaction(function () use ($t, $dono, $plano, $valor, $dados, $comprovativo, $r) {
            $pedido = new Order([
                'tenant_id' => $t->id,
                'user_id' => $dono?->id,
                'plan_id' => $plano->id,
                'amount' => $valor,
                'billing_cycle' => $dados['ciclo'],
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'payment_reference' => $dados['referencia'] ?? null,
                'payment_proof' => $comprovativo?->store('payment-proofs', 'public'),
                'notes' => __('Pedido feito pelo revendedor :nome (:codigo).', ['nome' => $r->nomeVisivel(), 'codigo' => $r->code]),
            ]);
            $pedido->forceFill(['reseller_id' => $r->id])->save();

            return $pedido;
        });
    }

    /** O comprovativo de um pedido que já existe, de uma empresa dele. */
    public function comprovativo(Reseller $r, int $empresaId, int $pedidoId, UploadedFile $ficheiro, ?string $referencia): Order
    {
        $t = $this->empresa($r, $empresaId);
        $pedido = Order::where('tenant_id', $t->id)->findOrFail($pedidoId);

        if ($pedido->status !== 'pending') {
            throw ValidationException::withMessages(['comprovativo' => __('Este pedido já não está à espera de pagamento.')]);
        }

        $pedido->forceFill(array_filter([
            'payment_proof' => $ficheiro->store('payment-proofs', 'public'),
            'payment_reference' => $referencia ?: null,
            'reseller_id' => $r->id,
        ]))->save();

        return $pedido;
    }

    /**
     * PAGAR UMA FACTURA DE RENOVAÇÃO PELO CLIENTE: a referência e o
     * comprovativo da transferência. Fica «por confirmar» até o super admin a
     * dar como paga (ou recusar, com o motivo).
     */
    public function pagarFactura(Reseller $r, int $empresaId, int $facturaId, UploadedFile $ficheiro, ?string $referencia): Invoice
    {
        $t = $this->empresa($r, $empresaId);
        $factura = Invoice::where('tenant_id', $t->id)->whereNotNull('subscription_id')->findOrFail($facturaId);

        if (! in_array($factura->status, ['pending', 'overdue'], true)) {
            throw ValidationException::withMessages(['comprovativo' => __('Esta factura já não está por pagar.')]);
        }

        $factura->forceFill([
            'payment_method' => 'bank_transfer',
            'payment_reference' => $referencia ?: $factura->payment_reference,
            'payment_proof' => $ficheiro->store('payment-proofs', 'public'),
            'payment_submitted_at' => now(),
            'payment_submitted_by_reseller_id' => $r->id,
            'payment_rejection_reason' => null,
        ])->save();

        app(AvisosDaRevenda::class)->pagamentoEnviado($r, $t, $factura);

        return $factura;
    }

    private static function linhaDoPedido(Order $o): array
    {
        return [
            'tipo' => 'pedido',
            'id' => $o->id,
            'empresa_id' => $o->tenant_id,
            'empresa' => $o->tenant?->name,
            'descricao' => trim(($o->plan?->name ?? '') . ' · ' . __(CicloDeFacturacao::nome($o->billing_cycle ?? 'monthly')), ' ·'),
            'valor' => (float) $o->amount,
            'referencia' => $o->payment_reference,
            'comprovativo' => $o->payment_proof ? Storage::url($o->payment_proof) : null,
            'estado' => match (true) {
                $o->status === 'approved' => 'confirmado',
                $o->status === 'rejected' => 'recusado',
                (bool) $o->payment_proof => 'por_confirmar',
                default => 'por_pagar',
            },
            'motivo' => $o->rejection_reason,
            'vence' => null,
            'data' => ($o->approved_at ?? $o->rejected_at ?? $o->updated_at)?->toIso8601String(),
        ];
    }

    private static function linhaDaFactura(Invoice $i): array
    {
        return [
            'tipo' => 'factura',
            'id' => $i->id,
            'empresa_id' => $i->tenant_id,
            'empresa' => $i->tenant?->name,
            'descricao' => trim($i->invoice_number . ' · ' . ($i->description ?? ''), ' ·'),
            'valor' => (float) $i->total,
            'referencia' => $i->payment_reference,
            'comprovativo' => $i->payment_proof ? Storage::url($i->payment_proof) : null,
            'estado' => match (true) {
                $i->status === 'paid' => 'confirmado',
                $i->payment_submitted_at !== null => 'por_confirmar',
                $i->status === 'overdue' || ($i->due_date && $i->due_date->lt(today())) => 'vencida',
                default => 'por_pagar',
            },
            'motivo' => $i->payment_rejection_reason,
            'vence' => $i->due_date?->toDateString(),
            'data' => ($i->paid_at ?? $i->payment_submitted_at ?? $i->invoice_date)?->toIso8601String(),
        ];
    }

    /**
     * OS PAGAMENTOS DO REVENDEDOR: o que as empresas dele têm por pagar (pedidos
     * e facturas), o que ele já enviou e espera confirmação, e o histórico do
     * que foi confirmado ou recusado.
     */
    public function pagamentos(Reseller $r): array
    {
        $ids = Tenant::where('reseller_id', $r->id)->pluck('id');

        $pedidos = Order::with(['tenant', 'plan'])->whereIn('tenant_id', $ids)->where('status', 'pending')->latest()->get()->map(fn ($o) => self::linhaDoPedido($o));
        $facturas = Invoice::with('tenant')->whereIn('tenant_id', $ids)->whereNotNull('subscription_id')
            ->whereIn('status', ['pending', 'overdue'])->orderBy('due_date')->get()->map(fn ($i) => self::linhaDaFactura($i));
        $abertos = $pedidos->concat($facturas);

        $historico = Order::with(['tenant', 'plan'])->where('reseller_id', $r->id)->whereIn('status', ['approved', 'rejected'])->latest('updated_at')->limit(20)->get()
            ->map(fn ($o) => self::linhaDoPedido($o))
            ->concat(Invoice::with('tenant')->where('payment_submitted_by_reseller_id', $r->id)->where('status', 'paid')->latest('paid_at')->limit(20)->get()->map(fn ($i) => self::linhaDaFactura($i)))
            ->sortByDesc('data')->take(20)->values();

        return [
            'por_pagar' => $abertos->whereIn('estado', ['por_pagar', 'vencida'])->values(),
            'por_confirmar' => $abertos->where('estado', 'por_confirmar')->values(),
            'historico' => $historico,
            'totais' => [
                'por_pagar' => round((float) $abertos->whereIn('estado', ['por_pagar', 'vencida'])->sum('valor'), 2),
                'por_pagar_n' => $abertos->whereIn('estado', ['por_pagar', 'vencida'])->count(),
                'por_confirmar' => round((float) $abertos->where('estado', 'por_confirmar')->sum('valor'), 2),
                'por_confirmar_n' => $abertos->where('estado', 'por_confirmar')->count(),
            ],
        ];
    }

    /** Os números da barra lateral: empresas e pagamentos por fazer. */
    public static function contadores(Reseller $r): array
    {
        $ids = Tenant::where('reseller_id', $r->id)->pluck('id');

        return [
            'empresas' => $ids->count(),
            'pagamentos' => $ids->isEmpty() ? 0
                : Order::whereIn('tenant_id', $ids)->where('status', 'pending')->whereNull('payment_proof')->count()
                    + Invoice::whereIn('tenant_id', $ids)->whereNotNull('subscription_id')->whereIn('status', ['pending', 'overdue'])->whereNull('payment_submitted_at')->count(),
        ];
    }

    /** O dono que a empresa teria: um email que já tem conta não se pode usar. */
    public static function emailLivre(string $email): bool
    {
        return ! User::where('email', mb_strtolower(trim($email)))->exists();
    }
}
