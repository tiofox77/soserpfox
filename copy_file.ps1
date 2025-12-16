# Buscar o arquivo
$file = Get-ChildItem -Path . -Filter "*.xlsx" | Where-Object { $_.Name -like "*Tipos*" } | Select-Object -First 1

if ($file) {
    Write-Host "Arquivo encontrado: $($file.Name)" -ForegroundColor Green
    $destination = "database\seeders\Accounting\$($file.Name)"
    Copy-Item -Path $file.FullName -Destination $destination -Force
    Write-Host "Copiado para: $destination" -ForegroundColor Green
} else {
    Write-Host "Nenhum arquivo Excel encontrado!" -ForegroundColor Red
}
