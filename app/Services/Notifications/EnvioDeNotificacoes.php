<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\D7NetworksService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * O envio de uma notificação de modelo, pelos três canais.
 *
 * Estava dentro do comando agendado e tinha três defeitos que, juntos,
 * explicam porque este módulo nunca funcionou:
 *
 *   · `sendEmail` era um TODO. Escrevia no log e não enviava nada — o canal
 *     mais usado dos três nunca mandou um único email de modelo.
 *   · `sendSMS` chamava `sendWhatsApp`. Ligar o SMS num modelo mandava um
 *     WhatsApp, e contava-o como SMS no resumo.
 *   · Não havia registo do que já tinha saído, portanto cada passagem
 *     reenviava tudo.
 *
 * Aqui os três canais enviam mesmo, cada um pelo seu meio, e nada sai duas
 * vezes no mesmo dia para o mesmo destinatário.
 *
 * Nada aqui lança para fora: um aviso que falha não pode derrubar a página de
 * quem estava a trabalhar quando o despacho pegou boleia do pedido dele.
 */
class EnvioDeNotificacoes
{
    /** @var array<string,int> contagem por canal desta passagem */
    private array $conta = ['email' => 0, 'sms' => 0, 'whatsapp' => 0, 'repetidos' => 0, 'falhados' => 0];

    public function contagem(): array
    {
        return $this->conta;
    }

    /**
     * Envia um modelo a um destinatário, por um canal.
     *
     * @return bool se saiu alguma coisa agora
     */
    public function enviar(
        NotificationTemplate $modelo,
        string $canal,
        string $destinatario,
        array $variaveis,
        ?int $registoId = null
    ): bool {
        if ($this->jaEnviadoHoje($modelo, $canal, $destinatario, $registoId)) {
            $this->conta['repetidos']++;

            return false;
        }

        // Reservar ANTES de enviar.
        //
        // Ao contrário — enviar e só depois marcar — dois pedidos simultâneos
        // passavam ambos pela verificação e mandavam ambos. A tabela tem índice
        // único; quem perder a corrida apanha a violação e desiste, que é
        // exactamente o que se quer.
        if (!$this->reservar($modelo, $canal, $destinatario, $registoId)) {
            $this->conta['repetidos']++;

            return false;
        }

        try {
            $enviou = match ($canal) {
                'email'    => $this->porEmail($modelo, $destinatario, $variaveis),
                'sms'      => $this->porSms($modelo, $destinatario, $variaveis),
                'whatsapp' => $this->porWhatsApp($modelo, $destinatario, $variaveis),
                default    => false,
            };

            if (!$enviou) {
                $this->marcarFalha($modelo, $canal, $destinatario, $registoId, 'canal não configurado');
                $this->conta['falhados']++;

                return false;
            }

            $this->conta[$canal]++;

            return true;
        } catch (\Throwable $e) {
            $this->marcarFalha($modelo, $canal, $destinatario, $registoId, $e->getMessage());
            $this->conta['falhados']++;

            Log::warning('Notificação não enviada', [
                'modelo' => $modelo->name,
                'canal'  => $canal,
                'erro'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ── canais ───────────────────────────────────────────────────────────────

    /**
     * Email pelo SMTP DA EMPRESA.
     *
     * Era um TODO. Cada empresa tem o seu servidor de saída configurado no ecrã
     * de notificações — usar o do sistema mandaria os avisos de uma empresa a
     * partir do endereço de outra.
     */
    private function porEmail(NotificationTemplate $modelo, string $email, array $variaveis): bool
    {
        $definicoes = TenantNotificationSetting::getForTenant($modelo->tenant_id);

        if (!$definicoes->email_enabled || blank($definicoes->smtp_host)) {
            return false;
        }

        $assunto = $this->substituir($modelo->email_subject ?? '', $variaveis);
        $corpo   = $this->substituir($modelo->email_body ?? '', $variaveis);

        if (blank($assunto) && blank($corpo)) {
            return false;
        }

        $definicoes->configureSMTP();

        Mail::to($email)->send(new \App\Mail\NotificacaoDeModelo(
            assuntoDaNotificacao: $assunto,
            corpoHtml: nl2br($corpo),
            remetente: $definicoes->from_email,
            nomeRemetente: $definicoes->from_name,
        ));

        return true;
    }

    /**
     * SMS pela operadora configurada.
     *
     * Isto chamava o envio de WhatsApp. Quem ligasse o SMS num modelo recebia
     * um WhatsApp — e o resumo dizia que tinham saído SMS.
     */
    private function porSms(NotificationTemplate $modelo, string $telefone, array $variaveis): bool
    {
        $definicoes = TenantNotificationSetting::getForTenant($modelo->tenant_id);

        if (!$definicoes->sms_enabled) {
            return false;
        }

        $texto = trim($this->substituir($modelo->sms_body ?? '', $variaveis));

        if ($texto === '') {
            return false;
        }

        if ($definicoes->sms_provider === 'd7networks') {
            if (blank($definicoes->sms_api_token)) {
                return false;
            }

            $d7 = new D7NetworksService($definicoes->sms_api_token, $definicoes->sms_sender_id);
            $r  = $d7->sendSMS($telefone, $texto);

            return (bool) ($r['success'] ?? false);
        }

        if ($definicoes->sms_provider === 'telcosms') {
            if (blank($definicoes->sms_api_token)) {
                return false;
            }

            $r = (new \App\Services\TelcoSmsService($definicoes->sms_api_token))
                ->send($telefone, $texto);

            return (bool) ($r['success'] ?? false);
        }

        // Outras operadoras ainda não têm caminho próprio. Devolver falso é
        // honesto: fica registado como falha e vê-se no ecrã, em vez de sair um
        // WhatsApp a fingir de SMS.
        return false;
    }

    private function porWhatsApp(NotificationTemplate $modelo, string $telefone, array $variaveis): bool
    {
        $definicoes = TenantNotificationSetting::getForTenant($modelo->tenant_id);

        if (!$definicoes->whatsapp_enabled
            || blank($definicoes->whatsapp_account_sid)
            || blank($modelo->whatsapp_template_sid)) {
            return false;
        }

        $whatsapp = new WhatsAppService(
            $definicoes->whatsapp_account_sid,
            $definicoes->whatsapp_auth_token,
            $definicoes->whatsapp_from_number
        );

        return (bool) $whatsapp->sendTemplate(
            $telefone,
            $modelo->name,
            $variaveis,
            $modelo->whatsapp_template_sid
        );
    }

    // ── memória do que já saiu ───────────────────────────────────────────────

    private function jaEnviadoHoje(NotificationTemplate $modelo, string $canal, string $destinatario, ?int $registoId): bool
    {
        return DB::table('notification_sends')
            ->where('tenant_id', $modelo->tenant_id)
            ->where('template_id', $modelo->id)
            ->where('record_id', $registoId)
            ->where('channel', $canal)
            ->where('recipient', $destinatario)
            ->where('window_date', now()->toDateString())
            ->exists();
    }

    /** Reserva o lugar. Falso se outro pedido chegou primeiro. */
    private function reservar(NotificationTemplate $modelo, string $canal, string $destinatario, ?int $registoId): bool
    {
        try {
            DB::table('notification_sends')->insert([
                'tenant_id'   => $modelo->tenant_id,
                'template_id' => $modelo->id,
                'record_id'   => $registoId,
                'channel'     => $canal,
                'recipient'   => mb_substr($destinatario, 0, 190),
                'window_date' => now()->toDateString(),
                'status'      => 'sent',
                'created_at'  => now(),
            ]);

            return true;
        } catch (\Throwable) {
            return false;   // índice único: já lá estava
        }
    }

    private function marcarFalha(NotificationTemplate $modelo, string $canal, string $destinatario, ?int $registoId, string $erro): void
    {
        try {
            DB::table('notification_sends')
                ->where('tenant_id', $modelo->tenant_id)
                ->where('template_id', $modelo->id)
                ->where('record_id', $registoId)
                ->where('channel', $canal)
                ->where('recipient', $destinatario)
                ->where('window_date', now()->toDateString())
                ->update(['status' => 'failed', 'error' => mb_substr($erro, 0, 500)]);
        } catch (\Throwable) {
            // sem consequência
        }
    }

    /** Substitui {{ variavel }} pelo valor. */
    private function substituir(string $texto, array $variaveis): string
    {
        foreach ($variaveis as $chave => $valor) {
            if (is_array($valor) || is_object($valor)) {
                continue;
            }

            $texto = str_replace(
                ['{{' . $chave . '}}', '{{ ' . $chave . ' }}'],
                (string) $valor,
                $texto
            );
        }

        return $texto;
    }
}
