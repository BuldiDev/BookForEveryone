# BookForEveryone - Docker Management Scripts

Write-Host "BookForEveryone - Docker Management" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

function Start-App {
    Write-Host "Avvio di tutti i servizi..." -ForegroundColor Green
    docker-compose up -d --build
    Write-Host ""
    Write-Host "Servizi avviati!" -ForegroundColor Green
    Write-Host "- Applicazione: http://localhost:8000" -ForegroundColor Yellow
    Write-Host "- Mercure: http://localhost:3000" -ForegroundColor Yellow
    Write-Host "- MailPit: http://localhost:8025" -ForegroundColor Yellow
    Write-Host "- Database: localhost:3307" -ForegroundColor Yellow
    Write-Host ""
}

function Stop-App {
    Write-Host "Arresto di tutti i servizi..." -ForegroundColor Red
    docker-compose down
    Write-Host "Servizi arrestati!" -ForegroundColor Green
}

function Restart-App {
    Write-Host "Riavvio di tutti i servizi..." -ForegroundColor Yellow
    docker-compose restart
    Write-Host "Servizi riavviati!" -ForegroundColor Green
}

function Show-Logs {
    param([string]$Service = "")
    if ($Service) {
        docker-compose logs -f $Service
    } else {
        docker-compose logs -f
    }
}

function Show-Status {
    docker-compose ps
}

# Menu
Write-Host "Seleziona un'opzione:" -ForegroundColor White
Write-Host "1. Avvia tutti i servizi" -ForegroundColor White
Write-Host "2. Ferma tutti i servizi" -ForegroundColor White
Write-Host "3. Riavvia tutti i servizi" -ForegroundColor White
Write-Host "4. Mostra stato servizi" -ForegroundColor White
Write-Host "5. Mostra logs" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Scelta"

switch ($choice) {
    "1" { Start-App }
    "2" { Stop-App }
    "3" { Restart-App }
    "4" { Show-Status }
    "5" { Show-Logs }
    default { Write-Host "Scelta non valida" -ForegroundColor Red }
}
