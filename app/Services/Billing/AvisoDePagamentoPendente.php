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

        $chave = "aviso-registo:{$empresa->id}";

        if (!\Cache::add($chave, true, now()->addHours(6))) {
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
