<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- SEO Meta Tags -->
    <title>Portal do Cliente - {{ app_name() ?? config('app.name', 'SOS ERP') }}</title>
    <meta name="description" content="Acesse sua área exclusiva do cliente para visualizar faturas, eventos, documentos e muito mais. Portal seguro e fácil de usar.">
    <meta name="keywords" content="portal cliente, área cliente, faturas online, eventos, documentos, {{ app_name() ?? config('app.name') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="{{ app_name() ?? config('app.name') }}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Portal do Cliente - {{ app_name() ?? config('app.name') }}">
    <meta property="og:description" content="Acesse sua área exclusiva do cliente">
    <meta property="og:site_name" content="{{ app_name() ?? config('app.name') }}">
    @if(app_logo())
    <meta property="og:image" content="{{ app_logo() }}">
    @endif
    
    <!-- Favicon -->
    @if(app_favicon())
    <link rel="icon" type="image/x-icon" href="{{ app_favicon() }}">
    <link rel="apple-touch-icon" href="{{ app_favicon() }}">
    @else
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    @endif
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    @livewireStyles
</head>
<body>
    {{ $slot }}
    
    @livewireScripts
    
    <!-- PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        }
    </script>
</body>
</html>
