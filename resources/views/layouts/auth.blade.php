<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- SEO Meta Tags -->
    <title>{{ config('app.name', 'SOS ERP') }} - @yield('title', 'Login') | Sistema de Gestão Empresarial</title>
    <meta name="description" content="SOS ERP - Sistema completo de gestão empresarial multi-tenant. Gerencie eventos, inventário, CRM, faturação, RH e muito mais. Plataforma profissional para empresas em Angola.">
    <meta name="keywords" content="ERP Angola, sistema de gestão, multi-tenant, gestão empresarial, eventos, inventário, CRM, faturação, RH, contabilidade">
    <meta name="author" content="SOS ERP">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ config('app.name', 'SOS ERP') }} - Sistema de Gestão Empresarial Multi-Tenant">
    <meta property="og:description" content="Plataforma completa de gestão empresarial. Gestão de eventos, inventário, CRM, faturação, RH e contabilidade. Solução profissional para empresas.">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">
    <meta property="og:site_name" content="{{ config('app.name', 'SOS ERP') }}">
    <meta property="og:locale" content="pt_AO">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="{{ config('app.name', 'SOS ERP') }} - Sistema de Gestão Empresarial">
    <meta name="twitter:description" content="Plataforma completa de gestão empresarial multi-tenant. Eventos, inventário, CRM, faturação e muito mais.">
    <meta name="twitter:image" content="{{ asset('images/og-image.png') }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url()->current() }}">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-500 to-purple-600 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo/Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-crown text-blue-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">{{ config('app.name') }}</h1>
            <p class="text-blue-100 mt-2">Sistema ERP Multi-tenant</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @yield('content')
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-white text-sm">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.</p>
        </div>
    </div>
    
    <!-- PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        }
    </script>
</body>
</html>
