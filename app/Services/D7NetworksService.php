<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class D7NetworksService
{
    protected $apiToken;
    protected $senderId;
    protected $baseUrl = 'https://api.d7networks.com/messages/v1';

    public function __construct($apiToken = null, $senderId = null)
    {
        $this->apiToken = $apiToken;
        $this->senderId = $senderId;
    }

    /**
     * Send SMS via D7 Networks
     * 
     * @param string|array $to Phone number(s) in international format
     * @param string $message Message content
     * @return array Response with status and message ID
     */
    public function sendSMS($to, string $message): array
    {
        if (!$this->apiToken) {
            return [
                'success' => false,
                'message' => 'API Token não configurado'
            ];
        }

        try {
            // Ensure $to is an array
            $recipients = is_array($to) ? $to : [$to];

            // Format phone numbers (remove + if present, D7 expects numbers without +)
            $recipients = array_map(function($number) {
                return ltrim($number, '+');
            }, $recipients);

            $payload = [
                'messages' => [
                    [
                        'channel' => 'sms',
                        'recipients' => $recipients,
                        'content' => $message,
                        'msg_type' => 'text',
                        'data_coding' => 'text'
                    ]
                ]
            ];

            // Add sender ID if configured
            if ($this->senderId) {
                $payload['messages'][0]['sender'] = $this->senderId;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/send', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('D7 Networks SMS sent successfully', [
                    'recipients' => $recipients,
                    'response' => $data
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS enviado com sucesso',
                    'data' => $data,
                    'message_id' => $data['data']['message_id'] ?? null
                ];
            }

            $error = $response->json();
            Log::error('D7 Networks SMS failed', [
                'recipients' => $recipients,
                'error' => $error,
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'message' => $error['detail'][0]['msg'] ?? 'Falha ao enviar SMS',
                'error' => $error
            ];

        } catch (\Exception $e) {
            Log::error('D7 Networks SMS exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao enviar SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get account balance
     * 
     * @return array Balance information
     */
    public function getBalance(): array
    {
        if (!$this->apiToken) {
            return [
                'success' => false,
                'message' => 'API Token não configurado'
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/balance');

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'balance' => $data['balance'] ?? 0,
                    'currency' => $data['currency'] ?? 'USD'
                ];
            }

            return [
                'success' => false,
                'message' => 'Falha ao obter saldo'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao obter saldo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to D7 Networks
     * 
     * @return array Connection test result
     */
    public function testConnection(): array
    {
        if (!$this->apiToken) {
            return [
                'success' => false,
                'message' => 'API Token não configurado'
            ];
        }

        $balanceResult = $this->getBalance();
        
        if ($balanceResult['success']) {
            return [
                'success' => true,
                'message' => 'Conexão bem-sucedida com D7 Networks',
                'balance' => $balanceResult['balance'] ?? 0,
                'currency' => $balanceResult['currency'] ?? 'USD'
            ];
        }

        return $balanceResult;
    }
}
