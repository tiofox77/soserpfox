# Monitor de Registro em Tempo Real
Write-Host "`n═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  MONITOR DE REGISTRO - TEMPO REAL" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════`n" -ForegroundColor Cyan

Write-Host "📊 Monitorando logs em tempo real..." -ForegroundColor Yellow
Write-Host "🎯 Aguardando novo registro...`n" -ForegroundColor Yellow

$logFile = "storage\logs\laravel.log"

# Limpar console
Clear-Host

Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║  MONITOR ATIVO - Faça o registro agora!                  ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green

Get-Content $logFile -Wait -Tail 0 | ForEach-Object {
    $line = $_
    
    # Destacar checkpoints importantes
    if ($line -match "CHECKPOINT|🎯") {
        Write-Host $line -ForegroundColor Magenta -BackgroundColor DarkBlue
    }
    elseif ($line -match "INICIANDO ENVIO|📧📧📧") {
        Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Yellow
        Write-Host $line -ForegroundColor Yellow -BackgroundColor DarkRed
        Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Yellow
    }
    elseif ($line -match "ENVIADO COM SUCESSO|✅✅✅") {
        Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Green
        Write-Host $line -ForegroundColor Green -BackgroundColor DarkGreen
        Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green
    }
    elseif ($line -match "ERRO.*EMAIL|❌❌❌") {
        Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Red
        Write-Host $line -ForegroundColor Red -BackgroundColor DarkRed
        Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Red
    }
    elseif ($line -match "DEBUG:|Template welcome|SMTP padrão") {
        Write-Host $line -ForegroundColor Cyan
    }
    elseif ($line -match "EMAIL DO SISTEMA|🔐") {
        Write-Host $line -ForegroundColor Green
    }
    elseif ($line -match "error|ERROR|Exception|Failed") {
        Write-Host $line -ForegroundColor Red
    }
    elseif ($line -match "warning|WARNING|⚠️") {
        Write-Host $line -ForegroundColor Yellow
    }
    elseif ($line -match "Mail::to|TemplateMail") {
        Write-Host $line -ForegroundColor White -BackgroundColor DarkBlue
    }
    else {
        # Outras linhas em cinza claro
        Write-Host $line -ForegroundColor Gray
    }
}
