<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Um carimbo vindo de um dispositivo, trazido para o fuso da aplicação.
     *
     * O POS offline e a aplicação móvel carimbam com `new Date().toISOString()`,
     * que devolve UTC com sufixo Z. O `Carbon::parse` respeita esse Z e fica
     * com uma data em UTC — e a gravação escreve os dígitos DELA, sem
     * converter. Enquanto a aplicação também corria em UTC isso dava certo por
     * coincidência. Em hora de Angola deixa de dar: uma venda feita às 14:00
     * chega como 13:00Z, é gravada "13:00", e passa a ser lida como 13:00 de
     * Angola — uma hora antes de ter acontecido.
     *
     * Aqui converte-se para o fuso da aplicação antes de gravar, que é o que
     * torna os dígitos gravados comparáveis com tudo o resto. Aceita também
     * carimbos sem fuso nenhum (a app móvel manda relógio de parede): esses
     * são lidos como já estando no fuso da aplicação, que é o que são.
     */
    public static function doDispositivo($iso): ?Carbon
    {
        if (empty($iso)) {
            return null;
        }

        try {
            return Carbon::parse($iso)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            // Um carimbo ilegível não pode fazer perder a venda que o traz:
            // quem chama decide o que usar em vez dele, normalmente o now().
            return null;
        }
    }

    /**
     * Formatar data para exibição PT (dd/mm/yyyy)
     */
    public static function format($date, $format = 'd/m/Y')
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->format($format);
    }

    /**
     * Formatar data e hora PT
     */
    public static function formatDateTime($date, $format = 'd/m/Y H:i')
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->format($format);
    }

    /**
     * Formatar data por extenso
     */
    public static function formatLong($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->locale('pt')->isoFormat('D [de] MMMM [de] YYYY');
    }

    /**
     * Formatar data curta com dia da semana
     */
    public static function formatWithDay($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->locale('pt')->isoFormat('ddd, D MMM');
    }

    /**
     * Converter de formato PT para Y-m-d (para DB)
     */
    public static function toDatabase($date)
    {
        if (!$date) return null;
        
        // Se já está no formato correto
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // Converter de dd/mm/yyyy para yyyy-mm-dd
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * Converter de Y-m-d para formato PT
     */
    public static function fromDatabase($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->format('d/m/Y');
    }

    /**
     * Data relativa (há X dias, etc)
     */
    public static function diffForHumans($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->locale('pt')->diffForHumans();
    }
}
