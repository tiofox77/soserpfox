<?php

namespace App\Services\Billing;

use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SmsService;
use App\Support\CicloDeFacturacao;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Avisa o CLIENTE, por email e por SMS, do que se passa com a subscrição dele.
 *
 * O ciclo de facturação já emitia a factura do período seguinte e já cortava o
 * acesso no fim — mas nunca dizia nada a ninguém. O cliente descobria que
 * tinha uma conta por pagar quando o sistema deixava de abrir.
 *
 * Cinco ocasiões, e só estas:
 *
 *   factura_emitida   saiu a conta do período seguinte
 *   factura_a_vencer  faltam poucos dias para o prazo
 *   factura_vencida   passou o prazo e o acesso vai cair
 *   renovada          o pagamento entrou e há período novo
 *   plano_a_expirar   o período acaba e não há factura nenhuma a cobri-lo
 *                     (testes e planos promocionais, que não se facturam)
 *
 * O QUE TORNA ISTO SEGURO
 * -----------------------
 * Nada disto pode sair duas vezes. A varredura corre à boleia do tráfego e a
 * condição que a dispara ("por pagar") continua verdadeira até alguém pagar:
 * sem memória, cada passagem repetia tudo — assédio por email, e em SMS
 * dinheiro da plataforma a sair a cada clique de um cliente qualquer.
 *
 * A memória é a tabela `avisos_de_subscricao`, com índice único, e reserva-se
 * o lugar ANTES de enviar. Ao contrário, dois pedidos simultâneos passavam
 * ambos pela verificação e mandavam ambos.
 *
 * A EMPRESA VEM SEMPRE DO DOCUMENTO
 * ---------------------------------
 * Isto corre no fim do pedido de UM utilizador, que tem a empresa dele fixada
 * na sessão. A empresa avisada sai sempre da factura ou da subscrição — nunca
 * de `currentTenant()`. E o SMTP e o SMS são os da PLATAFORMA (tenant nulo):
 * usar os do cliente seria a plataforma a cobrar-lhe com a conta dele.
 */
class AvisosDeSubscricao
{
    /** Contagem desta passagem. */
    private array $conta = ['email' => 0, 'sms' => 0, 'repetidos' => 0, 'falhados' => 0,
        'sem_contacto' => 0, 'nao_activada' => 0];

    public function __construct(
        private ContactoDeFacturacao $contactos,
    ) {
    }

    public function contagem(): array
    {
        return $this->conta;
    }

    // ── os cinco avisos ──────────────────────────────────────────────────────

    /**
     * Saiu a factura do período seguinte.
     *
     * Chamado do sítio exacto onde ela nasce, e não de uma varredura: a
     * varredura não distingue esta da PRIMEIRA factura de uma subscrição nova,
     * que não é uma renovação e não deve ser anunciada como tal.
     */
    public function facturaEmitida(Invoice $factura, Subscription $sub): void
    {
        $this->avisar('factura_emitida', $factura->tenant, "factura:{$factura->id}",
            $this->dadosDaFactura($factura, $sub));
    }

    /** O pagamento entrou e a subscrição foi estendida. */
    public function renovada(Invoice $factura, Subscription $sub): void
    {
        $this->avisar('renovada', $factura->tenant, "factura:{$factura->id}",
            $this->dadosDaFactura($factura, $sub));
    }

    /**
     * A varredura: o que está a chegar ao prazo, o que já passou, e os
     * períodos que acabam sem factura nenhuma a cobri-los.
     *
     * @return array contagens + o que faria (em modo de leitura)
     */
    public function varrer(bool $soVer = false): array
    {
        $this->soVer = $soVer;
        $this->avariou = false;
        $detalhe = [];

        $tecto = (int) config('billing.avisos_max_por_passagem', 25);
        $saidos = 0;

        foreach ($this->aVencer() as [$factura, $sub, $dias]) {
            if ($saidos >= $tecto) {
                break;
            }
            $dados = $this->dadosDaFactura($factura, $sub) + ['dias' => $dias];
            if ($this->avisar('factura_a_vencer', $factura->tenant, "factura:{$factura->id}", $dados)) {
                $saidos++;
                $detalhe[] = ['aviso' => 'a vencer', 'empresa' => $factura->tenant?->name, 'dias' => $dias,
                    'factura' => $factura->invoice_number];
            }
        }

        foreach ($this->vencidas() as [$factura, $sub, $atraso]) {
            if ($saidos >= $tecto) {
                break;
            }
            $dados = $this->dadosDaFactura($factura, $sub) + ['dias' => $atraso];
            if ($this->avisar('factura_vencida', $factura->tenant, "factura:{$factura->id}", $dados)) {
                $saidos++;
                $detalhe[] = ['aviso' => 'vencida', 'empresa' => $factura->tenant?->name, 'dias' => $atraso,
                    'factura' => $factura->invoice_number];
            }
        }

        foreach ($this->periodosAAcabar() as [$sub, $dias]) {
            if ($saidos >= $tecto) {
                break;
            }
            $dados = $this->dadosDaSubscricao($sub) + ['dias' => $dias];
            if ($this->avisar('plano_a_expirar', $sub->tenant, "subscricao:{$sub->id}", $dados)) {
                $saidos++;
                $detalhe[] = ['aviso' => 'plano a expirar', 'empresa' => $sub->tenant?->name, 'dias' => $dias,
                    'factura' => '—'];
            }
        }

        if ($saidos >= $tecto) {
            // Um tecto que corta em silêncio faz parecer que está tudo tratado.
            Log::warning('Avisos de subscrição: tecto da passagem atingido', ['tecto' => $tecto]);
        }

        $this->soVer = false;

        return $this->conta + [
 'detalhe'        => $detalhe,
 'tecto_atingido' => $saidos >= $tecto,
 // Quem lê o resumo tem de distinguir "não havia nada a enviar" de
 // "a base de dados recusou tudo". São coisas opostas e o número de
 // enviados é 0 nas duas.
 'avariou'        => $this->avariou,
        ];
    }

    private bool $soVer = false;

    /** Houve avaria de base de dados nesta passagem? */
    private bool $avariou = false;

    // ── quem entra em cada varredura ─────────────────────────────────────────

    /** Facturas por pagar, nos dias exactos configurados. */
    private function aVencer(): array
    {
        $dias = $this->diasConfigurados('billing.avisos_dias_antes', [3, 1]);

        $facturas = Invoice::with(['tenant', 'subscription.plan'])
            ->whereNotNull('subscription_id')
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [today(), today()->addDays(max($dias))])
            ->get();

        $saida = [];

        foreach ($facturas as $factura) {
            if (!$factura->tenant || !$factura->subscription) {
                continue;
            }

            $falta = (int) today()->diffInDays($factura->due_date, false);

            // Dias exactos e não um intervalo: senão o cliente recebia o mesmo
            // aviso todos os dias até pagar.
            if (!in_array($falta, $dias, true)) {
                continue;
            }

            $saida[] = [$factura, $factura->subscription, $falta];
        }

        return $saida;
    }

    /** Facturas cujo prazo passou, nos dias de atraso configurados. */
    private function vencidas(): array
    {
        $dias = $this->diasConfigurados('billing.avisos_dias_atraso', [1, 3, 7]);

        $facturas = Invoice::with(['tenant', 'subscription.plan'])
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [today()->subDays(max($dias)), today()->subDay()])
            ->get();

        $saida = [];

        foreach ($facturas as $factura) {
            if (!$factura->tenant || !$factura->subscription) {
                continue;
            }

            $atraso = (int) $factura->due_date->diffInDays(today(), false);

            if (!in_array($atraso, $dias, true)) {
                continue;
            }

            $saida[] = [$factura, $factura->subscription, $atraso];
        }

        return $saida;
    }

    /**
     * Períodos a acabar que não têm factura nenhuma a cobri-los.
     *
     * São os testes e os planos promocionais: a renovação não lhes emite conta
     * (só apanha 'active' com valor acima de zero), pelo que sem isto acabavam
     * sem um único aviso. Quem já tem factura por vencer fica de fora — senão
     * recebia dois avisos sobre a mesma coisa no mesmo dia.
     */
    private function periodosAAcabar(): array
    {
        $dias = $this->diasConfigurados('billing.avisos_dias_antes', [3, 1]);

        $subs = Subscription::with(['tenant', 'plan'])
            ->whereIn('status', ['active', 'trial'])
            ->whereNull('cancelled_at')
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), now()->addDays(max($dias) + 1)])
            ->get();

        $saida = [];

        foreach ($subs as $sub) {
            if (!$sub->tenant || !$sub->plan) {
                continue;
            }

            $falta = (int) today()->diffInDays($sub->current_period_end, false);

            if (!in_array($falta, $dias, true)) {
                continue;
            }

            $temFactura = Invoice::where('subscription_id', $sub->id)
                ->where('status', 'pending')
                ->whereDate('due_date', '>=', today())
                ->exists();

            if ($temFactura) {
                continue;
            }

            $saida[] = [$sub, $falta];
        }

        return $saida;
    }

    /** @return int[] */
    private function diasConfigurados(string $chave, array $porOmissao): array
    {
        $dias = config($chave, $porOmissao);
        $dias = is_array($dias) ? array_map('intval', $dias) : $porOmissao;

        return $dias ?: $porOmissao;
    }

    // ── o envio ──────────────────────────────────────────────────────────────

    /**
     * Manda um aviso pelos canais ligados.
     *
     * @return bool se saiu alguma coisa agora (para o tecto da passagem)
     */
    private function avisar(string $aviso, ?Tenant $empresa, string $referencia, array $dados): bool
    {
        if (!$empresa) {
            return false;
        }

        // Empresa que ainda não foi activada não recebe avisos.
        //
        // Aprovar um pedido de licença cria a empresa com o email do
        // formulário e sem ninguém lá dentro; o contacto de facturação recorre
        // a esse email, pelo que quem nunca instalou nada começava a receber
        // "a sua factura está a vencer". Contado à parte para isto aparecer no
        // relatório da varredura — saltar em silêncio esconderia empresas que
        // não estão a ser cobradas.
        if (!$empresa->activacaoConcluida()) {
            $this->conta['nao_activada']++;

            return false;
        }

        $contacto = $this->contactos->para($empresa);

        // A união de arrays com `+` mantém o valor da ESQUERDA. Aqui está bem
        // — o que vem do chamador manda e isto são só os valores em falta.
        // (Ao contrário do que acontecia nas contagens de dias: ver o comentário
        // em dadosDaFactura.)
        $dados = $dados + [
            'dias'             => 0,
            'responsavel_nome' => $contacto['nome'],
            'empresa_nome'     => $contacto['empresa'],
            'app_name'         => config('app.name', 'SOS ERP'),
            'app_url'          => config('app.url'),
            'billing_url'      => rtrim((string) config('app.url'), '/') . '/my-account?tab=billing',
        ];

        if (!$contacto['email'] && !$contacto['telefone']) {
            $this->conta['sem_contacto']++;

            return false;
        }

        $saiu = false;

        if ($contacto['email']) {
            $saiu = $this->porEmail($aviso, $empresa, $referencia, $contacto['email'], $dados) || $saiu;
        }

        // O canal PAGO tem interruptor próprio. Ligar os avisos não pode, por
        // si só, começar a gastar dinheiro na operadora.
        if ($contacto['telefone'] && config('billing.avisos_sms', false)) {
            $saiu = $this->porSms($aviso, $empresa, $referencia, $contacto, $dados) || $saiu;
        }

        return $saiu;
    }

    private function porEmail(string $aviso, Tenant $empresa, string $referencia, string $email, array $dados): bool
    {
        $slug = 'subscricao_' . $aviso;

        if (!$this->reservar($empresa, $aviso, $referencia, 'email', $email)) {
            return false;
        }

        if ($this->soVer) {
            $this->libertar($empresa, $aviso, $referencia, 'email', $email);
            $this->conta['email']++;

            return true;
        }

        try {
            $modelo = EmailTemplate::where('slug', $slug)->first();

            // O EmailTemplate::sendEmail procura o modelo SEM olhar ao estado,
            // mas o TemplateMail procura-o COM — um modelo desligado passava a
            // primeira verificação e rebentava lá dentro com outra mensagem.
            if (!$modelo || !$modelo->is_active) {
                throw new \RuntimeException("Modelo de email '{$slug}' não existe ou está desligado.");
            }

            // tenantId nulo de propósito: o SMTP é o da PLATAFORMA. Com o da
            // empresa, a cobrança sairia do endereço do próprio cliente.
            EmailTemplate::sendEmail($slug, $email, $this->escapar($dados), null);

            $this->conta['email']++;

            return true;
        } catch (\Throwable $e) {
            $this->marcarFalha($empresa, $aviso, $referencia, 'email', $email, $e->getMessage());
            $this->conta['falhados']++;

            Log::warning('Aviso de subscrição por email falhou', [
                'aviso' => $aviso, 'empresa' => $empresa->id, 'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function porSms(string $aviso, Tenant $empresa, string $referencia, array $contacto, array $dados): bool
    {
        $telefone = $contacto['telefone'];

        if (!$this->reservar($empresa, $aviso, $referencia, 'sms', $telefone)) {
            return false;
        }

        if ($this->soVer) {
            $this->libertar($empresa, $aviso, $referencia, 'sms', $telefone);
            $this->conta['sms']++;

            return true;
        }

        try {
            $modelo = SmsTemplate::getBySlug('subs_' . $aviso, null);

            if (!$modelo) {
                throw new \RuntimeException("Modelo de SMS 'subs_{$aviso}' não encontrado.");
            }

            $texto = $modelo->render($this->paraSms($dados));

            // tenantId nulo: credenciais e remetente da PLATAFORMA. Com o id da
            // empresa, o SMS que lhe cobra a subscrição saía da conta dela.
            $r = app(SmsService::class)->send($telefone, $texto, 'subs_' . $aviso, $contacto['user_id'], null);

            if (!($r['success'] ?? false)) {
                throw new \RuntimeException($r['error'] ?? 'SMS recusado pelo fornecedor.');
            }

            // O histórico fica sem empresa porque as credenciais são da
            // plataforma; sem isto ninguém sabe a quem foi cobrado o quê.
            if (!empty($r['log_id'])) {
                SmsLog::whereKey($r['log_id'])->update(['tenant_id' => $empresa->id]);
            }

            $this->conta['sms']++;

            return true;
        } catch (\Throwable $e) {
            $this->marcarFalha($empresa, $aviso, $referencia, 'sms', $telefone, $e->getMessage());
            $this->conta['falhados']++;

            Log::warning('Aviso de subscrição por SMS falhou', [
                'aviso' => $aviso, 'empresa' => $empresa->id, 'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ── memória ──────────────────────────────────────────────────────────────

    /**
     * Reserva o lugar antes de enviar. Falso se já lá estava.
     *
     * Reservar primeiro e enviar depois, e não ao contrário: assim dois
     * pedidos em simultâneo não mandam ambos. Quem perder a corrida apanha a
     * violação do índice único, que é exactamente o que se quer.
     */
    private function reservar(Tenant $empresa, string $aviso, string $referencia, string $canal, string $destinatario): bool
    {
        try {
            DB::table('avisos_de_subscricao')->insert([
                'tenant_id'    => $empresa->id,
                'aviso'        => $aviso,
                'referencia'   => $referencia,
                'canal'        => $canal,
                'destinatario' => mb_substr($destinatario, 0, 190),
                'window_date'  => now()->toDateString(),
                'status'       => 'sent',
                'tentativas'   => 1,
                'created_at'   => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Já lá estava: outra passagem — ou outro pedido em simultâneo —
            // chegou primeiro. É o caso normal e não é avaria nenhuma.
            $this->conta['repetidos']++;

            return false;
        } catch (\Throwable $e) {
            // TUDO O RESTO É AVARIA, e não pode passar por normalidade.
            //
            // Isto apanhava \Throwable e contava tudo como repetido. Com a
            // migração por correr, ou com a base em baixo, cada reserva
            // rebentava, o relatório dizia "0 email(s), 25 repetido(s)" e
            // quem o lesse concluía que já tinha saído tudo. Nenhum cliente
            // era avisado e ninguém ficava a saber.
            $this->conta['falhados']++;
            $this->avariou = true;

            Log::error('Avisos de subscrição: a memória não aceitou a reserva', [
                'aviso'   => $aviso,
                'empresa' => $empresa->id,
                'erro'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Em modo de leitura não fica rasto — senão a passagem a sério não enviava. */
    private function libertar(Tenant $empresa, string $aviso, string $referencia, string $canal, string $destinatario): void
    {
        DB::table('avisos_de_subscricao')
            ->where('tenant_id', $empresa->id)
            ->where('aviso', $aviso)
            ->where('referencia', $referencia)
            ->where('canal', $canal)
            ->where('destinatario', mb_substr($destinatario, 0, 190))
            ->where('window_date', now()->toDateString())
            ->delete();
    }

    private function marcarFalha(Tenant $empresa, string $aviso, string $referencia, string $canal, string $destinatario, string $erro): void
    {
        try {
            DB::table('avisos_de_subscricao')
                ->where('tenant_id', $empresa->id)
                ->where('aviso', $aviso)
                ->where('referencia', $referencia)
                ->where('canal', $canal)
                ->where('destinatario', mb_substr($destinatario, 0, 190))
                ->where('window_date', now()->toDateString())
                ->update(['status' => 'failed', 'error' => mb_substr($erro, 0, 500)]);
        } catch (\Throwable) {
            // sem consequência
        }
    }

    // ── os dados que os modelos usam ─────────────────────────────────────────

    /**
     * NÃO PONHA AQUI UMA CHAVE 'dias'.
     *
     * Os chamadores fazem `dadosDaFactura(...) + ['dias' => $faltam]`, e a
     * união de arrays em PHP mantém o valor da ESQUERDA quando a chave existe
     * dos dois lados. Um `'dias' => 0` aqui ganhava sempre, e todas as
     * mensagens saíam a dizer "faltam 0 dias" — a clientes reais. O valor por
     * omissão está no `avisar`, onde fica do lado certo da união.
     */
    private function dadosDaFactura(Invoice $factura, Subscription $sub): array
    {
        return [
            'factura_numero' => (string) $factura->invoice_number,
            'valor'          => number_format((float) $factura->total, 2, ',', '.'),
            'vencimento'     => optional($factura->due_date)->format('d/m/Y') ?? '—',
            'plano_nome'     => (string) ($sub->plan?->name ?? 'Subscrição'),
            'ciclo'          => CicloDeFacturacao::nome($sub->billing_cycle),
            'periodo_fim'    => optional($sub->current_period_end)->format('d/m/Y') ?? '—',
        ];
    }

    /** Idem: sem chave 'dias'. */
    private function dadosDaSubscricao(Subscription $sub): array
    {
        return [
            'factura_numero' => '—',
            'valor'          => number_format((float) ($sub->amount ?? 0), 2, ',', '.'),
            'vencimento'     => optional($sub->current_period_end)->format('d/m/Y') ?? '—',
            'plano_nome'     => (string) ($sub->plan?->name ?? 'Subscrição'),
            'ciclo'          => CicloDeFacturacao::nome($sub->billing_cycle),
            'periodo_fim'    => optional($sub->current_period_end)->format('d/m/Y') ?? '—',
        ];
    }

    /**
     * O `render` dos modelos de email faz substituição em bruto, sem escapar.
     * O nome de uma empresa vem da base e pode ter um `&` ou um `<`.
     */
    private function escapar(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            $dados[$chave] = is_scalar($valor) ? e((string) $valor) : $valor;
        }

        return $dados;
    }

    /**
     * O SMS é outro mundo: cabe-lhe pouco texto e é cobrado por pedaço.
     *
     * Um nome comprido come a mensagem toda, e um acento faz o fornecedor
     * comutar para UCS-2, onde o limite cai de 160 caracteres para 70 — a
     * mesma mensagem passa a custar o dobro.
     */
    private function paraSms(array $dados): array
    {
        return [
            'empresa'    => $this->semAcentos(mb_strimwidth((string) ($dados['empresa_nome'] ?? ''), 0, 26, '')),
            'plano'      => $this->semAcentos(mb_strimwidth((string) ($dados['plano_nome'] ?? ''), 0, 24, '')),
            'factura'    => (string) ($dados['factura_numero'] ?? '—'),
            'valor'      => (string) ($dados['valor'] ?? '0'),
            'vencimento' => (string) ($dados['vencimento'] ?? '—'),
            'ate'        => (string) ($dados['periodo_fim'] ?? '—'),
            'dias'       => (string) ($dados['dias'] ?? '0'),
            'url'        => (string) ($dados['app_url'] ?? ''),
        ];
    }

    private function semAcentos(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        // O iconv devolve falso em alguns sistemas; mais vale o texto com
        // acentos do que uma mensagem vazia.
        return $convertido === false ? $texto : preg_replace('/[^\x20-\x7E]/', '', $convertido);
    }
}
