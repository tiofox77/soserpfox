<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class SAFTHelper
{
    /**
     * Gerar hash SAFT-AO para documento fiscal conforme regulamento Angola
     * 
     * Estrutura do hash conforme SAFT-AO:
     * Data;DataHoraSistema;NumeroDocumento;ValorTotal;HashAnterior
     * 
     * @param string $invoiceDate Data do documento (Y-m-d)
     * @param string $systemEntryDate Data/hora de gravação (Y-m-d H:i:s)
     * @param string $invoiceNo Número do documento (ex: FT 2025/00001)
     * @param float $grossTotal Total do documento com impostos
     * @param string|null $previousHash Hash do documento anterior
     * @return string|null Hash gerado ou null se houver erro
     */
    public static function generateHash($invoiceDate, $systemEntryDate, $invoiceNo, $grossTotal, $previousHash = null)
    {
        try {
            // Se não houver hash anterior, usar string vazia conforme SAFT-AO
            if (empty($previousHash)) {
                $previousHash = '';
            }
            
            // Formatar valores conforme SAFT-AO
            $invoiceDateFormatted = date('Y-m-d', strtotime($invoiceDate));
            $systemEntryDateFormatted = date('Y-m-d\TH:i:s', strtotime($systemEntryDate));
            $grossTotalFormatted = number_format($grossTotal, 2, '.', '');
            
            // Concatenar dados conforme regulamento SAFT-AO Angola
            // Formato: Data;DataHoraSistema;NumeroDocumento;ValorTotal;HashAnterior
            $dataToSign = $invoiceDateFormatted . ';' . 
                          $systemEntryDateFormatted . ';' . 
                          $invoiceNo . ';' . 
                          $grossTotalFormatted;
            
            // Adicionar hash anterior ao final (encadeamento)
            if (!empty($previousHash)) {
                $dataToSign .= ';' . $previousHash;
            }
            
            // Verificar se chaves existem
            if (Storage::disk('local')->exists('saft/private_key.pem')) {
                $privateKey = Storage::disk('local')->get('saft/private_key.pem');
                $pkeyid = openssl_pkey_get_private($privateKey);
                
                if ($pkeyid) {
                    // Assinar com RSA-SHA256
                    openssl_sign($dataToSign, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
                    $signedHash = base64_encode($signature);
                    if (PHP_MAJOR_VERSION < 8) {
                        openssl_free_key($pkeyid);
                    }
                    
                    return $signedHash;
                }
            }
            
            // Se não houver chave privada, usar SHA-1 conforme padrão SAFT-PT/AO
            $hash = sha1($dataToSign);
            return $hash;
            
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar hash SAFT-AO: ' . $e->getMessage());
            \Log::error('Dados: ' . json_encode([
                'invoiceDate' => $invoiceDate,
                'systemEntryDate' => $systemEntryDate,
                'invoiceNo' => $invoiceNo,
                'grossTotal' => $grossTotal
            ]));
            return null;
        }
    }
    
    /**
     * Verificar se hash é válido usando chave pública
     * 
     * @param string $documentData Dados do documento
     * @param string $previousHash Hash anterior
     * @param string $signature Assinatura a verificar
     * @return bool
     */
    public static function verifyHash($documentData, $previousHash, $signature)
    {
        try {
            if (!Storage::disk('local')->exists('saft/public_key.pem')) {
                return false;
            }
            
            $publicKey = Storage::disk('local')->get('saft/public_key.pem');
            $pubkeyid = openssl_pkey_get_public($publicKey);
            
            if (!$pubkeyid) {
                return false;
            }
            
            $dataToVerify = $documentData . ';' . $previousHash;
            $signatureDecoded = base64_decode($signature);
            
            $result = openssl_verify($dataToVerify, $signatureDecoded, $pubkeyid, OPENSSL_ALGO_SHA256);
            
            if (PHP_MAJOR_VERSION < 8) {
                openssl_free_key($pubkeyid);
            }
            
            return $result === 1;
            
        } catch (\Exception $e) {
            \Log::error('Erro ao verificar hash SAFT-AO: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se chaves SAFT-AO existem
     * 
     * @return bool
     */
    public static function keysExist()
    {
        return Storage::disk('local')->exists('saft/public_key.pem') 
            && Storage::disk('local')->exists('saft/private_key.pem');
    }
    
    /**
     * Obter chave pública
     * 
     * @return string|null
     */
    public static function getPublicKey()
    {
        if (Storage::disk('local')->exists('saft/public_key.pem')) {
            return Storage::disk('local')->get('saft/public_key.pem');
        }
        
        return null;
    }
    
}
