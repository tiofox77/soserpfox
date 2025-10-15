<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $twilio;
    protected $accountSid;
    protected $authToken;
    protected $fromNumber;

    public function __construct(?string $accountSid = null, ?string $authToken = null, ?string $fromNumber = null)
    {
        $this->accountSid = $accountSid ?? config('services.twilio.sid');
        $this->authToken = $authToken ?? config('services.twilio.token');
        $this->fromNumber = $fromNumber;
        
        if ($this->accountSid && $this->authToken) {
            $this->twilio = new Client($this->accountSid, $this->authToken);
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(string $to, string $message): ?string
    {
        if (!$this->twilio) {
            Log::warning('WhatsApp service not configured');
            return null;
        }

        try {
            $from = $this->fromNumber;
            
            if (!$from) {
                throw new \Exception('From number not configured');
            }
            
            // Ensure numbers have whatsapp: prefix
            if (!str_starts_with($to, 'whatsapp:')) {
                $to = 'whatsapp:' . $to;
            }
            
            if (!str_starts_with($from, 'whatsapp:')) {
                $from = 'whatsapp:' . $from;
            }

            $result = $this->twilio->messages->create(
                $to,
                [
                    'from' => $from,
                    'body' => $message
                ]
            );

            Log::info('WhatsApp message sent', [
                'to' => $to,
                'sid' => $result->sid,
                'status' => $result->status
            ]);

            return $result->sid;

        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Send WhatsApp message with template
     */
    public function sendTemplate(string $to, string $templateName, array $variables = [], ?string $templateSid = null): ?string
    {
        if (!$this->twilio) {
            Log::warning('WhatsApp service not configured');
            return null;
        }

        if (!$templateSid) {
            Log::warning('Template SID not provided');
            return null;
        }

        try {
            $from = $this->fromNumber;
            
            if (!$from) {
                throw new \Exception('From number not configured');
            }
            
            // Ensure numbers have whatsapp: prefix
            if (!str_starts_with($to, 'whatsapp:')) {
                $to = 'whatsapp:' . $to;
            }
            
            if (!str_starts_with($from, 'whatsapp:')) {
                $from = 'whatsapp:' . $from;
            }

            // Preparar payload
            $payload = [
                'from' => $from,
                'contentSid' => $templateSid
            ];
            
            // Adicionar variáveis apenas se existirem
            if (!empty($variables)) {
                $payload['contentVariables'] = json_encode($variables);
            }
            
            Log::info('WhatsApp template payload', [
                'to' => $to,
                'payload' => $payload,
                'variables_raw' => $variables
            ]);

            $result = $this->twilio->messages->create($to, $payload);

            Log::info('WhatsApp template sent', [
                'to' => $to,
                'template' => $templateName,
                'sid' => $result->sid,
                'status' => $result->status
            ]);

            return $result->sid;

        } catch (\Exception $e) {
            Log::error('WhatsApp template send failed', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Fetch available templates from Twilio
     */
    public function fetchTemplates(): array
    {
        if (!$this->twilio) {
            return [];
        }

        try {
            $contents = $this->twilio->content->v1->contents->read(limit: 50);
            
            $templates = [];
            foreach ($contents as $content) {
                $templates[] = [
                    'sid' => $content->sid,
                    'name' => $content->friendlyName,
                    'language' => $content->language ?? 'pt-BR',
                ];
            }

            return $templates;

        } catch (\Exception $e) {
            Log::error('Failed to fetch templates', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get template details including variables
     */
    public function getTemplateDetails(string $contentSid): ?array
    {
        if (!$this->twilio) {
            return null;
        }

        try {
            $content = $this->twilio->content->v1->contents($contentSid)->fetch();
            
            // Extrair variáveis do template
            $variables = [];
            $body = '';
            
            if (isset($content->types) && isset($content->types['twilio/text'])) {
                $body = $content->types['twilio/text']['body'] ?? '';
                
                // Encontrar variáveis no formato {{nome}} - variáveis nomeadas
                preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $body, $matches);
                
                if (!empty($matches[1])) {
                    $variables = array_unique($matches[1]);
                }
            }

            Log::info('Template details extracted', [
                'sid' => $contentSid,
                'name' => $content->friendlyName,
                'body' => $body,
                'variables' => $variables
            ]);

            return [
                'sid' => $content->sid,
                'name' => $content->friendlyName,
                'language' => $content->language ?? 'pt-BR',
                'variables' => $variables,
                'body' => $body,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch template details', [
                'sid' => $contentSid,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Send SMS via Twilio
     */
    public function sendSMS(string $to, string $message): ?string
    {
        if (!$this->twilio) {
            Log::warning('Twilio service not configured');
            return null;
        }

        try {
            $from = $this->fromNumber;
            
            if (!$from) {
                throw new \Exception('From number not configured');
            }
            
            // SMS não usa prefixo whatsapp: 
            // Remover se existir
            $to = str_replace('whatsapp:', '', $to);
            $from = str_replace('whatsapp:', '', $from);

            $result = $this->twilio->messages->create(
                $to,
                [
                    'from' => $from,
                    'body' => $message
                ]
            );

            Log::info('SMS sent via Twilio', [
                'to' => $to,
                'sid' => $result->sid,
                'status' => $result->status
            ]);

            return $result->sid;

        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        if (!$this->twilio) {
            return [
                'success' => false,
                'message' => 'WhatsApp não está configurado'
            ];
        }

        try {
            $account = $this->twilio->api->v2010->accounts($this->accountSid)->fetch();
            
            return [
                'success' => true,
                'message' => 'Conexão bem-sucedida',
                'account_name' => $account->friendlyName,
                'status' => $account->status
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Falha na conexão: ' . $e->getMessage()
            ];
        }
    }
}
