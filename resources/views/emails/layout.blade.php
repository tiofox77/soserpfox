<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #374151;
            background-color: #f3f4f6;
            padding: 20px;
        }
        
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            width: 80px;
            height: 80px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .logo-text {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .email-body {
            padding: 40px 30px;
        }
        
        .email-content {
            color: #374151;
            font-size: 16px;
            line-height: 1.8;
        }
        
        .email-footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-text {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .footer-links {
            margin-top: 15px;
        }
        
        .footer-links a {
            color: #3b82f6;
            text-decoration: none;
            margin: 0 10px;
            font-size: 13px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .button {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
            transition: transform 0.2s;
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(59, 130, 246, 0.4);
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e5e7eb, transparent);
            margin: 30px 0;
        }
        
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .success-box {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .warning-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .error-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .email-header {
                padding: 30px 20px;
            }
            
            .email-body {
                padding: 30px 20px;
            }
            
            .email-footer {
                padding: 20px;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            
            .logo-text {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header com Logo -->
        <div class="email-header">
            <div class="logo-container">
                @if(app_logo())
                    <img src="{{ app_logo() }}" 
                         alt="{{ config('app.name', 'SOS ERP') }}" 
                         style="max-height: 80px; max-width: 200px; width: auto; height: auto; display: block; margin: 0 auto;">
                @else
                    <div class="logo">
                        <svg width="36" height="36" viewBox="0 0 512 512" fill="white" xmlns="http://www.w3.org/2000/svg">
                            <!-- Crown Icon (FontAwesome fa-crown) -->
                            <path d="M309 106c11.4-7 19-19.7 19-34c0-22.1-17.9-40-40-40s-40 17.9-40 40c0 14.4 7.6 27 19 34L209.7 220.6c-9.1 18.2-32.7 23.4-48.6 10.7L72 160c5-6.7 8-15 8-24c0-22.1-17.9-40-40-40S0 113.9 0 136s17.9 40 40 40c.2 0 .5 0 .7 0L86.4 427.4c5.5 30.4 32 52.6 63 52.6H426.6c30.9 0 57.4-22.1 63-52.6L535.3 176c.2 0 .5 0 .7 0c22.1 0 40-17.9 40-40s-17.9-40-40-40s-40 17.9-40 40c0 9 3 17.3 8 24l-89.1 71.3c-15.9 12.7-39.5 7.5-48.6-10.7L309 106z"/>
                        </svg>
                    </div>
                @endif
            </div>
            <div class="logo-text">{{ config('app.name', 'SOS ERP') }}</div>
        </div>
        
        <!-- Corpo do Email -->
        <div class="email-body">
            <div class="email-content">
                @yield('content')
            </div>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <p class="footer-text">
                © {{ date('Y') }} {{ config('app.name', 'SOS ERP') }}. Todos os direitos reservados.
            </p>
            <p class="footer-text">
                Este é um email automático, por favor não responda.
            </p>
            <div class="footer-links">
                <a href="{{ config('app.url') }}">Acessar Sistema</a>
                <span style="color: #d1d5db;">|</span>
                <a href="{{ config('app.url') }}/support">Suporte</a>
            </div>
        </div>
    </div>
</body>
</html>
