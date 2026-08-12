<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

/**
 * Todos os emails que saem do sistema ficam registados.
 *
 * O ecrã /superadmin/email-logs existia e mostrava dois registos: os envios
 * de teste do ecrã de SMTP e os de um ou outro sítio que se lembrava de
 * escrever na tabela à mão. Tudo o resto — a aprovação de um pedido, a
 * recusa, as boas-vindas, os avisos de stock, as notificações agendadas, a
 * reposição de palavra-passe — saía sem deixar rasto. Quando um cliente diz
 * "não recebi nada", não havia como saber se o email chegou a sair.
 *
 * Registar em cada sítio que envia é uma lista que nunca fica completa: basta
 * alguém acrescentar um `Mail::send` e o registo volta a ter buracos. Por isso
 * isto vive nos eventos do próprio Laravel, por onde passa obrigatoriamente
 * todo o correio, venha de onde vier.
 *
 * MessageSending grava a linha como 'pending'; MessageSent marca-a 'sent'. Se
 * o envio rebentar a meio, o evento de saída nunca chega e a linha fica em
 * 'pending' — que é a leitura certa: tentou-se, não se confirmou.
 */
class RegistarEmailEnviado
{
    /** Quantos caracteres do corpo se guardam para dar contexto. */
    private const PEDACO_DO_CORPO = 500;

    public function aoEnviar(MessageSending $event): void
    {
        $this->registar($event->message, 'pending', $event->data ?? []);
    }

    public function aoSair(MessageSent $event): void
    {
        // Tudo dentro do try, incluindo a procura da linha anterior: o registo
        // NUNCA pode fazer rebentar um envio. A consulta estava cá fora e, com
        // a tabela fora de alcance, o email deixava de sair por causa do log
        // que existe só para dizer que ele saiu.
        try {
            $mensagem = $event->message;
            $registo  = $this->encontrarRegisto($mensagem);

            if (!$registo) {
                // Sem linha anterior (o evento de entrada pode não ter passado
                // por aqui num envio em fila noutro processo), grava-se agora.
                $this->registar($mensagem, 'sent', $event->data ?? []);

                return;
            }

            $registo->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'message_id' => $this->idDaMensagem($mensagem),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Não foi possível marcar o email como enviado', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Um email que rebentou.
     *
     * Chamado à mão por quem apanha a excepção — o Laravel não emite evento
     * nenhum quando o envio falha.
     */
    public static function falhou(string $paraEmail, string $assunto, string $erro, ?int $tenantId = null): void
    {
        try {
            EmailLog::create([
                'tenant_id'     => $tenantId ?? (function_exists('activeTenantId') ? activeTenantId() : null),
                'to_email'      => $paraEmail,
                'from_email'    => config('mail.from.address', 'sem-remetente'),
                'from_name'     => config('mail.from.name'),
                'subject'       => mb_substr($assunto, 0, 255),
                'status'        => 'failed',
                'error_message' => mb_substr($erro, 0, 2000),
                'failed_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Não foi possível registar a falha de email', ['erro' => $e->getMessage()]);
        }
    }

    private function registar($mensagem, string $estado, array $dados): void
    {
        // O registo NUNCA pode impedir o email de sair: se isto falhar, o
        // email segue na mesma e fica só uma linha no log da aplicação.
        try {
            $destinatarios = $this->enderecos($mensagem, 'getTo');
            $remetentes    = $this->enderecos($mensagem, 'getFrom');

            if (empty($destinatarios)) {
                return;
            }

            $primeiro = $destinatarios[0];
            $de       = $remetentes[0] ?? ['email' => config('mail.from.address', 'sem-remetente'), 'nome' => null];

            EmailLog::create([
                'tenant_id'     => $this->empresa($dados),
                'user_id'       => auth()->id(),
                'to_email'      => mb_substr($primeiro['email'], 0, 255),
                'to_name'       => $primeiro['nome'] ? mb_substr($primeiro['nome'], 0, 255) : null,
                'from_email'    => mb_substr($de['email'], 0, 255),
                'from_name'     => $de['nome'] ? mb_substr($de['nome'], 0, 255) : null,
                'subject'       => mb_substr((string) $mensagem->getSubject(), 0, 255),
                'body_preview'  => $this->pedacoDoCorpo($mensagem),
                'template_slug' => $dados['template_slug'] ?? null,
                'status'        => $estado,
                'message_id'    => $this->idDaMensagem($mensagem),
                'sent_at'       => $estado === 'sent' ? now() : null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Não foi possível registar o email enviado', ['erro' => $e->getMessage()]);
        }
    }

    private function encontrarRegisto($mensagem): ?EmailLog
    {
        $id = $this->idDaMensagem($mensagem);

        if ($id) {
            $porId = EmailLog::where('message_id', $id)->where('status', 'pending')->latest('id')->first();

            if ($porId) {
                return $porId;
            }
        }

        // Alguns transportes reescrevem o Message-ID entre os dois eventos:
        // recorre-se ao destinatário e ao assunto, dentro de um minuto.
        $destinatarios = $this->enderecos($mensagem, 'getTo');

        if (empty($destinatarios)) {
            return null;
        }

        return EmailLog::where('to_email', $destinatarios[0]['email'])
            ->where('subject', mb_substr((string) $mensagem->getSubject(), 0, 255))
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinute())
            ->latest('id')
            ->first();
    }

    /** Endereços de um cabeçalho, em array simples. */
    private function enderecos($mensagem, string $metodo): array
    {
        if (!method_exists($mensagem, $metodo)) {
            return [];
        }

        $saida = [];

        foreach ((array) $mensagem->{$metodo}() as $endereco) {
            $saida[] = [
                'email' => method_exists($endereco, 'getAddress') ? $endereco->getAddress() : (string) $endereco,
                'nome'  => method_exists($endereco, 'getName') ? ($endereco->getName() ?: null) : null,
            ];
        }

        return $saida;
    }

    private function pedacoDoCorpo($mensagem): ?string
    {
        try {
            $corpo = $mensagem->getHtmlBody() ?: $mensagem->getTextBody();

            if (!$corpo) {
                return null;
            }

            $texto = trim(preg_replace('/\s+/', ' ', strip_tags((string) $corpo)));

            return mb_substr($texto, 0, self::PEDACO_DO_CORPO);
        } catch (\Throwable) {
            return null;
        }
    }

    private function idDaMensagem($mensagem): ?string
    {
        try {
            $cabecalhos = $mensagem->getHeaders();

            return $cabecalhos->has('Message-ID')
                ? mb_substr((string) $cabecalhos->get('Message-ID')->getBodyAsString(), 0, 255)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A que empresa pertence este email.
     *
     * Vem dos dados do Mailable quando quem o construiu os passou; senão, da
     * empresa activa no pedido. Um email de sistema (sem empresa) fica sem —
     * e é o que deve ficar.
     */
    private function empresa(array $dados): ?int
    {
        foreach (['tenant_id', 'tenantId'] as $chave) {
            if (!empty($dados[$chave]) && is_numeric($dados[$chave])) {
                return (int) $dados[$chave];
            }
        }

        return function_exists('activeTenantId') ? activeTenantId() : null;
    }
}
