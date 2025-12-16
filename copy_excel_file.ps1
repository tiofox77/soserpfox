$source = ".\Tipos de Documentos Contabilísticos (1).xlsx"
$destination = ".\database\seeders\Accounting\Tipos de Documentos Contabilísticos (1).xlsx"

Write-Host "Verificando arquivo fonte..." -ForegroundColor Yellow
if (Test-Path $source) {
    Write-Host "Arquivo encontrado! Copiando..." -ForegroundColor Yellow
    Copy-Item -Path $source -Destination $destination -Force
    Write-Host "Arquivo copiado com sucesso!" -ForegroundColor Green
    
    # Verificar se copiou
    if (Test-Path $destination) {
        Write-Host "Verificação: Arquivo existe no destino!" -ForegroundColor Green
    }
} else {
    Write-Host "Arquivo fonte não encontrado em: $source" -ForegroundColor Red
    Write-Host "Caminho atual: $(Get-Location)" -ForegroundColor Yellow
}
