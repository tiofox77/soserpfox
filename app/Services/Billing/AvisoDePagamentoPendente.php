<?php

namespace App\Services\Billing;

use App\Models\Order;
use App\Models\SoftwareSetting;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

/**
 * Avisa por SMS quando há um pagamento à espera de aprovação.
 *
 * O cliente transfere, anexa o comprovativo e fica à espera. Do outro lado,
 * o pedido cai numa lista que só é vista por quem se lembrar de abrir
 * /superadmin/billing. Entretanto o cliente está a pagar e sem acesso, ou com
 * o acesso a expirar, e não há nada que avise ninguém.
 *
 * Manda-se um SMS, e não um email, de propósito: um email pendurado numa caixa
 * de entrada não acorda ninguém ao fim de semana; um SMS sim. E este sistema
 * não tem worker de filas — o envio vai com o defer(), depois da resposta já
 * ter seguido para o browser, para o cliente não esperar pela operadora.
 *
 * DUAS ocasiões merecem aviso, e são diferentes:
 *
 *   · o pedido nasce pendente — alguém escolheu um plano e vai pagar;
 *   · o comprovativo é anexado — AGORA é que há trabalho para fazer, porque
 *     há um documento para conferir. É este o aviso que interessa.
 *
 * Nunca se avisa duas vezes pela mesma coisa, e uma falha do SMS nunca pode
 * fazer rebentar o pedido: quem está a pagar não pode perder a compra porque
 * a operadora está em baixo.
 */
class AvisoDePagamentoPendente
{
    /** Módulo das definições, para o número e o interruptor viverem na base. */
    private const MODULO = 'billing';

    /** O número que recebe os avisos, se ninguém tiver configurado outro. */
    public const NUMERO_POR_OMISSAO = '939729902';

    /**
     * Cada aviso sai UMA VEZ, e a memória disso vive na base de dados.
     *
     * Era uma chave em cache com 10 minutos de validade. Passados esses
     * minutos — ou a qualquer `cache:clear`, que corre em cada actualização —
     * a memória desaparecia e o mesmo aviso voltava a sair. Com o comprovativo
     * a ser reanexado, eram vários SMS pelo mesmo pedido, e o saldo da
     * operadora ia atrás.
     */
    private const TABELA_MEMORIA = "avisos_de_subscricao";

    /** Está ligado? */
    public static function ligado(): bool
    {
        return (bool) SoftwareSetting::get(self::MODULO, 'aviso_sms_ativo', true);
    }

    /** Para onde vai o aviso. */
    public static function numero(): string
    {
        $numero = trim((string) SoftwareSetting::get(self::MODULO, 'aviso_sms_numero', ''));

        return $numero !== '' ? $numero : self::NUMERO_POR_OMISSAO;
    }

    /**
     * Um pedido acabou de nascer à espera de pagamento.
     */
    public function pedidoCriado(Order $order): void
    {
        if ($order->status !== 'pending') {
            return;
        }

        $this->avisar($order, 'novo', $this->textoDoPedidoNovo($order));
    }

    /**
     * O comprovativo foi anexado — há um documento para conferir.
     */
    public function comprovativoAnexado(Order $order): void
    {
        if ($order->status !== 'pending') {
            return;
        }

        $this->avisar($order, 'comprovativo', $this->textoDoComprovativo($order));
    }

    /**
     * Uma empresa nova acabou de se registar.
     *
     * Não é um pedido de aprovação — é para saber que existe, e com que plano
     * entrou. Sem isto, quem descobria um cliente novo era quem se lembrasse
     * de abrir a lista de empresas.
     *
     * O plano diz muito: quem entra no gratuito é uma coisa, quem entra no
     * Enterprise é outra e provavelmente merece um telefonema no mesmo dia.
     */
    public function empresaRegistada(\App\Models\Tenant $empresa, ?\App\Models\Plan $plano, string $estado): void
    {
        if (!self::ligado()) {
            return;
        }

        // Mesma razão do aviso de pagamento: memória permanente, não uma chave
        // de cache que expira (e que qualquer `cache:clear` apagava). Uma
        // empresa regista-se uma vez; o aviso sai uma vez.
        if (!$this->reservarEmpresa($empresa, self::numero())) {
            return;
        }

        $comoEntrou = match ($estado) {
            'trial'   => 'em teste',
            'active'  => 'activa',
            'pending' => 'a aguardar pagamento',
            default   => $estado,
        };

        $texto = sprintf(
            'SOSERP: empresa nova - %s. Plano %s (%s). NIF %s.',
            mb_strimwidth($empresa->name ?? 'sem nome', 0, 30, '…'),
            $plano?->name ?? 'sem plano',
            $comoEntrou,
            $empresa->nif ?: '—'
        );

        $this->enviar(self::numero(), $texto, 'empresa_registada', ['tenant_id' => $empresa->id]);
    }

    /**
     * O texto tem de caber num ecrã de telemóvel e dizer o que é preciso
     * saber para decidir se vale a pena abrir o portátil: quem, quanto, e o
     * que está à espera.
     */
    private function textoDoPedidoNovo(Order $order): string
    {
        return sprintf(
            'SOSERP: %s escolheu o plano %s (%s) - %s Kz. Aguarda pagamento.',
            $this->nomeDaEmpresa($order),
            $order->plan?->name ?? 'sem plano',
            \App\Support\CicloDeFacturacao::nome($order->billing_cycle),
            number_format((float) $order->amount, 0, ',', '.')
        );
    }

    private function textoDoComprovativo(Order $order): string
    {
        return sprintf(
            'SOSERP: %s anexou comprovativo de %s Kz (%s). A AGUARDAR APROVACAO: %s',
            $this->nomeDaEmpresa($order),
            number_format((float) $order->amount, 0, ',', '.'),
            $order->plan?->name ?? 'sem plano',
            route('superadmin.billing')
        );
    }

    private function nomeDaEmpresa(Order $order): string
    {
        $nome = $order->tenant?->name ?? 'Empresa #' . $order->tenant_id;

        // Um nome comprido come o SMS todo.
        return mb_strimwidth($nome, 0, 34, '…');
    }

    /**
     * Enviar, sem nunca deixar rebentar quem nos chamou.
     */
    private function avisar(Order $order, string $ocasiao, string $texto): void
    {
        if (!self::ligado()) {
            return;
        }

        if (!$this->reservar($order, $ocasiao, self::numero())) {
            return;
        }

        $this->enviar(self::numero(), $texto, 'pagamento_pendente', [
            'order_id' => $order->id,
            'ocasiao'  => $ocasiao,
        ]);
    }

    /**
     * O envio, com as duas garantias que importam.
     *
     * defer(): corre depois da resposta seguir, no mesmo processo. Sem worker
     * de filas nesta máquina, é isto que evita pôr o cliente à espera da
     * operadora no meio de uma compra ou de um registo.
     *
     * try/catch: nada disto pode fazer rebentar quem nos chamou. Quem está a
     * pagar não perde a compra porque a operadora está em baixo, e quem se
     * está a registar não perde a conta.
     */
    /**
     * Marca este aviso como enviado — de forma PERMANENTE.
     *
     * Devolve false se já tinha saído, e aí não se envia nada. A chave única
     * da tabela é (tenant, aviso, referência, canal, destinatário, data): a
     * data usada é a da criação do pedido, que nunca muda, pelo que a memória
     * vale para sempre e não só para o dia de hoje.
     *
     * Falha FECHADO: se a memória não aceitar o registo, não se envia. Entre
     * perder um aviso e gastar o saldo da operadora a repetir o mesmo SMS, o
     * erro barato é o primeiro.
     */
    /** Como o reservar() dos pedidos, mas para o aviso de empresa nova. */
    private function reservarEmpresa(\App\Models\Tenant $empresa, string $numero): bool
    {
        try {
            \Illuminate\Support\Facades\DB::table(self::TABELA_MEMORIA)->insert([
                'tenant_id'    => $empresa->id,
                'aviso'        => 'pag_pend_empresa_nova',
                'referencia'   => 'tenant:' . $empresa->id,
                'canal'        => 'sms',
                'destinatario' => mb_substr($numero, 0, 190),
                'window_date'  => ($empresa->created_at ?? now())->toDateString(),
                'status'       => 'sent',
                'tentativas'   => 1,
                'created_at'   => now(),
            ]);

            return true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;
        } catch (\Throwable $e) {
            Log::error('Aviso de empresa nova: a memoria nao aceitou o registo; SMS nao enviado', [
                'tenant_id' => $empresa->id, 'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }
    private function reservar(Order $order, string $ocasiao, string $numero): bool
    {
        try {
            \Illuminate\Support\Facades\DB::table(self::TABELA_MEMORIA)->insert([
                'tenant_id'    => $order->tenant_id ?? 0,
                'aviso'        => mb_substr('pag_pend_' . $ocasiao, 0, 40),
                'referencia'   => 'order:' . $order->id,
                'canal'        => 'sms',
                'destinatario' => mb_substr($numero, 0, 190),
                // Data do PEDIDO, não de hoje: é o que torna a memória
                // permanente em vez de válida só para o dia.
                'window_date'  => ($order->created_at ?? now())->toDateString(),
                'status'       => 'sent',
                'tentativas'   => 1,
                'created_at'   => now(),
            ]);

            return true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;   // já saiu: o caso normal
        } catch (\Throwable $e) {
            Log::error('Aviso de pagamento: a memoria nao aceitou o registo; SMS nao enviado', [
                'order_id' => $order->id, 'ocasiao' => $ocasiao, 'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }
    private function enviar(string $numero, string $texto, string $tipo, array $contexto = []): void
    {
        defer(function () use ($numero, $texto, $tipo, $contexto) {
            try {
                app(SmsService::class)->send(
                    $numero,
                    $texto,
                    $tipo,
                    null,
                    null                    // configuração da PLATAFORMA, não da empresa
                );

                Log::info('Aviso ao administrador enviado', $contexto + [
                    'tipo' => $tipo,
                    'para' => $numero,
                ]);
            } catch (\Throwable $e) {
                Log::error('Aviso ao administrador falhou', $contexto + [
                    'tipo' => $tipo,
                    'erro' => $e->getMessage(),
                ]);
            }
        });
    }
}
