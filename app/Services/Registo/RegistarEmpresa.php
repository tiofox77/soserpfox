<?php

namespace App\Services\Registo;

use App\Models\AnalyticsEvent;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Plan;
use App\Models\SmtpSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\AvisoDePagamentoPendente;
use App\Services\Subscriptions\DireitoACortesia;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * O FIM DO REGISTO — a conta, a empresa, a subscrição, o pedido e os módulos.
 *
 * Tudo numa transacção. O que vem depois dela (a conversão para a análise, o
 * aviso ao dono da plataforma, o email de boas-vindas) não faz ninguém perder
 * a conta se falhar.
 */
class RegistarEmpresa
{
    /**
     * @return array{utilizador:User,empresa:Tenant,plano:Plan,estado:string,dias_de_teste:int}
     */
    public function registar(AssistenteDeRegisto $a): array
    {
        // Plano gratuito não leva dados de pagamento. Podem ter ficado de uma
        // escolha anterior — o assistente guarda o progresso — e uma referência
        // esquecida punha a conta a «aguardar aprovação» de 0 Kz que ninguém
        // iria aprovar.
        if ($a->naoHaNadaAPagar()) {
            $a->payment_reference = '';
            $a->payment_proof = null;
        }

        $r = DB::transaction(function () use ($a) {
            $user = $a->utilizador ?? User::create([
                'name' => $a->name,
                'email' => $a->email,
                'password' => Hash::make($a->password),
                'is_active' => true,
                'is_super_admin' => false,
            ]);

            $tenant = Tenant::create([
                'name' => $a->company_name,
                'company_name' => $a->company_name,
                'nif' => $a->company_nif,
                // O regime entra NA criação: é ele que decide os impostos com
                // que a empresa é provisionada.
                'regime' => Tenant::canonicalRegime($a->company_regime),
                'address' => $a->company_address,
                'phone' => $a->company_phone,
                'email' => $a->company_email ?: $a->email,
                'is_active' => true,
            ]);

            $user->tenants()->attach($tenant->id, ['is_active' => true, 'joined_at' => now()]);
            $user->tenant_id = $tenant->id;
            $user->save();

            setPermissionsTeamId($tenant->id);
            createDefaultRolesForTenant($tenant->id);
            $papel = Role::where('name', 'Super Admin')->where('tenant_id', $tenant->id)->first();
            if ($papel) {
                $user->assignRole($papel);
            }

            initializeAccountingDataForTenant($tenant->id);

            $comprovativo = $a->payment_proof?->store('payment-proofs', 'public');

            $plan = Plan::findOrFail($a->selected_plan_id);

            // Cortesia é uma só, para sempre: quem já teve o gratuito ou gastou
            // um período de teste subscreve à mesma, mas a pagar.
            $direito = DireitoACortesia::de($user, $tenant->nif);

            $pagou = $a->payment_reference !== '' || $comprovativo;
            $diasDeTeste = (int) $plan->trial_days;
            $temTeste = $diasDeTeste > 0 && $direito->temDireitoATeste($plan);
            $autoActiva = (bool) $plan->auto_activate;
            $agora = now();

            if ($diasDeTeste > 0 && ! $temTeste) {
                Log::info('Período de teste não concedido — cortesia já utilizada', [
                    'tenant_id' => $tenant->id, 'plan' => $plan->slug,
                    'ja_gratuito' => $direito->jaTeveGratuito(), 'ja_teste' => $direito->jaTeveTeste(),
                ]);
            }

            if ($pagou) {
                // Pagou: aguarda aprovação.
                [$estado, $fimDoTeste, $inicio, $fim] = ['pending', null, null, null];
            } elseif ($temTeste && ($autoActiva || (float) ($plan->price_monthly ?? 0) <= 0)) {
                // Teste só para planos GRATUITOS ou auto-activáveis. Antes bastava
                // trial_days > 0 e qualquer plano pago arrancava sem pagamento.
                $fimDoTeste = $agora->copy()->addDays($diasDeTeste);
                [$estado, $inicio, $fim] = ['trial', $agora, $fimDoTeste];
            } elseif ($autoActiva && $diasDeTeste === 0) {
                // Auto-activável que nunca teve teste: 30 dias. Não se confunde
                // com «o teste foi negado», que não dá dias nenhuns.
                [$estado, $fimDoTeste, $inicio, $fim] = ['active', null, $agora, $agora->copy()->addDays(30)];
            } else {
                [$estado, $fimDoTeste, $inicio, $fim] = ['pending', null, null, null];
            }

            $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => $estado,
                'trial_ends_at' => $fimDoTeste,
                'current_period_start' => $inicio,
                'current_period_end' => $fim,
                'ends_at' => $fim,
                'amount' => $plan->price_monthly,
                'billing_cycle' => 'monthly',
            ]);

            // Avisar o dono da plataforma que entrou uma empresa nova.
            try {
                app(AvisoDePagamentoPendente::class)->empresaRegistada($tenant, $plan, $estado);
            } catch (\Throwable $e) {
                Log::warning('Aviso de empresa registada falhou', ['erro' => $e->getMessage()]);
            }

            // O pedido nasce aprovado quando a subscrição foi auto-activada, para
            // não ficar pendente no painel do Super Admin.
            $autoTeste = $autoActiva && ! $pagou && $temTeste;
            $autoDirecto = $autoActiva && ! $pagou && ! $temTeste;
            $estadoDoPedido = ($autoTeste || $autoDirecto) ? 'approved' : 'pending';

            Order::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price_monthly,
                'payment_method' => $a->payment_method,
                'payment_reference' => $a->payment_reference,
                'payment_proof' => $comprovativo,
                'status' => $estadoDoPedido,
                'approved_at' => $estadoDoPedido === 'approved' ? $agora : null,
                'approved_by' => null,
                'notes' => $autoTeste
                    ? "Pedido auto-aprovado. Plano '{$plan->name}' com {$diasDeTeste} dias de trial gratuito (auto_activate=true). Empresa: {$tenant->name}"
                    : ($autoDirecto
                        ? "Pedido auto-aprovado. Plano '{$plan->name}' activado directamente (auto_activate=true). Empresa: {$tenant->name}"
                        : "Pedido criado via wizard de registro. Empresa: {$tenant->name}"),
            ]);

            // Os módulos só se ligam quando a subscrição já dá direito a usar o
            // sistema — um registo por aprovar nascia com tudo ligado. Pelo
            // TenantModuleSyncService: dependências, pré-requisitos e permissões.
            $modulos = is_array($plan->included_modules) ? $plan->included_modules : [];
            if (empty($modulos)) {
                $modulos = $plan->modules()->pluck('modules.slug')->toArray();
            }

            if (in_array($estado, ['trial', 'active'], true)) {
                $sync = new TenantModuleSyncService();
                foreach ($modulos as $slug) {
                    $sync->activateModule($tenant, $slug);
                }
            }

            return ['utilizador' => $user, 'empresa' => $tenant, 'plano' => $plan, 'estado' => $estado, 'dias_de_teste' => $diasDeTeste];
        });

        // O REVENDEDOR que trouxe a empresa — pelo link ou pelo código (RV-05/06).
        // Fora da transacção: um revendedor que entretanto deixou de estar
        // aprovado não pode deitar o registo abaixo.
        if ($revendedor = $a->revendedor()) {
            \App\Services\Revenda\LigacaoAoRevendedor::ligar($r['empresa'], $revendedor, $a->revendedorVeioDoLink ? 'link' : 'codigo');
        }

        $this->registarConversao($r['utilizador'], $r['empresa'], $r['plano'], $r['estado']);

        try {
            $this->boasVindas($r['utilizador'], $r['empresa']);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar email de boas-vindas', ['error' => $e->getMessage()]);
        }

        return $r;
    }

    /** A conversão fica registada mesmo quando o browser bloqueia o Pixel da Meta. */
    private function registarConversao(User $user, Tenant $tenant, Plan $plan, string $estado): void
    {
        $origem = session('registration_acquisition', []);

        // O registo conta-se sempre (é o contrato a nascer); o IP, o browser e
        // os identificadores das campanhas só com o consentimento de cada coisa.
        $estatisticas = \App\Services\Privacidade\Consentimentos::permite('estatisticas');
        $marketing = \App\Services\Privacidade\Consentimentos::permite('marketing');

        try {
            $evento = [
                'visitor_id' => session('registration_visitor_id', (string) Str::uuid()),
                'session_id' => (string) Str::uuid(),
                'type' => 'conversion',
                'event_name' => 'complete_registration',
                'url' => request()->fullUrl(),
                'path' => '/register',
                'referrer' => request()->headers->get('referer'),
                'utm_source' => $origem['utm_source'] ?? null,
                'utm_medium' => $origem['utm_medium'] ?? null,
                'utm_campaign' => $origem['utm_campaign'] ?? null,
                'utm_term' => $origem['utm_term'] ?? null,
                'utm_content' => $origem['utm_content'] ?? null,
                'ip' => $estatisticas ? \App\Support\Privacidade\Ip::anonimizar(request()->ip()) : null,
                'user_id' => $user->id,
                'meta' => [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'plan_slug' => $plan->slug,
                    'subscription_status' => $estado,
                    // O módulo por onde entrou e o id do evento da Meta: é o que
                    // liga a origem ao módulo e à empresa, e o pixel ao servidor.
                    'modulo' => session('registration_module'),
                    'event_id' => 'registration-'.$tenant->id,
                    'fbclid' => $marketing ? ($origem['fbclid'] ?? null) : null,
                    'gclid' => $marketing ? ($origem['gclid'] ?? null) : null,
                ],
                'user_agent' => $estatisticas ? substr((string) request()->userAgent(), 0, 500) : null,
                'created_at' => now(),
            ];
            if (Schema::hasColumn('analytics_events', 'tenant_id')) {
                $evento['tenant_id'] = $tenant->id;
            }
            if (Schema::hasColumn('analytics_events', 'anonimo')) {
                $evento['anonimo'] = ! $estatisticas;
            }
            AnalyticsEvent::create($evento);
        } catch (\Throwable $e) {
            Log::warning('Falha ao guardar conversão de cadastro', ['erro' => $e->getMessage()]);
        }

        // O event_id é DETERMINÍSTICO por empresa (não leva uuid aleatório): uma
        // empresa criada é UMA conversão. Recarregar a página ou reenviar o
        // pedido repete o mesmo id, e a Meta dedduplica — e um Conversions API,
        // se um dia existir, tem de reutilizar exactamente este id.
        //
        // GUARDADO, e não «flash» (26/09/2026): numa inscrição pendente o
        // `/home` redirecciona para `/subscription-expired`, e o redireccionamento
        // gastava a mensagem de uma só leitura antes de haver página com o
        // pixel — essas inscrições nunca chegavam à Meta. Fica na sessão até
        // uma página com o pixel e com consentimento de marketing o usar (ver
        // partials/meta-pixel); recarregar depois disso já não o repete.
        session()->put('meta_registration_completed', [
            'event_id' => 'registration-'.$tenant->id,
            'plan' => $plan->slug,
            'status' => $estado,
            'em' => now()->timestamp,
        ]);
        session()->forget(['registration_acquisition', 'registration_visitor_id', 'registration_plan', 'registration_module', 'registo_iniciado_gravado']);
    }

    /** O email de boas-vindas: o modelo `welcome` e o SMTP da plataforma, da base de dados. */
    private function boasVindas(User $user, Tenant $tenant): void
    {
        $smtp = SmtpSetting::getForTenant(null);
        if (! $smtp) {
            throw new \RuntimeException('Configuração SMTP não encontrada');
        }
        $smtp->configure();

        $modelo = EmailTemplate::where('slug', 'welcome')->first();
        if (! $modelo) {
            throw new \RuntimeException('Template welcome não encontrado');
        }

        $feito = $modelo->render([
            'user_name' => $user->name,
            'tenant_name' => $tenant->name,
            'app_name' => config('app.name', 'SOS ERP'),
            'app_url' => config('app.url'),
            'support_email' => 'sos@soserp.vip',
            'login_url' => route('login'),
        ]);

        Mail::send([], [], function ($m) use ($user, $feito) {
            $m->to($user->email, $user->name)->subject($feito['subject'])->html($feito['body_html']);
        });
    }
}
