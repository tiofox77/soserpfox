<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelcoSmsService
{
    private const BASE_URL = 'https://www.telcosms.co.ao/api/v2';

    public function __construct(private ?string $apiKeyApp = null) {}

    /** Envia pelo endpoint POST oficial. Resposta 2xx vazia significa sucesso. */
    public function sendSMS(string $to, string $message): ?string
    {
        $result = $this->send($to, $message);
        return $result['success'] ? ($result['message_id'] ?? 'sent') : null;
    }

    public function send(string $to, string $message): array
    {
        if (blank($this->apiKeyApp)) {
            return ['success' => false, 'message' => 'Chave da aplicação TelcoSMS não configurada.'];
        }
        $phone = $this->phoneForTelco($to);
        if ($phone === '' || trim($message) === '') {
            return ['success' => false, 'message' => 'Número e mensagem são obrigatórios.'];
        }

        try {
            $response = Http::acceptJson()->asJson()->timeout(20)->retry(2, 300)
                ->post(self::BASE_URL . '/send_message', [
                    'message' => [
                        'api_key_app' => $this->apiKeyApp,
                        'phone_number' => $phone,
                        'message_body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('SMS enviado pela TelcoSMS.', ['phone' => $phone, 'status' => $response->status()]);
                return ['success' => true, 'message' => 'SMS enviado com sucesso.', 'message_id' => data_get($data, 'message_id') ?? data_get($data, 'id'), 'data' => $data];
            }

            Log::warning('TelcoSMS recusou o envio.', ['phone' => $phone, 'status' => $response->status()]);
            return ['success' => false, 'message' => $this->errorMessage($response->json(), $response->status())];
        } catch (\Throwable $e) {
            Log::error('Falha de ligação à TelcoSMS.', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro de ligação à TelcoSMS: ' . $e->getMessage()];
        }
    }

    public function checkBalance(): array
    {
        if (blank($this->apiKeyApp)) {
            return ['success' => false, 'message' => 'Chave da aplicação TelcoSMS não configurada.'];
        }
        try {
            // O endpoint de saldo da TelcoSMS pode responder 5xx mesmo quando o
            // envio de SMS está operacional. Não lançar exceção no último retry:
            // o chamador precisa distinguir saldo indisponível de chave inválida.
            $response = Http::acceptJson()->timeout(20)->retry(2, 300, throw: false)
                ->get(self::BASE_URL . '/check_balance', ['api_key_app' => $this->apiKeyApp]);
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'balance_unavailable' => $response->serverError(),
                    'status' => $response->status(),
                    'message' => $this->errorMessage($response->json(), $response->status()),
                ];
            }
            $data = $response->json();
            $balance = data_get($data, 'balance') ?? data_get($data, 'saldo') ?? data_get($data, 'data.balance');
            return ['success' => true, 'message' => $balance === null ? 'Ligação à TelcoSMS validada.' : 'Saldo consultado com sucesso.', 'balance' => $balance, 'data' => $data];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro de ligação à TelcoSMS: ' . $e->getMessage()];
        }
    }

    private function phoneForTelco(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', str_replace('whatsapp:', '', $phone));
        // A documentação TelcoSMS usa o formato nacional angolano 9xx xxx xxx.
        return str_starts_with($digits, '244') && strlen($digits) === 12 ? substr($digits, 3) : $digits;
    }

    private function errorMessage(mixed $data, int $status): string
    {
        return (string) (data_get($data, 'message') ?? data_get($data, 'error') ?? "TelcoSMS respondeu HTTP {$status}.");
    }
}
