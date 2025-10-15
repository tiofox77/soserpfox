<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class D7SmsService
{
    protected $apiToken;
    protected $senderId;
    protected $baseUrl = 'https://api.d7networks.com/messages/v1/send';
    
    public function __construct(?string $apiToken = null, ?string $senderId = null)
    {
        $this->apiToken = $apiToken;
        $this->senderId = $senderId;
    }
    
    /**
     * Enviar SMS via D7 Networks
     */
    public function sendSMS(string $to, string $message): ?string
    {
        if (!$this->apiToken) {
            Log::warning('D7 Networks API Token not configured');
            return null;
        }
        
        try {
            // Remover prefixos whatsapp: se existir
            $to = str_replace('whatsapp:', '', $to);
            
            // D7 espera número no formato internacional sem +
            $to = str_replace('+', '', $to);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, [
                'messages' => [
                    [
                        'channel' => 'sms',
                        'recipients' => [$to],
                        'content' => $message,
                        'msg_type' => 'text',
                        'data_coding' => 'text',
                    ]
                ],
                'message_globals' => [
                    'originator' => $this->senderId ?? 'SofteAngola',
                    'report_url' => null,
                ]
            ]);
            
            $result = $response->json();
            
            if ($response->successful()) {
                Log::info('D7 SMS sent successfully', [
                    'to' => $to,
                    'response' => $result
                ]);
                
                return $result['request_id'] ?? 'sent';
            } else {
                Log::error('D7 SMS send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'error' => $result
                ]);
                
                return null;
            }
            
        } catch (\Exception $e) {
            Log::error('D7 SMS exception', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Testar conexão com D7 Networks
     */
    public function testConnection(): array
    {
        if (!$this->apiToken) {
            return [
                'success' => false,
                'message' => 'API Token não configurado'
            ];
        }
        
        try {
            // Fazer uma requisição de teste (pode ser para verificar saldo)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ])->get('https://api.d7networks.com/account/v1/balance');
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Conexão bem-sucedida',
                    'balance' => $data['balance'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na autenticação: ' . $response->status()
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
}
