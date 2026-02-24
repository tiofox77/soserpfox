<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e40af">
    <title>SOS ERP - Offline</title>
    <link rel="manifest" href="/manifest.json">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
            overflow: hidden;
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .bg-glow {
            position: fixed;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.15;
            pointer-events: none;
        }

        .bg-glow-1 {
            top: -150px;
            right: -100px;
            background: #3b82f6;
        }

        .bg-glow-2 {
            bottom: -200px;
            left: -150px;
            background: #8b5cf6;
        }

        .container {
            text-align: center;
            padding: 2.5rem;
            max-width: 480px;
            position: relative;
            z-index: 10;
        }

        .icon-wrapper {
            width: 140px;
            height: 140px;
            margin: 0 auto 2rem;
            position: relative;
        }

        .icon-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(139,92,246,0.15));
            border: 2px solid rgba(59,130,246,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
        }

        .icon-circle svg {
            width: 64px;
            height: 64px;
            color: #60a5fa;
        }

        .pulse-ring {
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 2px solid rgba(59,130,246,0.3);
            animation: pulse-ring 2s ease-out infinite;
        }

        .pulse-ring:nth-child(2) {
            animation-delay: 0.5s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 0.6; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
            letter-spacing: -0.025em;
        }

        .subtitle {
            font-size: 1rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .description {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .status-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }

        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .status-row + .status-row {
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .status-label {
            font-size: 0.8rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-dot.red { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }
        .status-dot.green { background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,0.5); }
        .status-dot.yellow { background: #eab308; box-shadow: 0 0 8px rgba(234,179,8,0.5); }

        .status-value {
            font-size: 0.8rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        .cached-info {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 4px 15px rgba(59,130,246,0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59,130,246,0.4);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            color: #e2e8f0;
            transform: translateY(-2px);
        }

        .footer-text {
            margin-top: 2rem;
            font-size: 0.7rem;
            color: #475569;
        }

        .footer-text strong {
            color: #64748b;
        }

        @media (max-width: 480px) {
            .container { padding: 1.5rem; }
            h1 { font-size: 1.5rem; }
            .icon-wrapper { width: 110px; height: 110px; }
            .icon-circle { width: 110px; height: 110px; }
            .icon-circle svg { width: 48px; height: 48px; }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

    <div class="container">
        <div class="icon-wrapper">
            <div class="pulse-ring"></div>
            <div class="pulse-ring"></div>
            <div class="icon-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 1l22 22"/>
                    <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                    <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                    <path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>
                    <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                    <line x1="12" y1="20" x2="12.01" y2="20"/>
                </svg>
            </div>
        </div>

        <h1>Sem Conexão à Internet</h1>
        <p class="subtitle">O SOS ERP está offline</p>
        <p class="description">
            Não foi possível estabelecer ligação com o servidor. 
            Verifique a sua conexão à internet e tente novamente.
        </p>

        <div class="status-card">
            <div class="status-row">
                <span class="status-label">
                    <span class="status-dot red"></span>
                    Conexão Internet
                </span>
                <span class="status-value" id="connection-status">Desconectado</span>
            </div>
            <div class="status-row">
                <span class="status-label">
                    <span class="status-dot green"></span>
                    Cache Local
                </span>
                <span class="status-value">Disponível</span>
            </div>
            <div class="status-row">
                <span class="status-label">
                    <span class="status-dot yellow"></span>
                    Service Worker
                </span>
                <span class="status-value" id="sw-status">Ativo</span>
            </div>
            <div class="cached-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                <span id="last-online">Último acesso: verificando...</span>
            </div>
        </div>

        <div class="btn-group">
            <button class="btn btn-primary" onclick="location.reload()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6"/><path d="M2.5 22v-6h6"/>
                    <path d="M2 11.5a10 10 0 0 1 18.8-4.3"/><path d="M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
                Tentar Novamente
            </button>
            <button class="btn btn-secondary" onclick="history.back()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>
                </svg>
                Voltar
            </button>
        </div>

        <p class="footer-text">
            <strong>SOS ERP</strong> - Sistema de Gestão Empresarial
        </p>
    </div>

    <script>
        // Monitorar reconexão automática
        window.addEventListener('online', () => {
            document.getElementById('connection-status').textContent = 'Reconectado!';
            document.querySelector('.status-dot.red').style.background = '#22c55e';
            document.querySelector('.status-dot.red').style.boxShadow = '0 0 8px rgba(34,197,94,0.5)';
            setTimeout(() => location.reload(), 800);
        });

        // Guardar último acesso
        const lastOnline = localStorage.getItem('soserp-last-online');
        if (lastOnline) {
            const date = new Date(parseInt(lastOnline));
            const now = new Date();
            const diffMin = Math.round((now - date) / 60000);
            let timeText;
            if (diffMin < 1) timeText = 'agora mesmo';
            else if (diffMin < 60) timeText = `há ${diffMin} min`;
            else if (diffMin < 1440) timeText = `há ${Math.round(diffMin/60)}h`;
            else timeText = date.toLocaleDateString('pt-AO') + ' ' + date.toLocaleTimeString('pt-AO', {hour:'2-digit', minute:'2-digit'});
            document.getElementById('last-online').textContent = 'Último acesso: ' + timeText;
        }

        // Verificar SW
        if (!('serviceWorker' in navigator)) {
            document.getElementById('sw-status').textContent = 'Indisponível';
        }
    </script>
</body>
</html>
