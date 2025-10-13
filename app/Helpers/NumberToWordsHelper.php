<?php

if (!function_exists('numberToWords')) {
    /**
     * Converter número para extenso em português (Angola)
     * 
     * @param float $number
     * @param string $currency (AOA, USD, EUR)
     * @return string
     */
    function numberToWords($number, $currency = 'AOA')
    {
        $number = (float) $number;
        
        // Separar inteiros e decimais
        $integerPart = floor($number);
        $decimalPart = round(($number - $integerPart) * 100);
        
        $words = '';
        
        // Parte inteira
        if ($integerPart == 0) {
            $words = 'zero';
        } else {
            $words = convertIntegerToWords($integerPart);
        }
        
        // Nome da moeda
        $currencyName = getCurrencyName($currency, $integerPart);
        $words .= ' ' . $currencyName;
        
        // Parte decimal (centavos)
        if ($decimalPart > 0) {
            $decimalWords = convertIntegerToWords($decimalPart);
            $centName = $decimalPart == 1 ? 'cêntimo' : 'cêntimos';
            $words .= ' e ' . $decimalWords . ' ' . $centName;
        }
        
        return ucfirst($words);
    }
}

if (!function_exists('convertIntegerToWords')) {
    /**
     * Converter número inteiro para palavras
     */
    function convertIntegerToWords($number)
    {
        $units = [
            '', 'um', 'dois', 'três', 'quatro', 'cinco', 
            'seis', 'sete', 'oito', 'nove'
        ];
        
        $teens = [
            'dez', 'onze', 'doze', 'treze', 'catorze', 'quinze',
            'dezasseis', 'dezassete', 'dezoito', 'dezanove'
        ];
        
        $tens = [
            '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta',
            'sessenta', 'setenta', 'oitenta', 'noventa'
        ];
        
        $hundreds = [
            '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
            'seiscentos', 'setecentos', 'oitocentos', 'novecentos'
        ];
        
        if ($number == 0) {
            return '';
        }
        
        if ($number < 10) {
            return $units[$number];
        }
        
        if ($number < 20) {
            return $teens[$number - 10];
        }
        
        if ($number < 100) {
            $ten = floor($number / 10);
            $unit = $number % 10;
            $result = $tens[$ten];
            if ($unit > 0) {
                $result .= ' e ' . $units[$unit];
            }
            return $result;
        }
        
        if ($number == 100) {
            return 'cem';
        }
        
        if ($number < 1000) {
            $hundred = floor($number / 100);
            $remainder = $number % 100;
            $result = $hundreds[$hundred];
            if ($remainder > 0) {
                $result .= ' e ' . convertIntegerToWords($remainder);
            }
            return $result;
        }
        
        if ($number < 1000000) {
            $thousand = floor($number / 1000);
            $remainder = $number % 1000;
            
            if ($thousand == 1) {
                $result = 'mil';
            } else {
                $result = convertIntegerToWords($thousand) . ' mil';
            }
            
            if ($remainder > 0) {
                if ($remainder < 100) {
                    $result .= ' e ' . convertIntegerToWords($remainder);
                } else {
                    $result .= ' ' . convertIntegerToWords($remainder);
                }
            }
            return $result;
        }
        
        if ($number < 1000000000) {
            $million = floor($number / 1000000);
            $remainder = $number % 1000000;
            
            if ($million == 1) {
                $result = 'um milhão';
            } else {
                $result = convertIntegerToWords($million) . ' milhões';
            }
            
            if ($remainder > 0) {
                if ($remainder < 100) {
                    $result .= ' e ' . convertIntegerToWords($remainder);
                } else {
                    $result .= ' ' . convertIntegerToWords($remainder);
                }
            }
            return $result;
        }
        
        // Bilhões
        $billion = floor($number / 1000000000);
        $remainder = $number % 1000000000;
        
        if ($billion == 1) {
            $result = 'um bilhão';
        } else {
            $result = convertIntegerToWords($billion) . ' bilhões';
        }
        
        if ($remainder > 0) {
            if ($remainder < 100) {
                $result .= ' e ' . convertIntegerToWords($remainder);
            } else {
                $result .= ' ' . convertIntegerToWords($remainder);
            }
        }
        
        return $result;
    }
}

if (!function_exists('getCurrencyName')) {
    /**
     * Obter nome da moeda no singular ou plural
     */
    function getCurrencyName($currency, $amount)
    {
        $currencies = [
            'AOA' => ['kwanza', 'kwanzas'],
            'USD' => ['dólar', 'dólares'],
            'EUR' => ['euro', 'euros'],
            'BRL' => ['real', 'reais'],
        ];
        
        if (!isset($currencies[$currency])) {
            return $currency;
        }
        
        return $amount == 1 ? $currencies[$currency][0] : $currencies[$currency][1];
    }
}
