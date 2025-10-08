<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'description',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Substituir variáveis no template
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $bodyHtml = $this->body_html;
        $bodyText = $this->body_text;

        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            $bodyHtml = str_replace($placeholder, $value, $bodyHtml);
            if ($bodyText) {
                $bodyText = str_replace($placeholder, $value, $bodyText);
            }
        }

        // Se o body_html não contém doctype, envolve no layout
        if (strpos($bodyHtml, '<!DOCTYPE') === false && strpos($bodyHtml, '@extends') === false) {
            $bodyHtml = $this->wrapInLayout($bodyHtml, $subject);
        }

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Envolver conteúdo no layout padrão
     */
    protected function wrapInLayout(string $content, string $subject): string
    {
        // Criar um arquivo temporário com o conteúdo
        $tempViewName = 'emails.temp_' . md5($content . time());
        $tempViewPath = resource_path('views/emails/temp_' . md5($content . time()) . '.blade.php');
        
        // Criar conteúdo do template temporário que estende o layout
        $tempContent = "@extends('emails.layout')\n\n@section('content')\n" . $content . "\n@endsection";
        
        // Salvar arquivo temporário
        file_put_contents($tempViewPath, $tempContent);
        
        try {
            // Renderizar usando Blade
            $rendered = view($tempViewName, [
                'subject' => $subject,
            ])->render();
            
            // Deletar arquivo temporário
            @unlink($tempViewPath);
            
            return $rendered;
        } catch (\Exception $e) {
            // Deletar arquivo temporário em caso de erro
            @unlink($tempViewPath);
            
            // Fallback: renderização manual
            return $this->manualWrapInLayout($content, $subject);
        }
    }
    
    /**
     * Fallback: envolver manualmente no layout
     */
    protected function manualWrapInLayout(string $content, string $subject): string
    {
        $layout = file_get_contents(resource_path('views/emails/layout.blade.php'));
        
        // Substituir @yield('content')
        $layout = str_replace("@yield('content')", $content, $layout);
        
        // Processar @if(app_logo())
        if (app_logo()) {
            $logoUrl = app_logo();
            $appName = config('app.name', 'SOS ERP');
            $logoHtml = '<img src="' . $logoUrl . '" alt="' . $appName . '" style="max-height: 80px; max-width: 200px; width: auto; height: auto; display: block; margin: 0 auto;">';
            
            $layout = preg_replace('/@if\(app_logo\(\)\).*?@else.*?@endif/s', $logoHtml, $layout);
        } else {
            $layout = preg_replace('/@if\(app_logo\(\)\).*?@else(.*?)@endif/s', '$1', $layout);
        }
        
        // Substituir variáveis Blade
        $layout = str_replace("{{ config('app.name', 'SOS ERP') }}", config('app.name', 'SOS ERP'), $layout);
        $layout = str_replace("{{ \$subject ?? config('app.name') }}", $subject, $layout);
        $layout = str_replace("{{ date('Y') }}", date('Y'), $layout);
        $layout = str_replace("{{ config('app.url') }}", config('app.url'), $layout);
        
        return $layout;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
