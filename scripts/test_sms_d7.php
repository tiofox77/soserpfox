<?php
/**
 * Teste de envio de SMS via D7 Networks API
 * Teste básico para validar integração
 */

echo "=== TESTE DE ENVIO DE SMS - D7 Networks ===\n\n";

// Configurações
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJhdXRoLWJhY2tlbmQ6YXBwIiwic3ViIjoiY2JkM2ZiOTAtZGVlZi00YWUwLTkwYTctZjI2MzIzNGNhMDNjIn0.eRjhl2ZrPL0qXdlcSpaCfwPSHGIiJKE1gZZEgRGeURI";
$originator = "SOS ERP"; // Sender ID
$recipients = array("+244954949595"); // TESTE APENAS ESTE NÚMERO
$message = "TESTE SMS SOS ERP - Voce esta recebendo esta mensagem? Responda SIM. Hora: " . date('H:i:s');

echo "👤 Sender ID: {$originator}\n";
echo "📱 Destinatários: " . implode(", ", $recipients) . "\n";
echo "📝 Mensagem: {$message}\n";
echo "🔑 Token: " . substr($token, 0, 20) . "...\n\n";
echo "🚀 Enviando SMS para " . count($recipients) . " números...\n\n";

// Inicializar cURL
$curl = curl_init();

// Payload da mensagem
$message_obj = array( 
    "channel" => "sms",
    "msg_type" => "text",
    "recipients" => $recipients,
    "content" => $message,
    "data_coding" => "auto"
);

$globals_obj = array( 
    "originator" => $originator,
    "report_url" => "https://soserp.vip/api/sms-delivery-report"
);

$payload = json_encode( 
    array( 
        "messages" => array($message_obj),
        "message_globals" => $globals_obj 
    ),
    JSON_PRETTY_PRINT
);

echo "📦 Payload enviado:\n";
echo $payload . "\n\n";

// Configurar cURL
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.d7networks.com/messages/v1/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ),
));

// Executar requisição
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
        echo "✅ SMS ENVIADO COM SUCESSO!\n\n";
        echo "📥 Resposta da API:\n";
        
        $responseData = json_decode($response, true);
        echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n\n";
        
        // Detalhes da resposta
        if (isset($responseData['data'])) {
            echo "📋 DETALHES:\n";
            foreach ($responseData['data'] as $msg) {
                echo "  • Message ID: " . ($msg['message_id'] ?? 'N/A') . "\n";
                echo "  • Status: " . ($msg['status'] ?? 'N/A') . "\n";
                echo "  • Recipient: " . ($msg['recipient'] ?? 'N/A') . "\n";
            }
        }
    } else {
        echo "❌ ERRO AO ENVIAR SMS\n\n";
        echo "📥 Resposta da API:\n";
        echo $response . "\n\n";
        
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['detail'])) {
            echo "💡 Detalhes do erro: " . $responseData['detail'] . "\n";
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n✅ Teste concluído!\n";
echo "📅 " . date('d/m/Y H:i:s') . "\n\n";
