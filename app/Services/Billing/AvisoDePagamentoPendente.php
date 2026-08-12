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

    /** Uma janela curta chega para apanhar duplo-clique e reenvios. */
    private const MINUTOS_SEM_REPETIR = 10;

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
            \App\Livewire\SuperAdmin\Billing::nomeDoCiclo($order->billing_cycle),
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

        $chave = "aviso-pagamento:{$ocasiao}:{$order->id}";

        // Cache::add é atómico: dois pedidos em simultâneo, um só aviso.
        if (!\Cache::add($chave, true, now()->addMinutes(self::MINUTOS_SEM_REPETIR))) {
            return;
        }

        $numero = self::numero();

        // defer(): corre depois da resposta seguir, no mesmo processo. Sem
        // worker de filas nesta máquina, é isto que evita pôr o cliente à
        // espera da operadora no meio de uma compra.
        defer(function () use ($numero, $texto, $order, $ocasiao) {
            try {
                app(SmsService::class)->send(
                    $numero,
                    $texto,
                    'pagamento_pendente',
                    null,
                    null                    // configuração da PLATAFORMA, não da empresa
                );

                Log::info('Aviso de pagamento pendente enviado', [
                    'order_id' => $order->id,
                    'ocasiao'  => $ocasiao,
                    'para'     => $numero,
                ]);
            } catch (\Throwable $e) {
                // Quem está a pagar não pode perder a compra porque a
                // operadora está em baixo.
                Log::error('Aviso de pagamento pendente falhou', [
                    'order_id' => $order->id,
                    'ocasiao'  => $ocasiao,
                    'erro'     => $e->getMessage(),
                ]);
            }
        });
    }
}
