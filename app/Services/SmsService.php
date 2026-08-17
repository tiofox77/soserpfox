<?php

namespace App\Services;

use App\Models\SmsSetting;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Enviar SMS usando D7 Networks API
     */
    public function send($recipient, $message, $type = null, $userId = null, $tenantId = null)
    {
        // FORA do try, e de propósito.
        //
        // O try deste método engole tudo e devolve um array, pelo que uma
        // excepção lançada lá dentro nunca chegava a quem chama — e o painel de
        // envio em massa contava como enviado um SMS que não saiu. Um número
        // que não se pode marcar é um erro de quem chama, e tem de o saber.
        //
        // Sem isto, um número mal gravado seguia para o fornecedor, era cobrado,
        // e falhava sem ninguém dar por isso: o histórico até dizia "Enviado".
        if ($this->formatPhoneNumber($recipient) === null) {
            Log::warning('SMS não enviado: número inválido.', [
                'recipient' => $recipient,
                'type'      => $type,
            ]);

            throw new \InvalidArgumentException(
                'Número de telefone inválido: ' . $recipient
                    . '. Em Angola são nove dígitos, com ou sem o indicativo 244.'
            );
        }

        try {
            Log::info('📱 Iniciando envio de SMS', [
                'recipient' => $recipient,
                'type' => $type,
                'message_preview' => substr($message, 0, 50) . '...'
            ]);

            // Buscar configuração SMS
            $setting = SmsSetting::getForTenant($tenantId);

            if (!$setting) {
                Log::error('❌ Configuração SMS não encontrada');
                throw new \Exception('Configuração SMS não encontrada');
            }

            Log::info('✅ Configuração SMS encontrada', [
                'provider' => $setting->provider,
                'sender_id' => $setting->sender_id
            ]);

            // O `provider` era escrito no log e mais nada: o payload montado
            // abaixo é o da D7 Networks, com `message_globals.originator` e
            // autenticação Bearer. Escolher outro fornecedor no ecrã mandava
            // esse mesmo payload para o URL dele, e o que se via era um erro
            // obscuro do outro lado em vez de "não é suportado".
            //
            // Só a D7 está implementada AQUI, ao nível da plataforma. Ao nível
            // da empresa há também Twilio, pelo ImmediateNotificationService.
            $suportados = ['d7networks'];

            if ($setting->provider && !in_array($setting->provider, $suportados, true)) {
                throw new \Exception(
                    "Fornecedor de SMS '{$setting->provider}' não é suportado nas definições da plataforma. "
                    . 'Suportado: ' . implode(', ', $suportados) . '.'
                );
            }

            // Preparar payload
            $messageObj = [
                "channel" => "sms",
                "msg_type" => "text",
                "recipients" => [$this->formatPhoneNumber($recipient)],
                "content" => $message,
                "data_coding" => "auto"
            ];

            $globalsObj = [
                "originator" => $setting->sender_id,
            ];

            // Adicionar report_url apenas se for uma URL válida (não localhost)
            if ($setting->report_url && !str_contains($setting->report_url, 'localhost')) {
                $globalsObj['report_url'] = $setting->report_url;
            }

            $payload = json_encode([
                "messages" => [$messageObj],
                "message_globals" => $globalsObj
            ]);

            Log::info('📦 Payload preparado', ['payload_size' => strlen($payload)]);

            // Enviar via cURL
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $setting->api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $setting->api_token
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            curl_close($curl);

            if ($error) {
                throw new \Exception("cURL Error: {$error}");
            }

            $responseData = json_decode($response, true);

            Log::info('📥 Resposta da API', [
                'http_code' => $httpCode,
                'response' => $responseData
            ]);

            // Salvar log (apenas com IDs válidos)
            $log = SmsLog::create([
                'recipient' => $this->formatPhoneNumber($recipient),
                'message' => $message,
                'sender_id' => $setting->sender_id,
                'type' => $type,
                'status' => ($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed',
                'request_id' => $responseData['request_id'] ?? null,
                'api_response' => $response,
                'error_message' => ($httpCode >= 200 && $httpCode < 300) ? null : $response,
                'user_id' => ($userId && $userId > 0 && \App\Models\User::find($userId)) ? $userId : null,
                'tenant_id' => ($tenantId && $tenantId > 0 && \App\Models\Tenant::find($tenantId)) ? $tenantId : null,
                'sent_at' => now(),
            ]);

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info('✅ SMS enviado com sucesso', [
                    'log_id' => $log->id,
                    'request_id' => $responseData['request_id'] ?? null
                ]);

                return [
                    'success' => true,
                    'log_id' => $log->id,
                    'request_id' => $responseData['request_id'] ?? null,
                    'response' => $responseData
                ];
            } else {
                throw new \Exception("API Error: HTTP {$httpCode} - {$response}");
            }

        } catch (\Exception $e) {
            Log::error('❌ Erro ao enviar SMS', [
                'error' => $e->getMessage(),
                'recipient' => $recipient ?? 'N/A'
            ]);

            // Salvar log de erro (apenas com IDs válidos)
            if (isset($setting)) {
                SmsLog::create([
                    'recipient' => $this->formatPhoneNumber($recipient ?? ''),
                    'message' => $message ?? '',
                    'sender_id' => $setting->sender_id ?? null,
                    'type' => $type,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'user_id' => ($userId && $userId > 0 && \App\Models\User::find($userId)) ? $userId : null,
                    'tenant_id' => ($tenantId && $tenantId > 0 && \App\Models\Tenant::find($tenantId)) ? $tenantId : null,
                    'sent_at' => now(),
                ]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formatar número de telefone
     */
    /**
     * O número em formato internacional, com o indicativo de Angola.
     *
     * Isto limitava-se a tirar os caracteres estranhos e a pôr um '+' à frente.
     * O que saía era o que estava gravado: uns com indicativo e outros sem —
     * '+938023744' ao lado de '+244944111781', e até '+83635622', que não é
     * número nenhum. Um número sem indicativo não chega ao destino, e o envio
     * é cobrado na mesma.
     *
     * As regras de Angola: nove dígitos a nível nacional (telemóveis começam
     * por 9, fixos por 2) e indicativo 244. Aceita-se como vem — com +244, com
     * 00244, com 244 à frente, ou só os nove dígitos — e sai sempre igual.
     *
     * @return string|null null quando não é um número que se possa marcar
     */
    public function formatPhoneNumber($phone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $phone);

        if ($digitos === '') {
            return null;
        }

        // 00244... é a forma antiga de escrever +244.
        if (str_starts_with($digitos, '00244')) {
            $digitos = substr($digitos, 2);
        }

        // Já vem com indicativo: nove dígitos depois do 244 e está feito.
        if (str_starts_with($digitos, '244')) {
            return strlen($digitos) === 12 ? '+' . $digitos : null;
        }

        // Nacional: nove dígitos. É aqui que se acrescenta o indicativo — o
        // caso que faltava e que deixou metade dos SMS por entregar.
        if (preg_match('/^[29]\d{8}$/', $digitos)) {
            return '+244' . $digitos;
        }

        // Não é de Angola nem tem forma de o ser. Devolver null e não um
        // palpite: mandar um número inventado ao fornecedor custa dinheiro e
        // falha em silêncio, que é o pior dos dois mundos.
        return null;
    }

    /**
     * Enviar SMS de nova conta criada
     */
    public function sendNewAccountSms($user, $password, $tenant)
    {
        if (!$user->phone) {
            Log::warning('⚠️ Usuário sem telefone, SMS não enviado', ['user_id' => $user->id]);
            return ['success' => false, 'error' => 'Usuário sem telefone'];
        }

        // Buscar template do banco
        $template = \App\Models\SmsTemplate::getBySlug('new_account', $tenant->id);

        if (!$template) {
            Log::warning('⚠️ Template new_account não encontrado');
            return ['success' => false, 'error' => 'Template não encontrado'];
        }

        // Renderizar template com dados
        $message = $template->render([
            'app_name' => config('app.name', 'SOS ERP'),
            'tenant_name' => $tenant->name,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_password' => $password,
            'app_url' => config('app.url'),
        ]);

        return $this->send(
            $user->phone,
            $message,
            'new_account',
            $user->id,
            $tenant->id
        );
    }

    /**
     * Enviar SMS de pagamento aprovado
     */
    public function sendPaymentApprovedSms($tenant, $plan)
    {
        $owner = $tenant->users()->wherePivot('is_active', true)->first();

        if (!$owner || !$owner->phone) {
            Log::warning('⚠️ Tenant sem owner ou telefone, SMS não enviado', ['tenant_id' => $tenant->id]);
            return ['success' => false, 'error' => 'Owner sem telefone'];
        }

        // Buscar template do banco
        $template = \App\Models\SmsTemplate::getBySlug('payment_approved', $tenant->id);

        if (!$template) {
            Log::warning('⚠️ Template payment_approved não encontrado');
            return ['success' => false, 'error' => 'Template não encontrado'];
        }

        // Renderizar template com dados
        $message = $template->render([
            'app_name' => config('app.name', 'SOS ERP'),
            'tenant_name' => $tenant->name,
            'plan_name' => $plan->name,
            'app_url' => config('app.url'),
        ]);

        return $this->send(
            $owner->phone,
            $message,
            'payment_approved',
            $owner->id,
            $tenant->id
        );
    }

    /**
     * Enviar SMS de plano expirando
     */
    public function sendPlanExpiringSms($tenant, $daysRemaining)
    {
        $owner = $tenant->users()->wherePivot('is_active', true)->first();

        if (!$owner || !$owner->phone) {
            Log::warning('⚠️ Tenant sem owner ou telefone, SMS não enviado', ['tenant_id' => $tenant->id]);
            return ['success' => false, 'error' => 'Owner sem telefone'];
        }

        // Buscar template do banco
        $template = \App\Models\SmsTemplate::getBySlug('plan_expiring', $tenant->id);

        if (!$template) {
            Log::warning('⚠️ Template plan_expiring não encontrado');
            return ['success' => false, 'error' => 'Template não encontrado'];
        }

        // Renderizar template com dados
        $message = $template->render([
            'app_name' => config('app.name', 'SOS ERP'),
            'tenant_name' => $tenant->name,
            'days_remaining' => $daysRemaining,
            'app_url' => config('app.url'),
        ]);

        return $this->send(
            $owner->phone,
            $message,
            'plan_expiring',
            $owner->id,
            $tenant->id
        );
    }
}
