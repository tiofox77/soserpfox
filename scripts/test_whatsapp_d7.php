<?php
/**
 * Teste de envio de WhatsApp via D7 Networks API
 */

echo "=== TESTE DE ENVIO WHATSAPP - D7 Networks ===\n\n";

// Configurações
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJhdXRoLWJhY2tlbmQ6YXBwIiwic3ViIjoiY2JkM2ZiOTAtZGVlZi00YWUwLTkwYTctZjI2MzIzNGNhMDNjIn0.eRjhl2ZrPL0qXdlcSpaCfwPSHGIiJKE1gZZEgRGeURI";
$originator = "SignOTP"; // Sender ID / WhatsApp Number
$recipient = "+244939729902"; // Número de Angola

// IMPORTANTE: Para WhatsApp você precisa de um template aprovado pela D7
// Este é um exemplo - você precisa substituir pelos seus valores reais
$template_id = "quick_reply_button"; // ID do seu template aprovado
$template_params = (object) [
    "0" => "Teste",
    "1" => "SOS ERP",
    "2" => date('d/m/Y H:i:s')
];

echo "📱 Destinatário: {$recipient}\n";
echo "📋 Template ID: {$template_id}\n";
echo "🔑 Token: " . substr($token, 0, 20) . "...\n\n";
echo "🚀 Enviando mensagem WhatsApp...\n\n";

// Construir payload
$payload = [
    "messages" => [
        [
            "originator" => $originator,
            "content" => [
                "message_type" => "TEMPLATE",
                "template" => [
                    "template_id" => $template_id,
                    "language" => "en",
                    "body_parameter_values" => $template_params
                ]
            ],
            "recipients" => [
                ["recipient" => $recipient]
            ],
            "report_url" => "https://soserp.vip/api/whatsapp-delivery-report"
        ]
    ]
];

$payload_json = json_encode($payload, JSON_PRETTY_PRINT);

echo "📦 Payload enviado:\n";
echo $payload_json . "\n\n";

// Inicializar cURL
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.d7networks.com/whatsapp/v2/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $payload_json,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

curl_close($curl);

// Exibir resultados
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($error) {
    echo "❌ ERRO DE CONEXÃO:\n";
    echo $error . "\n\n";
} else {
    echo "📊 HTTP Status Code: {$httpCode}\n\n";
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "✅ WHATSAPP ENVIADO COM SUCESSO!\n\n";
        echo "📥 Resposta da API:\n";
        
        $responseData = json_decode($response, true);
        echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n\n";
        
        // Detalhes da resposta
        if (isset($responseData['data'])) {
            echo "📋 DETALHES:\n";
            foreach ($responseData['data'] as $msg) {
                if (isset($msg['message_id'])) {
                    echo "  • Message ID: " . $msg['message_id'] . "\n";
                }
                if (isset($msg['status'])) {
                    echo "  • Status: " . $msg['status'] . "\n";
                }
                if (isset($msg['recipient'])) {
                    echo "  • Recipient: " . $msg['recipient'] . "\n";
                }
            }
        }
    } else {
        echo "❌ ERRO AO ENVIAR WHATSAPP\n\n";
        echo "📥 Resposta da API:\n";
        echo $response . "\n\n";
        
        $responseData = json_decode($response, true);
        if ($responseData) {
            if (isset($responseData['detail'])) {
                echo "💡 Detalhes do erro:\n";
                if (is_array($responseData['detail'])) {
                    foreach ($responseData['detail'] as $detail) {
                        if (is_array($detail)) {
                            echo "  • " . json_encode($detail) . "\n";
                        } else {
                            echo "  • " . $detail . "\n";
                        }
                    }
                } else {
                    echo "  • " . $responseData['detail'] . "\n";
                }
                echo "\n";
            }
            
            if (isset($responseData['message'])) {
                echo "💡 Mensagem: " . $responseData['message'] . "\n\n";
            }
        }
        
        echo "⚠️ NOTAS IMPORTANTES:\n";
        echo "  1. Para WhatsApp você precisa de um template APROVADO pela D7 Networks\n";
        echo "  2. Você precisa ter um número WhatsApp Business registrado\n";
        echo "  3. O 'originator' deve ser seu número WhatsApp Business\n";
        echo "  4. Os templates precisam ser criados e aprovados no painel D7\n";
        echo "  5. Verifique se sua conta D7 tem permissão para WhatsApp\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n✅ Teste concluído!\n";
echo "📅 " . date('d/m/Y H:i:s') . "\n\n";

echo "📖 PRÓXIMOS PASSOS:\n";
echo "  1. Verifique sua conta D7 Networks (https://d7networks.com/)\n";
echo "  2. Configure um número WhatsApp Business\n";
echo "  3. Crie e aprove templates de mensagem\n";
echo "  4. Atualize o 'originator' com seu número WhatsApp\n";
echo "  5. Atualize o 'template_id' com um template aprovado\n\n";
