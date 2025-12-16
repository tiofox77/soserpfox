<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
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
